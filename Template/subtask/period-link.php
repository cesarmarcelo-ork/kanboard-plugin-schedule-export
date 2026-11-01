<td>
<?php if (!empty($schedule_export_project_id) && $this->user->hasProjectAccess('SubtaskPlanController','edit',$schedule_export_project_id)): ?>
    <?= $this->url->link('Datas', 'SubtaskPlanController','edit', array(
        'plugin'=>'ScheduleExport',
        'project_id'=>$schedule_export_project_id,
        'task_id'=>$subtask['task_id'],
        'subtask_id'=>$subtask['id'],
    )) ?>
<?php endif ?>
</td>
