<?php

namespace Kanboard\Plugin\ScheduleExport\Model;

use DateTimeImmutable;
use DateTimeZone;
use Kanboard\Model\SubtaskTaskConversionModel as NativeConversion;

/**
 * Preserves ScheduleExport subtask dates when Kanboard promotes a subtask to a task.
 *
 * Reuses native creation/link/tag/removal workflow; changes only dates at creation.
 * No browser-extension dependencies and no native time-tracking modifications.
 */
class PlannedSubtaskConversionModel extends NativeConversion
{
    public function convertToTask($project_id, $subtask_id)
    {
        $subtask = $this->subtaskModel->getById($subtask_id);
        if (! $subtask) {
            return false;
        }

        $parent = $this->taskFinderModel->getById($subtask['task_id']);
        if (! $parent || (int) $parent['project_id'] !== (int) $project_id) {
            return false;
        }

        // Snapshot *before* native subtask deletion and FK ON DELETE CASCADE.
        $plan = (new SubtaskPlanModel($this->db->getConnection()))->get($subtask_id);
        if ($plan && ((int) $plan['project_id'] !== (int) $project_id ||
                (int) $plan['task_id'] !== (int) $subtask['task_id'])) {
            return false;
        }

        $values = array(
            'project_id' => $project_id,
            'title' => $subtask['title'],
            'time_estimated' => $subtask['time_estimated'],
            'time_spent' => $subtask['time_spent'],
            'owner_id' => $subtask['user_id'],
            'swimlane_id' => $parent['swimlane_id'],
            'priority' => $parent['priority'],
            'column_id' => $parent['column_id'],
            'category_id' => $parent['category_id'],
            'color_id' => $parent['color_id'],
        );

        if ($plan) {
            // Numeric timestamps are accepted by Kanboard DateParser::getTimestamp.
            // Use the application's configured local timezone for datetime-local values.
            $timezone = new DateTimeZone(date_default_timezone_get());
            foreach (array('planned_start' => 'date_started', 'planned_end' => 'date_due') as $source => $target) {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s', $plan[$source], $timezone);
                if (! $date || $date->format('Y-m-d\TH:i:s') !== $plan[$source]) {
                    return false; // Never discard a plan with invalid timestamps.
                }
                $values[$target] = (string) $date->getTimestamp();
            }
        }

        $task_id = $this->taskCreationModel->create($values);
        if ($task_id === false || (int) $task_id < 1) {
            return false;
        }

        // Preserve the source plan's additional scheduling parameters for audit.
        // They do not change native time_estimated/time_spent or task dates.
        if ($plan) {
            $this->taskMetadataModel->save($task_id, array('schedule_export_source_subtask_id' => (string) $subtask_id, 'schedule_export_workday_minutes' => (string) $plan['workday_minutes'], 'schedule_export_workdays' => (string) $plan['workdays']));
        }

        $link = $this->linkModel->getByLabel('is a child of');
        if ($link) {
            $this->taskLinkModel->create($task_id, $subtask['task_id'], $link['id']);
        }

        $this->tagDuplicationModel->duplicateTaskTags($parent['id'], $task_id);
        $this->subtaskModel->remove($subtask_id);

        return $task_id;
    }
}
