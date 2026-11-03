<?php

namespace Kanboard\Plugin\ScheduleExport\Model;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Creates exactly one native Kanboard subtask and, when requested, its plan.
 *
 * The native bulk-create endpoint is never overridden. The subtask identifier
 * returned by Kanboard is used directly for the new planning record.
 */
class PlannedSubtaskCreationService
{
    private $pdo;
    private $subtaskModel;
    private $planModel;

    public function __construct(PDO $pdo, $subtaskModel, SubtaskPlanModel $planModel)
    {
        $this->pdo = $pdo;
        $this->subtaskModel = $subtaskModel;
        $this->planModel = $planModel;
    }

    public function create($projectId, $taskId, $userId, array $values, $withPlan,
                           $start = '', $end = '', $minutes = 480, array $days = array())
    {
        $projectId = (int) $projectId;
        $taskId = (int) $taskId;
        $userId = (int) $userId;

        if ($projectId < 1 || $taskId < 1 || $userId < 1 ||
            !isset($values['task_id']) || (int)$values['task_id'] !== $taskId) {
            throw new InvalidArgumentException('Invalid subtask creation context');
        }

        // Validate BEFORE inserting the native subtask.
        if ($withPlan) {
            list($start, $end, $minutes, $days) =
                SubtaskPlanModel::validateInput($start, $end, $minutes, $days);
        }

        if (!$withPlan) {
            return $this->subtaskModel->create($values);
        }

        // Both records must succeed or neither should be persisted.
        if ($this->pdo->inTransaction()) {
            throw new RuntimeException('Unexpected active database transaction');
        }

        $this->pdo->beginTransaction();
        try {
            $subtaskId = $this->subtaskModel->create($values);
            if (!$subtaskId) {
                throw new RuntimeException('Native subtask creation failed');
            }

            $this->planModel->save(
                $projectId, $taskId, (int)$subtaskId,
                $start, $end, $minutes, $days, $userId
            );
            $this->pdo->commit();

            return (int)$subtaskId;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
