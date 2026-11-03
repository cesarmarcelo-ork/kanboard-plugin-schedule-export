<section class="page-header"><h2><?= t('Actual subtask completion') ?></h2></section>
<p><strong><?= $this->text->e($subtask['title']) ?></strong></p>
<p><?= t('Completion is recorded automatically when Kanboard marks the subtask as done. Adjust the date below if the work finished earlier.') ?></p>
<?php if (!empty($error)): ?><p class="alert alert-error"><?= $this->text->e($error) ?></p><?php endif ?>
<?php $canEdit = $this->user->hasProjectAccess('SubtaskCompletionController', 'save', $task['project_id']); ?>
<?php if ((int)$subtask['status'] === 2 && $canEdit): ?>
<form method="post" action="<?= $this->url->href('SubtaskCompletionController', 'save', array('plugin'=>'ScheduleExport', 'project_id'=>$task['project_id'], 'task_id'=>$task['id'], 'subtask_id'=>$subtask['id'])) ?>">
    <?= $this->form->csrf() ?>
    <input type="hidden" name="revision" value="<?= (int)($completion['revision'] ?? 0) ?>">
    <label for="completed_at"><?= t('Actual completion date and time') ?></label>
    <input id="completed_at" name="completed_at" type="datetime-local" step="60" required value="<?= $this->text->e($input) ?>">
    <p><?= t('Timezone: %s', date_default_timezone_get()) ?></p>
    <p><?= t('This adjustment preserves planned dates and native time tracking.') ?></p>
    <button type="submit" class="btn btn-blue"><?= t('Save actual completion') ?></button>
</form>
<?php elseif ((int)$subtask['status'] !== 2): ?>
<p><?= t('Complete the subtask in Kanboard before recording its actual completion date.') ?></p>
<?php else: ?>
<p><?= !empty($completion['completed_at']) ? $this->text->e($this->dt->datetime($completion['completed_at'])) : t('Not recorded') ?></p>
<?php endif ?>
<p><?= $this->url->link(t('Back to task'), 'TaskViewController', 'show', array('task_id'=>$task['id'], 'project_id'=>$task['project_id'])) ?></p>
<h3><?= t('Completion history') ?></h3>
<?php if (empty($history)): ?>
<p><?= t('No completion history recorded.') ?></p>
<?php else: ?>
<table>
<thead><tr><th><?= t('Recorded at') ?></th><th><?= t('Action') ?></th><th><?= t('Previous completion') ?></th><th><?= t('Actual completion') ?></th><th><?= t('Recorded by') ?></th></tr></thead>
<tbody>
<?php $actions = array('completed'=>t('Completed'), 'reopened'=>t('Reopened'), 'adjusted'=>t('Date adjusted')); ?>
<?php foreach ($history as $entry): ?>
<tr>
<td><?= $this->text->e($this->dt->datetime($entry['changed_at'])) ?></td>
<td><?= $this->text->e($actions[$entry['action']] ?? $entry['action']) ?></td>
<td><?= $entry['previous_completed_at'] ? $this->text->e($this->dt->datetime($entry['previous_completed_at'])) : '—' ?></td>
<td><?= $entry['completed_at'] ? $this->text->e($this->dt->datetime($entry['completed_at'])) : '—' ?></td>
<td><?= $this->text->e($entry['username'] ?: ($entry['changed_by'] ? '#'.$entry['changed_by'] : t('System / API'))) ?></td>
</tr>
<?php endforeach ?>
</tbody></table>
<?php endif ?>
