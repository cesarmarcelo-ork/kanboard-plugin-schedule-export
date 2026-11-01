<?php
namespace Kanboard\Plugin\ScheduleExport\Schema;
use PDO;
const VERSION = 1;
function version_1(PDO $pdo)
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS schedule_export_subtask_plans (
        subtask_id INT NOT NULL,
        task_id INT NOT NULL,
        project_id INT NOT NULL,
        planned_start VARCHAR(19) DEFAULT NULL,
        planned_end VARCHAR(19) DEFAULT NULL,
        workday_minutes INT NOT NULL DEFAULT 480,
        workdays VARCHAR(30) NOT NULL DEFAULT '1,2,3,4,5',
        updated_at BIGINT NOT NULL,
        updated_by INT NOT NULL,
        PRIMARY KEY(subtask_id),
        FOREIGN KEY(subtask_id) REFERENCES subtasks(id) ON DELETE CASCADE,
        FOREIGN KEY(task_id) REFERENCES tasks(id) ON DELETE CASCADE,
        FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
