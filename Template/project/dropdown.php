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
    <li>
        <?= $this->url->icon(
            'download',
            'Exportar cronograma com subtarefas (experimental .xml)',
            'ScheduleExportController',
            'download',
            array('project_id' => $project['id'], 'plugin' => 'ScheduleExport', 'subtasks' => 'detailed')
        ) ?>
    </li>
<?php endif ?>
