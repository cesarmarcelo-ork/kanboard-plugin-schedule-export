<?php
require __DIR__ . '/../Exporter/ProjectXmlExporter.php';

use Kanboard\Plugin\ScheduleExport\Exporter\ProjectXmlExporter;

function ensure($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
$exporter = new ProjectXmlExporter('UTC');
$day = strtotime('2026-10-26 08:00:00 UTC');
$project = array('id'=>1,'name'=>'EAP regression');
$tasks = array(
    array('id'=>10,'project_id'=>1,'title'=>'Parent','position'=>1,'date_started'=>$day,'date_due'=>$day+86400,'time_estimated'=>4),
    array('id'=>11,'project_id'=>1,'title'=>'Child','position'=>2,'date_started'=>$day,'date_due'=>$day+3600,'time_estimated'=>1),
);
$links = array(10=>array(array('task_id'=>11,'label'=>'is a parent of')));
$subtasks = array(
    10=>array(array('id'=>15,'task_id'=>10,'title'=>'Checklist A','time_estimated'=>2)),
    11=>array(array('id'=>16,'task_id'=>11,'title'=>'Checklist B','time_estimated'=>1)),
);
$legacy = $exporter->generate($project,$tasks,$links,'2026-10-26',$subtasks);
$explicitLegacy = $exporter->generate($project,$tasks,$links,'2026-10-26',$subtasks,false);
ensure($legacy === $explicitLegacy,'Legacy mode changed');
ensure(strpos($legacy,'kanboard-subtask:') === false,'Legacy mode created subtask rows');
$detailed = $exporter->generate($project,$tasks,$links,'2026-10-26',$subtasks,true);
ensure(strpos($detailed,'Source: kanboard-subtask:10:15') !== false,'Missing parent subtask');
ensure(strpos($detailed,'Source: kanboard-subtask:11:16') !== false,'Missing nested subtask');
ensure(strpos($detailed,'<WBS>1.2</WBS>') !== false,'Missing parent subtask WBS');
ensure(strpos($detailed,'<WBS>1.1.1</WBS>') !== false,'Missing nested subtask WBS');
ensure(strpos($detailed,'<WBS>1.3</WBS>') !== false,'Missing deadline milestone WBS');
ensure(strpos($detailed,'NAO PLANEJADA') !== false,'Missing no-dates warning');
ensure(substr_count($detailed,'<UID>') === count(array_unique(
    array_map('intval', preg_match_all('/<UID>(\\d+)<\\/UID>/', $detailed, $matches) ? $matches[1] : array())
)), 'UID collision');
echo "PASS: v0.3.0 legacy and detailed WBS regression\n";
