<?php
$canViewPlan = !empty($schedule_export_project_id)
    && $this->user->hasProjectAccess('SubtaskPlanController', 'edit', $schedule_export_project_id);
$canEditPlan = $canViewPlan
    && $this->user->hasProjectAccess('SubtaskPlanController', 'save', $schedule_export_project_id);
?>
<?php foreach (array('planned_start', 'planned_end') as $dateField): ?>
<td class="schedule-export-date" style="white-space: nowrap;">
<?php if ($canViewPlan): ?>
    <?php
    $value = $schedule_export_plan[$dateField] ?? '';
    $label = $value !== '' ? $this->dt->date($value) : ($canEditPlan ? t('Set date') : t('Not scheduled'));
    ?>
    <?php if ($canEditPlan): ?>
    <?= $this->url->link($this->text->e($label), 'SubtaskPlanController', 'edit', array(
        'plugin'=>'ScheduleExport',
        'project_id'=>$schedule_export_project_id,
        'task_id'=>$subtask['task_id'],
        'subtask_id'=>$subtask['id'],
    )) ?>
    <?php else: ?>
    <?= $this->text->e($label) ?>
    <?php endif ?>
<?php endif ?>
</td>
<?php endforeach ?>
