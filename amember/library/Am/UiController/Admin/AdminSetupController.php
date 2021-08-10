<?php

/*
 *
 *
 *     Author: Alex Scott
 *      Email: alex@cgi-central.net
 *        Web: http://www.cgi-central.net
 *    Details: Configuration
 *    FileName $RCSfile$
 *    Release: 6.3.6 ($Revision$)
 *
 * Please direct bug reports,suggestions or feedback to the cgi-central forums.
 * http://www.cgi-central.net/forum/
 *
 * aMember PRO is a commercial software. Any distribution is strictly prohibited.
 *
 */

class AdminSetupController extends Am_Mvc_Controller
{
    static protected $instance;
    protected $forms = [];

    /** @var string */
    protected $p;

    /** @var Am_Form_Setup */
    protected $form;

    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->hasPermission(Am_Auth_Admin::PERM_SETUP);
    }

    function getConfigValues()
    {
        $c = new Am_Config;
        $c->read();
        $ret = $this->_getConfigValues('', $c->getArray());
        // strip keys encoded for form
        foreach ($ret as $k => $v)
        {
            if (preg_match('/___/', $k))
                unset($ret[$k]);
        }
        return $ret;
    }

    function _getConfigValues($prefix, $node)
    {
        $ret = [];
        foreach ($node as $k => $v)
        {
            if (!is_array($v) || (isset($v[0]) || isset($v[1]))) {
                $ret[$prefix . $k] = $v;
            } else {
                $ret = array_merge_recursive($ret, $this->_getConfigValues("$prefix$k.", $v));
            }
        }
        return $ret;
    }

    function indexAction()
    {
        $this->_request->setParam('p', 'global');
        return $this->displayAction();
    }

    function displayAction()
    {
        $this->p = filterId($this->_request->getParam('p'));
        $this->setActiveMenu('setup');

        if ($this->p === 'ajax')
            return $this->ajaxAction();
        if ($this->p === 'recaptcha-validate')
            return $this->recaptchaValidateAction();
        $this->initSetupForms();
        $this->form = $this->getForm($this->p, false);
        $this->form->prepare();
        if ($this->form->isSubmitted())
        {
            $this->form->setDataSources([$this->_request]);
            if ($this->form->validate() && $this->form->saveConfig())
            {
                $this->getDi()->hook->call(Am_Event::SETUP_UPDATED);
                $this->getDi()->adminLogTable->log(sprintf('Update Configuration [%s]', $this->form->getPageId()));
                $this->redirectHtml($this->form->getUrl(), ___('Config values updated...'));
                return;
            }
        } else {
            $cfg = $this->getConfigValues();
            unset($cfg['p']);
            unset($cfg['page']);
            unset($cfg['hp_c']);
            $this->form->setDataSources([
                new HTML_QuickForm2_DataSource_Array($cfg),
                new HTML_QuickForm2_DataSource_Array($this->form->getDefaults()),
            ]);
        }
        $this->view->assign('p', $this->p);
        $this->view->assign('pages', $this->renderPages());
        $this->form->replaceDotInNames();
        $this->view->assign('pageObj', $this->form);
        $this->view->assign('form', $this->form);
        $this->view->display('admin/setup.phtml');
    }

    public function ajaxAction()
    {
        $this->p = filterId($this->_request->getParam('_p'));
        $this->initSetupForms();
        $this->form = $this->getForm($this->p, false);
        $this->form->prepare();
        $this->form->setDataSources([$this->_request]);
        $this->form->ajaxAction($this->getRequest());
    }

    public function recaptchaValidateAction()
    {
        $id = $this->getParam('id');
        $name = $this->getParam('name');

        $form = new Am_Form_Admin("{$id}-recaptcha-validate");
        $form->setAction($this->getDi()->url('admin-setup/recaptcha-validate', false));
        $captcha = $form->addGroup(null, ['class' => 'am-row-wide'])
                ->setLabel(___("Please complete reCapatcha\nit is necessary to validate your reCaptcha config before enable this option, otherwise you can lock himself from admin interface"));
        $captcha->addHtml()->setHtml('<div style="text-align:center">');
        $captcha->addRule('callback', ___('Validation is failed. Please check %sreCAPTCHA configuration%s (Public and Secret Keys)', '<a href="' . $this->getDi()->url('admin-setup/recaptcha') . '">', '</a>'), function() use ($form) {
            foreach ($form->getDataSources() as $ds) {
                if ($resp = $ds->getValue('g-recaptcha-response'))
                    break;
            }

            $status = false;
            if ($resp)
                $status = $this->getDi()->recaptcha->validate($resp);
            return $status;
        });
        $captcha->addStatic('captcha')
            ->setContent($this->getDi()->recaptcha
            ->render());
        $captcha->addHtml()->setHtml('</div>');

        $btn = $form->addGroup(null, ['class' => 'am-row-wide']);
        $btn->addHtml()->setHtml('<div style="text-align:center">');
        $btn->addSubmit('save', ['value' => ___('Confirm')]);
        $btn->addHtml()->setHtml('</div>');

        $form->addHidden('id', ['value' => $id]);
        $form->addHidden('name', ['value' => $name]);
        $form->addScript()
            ->setScript(<<<CUT
jQuery(function(){
    $('#{$id}-recaptcha-validate').ajaxForm({target: '#{$id}-recaptcha-form'});
});
CUT
            );

        if ($form->isSubmitted() && $form->validate()) {
            echo <<<CUT
<script type="text/javascript">
    jQuery('#{$id}-recaptcha-form').dialog('close');
    jQuery('[name={$name}]').prop('checked', true).change();
    jQuery('#{$id}-recaptcha-need-save').show();
</script>
CUT;
        } else {
            echo $form;
        }
    }

    function renderPages()
    {
        $out = "";
        foreach ($this->forms as $k => $page)
        {
            $out .= $this->renderPage(
                $page->getPageId(),
                $page->getUrl(),
                $page->renderTitle(),
                $page->renderComment()
            );
        }
        return $out;
    }

    function renderPage($pageId, $url, $title, $comment)
    {
        $cl = ($pageId == $this->p) ? 'sel' : 'notsel';
        return
            sprintf(
                '<li class="%s" id="setup-form-%s" data-title="%s"><a href="%s" title="%s"><span>%s</span></a></li>' . "\n",
                $cl,
                $pageId,
                Am_Html::escape(strtolower(strip_tags($title))),
                Am_Html::escape($url),
                $comment,
                $title
            );
    }

    function initSetupForms()
    {
        class_exists('Am_Form_Setup_Advanced', true);
        class_exists('Am_Form_Setup_Email', true);
        class_exists('Am_Form_Setup_EmailTemplates', true);
        class_exists('Am_Form_Setup_Global', true);
        class_exists('Am_Form_Setup_Language', true);
        class_exists('Am_Form_Setup_Loginpage', true);
        class_exists('Am_Form_Setup_Pdf', true);
        class_exists('Am_Form_Setup_PersonalData', true);
        class_exists('Am_Form_Setup_Recaptcha', true);
        class_exists('Am_Form_Setup_Theme', true);
        class_exists('Am_Form_Setup_VideoPlayer', true);

        foreach ($this->getDi()->modules->getPathForPluginsList('library/SetupForms.php') as $fn)
        {
            if (file_exists($fn)) {
                include_once $fn;
            }
        }

        foreach ($this->getDi()->modules->getEnabled() as $module)
        {
            if ($_ = am_glob(AM_APPLICATION_PATH . '/' . $module . '/library/Am/Form/Setup/*.php')) {
                foreach ($_ as $fn) {
                    include_once $fn;
                }
            }
        }

        foreach (get_declared_classes() as $class)
        {
            if (is_subclass_of($class, 'Am_Form_Setup'))
            {
                $rc = new ReflectionClass($class);
                if ($rc->isAbstract())
                    continue;
                if ($class == 'Am_Form_Setup_Theme')
                    continue;
                $this->addForm(new $class);
            }
        }

        foreach ($this->getDi()->plugins as $k => $mgr)
        {
            $mgr->loadEnabled()->getAllEnabled();
        }

        if (!defined('HP_ROOT_DIR'))
        {
            $pluginsFormLink = new Am_Form_Setup_Link('plugins');
            $pluginsFormLink->setOrder(200);
            $pluginsFormLink->setUrl($this->getDi()->url('admin-plugins#mine', false));
            $pluginsFormLink->setTitle(___('Plugins').'&nbsp;<i class="fas fa-external-link-square-alt" style="font-size: 70%; vertical-align: top"></i>');
        } else {
            $pluginsFormLink = new Am_Form_Setup_Plugins();
        }
        $this->addForm($pluginsFormLink);

        $event = new Am_Event_SetupForms($this);
        $this->getDi()->hook->call($event);

        uasort($this->forms, function($a, $b) {
            return $a->getOrder() - $b->getOrder();
        });
    }

    function addForm(Am_Form_Setup $form)
    {
        static $addToOrder = 0; // this is to maintain sort order in add order if that was not tuned
        $id = $form->getPageId();
        if (isset($this->forms[$id]))
            throw new Am_Exception_InternalError("Form [$id] is already exists");
        $form->setOrder($form->getOrder() + ++$addToOrder);
        $this->forms[$id] = $form;
        return $this;
    }

    function getForm($id, $autoCreate = true)
    {
        if (isset($this->forms[$id]))
            return $this->forms[$id];
        if (!$autoCreate)
            throw new Am_Exception_InputError("Form [$id] does not exists");
        $form = new Am_Form_Setup($id);
        $this->addForm($form);
        return $form;
    }

    static public function getInstance()
    {
        if (!self::$instance)
            self::$instance = new self;
        return self::$instance;
    }

    public function preDispatch()
    {
        $vars = $this->_request->toArray();
        foreach ($vars as $k => $v)
        {
            $kk = Am_Form_Setup::name2dots($k);
            if ($kk != $k)
            {
                unset($vars[$k]);
                $vars[$kk] = $v;
            }
        }
        $this->_request->setParams($vars);
    }
}