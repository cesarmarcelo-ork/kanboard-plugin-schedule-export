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
        // Replace only the native conversion service, preserving its public method contract.
        $this->container['subtaskTaskConversionModel'] = function ($container) {
            return new \Kanboard\Plugin\ScheduleExport\Model\PlannedSubtaskConversionModel($container);
        };

        $this->projectAccessMap->add('ScheduleExportController', 'download', Role::PROJECT_VIEWER);
        $this->projectAccessMap->add('SubtaskPlanController', 'edit', Role::PROJECT_VIEWER);
        $this->projectAccessMap->add('SubtaskPlanController', 'save', Role::PROJECT_MEMBER);
        $this->projectAccessMap->add('SubtaskPlanController', 'remove', Role::PROJECT_MEMBER);
        $this->template->hook->attach(
            'template:subtask:table:header:before-timetracking',
            'ScheduleExport:subtask/period-header'
        );
        $this->template->hook->attachCallable(
            'template:subtask:table:rows',
            'ScheduleExport:subtask/period-link',
            function ($subtask) {
                return array('schedule_export_project_id' =>
                    $this->subtaskModel->getProjectId((int)$subtask['id']));
            }
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
        return '0.4.1';
    }

    public function getPluginHomepage()
    {
        return 'https://github.com/cesarmarcelo-ork/kanboard-plugin-schedule-export';
    }

    public function getCompatibleVersion()
    {
        return '>=1.2.30';
    }
}
