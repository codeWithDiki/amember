<?php

/**
 * Base class for custom theme
 * @package Am_Plugin
 */
class Am_Theme extends Am_Plugin_Base
{
    protected $_idPrefix = 'Am_Theme_';
    protected $formThemeClassUser = null; // use default or set to className
    /**
     * Array of paths (relative to application/default/themes/XXX/public/)
     * that must be routed via PHP to substitute vars
     * for example css/theme.css
     * all these files can be accessed directly so please do not put anything
     * sensitive inside
     * @var array
     */
    protected $publicWithVars = [];

    public function __construct(Am_Di $di, $id, array $config)
    {
        parent::__construct($di, $config);
        $this->id = $id;
        $rm = new ReflectionMethod(get_class($this), 'initSetupForm');
        if ($rm->getDeclaringClass()->getName() != __CLASS__)
        {
            $this->getDi()->hook->add(Am_Event::SETUP_FORMS, [$this, 'eventSetupForm']);
        }
        $this->config = $this->config + $this->getDefaults();
    }

    final public function hasSetupForm()
    {
        $rm = new ReflectionMethod(get_class($this), 'initSetupForm');
        return $rm->getDeclaringClass()->getName() != __CLASS__;
    }

    public function init()
    {
        parent::init();
        if ($this->formThemeClassUser)
        {
            $this->getDi()->register('formThemeUser', $this->formThemeClassUser)
                ->addArgument(new sfServiceReference('service_container'));
        }
    }

    function eventSetupForm(Am_Event_SetupForms $event)
    {
        $form = new Am_Form_Setup_Theme($this->getId());
        $form->setTitle(ucwords(str_replace('-', ' ', $this->getId())) . ' Theme');
        $this->initSetupForm($form);
        foreach ($this->getDefaults() as $k => $v) {
            $form->setDefault($k, $v);
        }
        $event->addForm($form);
    }

    /** You can override it and add elements to create setup form */
    public function initSetupForm(Am_Form_Setup_Theme $form)
    {

    }

    public function getDefaults()
    {
        return [];
    }

    public function getRootDir()
    {
        return AM_THEMES_PATH . '/' . $this->getId();
    }

    static function isChild($theme)
    {
        return strpos($theme, 'child-') === 0;
    }
    static function getParentThemeName($theme)
    {
        if(self::isChild($theme))
        {
            list(, $theme) = explode('child-', $theme);
        }
        return $theme;
    }

    function hasParent()
    {
        return self::isChild($this->getId());
    }

    function getFullPath($relPath)
    {
        if($this->hasParent() && file_exists($this->getRootDir() . '/'.$relPath))
            return $this->getRootDir() . '/'.$relPath;
        else
            return $this->getParentThemeRootDir() . '/'.$relPath;
    }


    function getParentThemeRootDir()
    {
        return AM_THEMES_PATH . '/' . self::getParentThemeName($this->getId());
    }
    function addViewPath(&$path)
    {
        if($this->hasParent())
        {
            $path[] = $this->getParentThemeRootDir();
        }
        $path[] = $this->getRootDir();
    }

    public function printLayoutHead(Am_View $view)
    {
        if (file_exists($this->getFullPath('public/css/theme.css'))) {
            if (!in_array('css/theme.css', $this->publicWithVars))
                $view->headLink()->appendStylesheet($view->_scriptCss('theme.css'));
            else
                $view->headLink()->appendStylesheet($this->urlPublicWithVars('css/theme.css'));
        }

        if ($this->hasParent() && file_exists($this->getFullPath('public/css/child-theme.css')))
            $view->headLink()->appendStylesheet($view->_scriptCss('child-theme.css'));

    }

    function urlPublicWithVars($relPath)
    {
        return $this->getDi()->url('public/theme/' . $relPath,null,false);
    }

    function parsePublicWithVars($relPath)
    {
        if (!in_array($relPath, $this->publicWithVars)) {
            amDie("That files is not allowed to open via this URL");
        }

        $f = $this->getFullPath('public/' . $relPath);

        if (!file_exists($f)) {
            amDie("Could not find file [" . htmlentities($relPath, ENT_QUOTES, 'UTF-8') . "]");
        }
        $tpl = new Am_SimpleTemplate();
        $tpl->assign($this->config);
        return $tpl->render(file_get_contents($f));
    }

    function getNavigation($id)
    {
        $n = new Am_Navigation_User();
        $n->addMenuPages($id);
        return $n;
    }
}

