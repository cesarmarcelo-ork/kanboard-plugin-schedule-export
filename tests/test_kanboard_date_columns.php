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
$helper = new class($container, $container['helper']) extends \Kanboard\Core\Helper {
    private $realHelper;
    public $user;
    public $url;
    public $form;
    public function __construct($container, $realHelper) {
        parent::__construct($container);
        $this->realHelper = $realHelper;
    }
    public function getHelper($name) {
        if ($name === 'user') return $this->user;
        if ($name === 'url') return $this->url;
        if ($name === 'form') return $this->form;
        return $this->realHelper->getHelper($name);
    }
};
$helper->user = new class {
    public $edit = true;
    public function hasProjectAccess($controller, $action, $projectId) {
        return $action === 'save' ? $this->edit : true;
    }
};
$helper->url = new class {
    public function href($controller, $action, $params) { return "?".htmlspecialchars(http_build_query($params), ENT_QUOTES, "UTF-8"); }
    public function link($label, $controller, $action, $params) {
        return '<a href="?'.htmlspecialchars(http_build_query($params), ENT_QUOTES, 'UTF-8').'">'.$label.'</a>';
    }
};
$template = new class($helper) extends \Kanboard\Core\Template {
    public function getTemplateFile($name) {
        return __DIR__.'/../Template/'.(strpos($name, '/') === false ? 'subtask/' : '').$name.'.php';
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

$container['dateParser'] = new class { public function getUserDateTimeFormat() { return 'd/m/Y H:i'; } };
$helper->form = new class { public function csrf() { return '<input name="csrf_token" value="test">'; } };
$helper->user->edit = true;
$args['subtask']['status'] = 2;
$args['completion_project_id'] = 1;
$args['completion'] = array('completed_at'=>strtotime('2026-11-01 14:30:00'), 'revision'=>1);
$html = $template->render('completion-link', $args);
ensure(strpos($html, '01/11/2026 14:30') !== false && strpos($html, '<a ') !== false, 'Real dt helper renders date and datetime tooltip');
ensure(strpos($template->render('completion-header'), 'Conclusão efetiva') !== false, 'Actual completion translated');
$formArgs = array('task'=>array('id'=>9, 'project_id'=>1), 'subtask'=>array('id'=>30,'status'=>2,'title'=>'<unsafe>'), 'completion'=>$args['completion'], 'history'=>array(array('changed_at'=>time(),'previous_completed_at'=>null,'completed_at'=>time(),'action'=>'completed','username'=>'<user>','changed_by'=>1)), 'error'=>'', 'input'=>'2026-11-01T14:30');
$html = $template->render('task/edit-completion', $formArgs);
ensure(strpos($html, 'csrf_token')!==false && strpos($html, 'datetime-local')!==false, 'Member form includes CSRF and actual date');
ensure(strpos($html, '<unsafe>')===false && strpos($html, '<user>')===false, 'Titles and history usernames escaped');
$helper->user->edit=false;
ensure(strpos($template->render('task/edit-completion',$formArgs), '<form')===false,'Viewer only sees completion/history');
$helper->user->edit=true;
$formArgs['subtask']['status']=0;
ensure(strpos($template->render('task/edit-completion',$formArgs), '<form')===false,'Reopened subtask has no edit form');
echo "PASS: actual completion row, form, history, escaping and read-only rendering with real Kanboard helpers\n";
