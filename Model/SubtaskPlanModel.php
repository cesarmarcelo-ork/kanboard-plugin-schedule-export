<?php
namespace Kanboard\Plugin\ScheduleExport\Model;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;

/**
 * Persisted planned schedule, separate from Kanboard's native spent/estimated hours.
 * Caller MUST check Kanboard project access and subtask-to-card ownership.
 */
class SubtaskPlanModel
{
    private $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function get($subtaskId)
    {
        $q = $this->pdo->prepare('SELECT * FROM schedule_export_subtask_plans WHERE subtask_id = ?');
        $q->execute(array((int)$subtaskId));
        return $q->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getByTaskIds(array $taskIds)
    {
        $taskIds = array_values(array_unique(array_filter(array_map('intval', $taskIds), function ($id) {return $id > 0;})));
        if (!$taskIds) return array();
        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
        $query = $this->pdo->prepare('SELECT * FROM schedule_export_subtask_plans WHERE task_id IN (' . $placeholders . ')');
        $query->execute($taskIds);
        $result = array();
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) $result[(int)$row['subtask_id']] = $row;
        return $result;
    }

    public function save($projectId, $taskId, $subtaskId, $start, $end, $workdayMinutes, array $days, $userId)
    {
        $projectId = (int)$projectId; $taskId = (int)$taskId;
        $subtaskId = (int)$subtaskId; $userId = (int)$userId;
        if ($projectId < 1 || $taskId < 1 || $subtaskId < 1 || $userId < 1) throw new InvalidArgumentException('Invalid identifiers');
        $workdayMinutes = (int)$workdayMinutes;
        if ($workdayMinutes < 1 || $workdayMinutes > 1440) throw new InvalidArgumentException('Invalid workday minutes');
        $days = array_values(array_unique(array_map('intval', $days)));
        sort($days);
        if (!$days || count($days) > 7 || count(array_diff($days, range(1,7)))) throw new InvalidArgumentException('Invalid working days');
        if ($start === '' || $end === '') throw new InvalidArgumentException('Both dates are required');
        foreach (array($start,$end) as $value) {
            if (!is_string($value) || !preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d$/', $value)) throw new InvalidArgumentException('Invalid date syntax');
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s', $value, new DateTimeZone('UTC'));
            if (!$parsed || $parsed->format('Y-m-d\TH:i:s') !== $value) throw new InvalidArgumentException('Invalid calendar date');
        }
        if (strcmp($end, $start) <= 0) throw new InvalidArgumentException('End must be after start');

        $q = $this->pdo->prepare('SELECT s.id FROM subtasks s JOIN tasks t ON t.id = s.task_id WHERE s.id = ? AND s.task_id = ? AND t.project_id = ?');
        $q->execute(array($subtaskId, $taskId, $projectId));
        if (!$q->fetchColumn()) throw new InvalidArgumentException('Subtask does not belong to project/card');

        $now = time();
        $existing = $this->get($subtaskId);
        if ($existing) {
            $q=$this->pdo->prepare('UPDATE schedule_export_subtask_plans SET task_id=?,project_id=?,planned_start=?,planned_end=?,workday_minutes=?,workdays=?,updated_at=?,updated_by=? WHERE subtask_id=?');
            $q->execute(array($taskId,$projectId,$start,$end,$workdayMinutes,implode(',',$days),$now,$userId,$subtaskId));
        } else {
            $q=$this->pdo->prepare('INSERT INTO schedule_export_subtask_plans (subtask_id,task_id,project_id,planned_start,planned_end,workday_minutes,workdays,updated_at,updated_by) VALUES (?,?,?,?,?,?,?,?,?)');
            $q->execute(array($subtaskId,$taskId,$projectId,$start,$end,$workdayMinutes,implode(',',$days),$now,$userId));
        }
    }
    public function delete($projectId, $taskId, $subtaskId)
    {
        $q = $this->pdo->prepare(
            'DELETE FROM schedule_export_subtask_plans WHERE project_id = ? AND task_id = ? AND subtask_id = ?'
        );
        return $q->execute(array((int)$projectId,(int)$taskId,(int)$subtaskId));
    }

}
