<?php
namespace Kanboard\Plugin\ScheduleExport\Schema;
use PDO;
const VERSION = 1;
function version_1(PDO $pdo)
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS schedule_export_subtask_plans (
        subtask_id INTEGER PRIMARY KEY,
        task_id INTEGER NOT NULL,
        project_id INTEGER NOT NULL,
        planned_start TEXT,
        planned_end TEXT,
        workday_minutes INTEGER NOT NULL DEFAULT 480,
        workdays TEXT NOT NULL DEFAULT "1,2,3,4,5",
        updated_at INTEGER NOT NULL,
        updated_by INTEGER NOT NULL,
        FOREIGN KEY(subtask_id) REFERENCES subtasks(id) ON DELETE CASCADE,
        FOREIGN KEY(task_id) REFERENCES tasks(id) ON DELETE CASCADE,
        FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE
    )');
}
