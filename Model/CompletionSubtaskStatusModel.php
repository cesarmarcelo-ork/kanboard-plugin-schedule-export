<?php
namespace Kanboard\Plugin\ScheduleExport\Model;

class CompletionSubtaskStatusModel extends \Kanboard\Model\SubtaskStatusModel
{
    public function toggleStatus($subtaskId)
    {
        $completion = new SubtaskCompletionModel($this->db->getConnection());
        return $completion->atomic(function () use ($subtaskId, $completion) {
            if (!$completion->lockSubtasks('id', $subtaskId)) return false;
            // Native timer and assignment logic remain owned by Kanboard.
            return parent::toggleStatus($subtaskId);
        });
    }

    public function closeAll($taskId)
    {
        $completion = new SubtaskCompletionModel($this->db->getConnection());
        return $completion->atomic(function () use ($taskId, $completion) {
            $before = $completion->lockSubtasks('task_id', $taskId);
            $result = parent::closeAll($taskId);
            if ($result) foreach ($before as $row) {
                $completion->transition($row['id'], $row['status'], \Kanboard\Model\SubtaskModel::STATUS_DONE, (int)$this->userSession->getId());
            }
            return $result;
        });
    }
}
