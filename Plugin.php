<?php

namespace Kanboard\Plugin\ScheduleExport;

use Kanboard\Core\Plugin\Base;
use Kanboard\Core\Security\Role;
use Kanboard\Core\Translator;

/**
 * Read-only Kanboard schedule export to interoperable MSPDI XML.
 */
class Plugin extends Base
{
    public function initialize()
    {
        $this->container['subtaskModel'] = function ($container) {
            return new \Kanboard\Plugin\ScheduleExport\Model\CompletionSubtaskModel($container);
        };
        $this->container['subtaskStatusModel'] = function ($container) {
            return new \Kanboard\Plugin\ScheduleExport\Model\CompletionSubtaskStatusModel($container);
        };
        $this->projectAccessMap->add('SubtaskCompletionController', 'edit', Role::PROJECT_VIEWER);
        $this->projectAccessMap->add('SubtaskCompletionController', 'save', Role::PROJECT_MEMBER);
        // Replace only the native conversion service, preserving its public method contract.
        $this->container['subtaskTaskConversionModel'] = function ($container) {
            return new \Kanboard\Plugin\ScheduleExport\Model\PlannedSubtaskConversionModel($container);
        };

        $this->projectAccessMap->add('ScheduleExportController', 'download', Role::PROJECT_VIEWER);
        $this->projectAccessMap->add('SubtaskPlanController', 'edit', Role::PROJECT_VIEWER);
        $this->projectAccessMap->add('SubtaskPlanController', 'save', Role::PROJECT_MEMBER);
        $this->projectAccessMap->add('SubtaskPlanController', 'remove', Role::PROJECT_MEMBER);
        $this->projectAccessMap->add('PlannedSubtaskCreationController', 'create', Role::PROJECT_MEMBER);
        $this->projectAccessMap->add('PlannedSubtaskCreationController', 'save', Role::PROJECT_MEMBER);
        $this->template->hook->attach(
            'template:subtask:table:header:before-timetracking',
            'ScheduleExport:subtask/period-header'
        );
        $this->template->hook->attachCallable(
            'template:subtask:table:rows',
            'ScheduleExport:subtask/period-link',
            function ($subtask) {
                $projectId = $this->subtaskModel->getProjectId((int)$subtask['id']);
                $plan = null;
                if ($projectId && $this->helper->user->hasProjectAccess('SubtaskPlanController', 'edit', $projectId)) {
                    $model = new \Kanboard\Plugin\ScheduleExport\Model\SubtaskPlanModel($this->db->getConnection());
                    $plan = $model->get((int)$subtask['id']);
                }
                return array(
                    'schedule_export_project_id' => $projectId,
                    'schedule_export_plan' => $plan,
                );
            }
        );
        $this->template->hook->attach('template:subtask:table:header:before-timetracking', 'ScheduleExport:subtask/completion-header');
        $this->template->hook->attachCallable('template:subtask:table:rows', 'ScheduleExport:subtask/completion-link', function ($subtask) {
            $projectId = $this->subtaskModel->getProjectId((int)$subtask['id']);
            $completion = null;
            if ($projectId && $this->helper->user->hasProjectAccess('SubtaskCompletionController', 'edit', $projectId)) {
                $model = new \Kanboard\Plugin\ScheduleExport\Model\SubtaskCompletionModel($this->db->getConnection());
                $completion = $model->get((int)$subtask['id']);
            }
            return array('completion_project_id' => $projectId, 'completion' => $completion);
        });
        $this->template->hook->attach(
            'template:subtask:form:create',
            'ScheduleExport:subtask/create-entry'
        );
        $this->template->hook->attach(
            'template:project:dropdown',
            'ScheduleExport:project/dropdown'
        );
    }

    public function onStartup()
    {
        Translator::load($this->languageModel->getCurrentLanguage(), __DIR__ . '/Locale');
    }

    public function getPluginName()
    {
        return 'Kanboard Schedule Export';
    }

    public function getPluginDescription()
    {
        return 'Export Kanboard project schedules as Microsoft Project XML (MSPDI) for compatible planning applications';
    }

    public function getPluginAuthor()
    {
        return 'OrkFlowTech';
    }

    public function getPluginVersion()
    {
        return '0.6.0-dev';
    }

    public function getPluginHomepage()
    {
        return 'https://github.com/cesarmarcelo-ork/kanboard-plugin-schedule-export';
    }

    public function getCompatibleVersion()
    {
        return '>=1.2.50';
    }
}
