<?php
// Uses Kanboard's real helper registry, Template and DateHelper.
require $argv[1].'/vendor/autoload.php';
function ensure($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
$container = new \Pimple\Container();
$container['configModel'] = new class {
    public $format = 'd/m/Y';
    public function get($key, $default = '') {
        return $key === 'application_date_format' ? $this->format : $default;
    }
};
(new \Kanboard\ServiceProvider\HelperProvider())->register($container);
$helper = $container['helper'];
$helper->user = new class {
    public $edit = true;
    public function hasProjectAccess($controller, $action, $projectId) {
        return $action === 'save' ? $this->edit : true;
    }
};
$helper->url = new class {
    public function link($label, $controller, $action, $params) {
        return '<a href="?'.htmlspecialchars(http_build_query($params), ENT_QUOTES, 'UTF-8').'">'.$label.'</a>';
    }
};
$template = new class($helper) extends \Kanboard\Core\Template {
    public function getTemplateFile($name) {
        return __DIR__.'/../Template/subtask/'.$name.'.php';
    }
};
$args = array(
    'subtask' => array('id'=>30, 'task_id'=>9),
    'schedule_export_project_id' => 1,
    'schedule_export_plan' => array('planned_start'=>'2026-11-03T00:30:00', 'planned_end'=>'2026-11-06T17:00:00'),
);
foreach (array('UTC', 'America/Sao_Paulo', 'Asia/Tokyo') as $tz) {
    date_default_timezone_set($tz);
    $html = $template->render('period-link', $args);
    ensure(strpos($html, '03/11/2026') !== false && strpos($html, '06/11/2026') !== false, 'Actual Kanboard renders stored dates');
    ensure(substr_count($html, '<td ') === 2 && substr_count($html, '<a ') === 2, 'Full row renders with both editing links');
}
$container['configModel']->format = 'Y-m-d';
ensure(strpos($template->render('period-link', $args), '2026-11-03') !== false, 'Configured date format honored');
$helper->user->edit = false;
ensure(strpos($template->render('period-link', $args), '<a ') === false, 'Viewers cannot edit');
$args['schedule_export_plan'] = null;
ensure(substr_count($template->render('period-link', $args), 'Not scheduled') === 2, 'Unplanned viewer row');
\Kanboard\Core\Translator::load('pt_BR', __DIR__.'/../Locale');
$header = $template->render('period-header');
ensure(strpos($header, 'Data inicial estimada') !== false && strpos($header, 'Data final estimada') !== false, 'Actual translator renders PT-BR headers');
ensure(substr_count($header, 'white-space: nowrap') === 2, 'Both headers prevent wrapping');
echo "PASS: real Kanboard helper registry, Template, DateHelper, formats and translations\n";
