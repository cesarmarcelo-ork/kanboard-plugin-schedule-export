<?php
/** Synthetic regression coverage. No private Kanboard snapshot is distributed. */
require __DIR__ . '/../Exporter/ProjectXmlExporter.php';
use Kanboard\Plugin\ScheduleExport\Exporter\ProjectXmlExporter;
if ($argc!==2 || !is_dir($argv[1])) {fwrite(STDERR,"Usage: php test_matrix.php output_dir\n");exit(2);}
date_default_timezone_set('America/Sao_Paulo');
$gen=new ProjectXmlExporter('America/Sao_Paulo');
function timestamp($value){return $value ? strtotime($value . ':00') : 0;}
function task($id,$title,$start=null,$due=null,$position=1,$project=10){
  return array('id'=>$id,'project_id'=>$project,'title'=>$title,'date_started'=>timestamp($start),
    'date_due'=>timestamp($due),'time_estimated'=>8,'date_completed'=>0,'position'=>$position);
}
function rel($task,$kind){return array('task_id'=>$task,'label'=>$kind);}
$cases=array(
 'real_timestamps'=>array(array(task(1,'Data real','2026-09-12 19:21','2026-09-20 19:22')),array(),array()),
 'due_only'=>array(array(task(1,'Somente prazo',null,'2026-10-05 19:22')),array(),array()),
 'start_only'=>array(array(task(1,'Somente inicio','2026-10-06 19:21')),array(),array()),
 'missing_both'=>array(array(task(1,'Sem datas')),array(),array()),
 'hierarchy_notes'=>array(array(task(1,'Pai','2026-10-01 19:22','2026-10-06 19:22'),task(2,'Filho',null,'2026-10-08 19:22',2)),
  array(1=>array(rel(2,'is a parent of'))),array(1=>array(array('id'=>42,'task_id'=>1,'title'=>'Subtarefa sem data','time_estimated'=>2)))),
 'predecessor'=>array(array(task(1,'A','2026-10-01 19:21','2026-10-02 19:22'),task(2,'B','2026-10-03 19:21','2026-10-04 19:22',2)),
  array(1=>array(rel(2,'blocks'),rel(2,'blocks')),2=>array(rel(1,'is blocked by'))),array()),
 'unrelated_duplicates'=>array(array(task(1,'A','2026-10-01 19:21','2026-10-02 19:22'),task(2,'B','2026-10-01 19:21','2026-10-02 19:22',2)),
  array(1=>array(rel(2,'Esta tarefa e duplicada por (17) Designacao Rastreamento de tempo'))),array()),
 'special_text'=>array(array(task(1,'Árvore <A> & "projeto"','2026-10-01 19:21','2026-10-02 19:22')),array(),array()),
);
foreach ($cases as $name=>$tuple) {
    $xml=$gen->generate(array('id'=>10,'name'=>'Projeto & teste'),$tuple[0],$tuple[1],'2026-09-01',$tuple[2]);
    file_put_contents($argv[1].'/'.$name.'.xml',$xml);
}
function rejects($f,$desc){try {$f();} catch (InvalidArgumentException $e){return;} throw new RuntimeException('Not rejected: '.$desc);}
rejects(function()use($gen){$gen->generate(array('id'=>10,'name'=>'teste'),array(task(1,'Outros',null,null,1,99)),array(),'2026-10-01');},'cross project');
rejects(function()use($gen){$gen->generate(array('id'=>10,'name'=>'teste'),array(task(1,'A'),task(2,'B',null,null,2)),array(1=>array(rel(2,'is a parent of')),2=>array(rel(1,'is a parent of'))),'2026-10-01');},'hierarchy cycle');
rejects(function()use($gen){$gen->generate(array('id'=>10,'name'=>'teste'),array(task(1,'A'),task(2,'B',null,null,2),task(3,'C',null,null,3)),array(1=>array(rel(3,'is a parent of')),2=>array(rel(3,'is a parent of'))),'2026-10-01');},'multiple parents');
rejects(function()use($gen){$gen->generate(array('id'=>10,'name'=>'teste'),array(task(1,'A'),task(2,'B',null,null,2)),array(1=>array(rel(2,'blocks')),2=>array(rel(1,'blocks'))),'2026-10-01');},'dependency cycle');
rejects(function()use($gen){$gen->generate(array('id'=>10,'name'=>'teste'),array(task(1,'A','2026-11-05 19:21','2026-11-02 19:22')),array(),'2026-10-01');},'inverted dates');
rejects(function()use($gen){$gen->generate(array('id'=>10,'name'=>'teste'),array(task(1,'A')),array(),'2026-10-01',array(1=>array(array('id'=>3,'task_id'=>1),array('id'=>3,'task_id'=>1))));},'repeated subtask');
rejects(function()use($gen){$gen->generate(array('id'=>10,'name'=>'teste'),array(task(1,'A')),array(),'2026-10-01',array(1=>array(array('id'=>3,'task_id'=>2))));},'cross-task subtask');
echo 'PASS: '.count($cases).' regression scenarios and 7 invalid-data rejections' . "\n";
