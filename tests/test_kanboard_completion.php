<?php
// Real Kanboard/PicoDb status, timer, model helpers and plugin initialization.
$loader = require $argv[1].'/vendor/autoload.php';
$loader->addPsr4('Kanboard\\Plugin\\ScheduleExport\\', dirname(__DIR__));
require __DIR__.'/../Schema/Sqlite.php';
use Kanboard\Plugin\ScheduleExport\Model\SubtaskCompletionModel;
function ensure($ok,$msg) { if (!$ok) throw new RuntimeException($msg); }
date_default_timezone_set('UTC');
$c = new Pimple\Container();
(new Kanboard\ServiceProvider\ClassProvider())->register($c);
(new Kanboard\ServiceProvider\HelperProvider())->register($c);
$c['db'] = new PicoDb\Database(array('driver'=>'sqlite','filename'=>':memory:'));
$pdo = $c['db']->getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys=ON');
$pdo->exec('CREATE TABLE projects (id INTEGER PRIMARY KEY); CREATE TABLE tasks (id INTEGER PRIMARY KEY, project_id INT, time_spent REAL DEFAULT 0, time_estimated REAL DEFAULT 0, is_active INT DEFAULT 1, date_completed INT DEFAULT 0, date_modification INT DEFAULT 0); CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT); CREATE TABLE subtasks (id INTEGER PRIMARY KEY AUTOINCREMENT, task_id INT NOT NULL, title TEXT, status INT DEFAULT 0, user_id INT DEFAULT 0, position INT DEFAULT 1, time_estimated REAL DEFAULT 0, time_spent REAL DEFAULT 0); CREATE TABLE subtask_time_tracking (id INTEGER PRIMARY KEY AUTOINCREMENT, subtask_id INT, user_id INT, start INT, end INT, time_spent REAL DEFAULT 0)');
$pdo->exec("INSERT INTO projects VALUES(1); INSERT INTO tasks(id,project_id) VALUES(1,1); INSERT INTO users VALUES(1,'tester')");
\Kanboard\Plugin\ScheduleExport\Schema\version_1($pdo);
\Kanboard\Plugin\ScheduleExport\Schema\version_2($pdo);
$c['userSession'] = new class { public function getId() {return 1;} public function isLogged() {return true;} };
$c['configModel'] = new class { public function get($key,$default='') { return $key==='subtask_time_tracking' ? 1 : $default; } };
$c['queueManager'] = new class {public $count=0; public function push($job) {$this->count++;} };
$c['subtaskEventJob'] = new class {public function withParams(...$args) {return $this;} };
$c['taskEventJob'] = new class {public function withParams(...$args) {return $this;} };
$c['projectAccessMap'] = new class {public $rules=array(); public function add($controller,$action,$role) {$this->rules[$controller][$action]=$role;} };
$plugin = new Kanboard\Plugin\ScheduleExport\Plugin($c);
$plugin->initialize();
ensure($c['subtaskModel'] instanceof Kanboard\Plugin\ScheduleExport\Model\CompletionSubtaskModel,'Plugin wires completion model');
$m = new SubtaskCompletionModel($pdo);
$id = $c['subtaskModel']->create(array('task_id'=>1,'title'=>'Timer test','time_estimated'=>2));
ensure($id && $m->get($id)===null,'Todo starts without actual date');
ensure($c['subtaskStatusModel']->toggleStatus($id)===1,'Native toggle enters progress');
ensure($c['subtaskTimeTrackingModel']->hasTimer($id,1),'Native timer starts');
$pdo->exec('UPDATE subtask_time_tracking SET start='.(time()-3600));
ensure($c['subtaskStatusModel']->toggleStatus($id)===2,'Native toggle completes');
ensure(!$c['subtaskTimeTrackingModel']->hasTimer($id,1),'Native timer stops');
ensure($c['subtaskModel']->getById($id)['time_spent']>=0.99,'Native elapsed time preserved');
ensure($m->get($id)['completed_at']>0,'Completion synchronized with native toggle');
$timerBefore = $pdo->query('SELECT * FROM subtask_time_tracking')->fetchAll(PDO::FETCH_ASSOC);
$effortBefore = $c['subtaskModel']->getById($id)['time_spent'];
$m->saveActual(1,1,$id,date('Y-m-d\TH:i',time()-86400),1,1);
ensure($pdo->query('SELECT * FROM subtask_time_tracking')->fetchAll(PDO::FETCH_ASSOC)===$timerBefore,'Manual adjustment preserves timer records');
ensure($c['subtaskModel']->getById($id)['time_spent']===$effortBefore,'Manual adjustment preserves effort');
ensure($c['subtaskStatusModel']->toggleStatus($id)===0,'Native toggle reopens');
ensure($m->get($id)['completed_at']===null,'Reopening clears current completion');
ensure($c['subtaskModel']->update(array('id'=>$id,'status'=>2),false),'API/model update without events');
ensure($m->get($id)['completed_at']>0,'API path synchronizes without events');
$revision=$m->get($id)['revision'];
$c['subtaskModel']->update(array('id'=>$id,'title'=>'Edited'));
ensure($m->get($id)['revision']===$revision,'Title edit does not rewrite completion');
$newId=$c['subtaskModel']->create(array('task_id'=>1,'title'=>'New','status'=>2));
ensure($m->get($newId)['completed_at']>0,'Creation in done status synchronized');
$todoId=$c['subtaskModel']->create(array('task_id'=>1,'title'=>'Bulk'));
// Historical completed record, created before installation, must stay unknown.
$pdo->exec("INSERT INTO subtasks(task_id,title,status) VALUES(1,'Historical',2)");
$historical=(int)$pdo->lastInsertId();
ensure($c['taskStatusModel']->close(1),'Native task close');
ensure($m->get($todoId)['completed_at']>0,'Task closure synchronizes unfinished subtasks');
ensure($m->get($historical)===null,'Task closure does not fabricate historical completion');
ensure($m->get($id)['revision']===$revision,'Bulk closure preserves existing completion');
ensure($c['queueManager']->count>0,'Native event dispatch preserved');
// Force audit failure; native status and timer must both roll back.
$failId=$c['subtaskModel']->create(array('task_id'=>1,'title'=>'Rollback'));
$c['subtaskStatusModel']->toggleStatus($failId);
$pdo->exec("CREATE TRIGGER reject_completion BEFORE INSERT ON schedule_export_completion_history BEGIN SELECT RAISE(ABORT, 'injected failure'); END");
try { $c['subtaskStatusModel']->toggleStatus($failId); throw new RuntimeException('Missing failure'); }
catch (PDOException $e) {}
ensure((int)$c['subtaskModel']->getById($failId)['status']===1 && $c['subtaskTimeTrackingModel']->hasTimer($failId,1),'Audit failure rolls back native status and timer');
echo "PASS: real Kanboard plugin wiring, native toggles, API updates, timers, bulk task closure and rollback\n";
