<style>
.schedule-plan {max-width:880px;margin:16px 0 22px;font-size:14px}
.schedule-plan__panel{border:1px solid #d7dfe9;background:var(--color-background, #fff);border-radius:9px;padding:22px;box-shadow:0 2px 8px rgba(16,24,40,.04)}
.schedule-plan__heading{margin:0 0 6px;font-weight:650;font-size:18px}
.schedule-plan__muted{color:var(--color-text-light, #64748b);margin:0 0 18px}
.schedule-plan__grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}
.schedule-plan__field label{display:block;font-weight:600;margin-bottom:7px}
.schedule-plan__field input{width:100%;max-width:none;box-sizing:border-box;min-height:40px;padding:8px 10px;border:1px solid #aebdce;border-radius:5px;background:var(--color-background, #fff);color:inherit}
.schedule-plan__days{margin-top:20px}
.schedule-plan__days-title{display:block;font-weight:600;margin-bottom:10px}
.schedule-plan__days-row{display:flex;flex-wrap:wrap;gap:9px}
.schedule-plan__day{display:inline-flex;align-items:center;gap:6px;border:1px solid #d7dfe9;border-radius:6px;padding:7px 10px;cursor:pointer}
.schedule-plan__note{margin-top:18px;padding:12px;border-left:3px solid #4379a8;background:rgba(67,121,168,.08);font-size:13px}
.schedule-plan__actions{display:flex;align-items:center;flex-wrap:wrap;gap:14px;margin-top:18px}
.schedule-plan__actions button{padding:9px 18px;border-radius:5px}
.schedule-plan__remove{margin:18px 0}
@media(max-width:650px){.schedule-plan__panel{padding:14px}.schedule-plan__grid{grid-template-columns:1fr}}
</style>
<section class="page-header"><h2>Planejamento de subtarefa</h2></section>
<div class="schedule-plan">
    <p class="schedule-plan__muted"><strong><?= $this->text->e($subtask['title']) ?></strong> · Cartão #<?= (int)$task['id'] ?> · Subtarefa #<?= (int)$subtask['id'] ?></p>
    <form method="post" action="<?= $this->url->href('SubtaskPlanController','save',array('plugin'=>'ScheduleExport','project_id'=>$task['project_id'],'task_id'=>$task['id'],'subtask_id'=>$subtask['id'])) ?>">
        <?= $this->form->csrf() ?>
        <div class="schedule-plan__panel">
            <h3 class="schedule-plan__heading">Período planejado</h3>
            <p class="schedule-plan__muted">Defina o início e o término para representar esta subtarefa no Gantt do ProjectLibre.</p>
            <div class="schedule-plan__grid">
                <div class="schedule-plan__field">
                    <label for="planned_start">Data e hora inicial</label>
                    <input id="planned_start" name="planned_start" type="datetime-local" step="60" required value="<?= $this->text->e($plan['planned_start'] ?? '') ?>">
                </div>
                <div class="schedule-plan__field">
                    <label for="planned_end">Data e hora final</label>
                    <input id="planned_end" name="planned_end" type="datetime-local" step="60" required value="<?= $this->text->e($plan['planned_end'] ?? '') ?>">
                </div>
                <div class="schedule-plan__field">
                    <label for="workday_minutes">Jornada diária (minutos)</label>
                    <input id="workday_minutes" name="workday_minutes" type="number" min="1" max="1440" step="1" required value="<?= (int)($plan['workday_minutes'] ?? 480) ?>" aria-describedby="schedule-hours-help">
                    <small id="schedule-hours-help">Informe em minutos: 480 = 8 horas por dia.</small>
                </div>
            </div>
            <div class="schedule-plan__days">
                <span class="schedule-plan__days-title">Dias considerados</span>
                <div class="schedule-plan__days-row">
                    <?php $selected = explode(',', $plan['workdays'] ?? '1,2,3,4,5'); ?>
                    <?php foreach (array(1=>'Seg',2=>'Ter',3=>'Qua',4=>'Qui',5=>'Sex',6=>'Sáb',7=>'Dom') as $n=>$name): ?>
                        <label class="schedule-plan__day"><input type="checkbox" name="workdays[]" value="<?= $n ?>" <?= in_array((string)$n,$selected,true)?'checked':'' ?>><?= $name ?></label>
                    <?php endforeach ?>
                </div>
            </div>
            <div class="schedule-plan__note">As datas são armazenadas no Kanboard. O tempo estimado e o tempo gasto das subtarefas permanecem sob controle do rastreamento nativo.</div>
        </div>
        <div class="schedule-plan__actions">
            <button type="submit" class="btn btn-blue">Salvar planejamento</button>
            <?= $this->url->link('Cancelar','TaskViewController','show',array('task_id'=>$task['id'],'project_id'=>$task['project_id'])) ?>
        </div>
    </form>
    <?php if (!empty($plan)): ?>
        <form class="schedule-plan__remove" method="post" action="<?= $this->url->href('SubtaskPlanController','remove',array('plugin'=>'ScheduleExport','project_id'=>$task['project_id'],'task_id'=>$task['id'],'subtask_id'=>$subtask['id'])) ?>">
            <?= $this->form->csrf() ?>
            <button type="submit" class="btn btn-red" onclick="return confirm('Remover o planejamento desta subtarefa?')">Remover planejamento</button>
        </form>
    <?php endif ?>
</div>
