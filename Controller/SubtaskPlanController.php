<?php
namespace Kanboard\Plugin\ScheduleExport\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Plugin\ScheduleExport\Model\SubtaskPlanModel;

class SubtaskPlanController extends BaseController
{
    private function planModel()
    {
        return new SubtaskPlanModel($this->db->getConnection());
    }

    private function context()
    {
        $task = $this->getTask();
        $subtaskId = $this->request->getIntegerParam('subtask_id');
        $subtask = $this->subtaskModel->getById($subtaskId);
        if (!$subtask || (int)$subtask['task_id'] !== (int)$task['id']) {
            throw new \InvalidArgumentException('Subtarefa nao pertence ao cartao');
        }
        return array($task,$subtask);
    }

    public function edit()
    {
        list($task,$subtask) = $this->context();
        $plan = $this->planModel()->get((int)$subtask['id']);
        $this->response->html($this->template->render('ScheduleExport:task/edit-plan', array(
            'task'=>$task,'subtask'=>$subtask,'plan'=>$plan,
        )));
    }

    public function save()
    {
        $this->checkCSRFForm();
        list($task,$subtask) = $this->context();
        $values = $this->request->getValues();
        $start = trim((string)($values['planned_start'] ?? ''));
        $end = trim((string)($values['planned_end'] ?? ''));
        $minutes = (int)($values['workday_minutes'] ?? 480);
        $days = isset($values['workdays']) && is_array($values['workdays']) ? $values['workdays'] : array();
        $this->planModel()->save(
            (int)$task['project_id'],(int)$task['id'],(int)$subtask['id'],
            $start,$end,$minutes,$days,(int)$this->userSession->getId()
        );
        $this->response->redirect($this->helper->url->to('TaskViewController','show',array(
            'task_id'=>$task['id'],'project_id'=>$task['project_id']
        )));
    }
}
