<?php

namespace Kanboard\Plugin\ScheduleExport\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Plugin\ScheduleExport\Exporter\ProjectXmlExporter;

/** Exports only the project the authenticated Kanboard user may view. */
class ScheduleExportController extends BaseController
{
    public function download()
    {
        $project = $this->getProject();
        $projectId = (int) $project['id'];

        // No credentials, API tokens, external services, DB writes or OS commands.
        $open = $this->taskFinderModel->getAll($projectId, 1);
        $closed = $this->taskFinderModel->getAll($projectId, 0);
        $tasks = array_merge($open, $closed);

        $knownIds = array();
        foreach ($tasks as $task) {
            $knownIds[(int) $task['id']] = true;
        }
        $links = array();
        foreach (array_keys($knownIds) as $taskId) {
            $links[$taskId] = $this->taskLinkModel->getAll($taskId);
        }

        $subtasksByTask = array();
        // One read-only query for all native Kanboard subtasks.
        foreach ($this->subtaskModel->getAllByTaskIds(array_keys($knownIds)) as $subtask) {
            $subtasksByTask[(int) $subtask['task_id']][] = $subtask;
        }

        $exporter = new ProjectXmlExporter($this->timezoneModel->getCurrentTimezone());
        $detailed = $this->request->getStringParam('subtasks') === 'detailed';
        $xml = $exporter->generate($project, $tasks, $links, null, $subtasksByTask, $detailed);
        $filename = 'kanboard-' . $projectId . ($detailed ? '-subtarefas' : '') . '-cronograma.xml';
        $this->response->withFileDownload($filename);
        $this->response->xml($xml);
    }
}
