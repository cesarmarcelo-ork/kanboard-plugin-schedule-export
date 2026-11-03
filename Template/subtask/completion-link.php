<?php
$done = (int)($subtask['status'] ?? 0) === 2;
$timestamp = $done ? ($completion['completed_at'] ?? null) : null;
$label = $timestamp ? $this->dt->date($timestamp) : ($done ? t('Not recorded') : '—');
$canView = !empty($completion_project_id) && $this->user->hasProjectAccess('SubtaskCompletionController', 'edit', $completion_project_id);
?>
<td class="schedule-export-completion" style="white-space: nowrap;" title="<?= $timestamp ? $this->text->e($this->dt->datetime($timestamp)) : '' ?>">
    <?php if ($canView && ($done || !empty($completion))): ?>
        <?= $this->url->link($this->text->e($label), 'SubtaskCompletionController', 'edit', array('plugin'=>'ScheduleExport', 'project_id'=>$completion_project_id, 'task_id'=>$subtask['task_id'], 'subtask_id'=>$subtask['id'])) ?>
    <?php else: ?>
        <?= $this->text->e($label) ?>
    <?php endif ?>
</td>
