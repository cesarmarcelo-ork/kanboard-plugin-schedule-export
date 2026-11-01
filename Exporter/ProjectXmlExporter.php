<?php

namespace Kanboard\Plugin\ScheduleExport\Exporter;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Read-only MSPDI generator. The Kanboard task fields are the only scheduling
 * authority. True timestamps are retained with a 7x24 calendar.
 */
class ProjectXmlExporter
{
    const NS = 'http://schemas.microsoft.com/project';
    const ANCHOR_CLOCK = '08:00:00';
    private $timezone;

    public function __construct($timezone = null)
    {
        $this->timezone = new DateTimeZone($timezone ?: date_default_timezone_get());
    }

    private function localTime($timestamp, $pattern)
    {
        return (new DateTimeImmutable('@' . (int)$timestamp))->setTimezone($this->timezone)->format($pattern);
    }

    public function generate(array $project, array $tasks, array $linksByTask, $anchor = null, array $subtasksByTask = array(), $detailedSubtasks = false)
    {
        $byId = array();
        foreach ($tasks as $task) {
            $id = (int) ($task['id'] ?? 0);
            if ($id < 1 || isset($byId[$id]) || (int) ($task['project_id'] ?? 0) !== (int) ($project['id'] ?? 0)) {
                throw new \InvalidArgumentException('Invalid, duplicate or cross-project Kanboard card');
            }
            $byId[$id] = $task;
        }
        $nativeNotes = array();
        $subIds = array();
        foreach ($subtasksByTask as $parentId => $rows) {
            $parentId = (int) $parentId;
            if (!isset($byId[$parentId]) || !is_array($rows)) {
                throw new \InvalidArgumentException('Subtasks must belong to an exported card');
            }
            foreach ($rows as $sub) {
                $id = (int) ($sub['id'] ?? 0);
                if ($id < 1 || isset($subIds[$id]) || (int) ($sub['task_id'] ?? 0) !== $parentId) {
                    throw new \InvalidArgumentException('Invalid, duplicate or cross-card native subtask');
                }
                $subIds[$id] = true;
                $nativeNotes[$parentId][] = $sub;
            }
        }
        // Only explicitly declared periods are plotted. Native Kanboard subtasks
        // have no own start/finish fields; never derive them from parent dates.
        $subtaskPeriods = array();
        if ($detailedSubtasks) {
            foreach ($byId as $parentId => $parentTask) {
                foreach (preg_split('/\r\n|\n|\r/', (string)($parentTask['description'] ?? '')) as $line) {
                    $line = trim($line);
                    if (strpos($line, '[SE_SUBTASK_PERIOD') !== 0) continue;
                    if (!preg_match('/^\[SE_SUBTASK_PERIOD id=([1-9][0-9]*) start=([0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}) end=([0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2})\]$/', $line, $match)) {
                        throw new \InvalidArgumentException('Invalid SE_SUBTASK_PERIOD declaration on card ' . $parentId);
                    }
                    $subId = (int) $match[1];
                    $found = false;
                    foreach ($nativeNotes[$parentId] ?? array() as $sub) {
                        if ((int) $sub['id'] === $subId) { $found = true; break; }
                    }
                    if (!$found || (string)$subId !== $match[1] || isset($subtaskPeriods[$parentId][$subId])) {
                        throw new \InvalidArgumentException('Unknown or duplicate subtask period on card ' . $parentId);
                    }
                    $start = $this->parseLocalPeriodTimestamp($match[2]);
                    $finish = $this->parseLocalPeriodTimestamp($match[3]);
                    if ($finish <= $start) {
                        throw new \InvalidArgumentException('Subtask period end must follow start on card ' . $parentId);
                    }
                    $subtaskPeriods[$parentId][$subId] = array('start'=>$start,'finish'=>$finish);
                }
            }
        }
        $ids = array_keys($byId);
        usort($ids, function ($a, $b) use ($byId) {
            $cmp = (int) ($byId[$a]['position'] ?? 999999) <=> (int) ($byId[$b]['position'] ?? 999999);
            return $cmp ?: ($a <=> $b);
        });
        $parentCandidates = array();
        $predecessors = array();
        foreach ($linksByTask as $fromId => $relations) {
            $fromId = (int) $fromId;
            if (!isset($byId[$fromId]) || !is_array($relations)) continue;
            foreach ($relations as $relation) {
                $toId = (int) ($relation['task_id'] ?? 0);
                if ($toId === $fromId || !isset($byId[$toId])) continue;
                $type = $this->relationKind($relation['label'] ?? '');
                if ($type === 'parent') $parentCandidates[$toId][$fromId] = true;
                if ($type === 'child') $parentCandidates[$fromId][$toId] = true;
                if ($type === 'blocks') $predecessors[$toId][$fromId] = true;
                if ($type === 'blocked_by') $predecessors[$fromId][$toId] = true;
                // All other labels (including "duplicated by") are NOT dependencies.
            }
        }
        $parents = array();
        foreach ($parentCandidates as $child => $choices) {
            if (count($choices) !== 1) throw new \InvalidArgumentException('Ambiguous multiple parents for Kanboard card ' . $child);
            $parents[$child] = (int) key($choices);
        }
        foreach ($parents as $child => $parent) {
            $seen = array($child => true);
            $cursor = $parent;
            while ($cursor) {
                if (isset($seen[$cursor])) throw new \InvalidArgumentException('Cycle in Kanboard card hierarchy');
                $seen[$cursor] = true;
                $cursor = $parents[$cursor] ?? 0;
            }
        }
        $children = array();
        foreach ($parents as $child => $parent) $children[$parent][] = $child;
        $rank = array_flip($ids);
        foreach ($children as &$branch) usort($branch, function ($a, $b) use ($rank) { return $rank[$a] <=> $rank[$b]; });
        unset($branch);
        $ordered = array(); $wbs = array(); $levels = array();
        $visit = function ($id, $level, $path) use (&$visit, &$ordered, &$wbs, &$levels, $children) {
            $ordered[] = $id;
            $wbs[$id] = $path;
            $levels[$id] = $level;
            foreach ($children[$id] ?? array() as $index => $child) {
                $visit($child, $level + 1, $path . '.' . ($index + 1));
            }
        };
        $roots = array_values(array_filter($ids, function ($id) use ($parents) { return !isset($parents[$id]); }));
        foreach ($roots as $i => $id) $visit($id, 1, (string) ($i + 1));
        if (count($ordered) !== count($ids)) throw new \RuntimeException('Incomplete hierarchy');

        $anchorDate = $anchor === null ? (new DateTimeImmutable('now',$this->timezone))->format('Y-m-d') : $this->validDate($anchor);
        // The anchor is only a positional reference for unscheduled cards;
        // it must not masquerade as a Kanboard start date.
        foreach ($byId as $task) {
            foreach (array('date_started', 'date_due') as $field) {
                $date = $this->timestampDate($task[$field] ?? 0);
                if ($date !== null && $date < $anchorDate) $anchorDate = $date;
            }
        }
        $cardSummary = array_fill_keys(array_keys($children), true);
        $summary = $cardSummary;
        if ($detailedSubtasks) {
            foreach (array_keys($nativeNotes) as $taskId) $summary[$taskId] = true;
        }
        $edges = array();
        $notesProject = array(
            'Fonte: Kanboard. Exportacao somente leitura; nenhuma atividade e alterada.',
            'Cartoes: ' . count($byId) . '; vinculos EAP: ' . count($parents) . '; subtarefas nativas: ' . count($subIds) . ($detailedSubtasks ? ' (EAP detalhada, sem datas proprias).' : ' (apenas nas observacoes).'),
            'Politica: NUNCA inferir inicio a partir do prazo e duracao.',
            'Horario real de Kanboard exportado quando cadastrado; calendario 24 horas/7 dias para evitar ajustes automaticos.',
            'Calendario de 7 dias permite representar datas originais que caiam em sabados ou domingos.',
            'SEM DATAS aparece como marco de referencia NAO PLANEJADO, nao como prazo.',
        );
        foreach ($predecessors as $succ => $preds) {
            foreach ($preds as $pred => $_) {
                // Preserve original card-to-card blocker edges. Native-subtask rows
                // must not silently remove a dependency that exists in legacy mode.
                // MSPDI importers may treat promoted native-only parents as summaries.
                if (isset($cardSummary[$succ]) || isset($cardSummary[$pred])) {
                    $notesProject[] = 'Dependencia com tarefa-resumo nao exportada: ' . $pred . ' -> ' . $succ;
                    continue;
                }
                $edges[$succ][$pred] = true;
            }
        }
        if ($this->dependencyCycle($edges)) throw new \InvalidArgumentException('Cycle in Kanboard dependencies');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<Project xmlns="' . self::NS . '">' . "\n";
        $xml .= $this->fields(array(
            'SaveVersion'=>14, 'Name'=>(string) ($project['name'] ?? ''), 'Title'=>(string) ($project['name'] ?? ''),
            'StartDate'=>$this->iso($anchorDate,self::ANCHOR_CLOCK),
            'ScheduleFromStart'=>1, 'MinutesPerDay'=>1440, 'MinutesPerWeek'=>10080, 'DaysPerMonth'=>30, 'CalendarUID'=>1,
        ),1);
        $xml .= $this->calendarXml();
        $xml .= "  <Tasks>\n";
        $xml .= "    <Task>\n" . $this->fields(array(
            'UID'=>0, 'ID'=>0, 'Name'=>(string) ($project['name'] ?? ''), 'OutlineLevel'=>0, 'WBS'=>'0',
            'Start'=>$this->iso($anchorDate,self::ANCHOR_CLOCK),'Finish'=>$this->iso($anchorDate,self::ANCHOR_CLOCK),
            'Duration'=>'PT0H0M0S','Notes'=>implode("\n",$notesProject)),3) . "    </Task>\n";

        // Parent deadline milestones are appended as the last child, so a
        // Summary deadlines are shown with an auxiliary milestone in MSPDI readers.
        $atEnd = array();
        foreach ($ordered as $pos => $id) {
            if (!isset($summary[$id]) || $this->timestampDate($byId[$id]['date_due'] ?? 0) === null) continue;
            $last = $pos;
            for ($j=$pos+1; $j<count($ordered) && $levels[$ordered[$j]] > $levels[$id]; $j++) $last=$j;
            $atEnd[$last][] = $id;
        }
        // Native subtasks are emitted after the card's last descendant so WBS
        // remains valid even when a card also has child cards.
        $subAtEnd = array();
        if ($detailedSubtasks) {
            foreach ($ordered as $pos => $id) {
                if (empty($nativeNotes[$id])) continue;
                $last = $pos;
                for ($j=$pos+1; $j<count($ordered) && $levels[$ordered[$j]] > $levels[$id]; $j++) $last=$j;
                $subAtEnd[$last][] = $id;
            }
        }
        // A card remains a real Kanboard card (UID = card ID). For summary
        // cards, an additional read-only row preserves the card's OWN period
        // even if an MSPDI importer recalculates the summary bar from children.
        // Only a real Kanboard start can produce this row; without a finish it
        // is a START milestone, never a fabricated duration.
        $ownAtEnd = array();
        $ownPeriodRow = array();
        if ($detailedSubtasks) {
            foreach ($ordered as $pos => $id) {
                if (!isset($summary[$id]) || $this->timestampDate($byId[$id]['date_started'] ?? 0) === null) continue;
                $last = $pos;
                for ($j=$pos+1; $j<count($ordered) && $levels[$ordered[$j]] > $levels[$id]; $j++) $last=$j;
                $ownAtEnd[$last][] = $id;
                $ownPeriodRow[$id] = true;
            }
        }
        foreach ($atEnd as &$closing) usort($closing,function($a,$b) use($levels){return $levels[$b] <=> $levels[$a];});
        unset($closing);
        $nextUid = $ids ? max($ids)+1 : 1;
        $nextId=1;
        foreach ($ordered as $pos => $id) {
            $task = $byId[$id];
            $start = $this->timestampDate($task['date_started'] ?? 0);
            $due = $this->timestampDate($task['date_due'] ?? 0);
            $hasStart = $start !== null;
            $hasDue = $due !== null;
            $isSummary = isset($summary[$id]);
            $startDate = $start ?: ($due ?: $anchorDate);
            $endDate = $due ?: $startDate;
            $startIso = $this->sourceIso($task['date_started'] ?? 0)
                ?: ($this->sourceIso($task['date_due'] ?? 0) ?: $this->iso($anchorDate,self::ANCHOR_CLOCK));
            $finishIso = $this->sourceIso($task['date_due'] ?? 0) ?: $startIso;
            $issues=array();
            if (!$hasStart) $issues[]='Data de inicio NAO CADASTRADA no Kanboard.';
            if (!$hasDue) $issues[]='Prazo final NAO CADASTRADO no Kanboard.';
            if ($isSummary && $hasDue) {
                foreach ($children[$id] ?? array() as $childId) {
                    $childDue=$this->timestampDate($byId[$childId]['date_due'] ?? 0);
                    if ($childDue !== null && $childDue > $due) {
                        $issues[]='CONFLITO NA ORIGEM: filho Kanboard #'.$childId.' vence em '.$childDue.' depois do pai ('.$due.').';
                    }
                }
            }
            if ($hasStart && $hasDue && $due < $start) {
                // No silent clamping. The source must be corrected first.
                throw new \InvalidArgumentException('Due date precedes start date for Kanboard card ' . $id);
            }
            if (!$hasStart && !$hasDue) $issues[]='Marco colocado em data de referencia APENAS para visualizacao; NAO e um planejamento.';
            if ($hasStart xor $hasDue) $issues[]='Somente um limite temporal informado; NAO foi criado periodo de execucao.';
            $duration = 'PT0H0M0S';
            if ($hasStart && $hasDue) {
                $duration = $this->durationBetween($task['date_started'], $task['date_due']);
            }
            $notes = 'Source: kanboard:' . $project['id'] . ':' . $id;
            $notes .= "\nOriginal Kanboard start: " . $this->rawDate($task['date_started'] ?? 0);
            $notes .= "\nOriginal Kanboard due: " . $this->rawDate($task['date_due'] ?? 0);
            $notes .= "\nEstimated effort Kanboard (hours): " . (string) ($task['time_estimated'] ?? '0');
            $notes .= "\nHorarios XML: os originais quando cadastrados. Calendario 24h/7 dias; duracao XML com compatibilidade ProjectLibre 1.9.8 (verificar em outros importadores). Nao equivale a estimativa de trabalho.";
            if ($isSummary) $notes .= "\nTarefa-resumo: o aplicativo de planejamento pode calcular a barra pelos filhos; o prazo original aparece em marco auxiliar." .
                ($detailedSubtasks && isset($ownPeriodRow[$id]) ? "\nO periodo original do cartao aparece tambem numa linha auxiliar propria, distinta das subtarefas." : "");
            if (isset($nativeNotes[$id])) {
                $notes .= "\nSubtarefas Kanboard, sem datas proprias (nao foram agendadas artificialmente):";
                foreach ($nativeNotes[$id] as $sub) {
                    $notes .= "\n - #" . (int) $sub['id'] . ' ' . (string) ($sub['title'] ?? '') .
                        ' [estimativa(h): ' . (string) ($sub['time_estimated'] ?? '0') . ']';
                }
            }
            if ($issues) $notes .= "\nALERTAS: " . implode(' | ',$issues);
            $name = (string) ($task['title'] ?? 'Task ' . $id);
            if (!$hasStart && !$hasDue) $name .= ' [SEM DATAS — NAO PLANEJADO]';
            $fields = array(
                'UID'=>$id,'ID'=>$nextId++,'Name'=>$name,'WBS'=>$wbs[$id],'OutlineLevel'=>$levels[$id],
                'Start'=>$startIso,
                'Finish'=>$finishIso,
                'Duration'=>$duration, 'DurationFormat'=>7,
                'PercentComplete'=>!empty($task['date_completed']) ? 100 : 0,
                'Milestone'=>(!$isSummary && !($hasStart && $hasDue)) ? 1 : 0,
            );
            if ($hasDue) $fields['Deadline']=$this->sourceIso($task['date_due']);
            if (!$isSummary && ($hasStart || $hasDue)) {
                $fields['ConstraintType']=2; // Must Start On: pin the supplied single date/range.
                $fields['ConstraintDate']=$startIso;
            }
            $fields['Notes']=$notes;
            $xml .= "    <Task>\n" . $this->fields($fields,3);
            foreach (array_keys($edges[$id] ?? array()) as $pred) {
                $xml .= "      <PredecessorLink>\n";
                $xml .= $this->fields(array('PredecessorUID'=>$pred,'Type'=>1,'LinkLag'=>0,'LagFormat'=>7),4);
                $xml .= "      </PredecessorLink>\n";
            }
            $xml .= "    </Task>\n";
            $closingOwners = array_unique(array_merge($ownAtEnd[$pos] ?? array(), $subAtEnd[$pos] ?? array(), $atEnd[$pos] ?? array()));
            usort($closingOwners, function ($a, $b) use ($levels) { return $levels[$b] <=> $levels[$a]; });
            foreach ($closingOwners as $closingOwner) {
            // The card's own period is never inferred from native subtasks.
            // It is displayed as a direct child because MSPDI summary tasks
            // do not have an independently schedulable execution bar.
            if (in_array($closingOwner, $ownAtEnd[$pos] ?? array(), true)) {
                if ($nextUid > 2147483647) throw new \RuntimeException('No more UIDs for card period rows');
                $ownTask = $byId[$closingOwner];
                $ownStart = $this->sourceIso($ownTask['date_started']);
                $ownFinish = $this->sourceIso($ownTask['date_due'] ?? 0);
                $ownHasPeriod = $ownFinish !== null;
                $ownFields = array(
                    'UID'=>$nextUid++, 'ID'=>$nextId++,
                    'Name'=>($ownHasPeriod ? 'Periodo do cartao: ' : 'Inicio do cartao: ') . (string)($ownTask['title'] ?? ('#' . $closingOwner)),
                    'WBS'=>$wbs[$closingOwner] . '.' . (count($children[$closingOwner] ?? array()) + 1),
                    'OutlineLevel'=>$levels[$closingOwner] + 1,
                    'Start'=>$ownStart,
                    'Finish'=>$ownHasPeriod ? $ownFinish : $ownStart,
                    'Duration'=>$ownHasPeriod ? $this->durationBetween($ownTask['date_started'], $ownTask['date_due']) : 'PT0H0M0S',
                    'DurationFormat'=>7,
                    'Milestone'=>$ownHasPeriod ? 0 : 1,
                    'ConstraintType'=>2, 'ConstraintDate'=>$ownStart,
                    'Notes'=>'Linha auxiliar de visualizacao do periodo ORIGINAL do cartao Kanboard #' . $closingOwner .
                        "\nSource: kanboard-card-period:" . (int)$project['id'] . ':' . $closingOwner .
                        "\nNao e um novo cartao nem uma subtarefa; nao implica duracao das subtarefas.'
                );
                if ($ownHasPeriod) $ownFields['Deadline'] = $ownFinish;
                $xml .= "    <Task>\n" . $this->fields($ownFields,3) . "    </Task>\n";
            }
            if (in_array($closingOwner, $subAtEnd[$pos] ?? array(), true)) {
                $parentId = $closingOwner;
                foreach ($nativeNotes[$parentId] as $subIndex => $sub) {
                    if ($nextUid > 2147483647) throw new \RuntimeException('No more UIDs for native subtasks');
                    $number = count($children[$parentId] ?? array()) + (isset($ownPeriodRow[$parentId]) ? 1 : 0) + $subIndex + 1;
                    $subName = (string) ($sub['title'] ?? ('Subtask ' . $sub['id']));
                    $period = $subtaskPeriods[$parentId][(int)$sub['id']] ?? null;
                    $subStart = $period ? $this->sourceIso($period['start']) : $this->iso($anchorDate,self::ANCHOR_CLOCK);
                    $subFinish = $period ? $this->sourceIso($period['finish']) : $subStart;
                    $subFields = array(
                        'UID'=>$nextUid++, 'ID'=>$nextId++,
                        'Name'=>$subName . ($period ? '' : ' [SEM DATAS — NAO PLANEJADA]'),
                        'WBS'=>$wbs[$parentId] . '.' . $number,
                        'OutlineLevel'=>$levels[$parentId] + 1,
                        'Start'=>$subStart,
                        'Finish'=>$subFinish,
                        'Duration'=>$period ? $this->durationBetween($period['start'],$period['finish']) : 'PT0H0M0S',
                        'DurationFormat'=>7,'Milestone'=>$period ? 0 : 1,
                        'Notes'=>'Source: kanboard-subtask:' . $parentId . ':' . (int)$sub['id'] .
                            ($period
                                ? "\nPeriodo explicito cadastrado na descricao do cartao Kanboard; horario local: " . $subStart . ' a ' . $subFinish . '.'
                                : "\nSubtarefa nativa SEM DATAS PROPRIAS; marco de referencia tecnico, NAO cronograma.") .
                            "\nEstimated effort Kanboard (hours): " . (string)($sub['time_estimated'] ?? '0')
                    );
                    if ($period) {
                        $subFields['ConstraintType'] = 2;
                        $subFields['ConstraintDate'] = $subStart;
                    }
                    $xml .= "    <Task>\n" . $this->fields($subFields,3) . "    </Task>\n";
                }
            }
            if (in_array($closingOwner, $atEnd[$pos] ?? array(), true)) {
                $parent = $closingOwner;
                if ($nextUid>2147483647) throw new \RuntimeException('No more UIDs for deadline markers');
                $end=$this->timestampDate($byId[$parent]['date_due']);
                $marker = array(
                    'UID'=>$nextUid++,'ID'=>$nextId++, 'Name'=>'Prazo Kanboard: '.$end,
                    'WBS'=>$wbs[$parent].'.'.(count($children[$parent] ?? array()) + (isset($ownPeriodRow[$parent]) ? 1 : 0) + ($detailedSubtasks ? count($nativeNotes[$parent] ?? array()) : 0) + 1),
                    'OutlineLevel'=>$levels[$parent]+1,
                    'Start'=>$this->sourceIso($byId[$parent]['date_due']), 'Finish'=>$this->sourceIso($byId[$parent]['date_due']),
                    'Duration'=>'PT0H0M0S','DurationFormat'=>7,'Milestone'=>1,
                    'ConstraintType'=>2,'ConstraintDate'=>$this->sourceIso($byId[$parent]['date_due']),
                    'Notes'=>'Marco auxiliar, NAO e um cartao. Data/hora originais: '.$this->rawDate($byId[$parent]['date_due']) .
                        "\nCalendario 24 horas preserva o instante do prazo; marcos auxiliares nao representam trabalho.",
                );
                $xml .= "    <Task>\n" . $this->fields($marker,3) . "    </Task>\n";
            }
            }
        }
        return $xml . "  </Tasks>\n</Project>\n";
    }

