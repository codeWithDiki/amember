<?php
/*
*
*
*     Author: Alex Scott
*      Email: alex@cgi-central.net
*        Web: http://www.cgi-central.net
*    Details: Admin accounts
*    FileName $RCSfile$
*    Release: 6.3.6 ($Revision: 4649 $)
*
* Please direct bug reports,suggestions or feedback to the cgi-central forums.
* http://www.cgi-central.net/forum/
*
* aMember PRO is a commercial software. Any distribution is strictly prohibited.
*/

class Am_Grid_Action_CopySavedForm extends Am_Grid_Action_Abstract
{
    protected $id = 'copy';
    protected $privilege = 'insert';

    public function run()
    {
        $record = $this->grid->getRecord();

        $record->generateCode();
        $vars = $record->toRow();
        unset($vars['saved_form_id']);
        unset($vars['default_for']);
        $vars['title'] = ___('Copy of') . ' ' . $record->title;

        $back = @$_SERVER['HTTP_X_REQUESTED_WITH'];
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $request = new Am_Mvc_Request($vars + [
                $this->grid->getId() . '_a' => 'insert-' . $record->type,
            $this->grid->getId() . '_b' => $this->grid->getBackUrl()
            ], Am_Mvc_Request::METHOD_POST);
        $controller = new AdminSavedFormController($request, new Am_Mvc_Response(),
            ['di' => Am_Di::getInstance()]);

        $request->setModuleName('default')
            ->setControllerName('admin-saved-form')
            ->setActionName('index')
            ->setDispatched(true);

        $controller->dispatch('indexAction');
        $response = $controller->getResponse();
        $response->sendResponse();
        $_SERVER['HTTP_X_REQUESTED_WITH'] = $back;
    }
}

class Am_Grid_Action_Sort_SavedForm extends Am_Grid_Action_Sort_Abstract
{
    protected function setSortBetween($item, $after, $before)
    {
        $this->_simpleSort(Am_Di::getInstance()->savedFormTable, $item, $after, $before);
    }
}

class Am_Form_Admin_SavedForm extends Am_Form_Admin
{
    /** @var SavedForm */
    protected $record;
    /** @var Am_Form_Element_BricksEditor */
    protected $brickEditor;

    public function __construct(SavedForm $record)
    {
        $this->record = $record;
        parent::__construct();
    }

