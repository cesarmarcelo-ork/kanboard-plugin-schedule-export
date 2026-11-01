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
        $this->projectAccessMap->add('ScheduleExportController', 'download', Role::PROJECT_VIEWER);
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
        return '0.2.0-rc1';
    }

    public function getPluginHomepage()
    {
        return 'https://github.com/cesarmarcelo-ork';
    }

    public function getCompatibleVersion()
    {
        return '>=1.2.30';
    }
}
