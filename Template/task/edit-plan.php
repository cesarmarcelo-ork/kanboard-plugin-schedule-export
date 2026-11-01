<section class="page-header">
    <h2>Planejamento da subtarefa: <?= $this->text->e($subtask['title']) ?></h2>
</section>
<p>Cartão #<?= (int)$task['id'] ?>. As datas são explícitas e independentes das horas nativas estimadas ou gastas.</p>
<form method="post" action="<?= $this->url->href('SubtaskPlanController','save',array('plugin'=>'ScheduleExport','project_id'=>$task['project_id'],'task_id'=>$task['id'],'subtask_id'=>$subtask['id'])) ?>">
    <?= $this->form->csrf() ?>
    <fieldset style="max-width:720px;padding:18px;margin-top:12px;border:1px solid #d5dce5;border-radius:6px">
        <legend><strong>Planejamento da subtarefa</strong></legend>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:12px;margin-bottom:14px"><div><label for="planned_start">Data e hora inicial</label>
        <input id="planned_start" name="planned_start" type="datetime-local" step="1" required
            value="<?= $this->text->e($plan['planned_start'] ?? '') ?>" style="width:100%;max-width:270px"></div><div>
        <label for="planned_end">Data e hora final</label>
        <input id="planned_end" name="planned_end" type="datetime-local" step="1" required
            value="<?= $this->text->e($plan['planned_end'] ?? '') ?>" style="width:100%;max-width:270px"></div></div>
        <label for="workday_minutes">Jornada (minutos/dia)</label>
        <input id="workday_minutes" name="workday_minutes" type="number" min="1" max="1440" required
            value="<?= (int)($plan['workday_minutes'] ?? 480) ?>">
        <p><strong>Dias considerados</strong> (1 = segunda, 7 = domingo)</p>
        <?php $selected = explode(',', $plan['workdays'] ?? '1,2,3,4,5'); ?>
        <?php foreach (array(1=>'Seg',2=>'Ter',3=>'Qua',4=>'Qui',5=>'Sex',6=>'Sáb',7=>'Dom') as $n=>$name): ?>
            <label style="display:inline-block;margin-right:12px"><input type="checkbox" name="workdays[]" value="<?= $n ?>" <?= in_array((string)$n,$selected,true)?'checked':'' ?>> <?= $name ?></label>
        <?php endforeach ?>
        <p style="margin-top:14px;color:#596579">Os períodos são armazenados no Kanboard e não modificam o rastreamento de tempo nativo.</p>
    </fieldset>
    <button type="submit" class="btn btn-blue">Salvar período</button>
    <?= $this->url->link('Cancelar','TaskViewController','show',array('task_id'=>$task['id'],'project_id'=>$task['project_id'])) ?>
</form>

<?php if (!empty($plan)): ?>
<form method="post" action="<?= $this->url->href('SubtaskPlanController','remove',array('plugin'=>'ScheduleExport','project_id'=>$task['project_id'],'task_id'=>$task['id'],'subtask_id'=>$subtask['id'])) ?>">
    <?= $this->form->csrf() ?>
    <button type="submit" class="btn btn-red" onclick="return confirm('Remover o planejamento desta subtarefa?')">Remover planejamento</button>
</form>
<?php endif ?>