    public function init()
    {
        parent::init();

        $typeDef = $this->record->getTypeDef();

        $gr = $this->addGroup()
            ->setLabel(___('Form Type'));
        $gr->setSeparator(' ');
        $type = $gr->addSelect('type', null, ['options' => Am_Di::getInstance()->savedFormTable->getTypeOptions()]);
        if (!empty($this->record->type)) {
            $type->toggleFrozen(true);
        }

        if ($this->record->isLoaded()) {
            $label = Am_Html::escape(___('open'));
            $url = Am_Html::escape($this->record->getUrl(Am_Di::getInstance()->url('').'/'));
            $gr->addHtml()
                ->setHtml(<<<CUT
<a href="{$url}" class="link" target="_blank">{$label}</a>
CUT
                );
        }

        $this->addText('title', ['class' => 'am-el-wide translate'])
            ->setLabel(___("Custom Signup Form Title\n".
                "keep empty to use default title"));

        $this->addText('comment', ['class' => 'am-el-wide'])
            ->setLabel(___("Comment\nfor admin reference"));

        if ($this->record->isSignup() || $this->record->isProfile())
        {
            if (!empty($typeDef['generateCode'])) {
                $this->addText('code')
                    ->setLabel(___("Secret Code\n".
                        "if form is not choosen as default, this code\n".
                        "(inside URL) will be necessary to open form"))
                    ->addRule('regex', ___('Value must be alpha-numeric'), '/[a-zA-Z0-9_]/');
            }
        }

        if ($this->record->type == SavedForm::T_SIGNUP) {
            $this->addAdvCheckbox('is_disabled')->setLabel(___("Is Disabled?\n" .
                "disable usage of this form"));
        }

        $this->brickEditor = $this->addElement(new Am_Form_Element_BricksEditor('fields', [], $this->record->createForm()))
            ->setLabel(___('Fields'));

        if ($this->record->isSignup()) {
            $this->addSelect('tpl')
                ->setLabel(___("Template\nalternative template for signup page").
                               "\n" .
                           ___("aMember will look for templates in [application/default/views/signup/] folder\n".
                               "and in theme's [signup/] folder\n".
                               "and template filename must start with [signup]"))
                ->loadOptions($this->getSignupTemplates());
        }

        $fs = $this->addAdvFieldset('meta', ['id'=>'meta'])
            ->setLabel(___('Meta Data'));

        $fs->addText('meta_title', ['class' => 'am-el-wide'])
            ->setLabel(___('Title'));

        $fs->addText('meta_keywords', ['class' => 'am-el-wide'])
            ->setLabel(___('Keywords'));

        $fs->addText('meta_description', ['class' => 'am-el-wide'])
            ->setLabel(___('Description'));

        $gr = $fs->addGroup()->setLabel(___("Robots\n" .
            "instructions for search engines"));
        $gr->setSeparator(' ');
        $gr->addCheckbox('meta_robots[]', ['value' => 'noindex'], ['content' => 'noindex']);
        $gr->addCheckbox('meta_robots[]', ['value' => 'nofollow'], ['content' => 'nofollow']);
        $gr->addCheckbox('meta_robots[]', ['value' => 'noarchive'], ['content' => 'noarchive']);
        $gr->addFilter('array_filter');
    }

    public function render(HTML_QuickForm2_Renderer $renderer)
    {
        return parent::render($renderer);
    }

    static function getSignupTemplates()
    {
        $folders = [
            AM_APPLICATION_PATH . '/default/views/' => 1,
            Am_Di::getInstance()->theme->getRootDir() . '/' => 2,
        ];

        if(Am_Di::getInstance()->config->get('protect.wordpress.use_wordpress_theme'))
        {
            $path = defined("TEMPLATEPATH") ? TEMPLATEPATH : 'default';
            $path_parts = preg_split('/[\/\\\]/', $path);
            $path = array_pop($path_parts);
            if (file_exists(AM_APPLICATION_PATH . '/default/plugins/protect/wordpress/' . $path))
            {
                $path = $path;
            } elseif (preg_match("/^([a-zA-Z]+)/", $path, $regs) && file_exists(AM_APPLICATION_PATH . '/default/plugins/protect/wordpress/' . $regs[1])) {
                $path = $regs[1];
            } else {
                $path = false;
            }
            if($path) {
                $folders[AM_APPLICATION_PATH . '/default/plugins/protect/wordpress/' . $path] = 3;
            }
        }
        $ret = [null => 'signup.phtml'];
        foreach (array_keys($folders) as $f)
        {
            foreach ((array)am_glob($f . '/signup/signup*.phtml') as $file)
            {
                if (!strlen($file)) continue;
                $file = basename($file);
                if ($file == 'signup.phtml') continue;
                $ret[$file] = $file;
            }
        }
        return $ret;
    }

    public function renderEpilog()
    {
        return $this->brickEditor->renderConfigForms();
    }
}

