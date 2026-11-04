<?php
namespace Kanboard\Plugin\ScheduleExport\Model;

use PDO;

/** Actual completion is separate from planning and native effort/timers. */
class SubtaskCompletionModel
{
    private $pdo;
    private static $sequence = 0;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function atomic(callable $operation)
    {
        $nested = $this->pdo->inTransaction();
        $savepoint = 'schedule_completion_'.(++self::$sequence);
        if ($nested) $this->pdo->exec('SAVEPOINT '.$savepoint);
        else $this->pdo->beginTransaction();
        try {
            $result = $operation();
            if ($result === false) {
                if ($nested) {
                    $this->pdo->exec('ROLLBACK TO SAVEPOINT '.$savepoint);
                    $this->pdo->exec('RELEASE SAVEPOINT '.$savepoint);
                } else $this->pdo->rollBack();
            } elseif ($nested) $this->pdo->exec('RELEASE SAVEPOINT '.$savepoint);
            else $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($nested) {
                $this->pdo->exec('ROLLBACK TO SAVEPOINT '.$savepoint);
                $this->pdo->exec('RELEASE SAVEPOINT '.$savepoint);
            } elseif ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function lockSubtasks($field, $id)
    {
        if (!in_array($field, array('id', 'task_id'), true)) throw new \InvalidArgumentException('Invalid key');
        $sql = 'SELECT id, task_id, status FROM subtasks WHERE '.$field.' = ? ORDER BY id';
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') $sql .= ' FOR UPDATE';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array($id));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function get($id)
    {
        $stmt = $this->pdo->prepare('SELECT * FROM schedule_export_subtask_completions WHERE subtask_id = ?');
        $stmt->execute(array($id));
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getByTaskIds(array $taskIds)
    {
        $taskIds = array_values(array_unique(array_filter(array_map('intval', $taskIds), function ($id) {
            return $id > 0;
        })));
        if (empty($taskIds)) return array();

        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
        $stmt = $this->pdo->prepare(
            'SELECT c.subtask_id, c.completed_at, c.revision, s.task_id, t.project_id '
            . 'FROM schedule_export_subtask_completions c '
            . 'JOIN subtasks s ON s.id = c.subtask_id '
            . 'JOIN tasks t ON t.id = s.task_id '
            . 'WHERE s.task_id IN (' . $placeholders . ')'
        );
        $stmt->execute($taskIds);
        $result = array();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[(int)$row['subtask_id']] = $row;
        }
        return $result;
    }

    public function history($id)
    {
        $stmt = $this->pdo->prepare('SELECT h.*, u.username FROM schedule_export_completion_history h LEFT JOIN users u ON u.id = h.changed_by WHERE h.subtask_id = ? ORDER BY h.id DESC');
        $stmt->execute(array($id));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Called inside the native status transaction, with the subtask locked.
    public function transition($id, $before, $after, $actor)
    {
        if ((int)$before !== 2 && (int)$after === 2) $this->record($id, time(), 'completed', $actor);
        elseif ((int)$before === 2 && (int)$after !== 2) $this->record($id, null, 'reopened', $actor);
    }

    private function record($id, $date, $action, $actor)
    {
        $previous = $this->get($id);
        if ($previous) {
            $stmt = $this->pdo->prepare('UPDATE schedule_export_subtask_completions SET completed_at = ?, revision = revision + 1 WHERE subtask_id = ?');
            $stmt->execute(array($date, $id));
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO schedule_export_subtask_completions (subtask_id, completed_at, revision) VALUES (?, ?, 1)');
            $stmt->execute(array($id, $date));
        }
        $stmt = $this->pdo->prepare('INSERT INTO schedule_export_completion_history (subtask_id, previous_completed_at, completed_at, action, changed_at, changed_by) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute(array($id, $previous['completed_at'] ?? null, $date, $action, time(), $actor));
    }

    public function saveActual($projectId, $taskId, $id, $input, $revision, $actor)
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $input);
        if (!$date || $date->format('Y-m-d\TH:i') !== $input || $date->getTimestamp() <= 0 || $date->getTimestamp() > time()) {
            throw new \InvalidArgumentException('Enter a valid completion date that is not in the future.');
        }
        return $this->atomic(function () use ($projectId, $taskId, $id, $date, $revision, $actor) {
            $rows = $this->lockSubtasks('id', $id);
            $stmt = $this->pdo->prepare('SELECT project_id FROM tasks WHERE id = ?');
            $stmt->execute(array($taskId));
            if (!$rows || (int)$rows[0]['task_id'] !== (int)$taskId || (int)$stmt->fetchColumn() !== (int)$projectId) {
                throw new \InvalidArgumentException('Subtask does not belong to this task.');
            }
            if ((int)$rows[0]['status'] !== 2) throw new \InvalidArgumentException('Complete the subtask in Kanboard before recording its actual completion date.');
            $current = $this->get($id);
            if ((int)($current['revision'] ?? 0) !== (int)$revision) throw new \InvalidArgumentException('This subtask changed. Reload the page before saving.');
            $this->record($id, $date->getTimestamp(), 'adjusted', $actor);
            return true;
        });
    }
}
