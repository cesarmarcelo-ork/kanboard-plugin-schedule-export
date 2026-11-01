<?php
namespace Kanboard\Plugin\ScheduleExport\Schema;
use PDO;
const VERSION = 1;
function version_1(PDO $pdo)
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS schedule_export_subtask_plans (
        subtask_id INTEGER PRIMARY KEY REFERENCES subtasks(id) ON DELETE CASCADE,
        task_id INTEGER NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
        project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
        planned_start VARCHAR(19),
        planned_end VARCHAR(19),
        workday_minutes INTEGER NOT NULL DEFAULT 480,
        workdays VARCHAR(30) NOT NULL DEFAULT '1,2,3,4,5',
        updated_at BIGINT NOT NULL,
        updated_by INTEGER NOT NULL
    )");
}