class AdminSavedFormController extends Am_Mvc_Controller_Grid
{
    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->hasPermission(Am_Auth_Admin::PERM_FORM);
    }

    function init()
    {
        if (!class_exists('Am_Form_Brick', false)) {
            class_exists('Am_Form_Brick', true);
            Am_Di::getInstance()->hook->call(Am_Event::LOAD_BRICKS);
        }
        parent::init();
    }

    public function createGrid()
    {
        $table = $this->getDi()->savedFormTable;
        $ds = new Am_Query($table);
        $ds->addWhere('`type` in (?a)', array_keys($table->getTypeDefs()));
        $ds->addOrderRaw("sort_order");
        $grid = new Am_Grid_Editable('_s', ___('Forms Editor'), $ds, $this->_request, $this->view);
        $grid->setPermissionId(Am_Auth_Admin::PERM_FORM);
        $grid->setEventId('gridSavedForm');
        $grid->setForm([$this, 'createForm']);
        $grid->setRecordTitle(' ');

        $grid->addField(SavedForm::D_SIGNUP, ___('Default Signup'), false)
            ->setWidth('5%')
            ->setRenderFunction([$this, 'renderSignupDefault']);
        $grid->addField(SavedForm::D_MEMBER, ___('Default for Members'), false)
            ->setWidth('5%')
            ->setRenderFunction([$this, 'renderSignupDefault']);
        $grid->addField(SavedForm::D_PROFILE, ___('Default for Profile'), false)
            ->setWidth('5%')
            ->setRenderFunction([$this, 'renderProfileDefault']);

        $existingTypes = $this->getDi()->savedFormTable->getExistingTypes();

        $grid->actionGet('edit')->setTarget('_top')->showFormAfterSave(true);

        $grid->actionDelete('insert');
        foreach ($this->getDi()->savedFormTable->getTypeDefs() as $type => $typeDef)
        {
            if (!empty($typeDef['isSingle']) && in_array($type, $existingTypes))
                continue;
            $grid->actionAdd(new Am_Grid_Action_Insert('insert-'.$type))
                ->addUrlParam('type', $type)->setTitle(___('New %s', $typeDef['title']));
        }
        $grid->addCallback(Am_Grid_Editable::CB_BEFORE_SAVE, [$this, 'beforeSave']);
        $grid->addCallback(Am_Grid_ReadOnly::CB_TR_ATTRIBS, function(& $ret, $record) {
           if ($record->is_disabled) {
                $ret['class'] = isset($ret['class']) ? $ret['class'] . ' disabled' : 'disabled';
            }
        });

        $grid->addField('type', ___('Type'), true, null, null, '1%')
            ->setRenderFunction(function($r, $fn, $g, $fo) {
                static $colors = ['#a5d6a7', '#ffffcf', '#a1b2c0', '#ee8879', '#b786c7', '#addbec', '#dedede'],
                    $map = [];

                if (!isset($map[$r->$fn])) {
                    $map[$r->$fn] = $colors ? array_shift($colors) : '#dedede';
                }
                $c = $map[$r->$fn];
                return $g->renderTd(<<<CUT
<span style="text-transform: lowercase;
    letter-spacing: .8px;
    padding: 0.2em 0.5em;
    font-size: 80%;
    white-space: nowrap;
    background:$c">{$r->$fn}</span>
CUT
    , false);
            });
        $grid->addField('title', ___('Title'))
            ->setRenderFunction([$this, 'renderTitle']);
        $grid->addField('url', ___('URL'), false)
            ->setRenderFunction([$this, 'renderUrl']);
        $grid->actionGet('delete')
            ->setIsAvailableCallback(function($r) {return $r->canDelete();});
        $grid->actionAdd(new Am_Grid_Action_CopySavedForm())
            ->setTarget('_top')
            ->setIsAvailableCallback(function($r) {return !$r->isSingle();});

        $grid->setFormValueCallback('meta_robots', ['RECORD', 'unserializeList'], ['RECORD', 'serializeList']);
        $grid->addCallback(Am_Grid_ReadOnly::CB_RENDER_STATIC, function(& $out, $grid) {
            $out .= <<<CUT
<script type="text/javascript">
jQuery('.am-grid-wrap').on('change', 'input.set-default', function(){
    var t = jQuery(this);
    jQuery.post(t.data('url'), t.data('post'), function(){
        window.location.reload();
    });
    jQuery(":radio." + t.data('class')).not(t).prop('checked', false);
});
</script>
CUT;
        });

        $grid->actionAdd(new Am_Grid_Action_Sort_SavedForm);
        $grid->setFilter(new Am_Grid_Filter_Text(null, [
            'title' => 'LIKE',
            'comment' => 'LIKE',
            'code' => 'LIKE'
        ], ['placeholder' => ___('Title/Comment/Code')]));

        return $grid;
    }

    function renderTitle($r, $fn, $g, $fo)
    {
        $title = $g->escape($r->title);
        $desc = $g->escape($r->comment);
        $title .= "<div style='opacity: .7'>{$desc}</div>";
        return $g->renderTd($title, false);
    }

    public function beforeSave(array & $values)
    {
        $fields = json_decode($values['fields'],true);
        foreach($fields as &$f)
            if(isset($f['labels']))
                foreach($f['labels'] as $k=>$l)
                    $f['labels'][$k] = preg_replace("/\r\n/","\n",$l);
        $values['fields'] = json_encode($fields);
        if (($values['type'] == 'signup') && !strlen(@$values['code']) && empty($record->code))
        {
            $values['code'] = $this->getDi()->security->randomString(8);
        }
    }

    public function renderDefault(SavedForm $record, $field, $type)
    {
        $html = "";
        if ($record->type == $type)
        {
            $url = explode('?', $this->getDi()->url('admin-saved-form/set-default', false), 2);
            if (empty($url[1])) $url[1] = '_=1';
            $url[1] .= "&default[$field]=" . $record->saved_form_id ;
            $checked = $record->isDefault($field) ? "checked='checked'" : "";
            $html = sprintf("
                <input type=\"radio\" class=\"set-default default-$field\" data-class=\"default-$field\" data-url=\"%s\" data-post=\"%s\" %s/>
                ",
                Am_Html::escape($url[0]),
                Am_Html::escape($url[1]),
                $checked);
        }
        return $this->renderTd($html, false);
    }

    public function renderSignupDefault(SavedForm $record, $field)
    {
        return $this->renderDefault($record, $field, SavedForm::T_SIGNUP);
    }

    public function renderProfileDefault(SavedForm $record, $field)
    {
        return $this->renderDefault($record, $field, SavedForm::T_PROFILE);
    }

    public function setDefaultAction()
    {
        foreach ($this->getRequest()->getPost('default') as $d => $id)
            $this->getDi()->savedFormTable->setDefault($d, $id);
        $this->_redirect('admin-saved-form');
    }

    public function createForm()
    {
        $record = $this->grid->getRecord();
        $post = $this->grid->getCompleteRequest()->getPost();
        if (!$record->isLoaded())
        {
            if ($type = $this->_request->getFiltered('type'))
                $record->type = $type;
            if ($record->type && empty($post['type'])) // form was not submitted yet
                $record->setDefaults();
        }
        $form = new Am_Form_Admin_SavedForm($record);
        $form->addRule('callback', '-error-', [$this, 'validate']);
        return $form;
    }

    public function renderUrl(SavedForm $record)
    {
        $content = sprintf('<a target="_blank" href="%s" class="link">%s</a>',
            $record->getUrl(ROOT_URL . '/'), $record->getUrl(""));
        return $this->renderTd($content, false);
    }

    function validate(array $value)
    {
        /// check for unique code
        $el = $this->grid->getForm()->getElementById('code-0');
        if ($el && strlen($code = $el->getValue()))
        {
            if ($id = $this->getDi()->db->selectCell("SELECT saved_form_id
                    FROM {$this->getDi()->savedFormTable->getName()}
                    WHERE code=? AND saved_form_id<>?d AND type=?",
                        $code,
                        (int)@$value['_s_id'],
                        $value['type']))
            {
                $code = $this->escape($code);
                $el->setError(___('The code [%s] is already used by signup form #%s, please choose another code', $code, $id));
                return false;
            }
        }
        return true;
    }
}