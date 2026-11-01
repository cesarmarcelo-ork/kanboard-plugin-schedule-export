<?php
// Standalone behavioral regression for the native conversion adapter.
// Uses the actual SQLite plan table and a minimal Kanboard model stub.
namespace Kanboard\Model {
    class SubtaskTaskConversionModel {
        protected $services;
        public function __construct($services = array()) { $this->services = $services; }
        public function __get($name) { return $this->services[$name]; }
    }
}
namespace {
    require __DIR__.'/../Model/SubtaskPlanModel.php';
    require __DIR__.'/../Model/PlannedSubtaskConversionModel.php';
    require __DIR__.'/../Schema/Sqlite.php';

    function check041($condition, $message) {
        if (! $condition) throw new \RuntimeException($message);
    }
    class FakeService {
        public $args = array();
        public $result = true;
        public function __call($name, $args) {
            $this->args[] = array($name, $args);
            return $this->result;
        }
    }
    class FakeSubtask {
        public $pdo;
        public $deleted = array();
        public function __construct($pdo) { $this->pdo = $pdo; }
        public function getById($id) {
            return $this->pdo->query('SELECT * FROM subtasks WHERE id='.(int)$id)->fetch(\PDO::FETCH_ASSOC) ?: false;
        }
        public function remove($id) {
            $this->deleted[] = $id;
            return $this->pdo->exec('DELETE FROM subtasks WHERE id='.(int)$id) > 0;
        }
    }
    class FakeTaskFinder {
        public function getById($id) {
            if ($id !== 10) return false;
            return array('id'=>10,'project_id'=>1,'swimlane_id'=>1,'priority'=>0,
                'column_id'=>1,'category_id'=>0,'color_id'=>'blue');
        }
    }
    class FakeTaskCreation {
        public $values = array();
        public $result = 42;
        public function create(array $values) { $this->values[] = $values; return $this->result; }
    }
    class FakeMetadata {
        public $calls = array();
        public function save($id, array $values) { $this->calls[] = array($id, $values); return true; }
    }
    class FakeDatabase {
        private $pdo;
        public function __construct($pdo) { $this->pdo = $pdo; }
        public function getConnection() { return $this->pdo; }
    }
    $pdo = new \PDO('sqlite::memory:');
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys=ON');
    $pdo->exec('CREATE TABLE projects(id INTEGER PRIMARY KEY)');
    $pdo->exec('CREATE TABLE tasks(id INTEGER PRIMARY KEY,project_id INTEGER)');
    $pdo->exec('CREATE TABLE subtasks(id INTEGER PRIMARY KEY,task_id INTEGER,title TEXT,
        time_estimated REAL,time_spent REAL,user_id INTEGER)');
    $pdo->exec('INSERT INTO projects VALUES(1)');
    $pdo->exec('INSERT INTO tasks VALUES(10,1)');
    $pdo->exec("INSERT INTO subtasks VALUES(100,10,'Planned',3.5,1.25,7)");
    $pdo->exec("INSERT INTO subtasks VALUES(101,10,'No dates',0,0,7)");
    \Kanboard\Plugin\ScheduleExport\Schema\version_1($pdo);
    $plans = new \Kanboard\Plugin\ScheduleExport\Model\SubtaskPlanModel($pdo);
    $plans->save(1,10,100,'2026-11-03T09:00:00','2026-11-06T17:00:00',480,array(1,2,3,4,5),7);

    $subtasks = new FakeSubtask($pdo);
    $created = new FakeTaskCreation();
    $metadata = new FakeMetadata();
    $link = new FakeService();
    $links = new FakeService();
    $tags = new FakeService();
    $conversion = new \Kanboard\Plugin\ScheduleExport\Model\PlannedSubtaskConversionModel(array(
        'db'=>new FakeDatabase($pdo), 'subtaskModel'=>$subtasks,
        'taskFinderModel'=>new FakeTaskFinder(), 'taskCreationModel'=>$created,
        'taskMetadataModel'=>$metadata, 'linkModel'=>$link,
        'taskLinkModel'=>$links, 'tagDuplicationModel'=>$tags
    ));
    date_default_timezone_set('America/Sao_Paulo');
    check041($conversion->convertToTask(1,100)===42,'Planned conversion failed');
    $v=$created->values[0];
    check041($v['date_started']===(string)strtotime('2026-11-03 09:00:00'),'Start was not transferred');
    check041($v['date_due']===(string)strtotime('2026-11-06 17:00:00'),'End was not transferred');
    check041($v['time_estimated']==3.5 && $v['time_spent']==1.25,'Native time tracking changed');
    check041($plans->get(100)===null,'Source plan was not removed by FK cascade');
    check041(count($metadata->calls)===1 && $metadata->calls[0][1]['schedule_export_workday_minutes']==='480',
        'Planning metadata not preserved');

    check041($conversion->convertToTask(1,101)===42,'Unplanned native conversion failed');
    check041(!isset($created->values[1]['date_started']) && !isset($created->values[1]['date_due']),
        'Fabricated dates for unplanned subtask');
    check041(count($metadata->calls)===1,'Invented metadata for unplanned subtask');

    $pdo->exec("INSERT INTO subtasks VALUES(102,10,'Wrong project',0,0,7)");
    check041($conversion->convertToTask(2,102)===false,'Cross-project conversion permitted');
    check041($subtasks->getById(102)!==false,'Failed conversion removed source');
    $pdo->exec("INSERT INTO subtasks VALUES(103,10,'Failed create',0,0,7)");
    $created->result=false;
    check041($conversion->convertToTask(1,103)===false,'Failed create not detected');
    check041($subtasks->getById(103)!==false,'Failed create removed source');
    echo "PASS: v0.4.1 conversion with dates, without dates, metadata, ownership and failure\n";
}
