<?php
require __DIR__.'/../Model/SubtaskCompletionModel.php';
require __DIR__.'/../Schema/Sqlite.php';
use Kanboard\Plugin\ScheduleExport\Model\SubtaskCompletionModel;
function ensure($ok, $message) { if (!$ok) throw new RuntimeException($message); }
function rejected($fn) { try { $fn(); } catch (InvalidArgumentException $e) { return; } throw new RuntimeException('Expected validation failure'); }
date_default_timezone_set('America/Sao_Paulo');
$pdo = new PDO('sqlite::memory:', null, null, array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));
$pdo->exec('PRAGMA foreign_keys=ON');
$pdo->exec('CREATE TABLE projects (id INTEGER PRIMARY KEY); CREATE TABLE tasks (id INTEGER PRIMARY KEY, project_id INT); CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT); CREATE TABLE subtasks (id INTEGER PRIMARY KEY, task_id INT, status INT)');
$pdo->exec("INSERT INTO projects VALUES(1); INSERT INTO tasks VALUES(1,1); INSERT INTO users VALUES(1,'cesar'); INSERT INTO subtasks VALUES(1,1,2),(2,1,2),(3,1,0)");
\Kanboard\Plugin\ScheduleExport\Schema\version_1($pdo);
\Kanboard\Plugin\ScheduleExport\Schema\version_2($pdo);
$m = new SubtaskCompletionModel($pdo);
ensure($m->get(1) === null && $m->get(2) === null, 'Migration does not fabricate historical dates');
$m->atomic(function () use ($m) { $m->transition(1, 1, 2, 1); });
$first = $m->get(1);
ensure(abs(time()-$first['completed_at'])<3, 'Automatic completion records now');
$m->atomic(function () use ($m) { $m->transition(1, 2, 2, 1); });
ensure(count($m->history(1))===1, 'Repeated done is idempotent');
$input = date('Y-m-d\TH:i', time()-86400);
$m->saveActual(1,1,1,$input,1,1);
ensure((int)$m->get(1)['completed_at']===strtotime($input), 'Manual date uses active Kanboard timezone');
rejected(function () use ($m,$input) { $m->saveActual(1,1,1,$input,1,1); });
rejected(function () use ($m,$input) { $m->saveActual(2,1,1,$input,2,1); });
rejected(function () use ($m,$input) { $m->saveActual(1,2,1,$input,2,1); });
foreach (array('', '2026-02-30T10:00', 'bad', date('Y-m-d\TH:i',time()+86400)) as $invalid) {
    rejected(function () use ($m,$invalid) { $m->saveActual(1,1,1,$invalid,2,1); });
}
rejected(function () use ($m,$input) { $m->saveActual(1,1,3,$input,0,1); });
$m->saveActual(1,1,2,$input,0,1);
ensure($m->get(2)['completed_at']!==null, 'Historical done can be entered manually');
$m->atomic(function () use ($pdo,$m) { $pdo->exec('UPDATE subtasks SET status=0 WHERE id=1'); $m->transition(1,2,0,1); });
ensure($m->get(1)['completed_at']===null && count($m->history(1))===3, 'Reopening clears current date and retains history');
rejected(function () use ($m,$input) { $m->saveActual(1,1,1,$input,3,1); });
$m->atomic(function () use ($pdo,$m) { $pdo->exec('UPDATE subtasks SET status=2 WHERE id=1'); $m->transition(1,0,2,1); });
ensure((int)$m->get(1)['revision']===4, 'Recompletion starts new cycle');
$pdo->beginTransaction();
$m->atomic(function () use ($m) { $m->transition(1,2,0,1); });
$pdo->rollBack();
ensure((int)$m->get(1)['revision']===4, 'Outer rollback restores history and current state');
try {
    $m->atomic(function () use ($pdo,$m) { $pdo->exec('UPDATE subtasks SET status=0 WHERE id=1'); $m->transition(1,2,0,1); throw new RuntimeException('injected'); });
} catch (RuntimeException $e) { ensure($e->getMessage()==='injected','Expected injected failure'); }
ensure((int)$pdo->query('SELECT status FROM subtasks WHERE id=1')->fetchColumn()===2 && (int)$m->get(1)['revision']===4,'Atomic rollback');
$pdo->exec('DELETE FROM subtasks WHERE id=1');
ensure($m->get(1)===null && $m->history(1)===array(), 'Deletion cascades');
echo "PASS: completion lifecycle, migration, history, validation, timezone, stale edits and rollback\n";
