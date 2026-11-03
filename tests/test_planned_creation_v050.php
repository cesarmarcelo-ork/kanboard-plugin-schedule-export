<?php
// v0.5.0 standalone integration regression: native creation + persisted plan.
// Uses actual SQLite schema/model, a fake native create service and no private data.
require __DIR__.'/../Schema/Sqlite.php';
require __DIR__.'/../Model/SubtaskPlanModel.php';
require __DIR__.'/../Model/PlannedSubtaskCreationService.php';

use Kanboard\Plugin\ScheduleExport\Model\SubtaskPlanModel;
use Kanboard\Plugin\ScheduleExport\Model\PlannedSubtaskCreationService;

function assert050($ok, $message) { if (!$ok) throw new RuntimeException($message); }

class FakeNativeCreator050
{
    private $pdo;
    public $fail = false;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }
    public function create(array $values)
    {
        if ($this->fail) return false;
        $q = $this->pdo->prepare('INSERT INTO subtasks(task_id,title) VALUES (?,?)');
        $q->execute(array($values['task_id'], $values['title']));
        return (int)$this->pdo->lastInsertId();
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys=ON');
$pdo->exec('CREATE TABLE projects(id INTEGER PRIMARY KEY)');
$pdo->exec('CREATE TABLE tasks(id INTEGER PRIMARY KEY, project_id INTEGER NOT NULL)');
$pdo->exec('CREATE TABLE subtasks(id INTEGER PRIMARY KEY AUTOINCREMENT, task_id INTEGER NOT NULL, title TEXT)');
$pdo->exec('INSERT INTO projects VALUES (1),(2)');
$pdo->exec('INSERT INTO tasks VALUES (10,1)');
\Kanboard\Plugin\ScheduleExport\Schema\version_1($pdo);

$native = new FakeNativeCreator050($pdo);
$plans = new SubtaskPlanModel($pdo);
$service = new PlannedSubtaskCreationService($pdo, $native, $plans);
$count = function () use ($pdo) {return (int)$pdo->query('SELECT count(*) FROM subtasks')->fetchColumn();};

$id1 = $service->create(1,10,7,array('task_id'=>10,'title'=>'Native without planning'),false);
assert050($id1 > 0 && $plans->get($id1) === null, 'Optional planning accidentally required');

$id2 = $service->create(1,10,7,array('task_id'=>10,'title'=>'Scheduled native'),true,
    '2026-11-05T09:00','2026-11-07T17:00',480,array(1,2,3,4,5));
$row = $plans->get($id2);
assert050($row !== null && (int)$row['subtask_id'] === $id2, 'Plan linked to wrong subtask');
assert050($row['planned_start'] === '2026-11-05T09:00:00', 'Incorrect normalization');
assert050($row['planned_end'] === '2026-11-07T17:00:00', 'Incorrect end date');
assert050($row['workdays'] === '1,2,3,4,5', 'Incorrect days persisted');

$baseline = $count();
foreach (array(
    array('2026-11-07T09:00','2026-11-05T17:00',480,array(1,2,3)),
    array('2026-11-05T09:00','2026-11-07T17:00',480,array()),
    array('2026-02-30T09:00','2026-11-07T17:00',480,array(1)),
    array('2026-11-05T09:00','2026-11-07T17:00',1441,array(1)),
) as $invalid) {
    try {
        $service->create(1,10,7,array('task_id'=>10,'title'=>'Should never persist'),true,
            $invalid[0],$invalid[1],$invalid[2],$invalid[3]);
        throw new RuntimeException('Invalid planning accepted');
    } catch (InvalidArgumentException $expected) {}
    assert050($count() === $baseline, 'Invalid planning created a subtask');
}

// A plan write failure must roll back native creation.
try {
    $service->create(2,10,7,array('task_id'=>10,'title'=>'Wrong project'),true,
        '2026-11-05T09:00','2026-11-07T17:00',480,array(1,2,3));
    throw new RuntimeException('Wrong-project plan accepted');
} catch (InvalidArgumentException $expected) {}
assert050($count() === $baseline, 'Failed plan left an orphan subtask');
assert050(!$pdo->inTransaction(), 'Transaction remained open');

$native->fail = true;
try {
    $service->create(1,10,7,array('task_id'=>10,'title'=>'Failed native create'),true,
        '2026-11-05T09:00','2026-11-07T17:00',480,array(1));
    throw new RuntimeException('Native failure ignored');
} catch (RuntimeException $expected) {
    if ($expected->getMessage() === 'Native failure ignored') throw $expected;
}
assert050($count() === $baseline && !$pdo->inTransaction(), 'Native failure left state behind');

echo "PASS: v0.5.0 optional planning, exact subtask ID, date validation and transactional rollback\n";
