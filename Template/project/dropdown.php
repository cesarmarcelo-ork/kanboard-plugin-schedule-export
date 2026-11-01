<?php if ($this->user->hasProjectAccess('ScheduleExportController', 'download', $project['id'])): ?>
    <li>
        <?= $this->url->icon(
            'download',
            t('Export project schedule (.xml)'),
            'ScheduleExportController',
            'download',
            array('project_id' => $project['id'], 'plugin' => 'ScheduleExport')
        ) ?>
    </li>
<?php endif ?>
