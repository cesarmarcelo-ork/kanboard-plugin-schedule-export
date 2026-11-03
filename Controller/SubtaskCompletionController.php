<?php
namespace Kanboard\Plugin\ScheduleExport\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Plugin\ScheduleExport\Model\SubtaskCompletionModel;

class SubtaskCompletionController extends BaseController
{
    private function context()
    {
        $task = $this->getTask();
        $subtask = $this->subtaskModel->getById($this->request->getIntegerParam('subtask_id'));
        if (!$subtask || (int)$subtask['task_id'] !== (int)$task['id']) {
            throw new \InvalidArgumentException('Subtask does not belong to this task.');
        }
        return array($task, $subtask);
    }

    private function renderForm($task, $subtask, $error = '', $input = null)
    {
        $model = new SubtaskCompletionModel($this->db->getConnection());
        $completion = $model->get($subtask['id']);
        $this->response->html($this->helper->layout->task('ScheduleExport:task/edit-completion', array(
            'task' => $task,
            'project' => $this->projectModel->getById($task['project_id']),
            'subtask' => $subtask,
            'completion' => $completion,
            'history' => $model->history($subtask['id']),
            'error' => $error,
            'input' => $input ?? (!empty($completion['completed_at']) ? date('Y-m-d\TH:i', $completion['completed_at']) : ''),
        )));
    }

    public function edit()
    {
        list($task, $subtask) = $this->context();
        $this->renderForm($task, $subtask);
    }

    public function save()
    {
        $this->checkCSRFForm();
        list($task, $subtask) = $this->context();
        $values = $this->request->getValues();
        $input = trim((string)($values['completed_at'] ?? ''));
        try {
            $model = new SubtaskCompletionModel($this->db->getConnection());
            $model->saveActual($task['project_id'], $task['id'], $subtask['id'], $input, (int)($values['revision'] ?? -1), (int)$this->userSession->getId());
        } catch (\InvalidArgumentException $e) {
            $this->renderForm($task, $this->subtaskModel->getById($subtask['id']), t($e->getMessage()), $input);
            return;
        }
        $this->response->redirect($this->helper->url->to('TaskViewController', 'show', array('task_id'=>$task['id'], 'project_id'=>$task['project_id'])));
    }
}
