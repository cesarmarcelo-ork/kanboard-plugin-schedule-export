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
    array('id'=>20,'project_id'=>1,'title'=>'Native only','position'=>3,'date_started'=>strtotime('2026-10-27 09:00:00 UTC'),'date_due'=>strtotime('2026-10-29 17:00:00 UTC'),'time_estimated'=>3,'description'=>'[SE_SUBTASK_PERIOD id=17 start=2026-10-27T09:00:00 end=2026-10-29T17:00:00]'),
    array('id'=>30,'project_id'=>1,'title'=>'After Native','position'=>4,'date_started'=>strtotime('2026-10-29 18:00:00 UTC'),'date_due'=>strtotime('2026-10-30 18:00:00 UTC'),'time_estimated'=>2),
    array('id'=>40,'project_id'=>1,'title'=>'Card with undated native','position'=>5,'date_started'=>strtotime('2026-11-10 10:00:00 UTC'),'date_due'=>strtotime('2026-11-12 18:00:00 UTC'),'time_estimated'=>3),
);
$links = array(
    10=>array(array('task_id'=>11,'label'=>'is a parent of')),
    20=>array(array('task_id'=>30,'label'=>'blocks'))
);
$subtasks = array(
    10=>array(array('id'=>15,'task_id'=>10,'title'=>'Checklist A','time_estimated'=>2)),
    11=>array(array('id'=>16,'task_id'=>11,'title'=>'Checklist B','time_estimated'=>1)),
    20=>array(array('id'=>17,'task_id'=>20,'title'=>'No child cards','time_estimated'=>2)),
    40=>array(array('id'=>18,'task_id'=>40,'title'=>'Undated native','time_estimated'=>2)),
);
$legacy = $exporter->generate($project,$tasks,$links,'2026-10-26',$subtasks);
$explicitLegacy = $exporter->generate($project,$tasks,$links,'2026-10-26',$subtasks,false);
ensure($legacy === $explicitLegacy,'Legacy mode changed');
ensure(strpos($legacy,'kanboard-subtask:') === false,'Legacy mode created subtask rows');
$detailed = $exporter->generate($project,$tasks,$links,'2026-10-26',$subtasks,true);
ensure(strpos($detailed,'Source: kanboard-subtask:10:15') !== false,'Missing parent subtask');
ensure(strpos($detailed,'Source: kanboard-subtask:11:16') !== false,'Missing nested subtask');
ensure(strpos($detailed,'<WBS>1.3</WBS>') !== false,'Missing parent subtask WBS');
ensure(strpos($detailed,'<WBS>1.1.2</WBS>') !== false,'Missing nested subtask WBS');
ensure(strpos($detailed,'<WBS>1.4</WBS>') !== false,'Missing deadline milestone WBS');
ensure(strpos($detailed,'Source: kanboard-subtask:20:17') !== false,'Missing native-only parent subtask');
ensure(strpos($detailed,'Source: kanboard-card-period:1:10') !== false,'Missing own bar of parent with child cards');
ensure(strpos($detailed,'Source: kanboard-card-period:1:11') !== false,'Missing own bar of child card with subtasks');
ensure(strpos($detailed,'Source: kanboard-card-period:1:20') !== false,'Missing own bar of card with only subtasks');
ensure(strpos($detailed,'Source: kanboard-card-period:1:40') !== false,'Missing own bar of card with undated subtasks');
ensure((bool)preg_match('~<Task>(?:(?!</Task>).)*<Name>Undated native \[SEM DATAS — NAO PLANEJADA\]</Name>(?:(?!</Task>).)*<Start>2026-11-10T10:00:00</Start>(?:(?!</Task>).)*<Milestone>1</Milestone>(?:(?!</Task>).)*</Task>~s',$detailed),'Technical subtask milestone should be anchored at card start, not earliest project date');

