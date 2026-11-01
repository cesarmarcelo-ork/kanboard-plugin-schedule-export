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
        $summary = array_fill_keys(array_keys($children), true);
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
                if (isset($summary[$succ]) || isset($summary[$pred])) {
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
                foreach ($children[$id] as $childId) {
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
            if ($isSummary) $notes .= "\nTarefa-resumo: o aplicativo de planejamento pode calcular a barra pelos filhos; o prazo original aparece em marco auxiliar.";
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
            $closingOwners = array_unique(array_merge(array_keys(array_fill_keys($subAtEnd[$pos] ?? array(), true)), array_keys(array_fill_keys($atEnd[$pos] ?? array(), true))));
            usort($closingOwners, function ($a, $b) use ($levels) { return $levels[$b] <=> $levels[$a]; });
            foreach ($closingOwners as $closingOwner) {
            if (in_array($closingOwner, $subAtEnd[$pos] ?? array(), true)) {
                $parentId = $closingOwner;
                foreach ($nativeNotes[$parentId] as $subIndex => $sub) {
                    if ($nextUid > 2147483647) throw new \RuntimeException('No more UIDs for native subtasks');
                    $number = count($children[$parentId] ?? array()) + $subIndex + 1;
                    $subName = (string) ($sub['title'] ?? ('Subtask ' . $sub['id']));
                    $subFields = array(
                        'UID'=>$nextUid++, 'ID'=>$nextId++,
                        'Name'=>$subName . ' [SEM DATAS — NAO PLANEJADA]',
                        'WBS'=>$wbs[$parentId] . '.' . $number,
                        'OutlineLevel'=>$levels[$parentId] + 1,
                        'Start'=>$this->iso($anchorDate,self::ANCHOR_CLOCK),
                        'Finish'=>$this->iso($anchorDate,self::ANCHOR_CLOCK),
                        'Duration'=>'PT0H0M0S','DurationFormat'=>7,'Milestone'=>1,
                        'Notes'=>'Source: kanboard-subtask:' . $parentId . ':' . (int)$sub['id'] .
                            "\nSubtarefa nativa SEM DATAS PROPRIAS; marco de referencia tecnico, NAO cronograma." .
                            "\nEstimated effort Kanboard (hours): " . (string)($sub['time_estimated'] ?? '0')
                    );
                    $xml .= "    <Task>\n" . $this->fields($subFields,3) . "    </Task>\n";
                }
            }
            if (in_array($closingOwner, $atEnd[$pos] ?? array(), true)) {
                $parent = $closingOwner;
                if ($nextUid>2147483647) throw new \RuntimeException('No more UIDs for deadline markers');
                $end=$this->timestampDate($byId[$parent]['date_due']);
                $marker = array(
                    'UID'=>$nextUid++,'ID'=>$nextId++, 'Name'=>'Prazo Kanboard: '.$end,
                    'WBS'=>$wbs[$parent].'.'.(count($children[$parent] ?? array()) + ($detailedSubtasks ? count($nativeNotes[$parent] ?? array()) : 0) + 1),
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
