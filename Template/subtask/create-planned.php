<style>
.schedule-create {max-width:880px;margin:16px 0 22px;font-size:14px}
.schedule-create__panel{border:1px solid #d7dfe9;background:var(--color-background,#fff);border-radius:9px;padding:20px}
.schedule-create__grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin-top:14px}
.schedule-create__field label{display:block;font-weight:600;margin-bottom:7px}
.schedule-create__field input{width:100%;min-height:39px;box-sizing:border-box;padding:8px;border:1px solid #aebdce;border-radius:5px}
.schedule-create__days{display:flex;gap:9px;flex-wrap:wrap;margin:10px 0 16px}
.schedule-create__days label{display:inline-flex;align-items:center;gap:5px;padding:6px;border:1px solid #d7dfe9;border-radius:5px}
.schedule-create__actions{margin-top:18px;display:flex;align-items:center;gap:15px}
@media(max-width:650px){.schedule-create__grid{grid-template-columns:1fr}}
</style>
<?php
$planned = isset($values['schedule_export_plan']) && (string)$values['schedule_export_plan'] === '1';
$selectedDays = isset($values['workdays']) && is_array($values['workdays'])
    ? array_map('intval', $values['workdays'])
    : ($planned ? array() : array(1,2,3,4,5));
?>
<div class="page-header"><h2><?= t('Add a sub-task with planning') ?></h2></div>
<div class="schedule-create">
    <p><?= t('Planning is optional. Leave unchecked to create an unscheduled sub-task.') ?></p>
    <form method="post" action="<?= $this->url->href('PlannedSubtaskCreationController','save',array('plugin'=>'ScheduleExport','task_id'=>$task['id'],'project_id'=>$task['project_id'])) ?>" autocomplete="off">
        <?= $this->form->csrf() ?>
        <?= $this->subtask->renderTitleField($values, $errors, array('autofocus')) ?>
        <?= $this->subtask->renderAssigneeField($users_list, $values, $errors) ?>
        <?= $this->subtask->renderTimeEstimatedField($values, $errors) ?>
        <div class="schedule-create__panel">
            <label><input type="checkbox" name="schedule_export_plan" value="1" <?= $planned ? 'checked' : '' ?>> <strong><?= t('Plan this sub-task now') ?></strong></label>
            <?php if (!empty($errors['schedule_export_plan'])): ?>
                <p class="alert alert-error"><?= $this->text->e(implode(' ', (array)$errors['schedule_export_plan'])) ?></p>
            <?php endif ?>
            <div class="schedule-create__grid">
                <div class="schedule-create__field">
                    <label for="planned_start"><?= t('Planned start date and time') ?></label>
                    <input id="planned_start" name="planned_start" type="datetime-local" step="60" value="<?= $this->text->e($values['planned_start'] ?? '') ?>">
                </div>
                <div class="schedule-create__field">
                    <label for="planned_end"><?= t('Planned end date and time') ?></label>
                    <input id="planned_end" name="planned_end" type="datetime-local" step="60" value="<?= $this->text->e($values['planned_end'] ?? '') ?>">
                </div>
                <div class="schedule-create__field">
                    <label for="workday_minutes"><?= t('Working day (minutes)') ?></label>
                    <input id="workday_minutes" name="workday_minutes" type="number" min="1" max="1440" step="1" value="<?= $this->text->e($values['workday_minutes'] ?? 480) ?>">
                    <small><?= t('Enter minutes: 480 = 8 hours per day.') ?></small>
                </div>
            </div>
            <p style="margin-top:18px"><strong><?= t('Working days') ?></strong></p>
            <div class="schedule-create__days">
                <?php foreach (array(1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun') as $n=>$name): ?>
                    <label><input type="checkbox" name="workdays[]" value="<?= $n ?>" <?= in_array($n,$selectedDays,true) ? 'checked' : '' ?>> <?= t($name) ?></label>
                <?php endforeach ?>
            </div>
            <small><?= t('Planned dates are stored in Kanboard. Native estimated and spent time are unchanged.') ?></small>
        </div>
        <div class="schedule-create__actions">
            <button type="submit" class="btn btn-blue"><?= t('Create sub-task') ?></button>
            <?= $this->url->link(t('Cancel'), 'TaskViewController', 'show', array('task_id'=>$task['id'],'project_id'=>$task['project_id'])) ?>
        </div>
    </form>
</div>