ensure(strpos($legacy,'kanboard-card-period:') === false,'Legacy mode must not introduce synthetic own bars');
ensure(substr_count($detailed,'<Name>Periodo do cartao: ') === 4,'Expected own bar for all four summary cards');
ensure((bool)preg_match('~<Task>(?:(?!</Task>).)*<Name>Periodo do cartao: Native only</Name>(?:(?!</Task>).)*<Start>2026-10-27T09:00:00</Start>(?:(?!</Task>).)*<Finish>2026-10-29T17:00:00</Finish>(?:(?!</Task>).)*<Milestone>0</Milestone>(?:(?!</Task>).)*</Task>~s',$detailed),'Native-only card lost its own planned bar');
ensure((bool)preg_match('~<Task>(?:(?!</Task>).)*<UID>20</UID>(?:(?!</Task>).)*<Name>Native only</Name>(?:(?!</Task>).)*</Task>~s',$detailed),'Original card UID must remain visible');

ensure(strpos($detailed,'<WBS>2.2</WBS>') !== false,'Missing native-only WBS');
ensure(strpos($detailed,'<WBS>2.3</WBS>') !== false,'Missing native-only deadline milestone');
ensure(strpos($detailed,'<Start>2026-10-27T09:00:00</Start>') !== false,'Explicit subtask start missing');
ensure(strpos($detailed,'<Finish>2026-10-29T17:00:00</Finish>') !== false,'Explicit subtask finish missing');
ensure((bool)preg_match('~<Task>(?:(?!</Task>).)*<Name>No child cards</Name>(?:(?!</Task>).)*<Milestone>0</Milestone>(?:(?!</Task>).)*</Task>~s', $detailed),'Period subtask should show Gantt bar');
ensure(strpos($legacy,'<Name>No child cards</Name>') === false,'Legacy must not produce subtask row');

ensure(strpos($legacy,'<PredecessorUID>20</PredecessorUID>') !== false,'Legacy blocker missing');
ensure(strpos($detailed,'<PredecessorUID>20</PredecessorUID>') !== false,'Detailed mode removed existing blocker');
ensure(strpos($detailed,'NAO PLANEJADA') !== false,'Missing no-dates warning');
ensure(substr_count($detailed,'<UID>') === count(array_unique(
    array_map('intval', preg_match_all('/<UID>(\\d+)<\\/UID>/', $detailed, $matches) ? $matches[1] : array())
)), 'UID collision');
// Invalid, reversed, duplicate or unknown periods must never create inferred schedules.
$invalidDescriptions = array(
    '[SE_SUBTASK_PERIOD id=17 start=2026-10-29T17:00:00 end=2026-10-27T09:00:00]',
    '[SE_SUBTASK_PERIOD id=17 start=2026-02-30T09:00:00 end=2026-03-01T09:00:00]',
    '[SE_SUBTASK_PERIOD id=18 start=2026-10-27T09:00:00 end=2026-10-29T17:00:00]',
    '[SE_SUBTASK_PERIOD id=17 start=2026-10-27T09:00:00 end=2026-10-29T17:00:00]' . "\n" .
      '[SE_SUBTASK_PERIOD id=17 start=2026-10-28T09:00:00 end=2026-10-29T17:00:00]',
    '[SE_SUBTASK_PERIOD id=17 start=2026-10-27T09:00:00]',
);
foreach ($invalidDescriptions as $badDescription) {
    $badTasks = $tasks;
    $badTasks[2]['description'] = $badDescription;
    try {
        $exporter->generate($project, $badTasks, $links, '2026-10-26', $subtasks, true);
        throw new RuntimeException('Invalid subtask period was accepted');
    } catch (InvalidArgumentException $expected) {
        // Invalid declarations fail explicitly; no silent inferred bar.
    }
}
// Synthetic, non-sensitive MSPDI sample for manual import in ProjectLibre/OPS.
file_put_contents(__DIR__ . '/fixture_v030.xml', $detailed);
echo "PASS: v0.3.0 legacy and detailed WBS regression\n";
