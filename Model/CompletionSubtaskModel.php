<?php
namespace Kanboard\Plugin\ScheduleExport\Model;

/** Preserve native model behavior, including events and effort aggregation. */
class CompletionSubtaskModel extends \Kanboard\Model\SubtaskModel
{
    public function create(array $values)
    {
        $completion = new SubtaskCompletionModel($this->db->getConnection());
        return $completion->atomic(function () use ($values, $completion) {
            $id = parent::create($values);
            if ($id !== false) {
                $row = $this->getById($id);
                $completion->transition($id, self::STATUS_TODO, $row['status'], (int)$this->userSession->getId());
            }
            return $id;
        });
    }

    public function update(array $values, $fireEvent = true)
    {
        $completion = new SubtaskCompletionModel($this->db->getConnection());
        return $completion->atomic(function () use ($values, $fireEvent, $completion) {
            $before = $completion->lockSubtasks('id', $values['id']);
            if (!$before) return false;
            $result = parent::update($values, $fireEvent);
            if ($result) {
                $after = $this->getById($values['id']);
                $completion->transition($values['id'], $before[0]['status'], $after['status'], (int)$this->userSession->getId());
            }
            return $result;
        });
    }
}