    private function parseLocalPeriodTimestamp($iso)
    {
        $value = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s', (string)$iso, $this->timezone);
        if (!$value || $value->format('Y-m-d\TH:i:s') !== $iso) {
            throw new \InvalidArgumentException('Invalid local date/time in SE_SUBTASK_PERIOD');
        }
        return $value->getTimestamp();
    }

    private function calendarXml()
    {
        $xml="  <Calendars>\n    <Calendar>\n";
        $xml.=$this->fields(array('UID'=>1,'Name'=>'Kanboard 7x24 source timestamps','IsBaseCalendar'=>1,'BaseCalendarUID'=>-1),3);
        $xml.="      <WeekDays>\n";
        for ($day=1;$day<=7;$day++) {
            $xml.="        <WeekDay>\n";
            $xml.=$this->fields(array('DayType'=>$day,'DayWorking'=>1),5);
            $xml.="          <WorkingTimes>\n";
            foreach (array(array('00:00:00','12:00:00'),array('12:00:00','23:59:59')) as $times) {
                $xml.="            <WorkingTime>\n";
                $xml.=$this->fields(array('FromTime'=>$times[0],'ToTime'=>$times[1]),7);
                $xml.="            </WorkingTime>\n";
            }
            $xml.="          </WorkingTimes>\n        </WeekDay>\n";
        }
        return $xml."      </WeekDays>\n    </Calendar>\n  </Calendars>\n";
    }
    private function fields(array $values,$indent)
    {
        $s=str_repeat('  ',$indent);$out='';
        foreach ($values as $k=>$v) $out.=$s.'<'.$k.'>'.$this->escape((string)$v).'</'.$k.">\n";
        return $out;
    }
    private function escape($value)
    {
        return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/','',(string)$value),ENT_XML1|ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    }
    private function timestampDate($timestamp)
    {
        return is_numeric($timestamp) && (int)$timestamp > 0 ? $this->localTime($timestamp,'Y-m-d') : null;
    }
    private function sourceIso($timestamp)
    {
        return is_numeric($timestamp) && (int)$timestamp > 0 ? $this->localTime($timestamp,'Y-m-d\TH:i:s') : null;
    }
    private function durationBetween($start,$due)
    {
        $seconds=(int)$due-(int)$start;
        if ($seconds < 0) throw new \InvalidArgumentException('Finish before start in Kanboard');
        if ($seconds > 86400*10000) throw new \InvalidArgumentException('Duration above safety limit');
        // ProjectLibre Desktop 1.9.8 treats a 00:00:00..23:59:59
        // 7x24 working day as 1439 minutes, not 1440. For every complete
        // elapsed 24-hour period, its XML importer shifts Finish forward
        // by one minute if given the mathematically exact Duration.
        // Adjust *only the MSPDI Duration*, never source Start/Finish/Deadline.
        // Verified against a real Kanboard reference XML and a separate
        // ProjectLibre import diagnostic. This is importer compatibility,
        // not a change in the Kanboard schedule or effort estimate.
        $calendarLossSeconds = intdiv($seconds, 86400) * 60;
        $importerDuration = $seconds - $calendarLossSeconds;
        $hours=intdiv($importerDuration,3600);
        $minutes=intdiv($importerDuration % 3600,60);
        $rest=$importerDuration % 60;
        return 'PT'.$hours.'H'.$minutes.'M'.$rest.'S';
    }
    private function rawDate($timestamp)
    {
        return is_numeric($timestamp) && (int)$timestamp > 0 ? $this->localTime($timestamp,'d/m/Y H:i') : 'NOT SET';
    }
    private function validDate($date)
    {
        $obj=DateTimeImmutable::createFromFormat('!Y-m-d',(string)$date,new DateTimeZone('UTC'));
        if (!$obj || $obj->format('Y-m-d')!==$date) throw new \InvalidArgumentException('Invalid anchor date');
        return $date;
    }
    private function iso($date,$time){return $date.'T'.$time;}
    private function daysInclusive($from,$to)
    {
        $a=new DateTimeImmutable($this->validDate($from),new DateTimeZone('UTC'));
        $b=new DateTimeImmutable($this->validDate($to),new DateTimeZone('UTC'));
        $num=(int)$a->diff($b)->days+1;
        if ($num>10000) throw new \InvalidArgumentException('Date range too long');
        return $num;
    }
    private function relationKind($label)
    {
        $name=strtolower(trim((string)$label)); // labels in supported relation types are ASCII or accented uppercase
        $name = strtr($name,array('é'=>'e','ê'=>'e','á'=>'a','ã'=>'a','í'=>'i','ó'=>'o','ç'=>'c'));
        $types=array(
            'is a parent of'=>'parent','is parent of'=>'parent','parent of'=>'parent','e pai de'=>'parent','pai de'=>'parent',
            'is a child of'=>'child','is child of'=>'child','child of'=>'child','e filho de'=>'child','filho de'=>'child',
            'blocks'=>'blocks','bloqueia'=>'blocks','is blocked by'=>'blocked_by','blocked by'=>'blocked_by',
            'e bloqueado por'=>'blocked_by','bloqueado por'=>'blocked_by'
        );
        return $types[$name] ?? null;
    }
    private function dependencyCycle(array $edges)
    {
        $mark=array();
        $visit=function($node) use(&$visit,&$mark,$edges){
            if (($mark[$node]??0)===1) return true;
            if (($mark[$node]??0)===2) return false;
            $mark[$node]=1;
            foreach(array_keys($edges[$node]??array()) as $pred) if ($visit($pred)) return true;
            $mark[$node]=2;return false;
        };
        foreach(array_keys($edges) as $id) if ($visit($id)) return true;
        return false;
    }
}
