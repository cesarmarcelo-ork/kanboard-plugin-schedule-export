<?php

namespace Kanboard\Plugin\ScheduleExport\Controller;

use InvalidArgumentException;
use Kanboard\Controller\BaseController;
use Kanboard\Plugin\ScheduleExport\Model\PlannedSubtaskCreationService;
use Kanboard\Plugin\ScheduleExport\Model\SubtaskPlanModel;

class PlannedSubtaskCreationController extends BaseController
{
    /**
     * A dedicated single-subtask form. The native bulk creation path is intact.
     */
    public function create(array $values = array(), array $errors = array())
    {
        $task = $this->getTask();
        if (empty($values)) {
            $values = array(
                'task_id' => $task['id'],
                'user_id' => $task['owner_id'],
                'time_estimated' => 0,
                'workday_minutes' => 480,
                'workdays' => array(1, 2, 3, 4, 5),
            );
        }

        $this->response->html($this->helper->layout->task(
            'ScheduleExport:subtask/create-planned',
            array(
                'task' => $task,
                'project' => $this->projectModel->getById($task['project_id']),
                'users_list' => $this->projectUserRoleModel->getAssignableUsersList($task['project_id']),
                'values' => $values,
                'errors' => $errors,
            )
        ));
    }

    public function save()
    {
        $this->checkCSRFForm();
        $task = $this->getTask();
        $input = $this->request->getValues();

        $title = isset($input['title']) && is_string($input['title'])
            ? trim($input['title']) : '';
        $values = array(
            'task_id' => (int)$task['id'],
            'title' => $title,
            'user_id' => (int)($input['user_id'] ?? 0),
            'time_estimated' => $input['time_estimated'] ?? 0,
        );
        list($valid, $errors) = $this->subtaskValidator->validateCreation($values);

        // The native form supports bulk titles. This planned form deliberately
        // creates ONE subtask so that a plan never attaches to the wrong ID.
        if (strpos($title, "\n") !== false || strpos($title, "\r") !== false) {
            $errors['title'] = array(t('Please enter a single sub-task title.'));
        }

        $assignees = $this->projectUserRoleModel->getAssignableUsersList($task['project_id']);
        if ($values['user_id'] !== 0 && !array_key_exists($values['user_id'], $assignees)) {
            $errors['user_id'] = array(t('Select a valid assignee for this project.'));
        }

        $withPlan = isset($input['schedule_export_plan']) && (string)$input['schedule_export_plan'] === '1';
        $start = is_string($input['planned_start'] ?? null) ? trim($input['planned_start']) : '';
        $end = is_string($input['planned_end'] ?? null) ? trim($input['planned_end']) : '';
        $minutes = $input['workday_minutes'] ?? 480;
        $days = isset($input['workdays']) && is_array($input['workdays']) ? $input['workdays'] : array();

        if ($withPlan) {
            try {
                SubtaskPlanModel::validateInput($start, $end, $minutes, $days);
            } catch (InvalidArgumentException $e) {
                $errors['schedule_export_plan'] = array(
                    t('Please enter valid planned dates, working minutes, and at least one working day.')
                );
            }
        }

        if (!$valid || !empty($errors)) {
            return $this->create($input, $errors);
        }

        try {
            $pdo = $this->db->getConnection();
            $plans = new SubtaskPlanModel($pdo);
            $service = new PlannedSubtaskCreationService($pdo, $this->subtaskModel, $plans);
            $subtaskId = $service->create(
                $task['project_id'], $task['id'], $this->userSession->getId(),
                $values, $withPlan, $start, $end, $minutes, $days
            );
            if (!$subtaskId) {
                $this->flash->failure(t('Unable to create the sub-task. No planning was saved.'));
                return $this->create($input);
            }
        } catch (\Throwable $e) {
            $this->flash->failure(t('An error prevented the planned sub-task from being created.'));
            return $this->create($input);
        }

        $this->flash->success(t('Subtask added successfully.'));
        return $this->response->redirect(
            $this->helper->url->to('TaskViewController', 'show', array('task_id' => $task['id'], 'project_id' => $task['project_id']), 'subtasks'),
            true
        );
    }
}
