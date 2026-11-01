<?php
require __DIR__ . '/../Exporter/ProjectXmlExporter.php';

use Kanboard\Plugin\ScheduleExport\Exporter\ProjectXmlExporter;

function check($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}

$builder = new ProjectXmlExporter();
$base = strtotime('2026-10-26 12:00:00 UTC');
$task = function ($id, $title, $started = 0, $due = 0, $pos = 1) {
    return array('id'=>$id,'project_id'=>10,'title'=>$title,'date_started'=>$started,
                 'date_due'=>$due,'position'=>$pos,'time_estimated'=>8,'date_completed'=>0);
};
$tasks = array($task(10,'Fase A', $base,$base,1), $task(11,'Preparar laboratório & equipe', $base,$base+86400,2),
    $task(12,'Validar integração', $base+86400,$base+2*86400,3));
$links = array(10=>array(array('task_id'=>11, 'label'=>'is a parent of'),array('task_id'=>12,'label'=>'is a parent of')),
               11=>array(array('task_id'=>12,'label'=>'blocks')));
$xml = $builder->generate(array('id'=>10,'name'=>'Projeto demonstração'),$tasks,$links,'2026-10-26');
check(strpos($xml,'&amp; equipe') !== false, 'XML escaping failed');
check(strpos($xml,'<PredecessorUID>11</PredecessorUID>') !== false, 'Predecessor missing');
check(strpos($xml,'<WBS>1.2</WBS>') !== false, 'WBS missing');
check(strpos($xml,'<OutlineLevel>2</OutlineLevel>') !== false, 'Outline missing');
check(strpos($xml,'<UID>12</UID>') !== false, 'Stable identity missing');
check(strpos($xml,'<PercentComplete>100</PercentComplete>') === false, 'Invented completion');
check(strpos($xml,'Source: kanboard:10:12') !== false, 'Source marker missing');
try {
   $bad = $tasks;
   $bad[1]['project_id'] = 999;
   $builder->generate(array('id'=>10,'name'=>'Projeto'),$bad,$links,'2026-10-26');
   throw new RuntimeException('Cross-project tasks were not blocked');
} catch (InvalidArgumentException $expected) {
    // expected
}
file_put_contents(__DIR__.'/fixture.xml',$xml);
echo "PASS: export XML, hierarchy, dependency, security, stable IDs\n";
