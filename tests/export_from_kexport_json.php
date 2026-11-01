<?php
/**
 * Offline QA only. Uses an external K-ExportImport snapshot as authoritative
 * test data. Does NOT change Kanboard; DO NOT place private JSON in GitHub.
 * Usage: php tests/export_from_kexport_json.php file.json output.xml
 */
require __DIR__ . '/../Exporter/ProjectXmlExporter.php';
use Kanboard\Plugin\ScheduleExport\Exporter\ProjectXmlExporter;
if ($argc !== 3) { fwrite(STDERR, "Usage: php export_from_kexport_json.php snapshot.json output.xml\n"); exit(2); }
$raw=file_get_contents($argv[1]);
if ($raw === false) throw new RuntimeException('Snapshot is unreadable');
$data=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
if (($data['format'] ?? '') !== 'K-Export&Import/1.2') throw new InvalidArgumentException('Unexpected reference format');
if (empty($data['project']['id']) || !isset($data['cards'])) throw new InvalidArgumentException('Invalid project snapshot');
date_default_timezone_set('America/Sao_Paulo');
function parseDateField($value) {
    if (!is_string($value) || $value==='' || $value==='Iniciar agora') return 0;
    $date=DateTimeImmutable::createFromFormat('!d/m/Y H:i',$value,new DateTimeZone('America/Sao_Paulo'));
    if (!$date || $date->format('d/m/Y H:i')!==$value) throw new InvalidArgumentException('Unrecognized source date: '.$value);
    return $date->getTimestamp();
}
$projectId=(int)$data['project']['id'];
$tasks=[]; $links=[]; $subtasks=[]; $byId=[];
foreach ($data['cards'] as $card) {
    $b=$card['base'];$info=$card['technical'];$id=(int)$info['sourceId'];
    if ($id<1 || isset($byId[$id]) || (int)$b['task_id']!==$id) throw new InvalidArgumentException('Invalid or duplicate ID');
    $byId[$id]=true;
    $tasks[]=array('id'=>$id,'project_id'=>$projectId,'title'=>$b['title'],
        'position'=>(int)($b['position'] ?: 0),
        'date_started'=>parseDateField($b['date_started']),
        'date_due'=>parseDateField($b['date_due']),
        'time_estimated'=>(float)($b['time_estimated'] ?: 0),
        'date_completed'=>parseDateField($b['date_completed'] ?? ''));
    foreach ($card['detail']['subtasks'] as $sub) {
        $subtasks[$id][]=array('id'=>(int)$sub['id'],'task_id'=>$id,'title'=>$sub['title'],
            'time_estimated'=>(float)($sub['timeEstimated'] ?: 0));
    }
    if (($info['sourceParentId'] ?? '') !== '') {
        $parent=(int)$info['sourceParentId'];
        $links[$parent][]=array('task_id'=>$id,'label'=>'is a parent of');
    }
    // Include unrelated links to prove they cannot become false dependencies.
    foreach ($card['detail']['internalLinks'] as $link) {
        if (($link['hierarchyType']??'other')==='other') {
            $links[$id][]=array('task_id'=>(int)$link['taskId'],'label'=>(string)$link['label']);
        }
    }
}
if (count($tasks)!==36 || array_sum(array_map('count',$subtasks))!==116) throw new RuntimeException('Reference counts changed');
$xml=(new ProjectXmlExporter())->generate(
    array('id'=>$projectId,'name'=>$data['project']['name']),$tasks,$links,'2026-09-01',$subtasks);
if(file_put_contents($argv[2],$xml)===false) throw new RuntimeException('Unable to write XML');
echo 'PASS: generated MSPDI from authoritative K-ExportImport JSON; '
    .count($tasks).' cards; '.array_sum(array_map('count',$subtasks))." native subtasks.\n";