class Am_Theme_Default extends Am_Theme
{
    public function initSetupForm(Am_Form_Setup_Theme $form)
    {
        $form->addUpload('header_logo', null, ['prefix' => 'theme-default'])
            ->setLabel(___("Header Logo\n" .
                'keep it empty for default value'))->default = '';

        $g = $form->addGroup(null, ['id' => 'logo-link-group'])
            ->setLabel(___('Add hyperlink for Logo'));
        $g->setSeparator(' ');
        $g->addAdvCheckbox('logo_link');
        $g->addText('home_url', ['style' => 'width:80%', 'placeholder' => $this->getDi()->config->get('root_url')], ['prefix' => 'theme-default'])
            ->default = '';

        $form->addScript()
            ->setScript(<<<CUT
jQuery(function($){
    $('[type=checkbox][name$=logo_link]').change(function(){
        $(this).nextAll().toggle(this.checked);
    }).change();
});
CUT
            );

        $form->addHtmlEditor('header', null, ['showInPopup' => true])
            ->setLabel(___("Header\nthis content will be included to header"))
            ->setMceOptions([
                'placeholder_items' => [
                ['Current Year', '%year%'],
                ['Site Title', '%site_title%'],
                ]
            ])->default = '';
        $form->addHtmlEditor('footer', null, ['showInPopup' => true])
            ->setLabel(___("Footer\nthis content will be included to footer"))
            ->setMceOptions([
                'placeholder_items' => [
                ['Current Year', '%year%'],
                ['Site Title', '%site_title%'],
                ]
            ])->default = '';
        $form->addAdvCheckbox('gravatar')
            ->setLabel('User Gravatar in user identity block');
        $form->addSaveCallbacks([$this, 'moveLogoFile'], null);
    }

    function moveLogoFile(Am_Config $before, Am_Config $after)
    {
        $this->moveFile($before, $after, 'header_logo', 'header_path');
    }

    function moveFile(Am_Config $before, Am_Config $after, $nameBefore, $nameAfter)
    {
        $t_id = "themes.{$this->getId()}.{$nameBefore}";
        $t_path = "themes.{$this->getId()}.{$nameAfter}";
        if (!$after->get($t_id)) {
            $after->set($t_path, null);
        } elseif ( ($after->get($t_id) && !$after->get($t_path)) ||
            ($after->get($t_id) && $after->get($t_id) != $before->get($t_id))) {

            $upload = $this->getDi()->uploadTable->load($after->get($t_id));
            switch ($upload->getType())
            {
                case 'image/gif' :
                    $ext = 'gif';
                    break;
                case 'image/png' :
                    $ext = 'png';
                    break;
                case 'image/jpeg' :
                    $ext = 'jpg';
                    break;
                case 'image/svg+xml' :
                    $ext = 'svg';
                    break;
                default :
                    throw new Am_Exception_InputError(sprintf('Unknown MIME type [%s]', $upload->getType()));
            }

            $name = str_replace(".{$upload->prefix}.", '', $upload->path);
            $filename = $upload->getFullPath();

            $newName =  $name . '.' . $ext;
            $newFilename = $this->getDi()->public_dir . '/' . $newName;
            copy($filename, $newFilename);
            $after->set($t_path, $newName);
        }
    }

    function init()
    {
        parent::init();
        if ($this->getConfig('gravatar')) {
            $this->getDi()->blocks->remove('member-identity');
            $this->getDi()->blocks->add(new Am_Block('member/identity', null, 'member-identity-gravatar', null, function(Am_View $v){
                $login = Am_Html::escape($v->di->user->login);
                $url = Am_Di::getInstance()->url('logout');
                $url_label = Am_Html::escape(___('Logout'));
                $avatar_url = Am_Html::escape('//www.gravatar.com/avatar/' . md5(strtolower(trim($v->di->user->email))) . '?s=24&d=mm');
                return <<<CUT
<div class="am-user-identity-block-avatar">
    <div class="am-user-identity-block-avatar-pic">
        <img src="$avatar_url" />
    </div>
    <span class="am-user-identity-block_login">$login</span> <a href="$url">$url_label</a>
</div>
CUT;
            }));
        }
    }

    function onBeforeRender(Am_Event $e)
    {
        $e->getView()->theme_logo_url = $this->logoUrl($e->getView());
    }

    function logoUrl(Am_View $v)
    {
        if ($path = $this->getConfig('header_path')) {
            return $this->getDi()->url("data/public/{$path}", false);
        } elseif (($logo_id = $this->getConfig('header_logo')) && ($upload = $this->getDi()->uploadTable->load($logo_id, false))) {
            return $this->getDi()->url('upload/get/' . preg_replace('/^\./', '', $upload->path), false);
        } else {
            return $v->_scriptImg('/header-logo.png');
        }
    }

    public function getDefaults()
    {
        return parent::getDefaults() + [
                'logo_link' => 1
            ];
    }

}
