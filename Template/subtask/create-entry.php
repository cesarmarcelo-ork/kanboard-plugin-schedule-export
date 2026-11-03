<?php
// This hook appears inside Kanboard's native "Add a sub-task" form.
// Leave the original bulk-create form, inputs and submit action unchanged.
$taskId = (int)($values['task_id'] ?? 0);
?>
<?php if ($taskId > 0): ?>
<div class="alert alert-info">
    <a href="<?= $this->url->href('PlannedSubtaskCreationController', 'create', array('plugin' => 'ScheduleExport', 'task_id' => $taskId)) ?>">
        <?= t('Create one sub-task with planning') ?>
    </a>
    <p><?= t('Create a single sub-task and optionally set planned dates, or keep using bulk creation without dates.') ?></p>
</div>
<?php endif ?>
