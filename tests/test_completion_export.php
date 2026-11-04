<?php
require __DIR__.'/../Model/SubtaskCompletionModel.php';
require __DIR__.'/../Model/SubtaskPlanModel.php';
require __DIR__.'/../Schema/Sqlite.php';
require __DIR__.'/../Exporter/ProjectXmlExporter.php';

use Kanboard\Plugin\ScheduleExport\Model\SubtaskCompletionModel;
use Kanboard\Plugin\ScheduleExport\Model\SubtaskPlanModel;
use Kanboard\Plugin\ScheduleExport\Exporter\ProjectXmlExporter;

function exportAssert($ok, $message) { if (!$ok) throw new RuntimeException($message); }
function xmlTask($xml, $source) {
    $doc = simplexml_load_string($xml);
    $doc->registerXPathNamespace('m', ProjectXmlExporter::NS);
    foreach ($doc->xpath('//m:Task') as $task) {
        if (strpos((string)$task->Notes, $source) !== false) return $task;
    }
    throw new RuntimeException('Task not found: '.$source);
}

date_default_timezone_set('America/Sao_Paulo');
$db = new PDO('sqlite::memory:', null, null, array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));
$db->exec('PRAGMA foreign_keys=ON');
$db->exec('CREATE TABLE projects(id INTEGER PRIMARY KEY)');
$db->exec('CREATE TABLE tasks(id INTEGER PRIMARY KEY, project_id INTEGER NOT NULL)');
$db->exec('CREATE TABLE users(id INTEGER PRIMARY KEY, username TEXT)');
$db->exec('CREATE TABLE subtasks(id INTEGER PRIMARY KEY, task_id INTEGER NOT NULL, status INTEGER NOT NULL)');
$db->exec("INSERT INTO projects VALUES(1); INSERT INTO tasks VALUES(10,1); INSERT INTO users VALUES(7,'cesar')");
$db->exec('INSERT INTO subtasks VALUES(100,10,2),(101,10,2),(102,10,0),(103,10,1)');
\Kanboard\Plugin\ScheduleExport\Schema\version_1($db);
\Kanboard\Plugin\ScheduleExport\Schema\version_2($db);

$plans = new SubtaskPlanModel($db);
foreach (array(100,101,102,103) as $id) {
    $plans->save(1,10,$id,'2026-11-01T08:00:00','2026-11-03T17:00:00',480,array(1,2,3,4,5),7);
}
$actualUtc = strtotime('2026-11-04 01:30:00 UTC');
$db->exec('INSERT INTO schedule_export_subtask_completions VALUES(100,'.$actualUtc.',1)');
$db->exec('INSERT INTO schedule_export_subtask_completions VALUES(102,NULL,2)');

$subtasks = array(10=>array(
    array('id'=>100,'task_id'=>10,'title'=>'Completed recorded','status'=>2,'time_estimated'=>8),
    array('id'=>101,'task_id'=>10,'title'=>'Historical completed','status'=>2,'time_estimated'=>3),
    array('id'=>102,'task_id'=>10,'title'=>'Reopened','status'=>0,'time_estimated'=>2),
    array('id'=>103,'task_id'=>10,'title'=>'In progress','status'=>1,'time_estimated'=>1),
));
$tasks = array(array('id'=>10,'project_id'=>1,'title'=>'Card','position'=>1,'date_started'=>strtotime('2026-11-01 08:00:00 -03:00'),'date_due'=>strtotime('2026-11-03 17:00:00 -03:00')));
$completionModel = new SubtaskCompletionModel($db);
$completions = $completionModel->getByTaskIds(array(10));
exportAssert(isset($completions[100]) && (int)$completions[100]['project_id']===1, 'Completion batch query lost ownership');

$xml = (new ProjectXmlExporter('America/Sao_Paulo'))->generate(
    array('id'=>1,'name'=>'Completion interoperability'), $tasks, array(), '2026-11-01',
    $subtasks, true, $plans->getByTaskIds(array(10)), $completions
);

$recorded = xmlTask($xml,'kanboard-subtask:10:100');
exportAssert((string)$recorded->Finish==='2026-11-03T17:00:00','Planned Finish was overwritten');
exportAssert((string)$recorded->Duration==='PT57H0M0S','Elapsed duration was converted to working hours');
exportAssert((string)$recorded->DurationFormat==='8','Elapsed-day duration format missing');
exportAssert((string)$recorded->ActualFinish==='2026-11-03T22:30:00','ActualFinish did not use export timezone');
exportAssert((string)$recorded->PercentComplete==='100','Completed subtask percentage missing');
exportAssert((string)$recorded->PercentWorkComplete==='100','Completed work percentage missing');
exportAssert((string)$recorded->RemainingDuration==='PT0H0M0S','Completed subtask has remaining duration');

$historical = xmlTask($xml,'kanboard-subtask:10:101');
exportAssert((string)$historical->PercentComplete==='100','Historical done status missing');
exportAssert(!isset($historical->ActualFinish),'Historical completion date was invented');

$reopened = xmlTask($xml,'kanboard-subtask:10:102');
exportAssert((string)$reopened->PercentComplete==='0' && !isset($reopened->ActualFinish),'Reopened task exported as completed');
$progress = xmlTask($xml,'kanboard-subtask:10:103');
exportAssert((string)$progress->PercentComplete==='0' && !isset($progress->ActualFinish),'In-progress task exported as completed');

try {
    $bad = $completions;
    $bad[100]['project_id'] = 2;
    (new ProjectXmlExporter())->generate(array('id'=>1,'name'=>'Bad'),$tasks,array(),'2026-11-01',$subtasks,true,$plans->getByTaskIds(array(10)),$bad);
    throw new RuntimeException('Cross-project completion accepted');
} catch (InvalidArgumentException $expected) {}

file_put_contents(__DIR__.'/fixture_completion.xml',$xml);
echo "PASS: planned Finish, ActualFinish, completion percentages, timezone and no fabricated dates\n";
