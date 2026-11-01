<?php if ($this->user->hasProjectAccess('ScheduleExportController', 'download', $project['id'])): ?>
    <li>
        <?= $this->url->icon(
            'download',
            'Exportar cronograma (.xml)',
            'ScheduleExportController',
            'download',
            array('project_id' => $project['id'], 'plugin' => 'ScheduleExport')
        ) ?>
    </li>
<?php endif ?>
