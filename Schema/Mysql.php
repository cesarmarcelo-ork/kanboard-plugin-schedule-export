<?php
namespace Kanboard\Plugin\ScheduleExport\Schema;
use PDO;
const VERSION = 2;
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

function version_2(PDO $pdo)
{
    $pdo->exec("CREATE TABLE schedule_export_subtask_completions (
        subtask_id INT PRIMARY KEY,
        completed_at BIGINT NULL,
        revision INT NOT NULL DEFAULT 0,
        FOREIGN KEY(subtask_id) REFERENCES subtasks(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE schedule_export_completion_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        subtask_id INT NOT NULL,
        previous_completed_at BIGINT NULL,
        completed_at BIGINT NULL,
        action VARCHAR(20) NOT NULL,
        changed_at BIGINT NOT NULL,
        changed_by INT NOT NULL,
        FOREIGN KEY(subtask_id) REFERENCES subtasks(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec('CREATE INDEX schedule_completion_history_subtask ON schedule_export_completion_history (subtask_id)');
}
