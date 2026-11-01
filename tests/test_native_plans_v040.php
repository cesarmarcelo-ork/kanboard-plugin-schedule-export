<?php
require __DIR__.'/../Model/SubtaskPlanModel.php';
require __DIR__.'/../Schema/Sqlite.php';
require __DIR__.'/../Exporter/ProjectXmlExporter.php';

use Kanboard\Plugin\ScheduleExport\Model\SubtaskPlanModel;
use Kanboard\Plugin\ScheduleExport\Exporter\ProjectXmlExporter;

function nativeAssert($ok,$msg) { if (!$ok) throw new RuntimeException($msg); }

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA foreign_keys=ON');
$db->exec('CREATE TABLE projects(id INTEGER PRIMARY KEY)');
$db->exec('CREATE TABLE tasks(id INTEGER PRIMARY KEY, project_id INTEGER NOT NULL)');
$db->exec('CREATE TABLE subtasks(id INTEGER PRIMARY KEY, task_id INTEGER NOT NULL)');
$db->exec('INSERT INTO projects VALUES(1),(2)');
$db->exec('INSERT INTO tasks VALUES(10,1),(20,2)');
$db->exec('INSERT INTO subtasks VALUES(100,10),(200,20)');
\Kanboard\Plugin\ScheduleExport\Schema\version_1($db);
$model = new SubtaskPlanModel($db);
nativeAssert($model->get(100) === null,'Unexpected existing plan');
$model->save(1,10,100,'2026-11-02T09:00:00','2026-11-04T17:00:00',480,array(1,2,3,4,5),7);
$row = $model->get(100);
nativeAssert($row['planned_start']==='2026-11-02T09:00:00','Start not persisted');
nativeAssert($row['planned_end']==='2026-11-04T17:00:00','Finish not persisted');
nativeAssert($row['workdays']==='1,2,3,4,5','Workdays not persisted');
nativeAssert(count($model->getByTaskIds(array(10)))===1,'Project plans not readable');
$model->save(1,10,100,'2026-11-03T09:00:00','2026-11-04T17:00:00',360,array(2,3,4),7);
nativeAssert($model->get(100)['workday_minutes']==360,'Update failed');
foreach (array(
 array(2,10,100,'2026-11-03T09:00:00','2026-11-04T17:00:00'),
 array(1,10,200,'2026-11-03T09:00:00','2026-11-04T17:00:00'),
 array(1,10,100,'2026-11-05T09:00:00','2026-11-04T17:00:00'),
 array(1,10,100,'2026-02-30T09:00:00','2026-11-04T17:00:00')
) as $invalid) {
    try {
        $model->save($invalid[0],$invalid[1],$invalid[2],$invalid[3],$invalid[4],480,array(1,2,3,4,5),7);
        throw new RuntimeException('Invalid plan accepted');
    } catch (InvalidArgumentException $expected) {}
}
$day = strtotime('2026-11-02 08:00:00 UTC');
$tasks = array(array('id'=>10,'project_id'=>1,'title'=>'Office','position'=>1,'date_started'=>$day,'date_due'=>$day+3*86400));
$subs=array(10=>array(array('id'=>100,'task_id'=>10,'title'=>'Native scheduled'),array('id'=>101,'task_id'=>10,'title'=>'Native unscheduled')));
$exporter = new ProjectXmlExporter('UTC');
$xml=$exporter->generate(array('id'=>1,'name'=>'Project'),$tasks,array(),'2026-11-02',$subs,true,$model->getByTaskIds(array(10)));
nativeAssert(strpos($xml,'<Name>Native scheduled</Name>')!==false,'Persisted subtask missing');
nativeAssert(strpos($xml,'<Start>2026-11-03T09:00:00</Start>')!==false,'Persisted date not in MSPDI');
nativeAssert((bool)preg_match('~<Task>(?:(?!</Task>).)*<Name>Native unscheduled \\[SEM DATAS — NAO PLANEJADA\\]</Name>(?:(?!</Task>).)*<Duration>PT0H0M0S</Duration>(?:(?!</Task>).)*<Milestone>0</Milestone>(?:(?!</Task>).)*</Task>~s',$xml),'Unplanned subtask should be non-milestone');
nativeAssert(strpos($xml,'Source: kanboard-card-period:1:10')!==false,'Parent own period lost');
echo "PASS: v0.4 native planning sqlite, ownership and MSPDI\n";
