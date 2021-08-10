<?php

class Am_Theme_Black extends Am_Theme_Default
{
    protected $publicWithVars = ['css/theme.css'];
    protected $formThemeClassUser = 'Am_Form_Theme_Black';

    const F_TAHOMA = 'Tahoma',
        F_ARIAL = 'Arial',
        F_TIMES = 'Times',
        F_HELVETICA = 'Helvetica',
        F_GEORGIA = 'Georgia',
        F_ROBOTO = 'Roboto',
        F_POPPINS = 'Poppins',
        F_OXYGEN = 'Oxygen',
        F_OPEN_SANS = 'Open Sans',
        F_HIND = 'Hind',
        F_RAJDHANI = 'Rajdhani',
        F_NUNITO = 'Nunito',
        F_RALEWAY = 'Raleway',
        F_ARSENAL = 'Arsenal',
        F_JOSEFIN_SANS = 'Josefin Sans';

    public function initSetupForm(Am_Form_Setup_Theme $form)
    {
        $this->getDi()->view->headLink()
            ->appendStylesheet('https://fonts.googleapis.com/css?family=Roboto:400,700')
            ->appendStylesheet('https://fonts.googleapis.com/css?family=Poppins:300,700')
            ->appendStylesheet('https://fonts.googleapis.com/css?family=Oxygen:400,700')
            ->appendStylesheet('https://fonts.googleapis.com/css?family=Hind:400,700')
            ->appendStylesheet('https://fonts.googleapis.com/css?family=Rajdhani:400,700')
            ->appendStylesheet('https://fonts.googleapis.com/css?family=Nunito:400,700')
            ->appendStylesheet('https://fonts.googleapis.com/css?family=Raleway:400,700')
            ->appendStylesheet('https://fonts.googleapis.com/css?family=Open+Sans:400,700')
            ->appendStylesheet('https://fonts.googleapis.com/css?family=Arsenal:400,700')
            ->appendStylesheet('https://fonts.googleapis.com/css?family=Josefin+Sans:400,700');

        $this->addElementLogo($form);

        $form->addProlog(<<<CUT
<style type="text/css">
<!--
    .am-row:hover .color-pick {
        opacity: 1;
    }
    .color-pick {
        opacity: 0;
        display: inline-block;
        vertical-align: middle;
        cursor: pointer;
        width: 1em;
        height: 1em;
        border-radius: 50%;
        transition: transform .3s, opacity .3s;
    }
    .color-pick:hover {
        transform: scale(1.8);
    }
    .am-form div.am-element-title {
        text-align:left;
    }
-->
</style>
CUT
        );

        $form->addScript()
            ->setScript(<<<CUT
jQuery(document).on('click', '.color-pick', function(){
    $(this).closest('.am-row').find('input').val($(this).data('color')).change().valid();
});
jQuery(function(){
    function hexToRgb(hex) {
       var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
       return result ? {
           r: parseInt(result[1], 16),
           g: parseInt(result[2], 16),
           b: parseInt(result[3], 16)
       } : null;
    }

    $('.color-input').change(function(){
        var tColor = 'inherit';

        if ((c = hexToRgb($(this).val())) &&
            (1 - (0.299 * c.r + 0.587 * c.g + 0.114 * c.b) / 255 > 0.5)) {
            tColor = '#fff';
        }
        $(this).css({background: $(this).val(), color: tColor, border: 'none'});
    }).change();
});
CUT
            );

        $label = ___('manage');
        $url = $this->getDi()->url('admin-menu', ['menu_id' => '_header']);
        $form->addHtml()
            ->setLabel(___("Header Menu Items\nyou can edit items that appear in user menu"))
            ->setHtml(<<<CUT
<a class="link" href="{$url}" target="_blank">{$label}</a>
CUT
            );

        $gr = $form->addGroup()
            ->setLabel(___('Layout Max Width'));
        $gr->setSeparator(' ');
        $gr->addText('max_width', ['size' => 3]);
        $gr->addHtml()->setHtml('px');

        $gr = $form->addGroup()
            ->setLabel(___("Font\nSize and Family"));
        $gr->setSeparator(' ');

        $gr->addText('font_size', ['size' => 3]);
        $gr->addHtml()->setHtml('px');
        $gr->addSelect('font_family')
            ->loadOptions($this->getFontOptions());
        $form->addHtml()
            ->setHtml(<<<CUT
<div id="font-preview" style="opacity:.7; white-space: nowrap; overflow: hidden; text-overflow:ellipsis">Almost before we knew it, we had left the ground.</div>
CUT
        );

        $form->addScript()
            ->setScript(<<<CUT
jQuery(document).on('change', '[name$=font_family]', function(){
    $('#font-preview').css({fontFamily: $(this).val()});
});
jQuery(document).on('change', '[name$=font_size]', function(){
    $('#font-preview').css({fontSize: $(this).val() + 'px'});
});
jQuery(function(){
    $('[name$=font_family]').change();
    $('[name$=font_size]').change();
});
CUT
        );

        $this->addElementColor($form, 'menu_color', "Menu Color\n" .
            'you can use any valid <a href="http://www.w3schools.com/html/html_colors.asp" class="link" target="_blank" rel="noreferrer">HTML color</a>, you can find useful color palette <a href="http://www.w3schools.com/TAGS/ref_colornames.asp" class="link" target="_blank" rel="noreferrer">here</a>');

        $label = ___('manage');
        $url = $this->getDi()->url('admin-menu');
        $form->addHtml()
            ->setLabel(___("User Menu Items\nyou can edit items that appear in user menu"))
            ->setHtml(<<<CUT
<a class="link" href="{$url}" target="_blank">{$label}</a>
CUT
            );

        $this->addElementColor($form, 'link_color', "Links Color\n" .
            'you can use any valid <a href="http://www.w3schools.com/html/html_colors.asp" class="link" target="_blank" rel="noreferrer">HTML color</a>, you can find useful color palette <a href="http://www.w3schools.com/TAGS/ref_colornames.asp" class="link" target="_blank" rel="noreferrer">here</a>');

        $this->addElementColor($form, 'btn_color', "Button Color\n" .
            'you can use any valid <a href="http://www.w3schools.com/html/html_colors.asp" class="link" target="_blank" rel="noreferrer">HTML color</a>, you can find useful color palette <a href="http://www.w3schools.com/TAGS/ref_colornames.asp" class="link" target="_blank" rel="noreferrer">here</a>');

        $gr = $form->addGroup()
            ->setLabel(___('User Gravatar in user identity block'));
        $gr->addHtml()
            ->setHtml($this->_htmlGravatar());

        $gr->addAdvCheckbox('gravatar');

        $form->addScript()
            ->setScript(<<<CUT
jQuery(document).on('change', '[name$=gravatar][type=checkbox]', function(){
    $('#gravatar-yes').toggle(this.checked);
    $('#gravatar-no').toggle(!this.checked);
});
jQuery(function(){
    $('[name$=gravatar][type=checkbox]').change();
});
CUT
        );

        $gr = $form->addGroup()
            ->setLabel(___('Identity Block Position'));
        $gr->addHtml()
            ->setHtml($this->_htmlIdentity());
        $gr->addAdvRadio('identity_align')
            ->loadOptions([
                'left' => ___('Left'),
                'right' => ___('Right')
            ]);

        $form->addScript()
            ->setScript(<<<CUT
jQuery(document).on('change', '[name$=identity_align]', function(){
    $('#identity-left').toggle(jQuery('[name$=identity_align]:checked').val() ==     'left');
    $('#identity-right').toggle(jQuery('[name$=identity_align]:checked').val() == 'right');
});
jQuery(function(){
    $('[name$=identity_align]').change();
});
CUT
        );

        $form->addAdvRadio('identity_type')
            ->setLabel(___("Identity Type\nwhat you want to display in identity block"))
            ->loadOptions([
                'login' => ___('Username'),
                'full_name' => ___('Full Name'),
                'email' => ___('E-Mail'),
            ]);

        $gr = $form->addGroup()
            ->setLabel(___('Dashboard Menu Item'));
        $gr->addHtml()
            ->setHtml($this->_htmlDashboard());

        $gr->addAdvRadio('menu_dashboard')
            ->loadOptions([
                'icon' => ___('Icon'),
                'text' => ___('Text')
            ]);

        $form->addScript()
            ->setScript(<<<CUT
jQuery(document).on('change', '[name$=menu_dashboard]', function(){
    $('#menu_dashboard-text').toggle(jQuery('[name$=menu_dashboard]:checked').val() == 'text');
    $('#menu_dashboard-icon').toggle(jQuery('[name$=menu_dashboard]:checked').val() == 'icon');
});
jQuery(function(){
    $('[name$=menu_dashboard]').change();
});
CUT
        );

        $gr = $form->addGroup()
            ->setLabel(___('Dashboard Layout'));
        $gr->addHtml()
            ->setHtml($this->_htmlDashboardLayout());

        $gr->addAdvRadio('dashboard_layout')
            ->loadOptions([
                'two-col' => ___('Two Columns'),
                'one-col' => ___('Single Column')
            ]);

        $form->addScript()
            ->setScript(<<<CUT
jQuery(document).on('change', '[name$=dashboard_layout]', function(){
    $('#dashboard_layout-one-col').toggle(jQuery('[name$=dashboard_layout]:checked').val() == 'one-col');
    $('#dashboard_layout-two-col').toggle(jQuery('[name$=dashboard_layout]:checked').val() == 'two-col');
});
jQuery(function(){
    $('[name$=dashboard_layout]').change();
});
CUT
            );

        $form->addHtmlEditor('footer', null, ['showInPopup' => true])
                ->setLabel(___("Footer\nthis content will be included to footer"))
                ->setMceOptions(['placeholder_items' => [
                    ['Current Year', '%year%'],
                    ['Site Title', '%site_title%'],
                ]])->default = '';

        $form->addProlog(<<<CUT
<style type="text/css">
<!--
    #sm-settings {
        display:none;
        border-bottom:1px solid #d5d5d5;
    }
    #sm-settings div.am-row {
        border-bottom: none;
    }
-->
</style>
CUT
        );

        $l_settings = ___('Settings');

        $form->addHtml()
            ->setLabel(___('Social Media'))
            ->setHtml(<<<CUT
<a href="javascript:;" class="link local link-toggle"
    onclick="jQuery('#sm-settings').toggle(); jQuery(this).closest('.am-row').toggleClass('am-row-head'); jQuery(this).toggleClass('link-toggle-on');">{$l_settings}</a>
CUT
            );

        $form->addRaw()
            ->setContent(<<<CUT
                <div id="sm-settings">
CUT
            );

        $gr = $form->addGroup(null, ['class' => 'am-row-highlight'])
            ->setLabel(___("Glyphs Size"));
        $gr->setSeparator(' ');

        $gr->addText('sm_size', ['size' => 3]);
        $gr->addHtml()->setHtml('px');

        $this->addElementColor($form, 'sm_color', "Glyphs Color\n" .
            'you can use any valid <a href="http://www.w3schools.com/html/html_colors.asp" class="link" target="_blank" rel="noreferrer">HTML color</a>, you can find useful color palette <a href="http://www.w3schools.com/TAGS/ref_colornames.asp" class="link" target="_blank" rel="noreferrer">here</a>', 'theme-color', ['class' => 'am-row-highlight']);

        $sm = [
            'twitter' => 'Twitter',
            'instagram' => 'Instagram',
            'youtube' => 'YouTube',
            'twitch' => 'Twitch',
            'facebook' => 'Facebook',
            'tiktok' => 'TikTok',
            'wechat' => 'WeChat',
            'snapchat' => 'SnapChat',
            'vk' => 'VK',
            'telegram' => 'Telegram',
            'whatsapp' => 'WhatsApp',
            'github' => 'GitHub',
            'bitbucket' => 'BitBucket',
            'behance' => 'Behance',
        ];

        $sm_icons = $this->getConfig('sm_icons');

        foreach ($sm as $id => $label) {
            $g = $form->addGroup(null, ['class' => 'am-row-highlight'])
                ->setLabel($label . "\n" . ___('link to your account'));

            $g->addHtml()->setHtml(<<<CUT
<div style="display: flex">
<div style="display: flex; flex-direction: column; justify-content: center">
    <i class="fab fa-{$sm_icons[$id]}" style="float:left"></i>
</div>
CUT
            );
            $g->addText("sm.{$id}", ['class' => 'am-el-wide', 'style' => 'margin-left: 10px;']);
            $g->addHtml()->setHtml('</div>');
        }


        $form->addRaw()
            ->setContent('</div>');

        $form->addSelect('form_theme')->setLabel(___('Form Theme'))
            ->loadOptions($this->getFormThemes());

        $fs = $form->addAdvFieldset('', ['id'=>'sct-login'])
            ->setLabel('Login Page');

        $gr = $fs->addGroup()
            ->setLabel(___('Login Page Layout'));
        $gr->addHtml()
            ->setHtml($this->_htmlLoginLayout());

        $gr->addAdvRadio('login_layout')
            ->loadOptions([
                'layout.phtml' => ___('Standard'),
                'layout-login-sidebar.phtml' => ___('Login with Sidebar')
            ]);
        $fs->addHtmlEditor('login_sidebar', ['id'=>'login-sidebar'], ['showInPopup' => true])
                ->setLabel(___("Sidebar Content"))
                ->default = '';

        $fs->addAdvCheckbox('login_no_header')
            ->setLabel(___("Remove Header from Login Page"));

        $fs->addUpload('login_logo', null, ['prefix' => 'theme-default'])
                ->setLabel(___("Logo on Login Form\n" .
                    'keep it empty if none'))->default = '';

        $gr = $fs->addGroup()
            ->setLabel(___('Login Form Type'));
        $gr->addHtml()
            ->setHtml($this->_htmlLoginType());

        $gr->addAdvRadio('login_type')
            ->loadOptions([
                '' => ___('With Labels'),
                'am-page-login-no-label' => ___('No Labels'),
            ]);

        $form->addScript()
            ->setScript(<<<CUT
jQuery(document).on('change', '[name$=login_layout]', function(){
    $('#row-login-sidebar').toggle(jQuery('[name$=login_layout]:checked').val() == 'layout-login-sidebar.phtml');
    $('#login_layout-standard').toggle(jQuery('[name$=login_layout]:checked').val() == 'layout.phtml');
    $('#login_layout-sidebar').toggle(jQuery('[name$=login_layout]:checked').val() == 'layout-login-sidebar.phtml');
});
jQuery(function(){
    $('[name$=login_layout]').change();
});
CUT
        );

        $form->addScript()
            ->setScript(<<<CUT
jQuery(document).on('change', '[name$=login_type]', function(){
    $('#login_type-labels').toggle(jQuery('[name$=login_type]:checked').val() == '');
    $('#login_type-no-labels').toggle(jQuery('[name$=login_type]:checked').val() == 'am-page-login-no-label');
});
jQuery(function(){
    $('[name$=login_type]').change();
});
CUT
            );

        $fs = $form->addAdvFieldset('', ['id' => 'sct-css'])
            ->setLabel(___("Additional CSS"));
        $fs->addTextarea('css', ['class' => 'am-el-wide am-row-wide', 'rows'=>12])
            ->setLabel("Add your own CSS code here to customize the appearance and layout of your site");

        $fs = $form->addAdvFieldset('', ['id' => 'sct-bf'])
            ->setLabel(___("Tracking/Widget Code"));
        $fs->addHtml(null, ['class' => 'am-row-wide am-no-label'])
            ->setHtml("Add your own Javascript/Html code here. It will be appended to each page content");
        $fs->addTextarea('body_finish_out', ['class' => 'am-el-wide am-row-wide', 'rows'=>12])
            ->setLabel("Shown if User is NOT LOGGED IN");
        $gr = $fs->addGroup(null, ['class' => 'am-row-wide'])
            ->setLabel(___("Shown if User is LOGGED IN"));
        $gr->addTextarea('body_finish_in', ['class' => 'am-el-wide', 'rows'=>12]);
        $gr->addHtml()->setHtml('<br/><br/>' . ___('You can use user specific placeholders here %user.*% eg.: %user.user_id%, %user.login%, %user.email% etc.'));



        $form->addSaveCallbacks([$this, 'moveLogoFile'], null);
        $form->addSaveCallbacks([$this, 'moveLoginLogoFile'], null);
        $form->addSaveCallbacks([$this, 'updateVersion'], null);
        $form->addSaveCallbacks([$this, 'updateLoginCss'], null);

        $form->addSaveCallbacks(null, [$this, 'updateFile']);
    }

    protected function getFormThemes()
    {
        return [
            '' => 'Default',
            'vertical' => 'One-Column',
            'vertical,nolabels,nolines,noborder' => 'One-Column No-Labels'
        ];
    }

    protected function _htmlLoginType()
    {
        return <<<CUT
<div style="float:right">
    <img src="{$this->getDi()->view->_scriptImg('admin/setup-login-type-labels.png')}" id="login_type-labels" />
    <img src="{$this->getDi()->view->_scriptImg('admin/setup-login-type-no-labels.png')}" id="login_type-no-labels" />
</div>
CUT;
    }

    protected function _htmlLoginLayout()
    {
        return <<<CUT
<div style="float:right">
    <img src="{$this->getDi()->view->_scriptImg('admin/setup-login-layout-standard.png')}" id="login_layout-standard" />
    <img src="{$this->getDi()->view->_scriptImg('admin/setup-login-layout-sidebar.png')}" id="login_layout-sidebar" />
</div>
CUT;
    }

    protected function _htmlGravatar()
    {
        return <<<CUT
<div style="float:right">
    <img src="{$this->getDi()->view->_scriptImg('admin/setup-gravatar-enabled.png')}" id="gravatar-yes" />
    <img src="{$this->getDi()->view->_scriptImg('admin/setup-gravatar-disabled.png')}" id="gravatar-no" />
</div>
CUT;
    }

    protected function _htmlDashboard()
    {
        return <<<CUT
<div style="float:right">
    <img src="{$this->getDi()->view->_scriptImg('admin/setup-dashboard-text.png')}" id="menu_dashboard-text" />
    <img src="{$this->getDi()->view->_scriptImg('admin/setup-dashboard-icon.png')}" id="menu_dashboard-icon" />
</div>
CUT;
    }

    protected function _htmlDashboardLayout()
    {
        return <<<CUT
<div style="float:right">
    <img src="{$this->getDi()->view->_scriptImg('admin/setup-dashboard-layout-two-col.png')}" id="dashboard_layout-two-col" />
    <img src="{$this->getDi()->view->_scriptImg('admin/setup-dashboard-layout-one-col.png')}" id="dashboard_layout-one-col" />
</div>
CUT;
    }

    protected function _htmlIdentity()
    {
        return <<<CUT
<div style="float:right">
    <img src="{$this->getDi()->view->_scriptImg('admin/setup-identity-left.png')}" id="identity-left" />
    <img src="{$this->getDi()->view->_scriptImg('admin/setup-identity-right.png')}" id="identity-right" />
</div>
CUT;
    }

    function addElementLogo($form)
    {
        $form->addProlog(<<<CUT
<style type="text/css">
<!--
    #logo-settings {
        display:none;
        border-bottom:1px solid #d5d5d5;
    }
    #logo-settings div.am-row {
        border-bottom: none;
    }
-->
</style>
CUT
        );

        $l_settings = Am_Html::escape(___('Settings'));

        $form->addHtml()
            ->setLabel(___('Header Logo'))
            ->setHtml(<<<CUT
<a href="javascript:;" class="link local link-toggle"
    onclick="jQuery('#logo-settings').toggle(); jQuery(this).closest('.am-row').toggleClass('am-row-head'); jQuery(this).toggleClass('link-toggle-on');">{$l_settings}</a>
CUT
            );

        $form->addRaw()
            ->setContent(<<<CUT
                <div id="logo-settings">
CUT
                );

        $form->addUpload('header_logo', ['class' => 'am-row-highlight'],
            ['prefix' => 'theme-default'])
                ->setLabel(___("Logo Image\n" .
                    'keep it empty for default value'))->default = '';

        $form->addAdvRadio('logo_align', ['class' => 'am-row-highlight'],
            [
                'options' => [
                    'left' => ___('Left'),
                    'center' => ___('Center'),
                    'right' => ___('Right')
                ]
            ])->setLabel(___('Logo Position'));

        $form->addAdvRadio('logo_width', ['class' => 'am-row-highlight'],
            [
                'options' => [
                    'auto' => ___('As Is'),
                    '100%' => ___('Responsive')
                ]
            ])->setLabel(___('Logo Width'));

        $g = $form->addGroup(null, ['class' => 'am-row-highlight'])
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

        $form->addRaw()
            ->setContent('</div>');
    }

    function addElementColor($form, $name, $label, $id = null, $attr = [])
    {
        $gr = $form->addGroup(null, $attr)
            ->setLabel($label);
        $gr->setSeparator(' ');

        $attr = ['size' => 7, 'class' => 'color-input', 'placeholder' => ___('None')];
        if ($id) {
            $attr['id'] = $id;
        }

        $gr->addText($name, $attr)
            ->addRule('regex', ___('Color should be in hex representation'), '/#[0-9a-f]{6}/i');

        foreach (['#f1f5f9', '#dee7ec', '#ffebcd', '#ff8a80', '#ea80fc',
            '#d1c4e9', '#e3f2fd', '#bbdefb', '#0079d1', '#b2dfdb', '#e6ee9c',
            '#c8e6c9', '#4caf50', '#bcaaa4', '#212121', '#263238', '#2a333c'] as $color) {
            $gr->addHtml()
                ->setHtml("<div class='color-pick' style='background:{$color}' data-color='$color'></div>");
        }
    }

    function printLayoutHead(Am_View $view)
    {
        if ($this->getConfig('font_family') == self::F_ROBOTO) {
            $view->headLink()->appendStylesheet('https://fonts.googleapis.com/css?family=Roboto:400,700');
        }
        if ($this->getConfig('font_family') == self::F_POPPINS) {
            $view->headLink()->appendStylesheet('https://fonts.googleapis.com/css?family=Poppins:300,700');
        }
        if ($this->getConfig('font_family') == self::F_OXYGEN) {
            $view->headLink()->appendStylesheet('https://fonts.googleapis.com/css?family=Oxygen:400,700');
        }
        if ($this->getConfig('font_family') == self::F_OPEN_SANS) {
            $view->headLink()->appendStylesheet('https://fonts.googleapis.com/css?family=Open+Sans:400,700');
        }
        if ($this->getConfig('font_family') == self::F_HIND) {
            $view->headLink()->appendStylesheet('https://fonts.googleapis.com/css?family=Hind:400,700');
        }
        if ($this->getConfig('font_family') == self::F_RAJDHANI) {
            $view->headLink()->appendStylesheet('https://fonts.googleapis.com/css?family=Rajdhani:400,700');
        }
        if ($this->getConfig('font_family') == self::F_NUNITO) {
            $view->headLink()->appendStylesheet('https://fonts.googleapis.com/css?family=Nunito:400,700');
        }
        if ($this->getConfig('font_family') == self::F_RALEWAY) {
            $view->headLink()->appendStylesheet('https://fonts.googleapis.com/css?family=Raleway:400,700');
        }
        if ($this->getConfig('font_family') == self::F_ARSENAL) {
            $view->headLink()->appendStylesheet('https://fonts.googleapis.com/css?family=Arsenal:400,700');
        }
        if ($this->getConfig('font_family') == self::F_JOSEFIN_SANS) {
            $view->headLink()->appendStylesheet('https://fonts.googleapis.com/css?family=Josefin+Sans:400,700');
        }
        $_ = $this->getConfig('version');
        if (file_exists("{$this->getDi()->public_dir}/{$this->getId()}/theme.css")) {
            $view->headLink()
                ->appendStylesheet($this->getDi()->url("data/public/{$this->getId()}/theme.css", strval($_), false));
        } else {
            $view->headLink()
                ->appendStylesheet($this->urlPublicWithVars("css/theme.css" . ($_ ? "?$_" : "")));
        }

        if ($css = $this->getConfig('css')) {
            $view->headStyle()->appendStyle($css);
        }
        if ($this->getConfig('menu_dashboard') == 'text') {
            $view->headStyle()->appendStyle(<<<CUT
ul.am-tabs #menu-member {
    width: auto;
}

ul.am-tabs #menu-member::before {
    margin:0;
    display: none;
}
CUT
            );
        }
        if ($this->getConfig('dashboard_layout') == 'one-col') {
            $view->headStyle()->appendStyle(<<<CUT

.am-layout-two-coll .am-coll-left, .am-layout-two-coll .am-coll-right {
    float: none;
    width: auto;
}

.am-layout-two-coll .am-coll-left .am-coll-content,
.am-layout-two-coll .am-coll-right .am-coll-content {
    margin: 0;
}
CUT
            );
        }
        if (!$view->di->auth->getUser() && $_ = $this->getConfig('body_finish_out')) {
            $view->placeholder('body-finish')->append($_);
        }
        if ($view->di->auth->getUser() && $_ = $this->getConfig('body_finish_in')) {
            $tmpl = new Am_SimpleTemplate();
            $tmpl->assign('user', $view->di->auth->getUser());

            $view->placeholder('body-finish')->append($tmpl->render($_));
        }
        if ($view->di->auth->getUser() && $this->getConfig('identity_type')!='login') {
            $_ = json_encode($this->getConfig('identity_type') == 'full_name' ? $view->di->user->getName() : $view->di->user->email);
            $view->placeholder('body-finish')->append(<<<CUT
<script type="text/javascript">
    jQuery(function(){jQuery('.am-user-identity-block_login, .am-login-text_login').text({$_})});
</script>
CUT
);
        }
     }

    function getFontOptions()
    {
        return [
            self::F_TAHOMA => 'Tahoma',
            self::F_ARIAL => 'Arial',
            self::F_TIMES => 'Times',
            self::F_HELVETICA => 'Helvetica',
            self::F_GEORGIA => 'Georgia',
            self::F_ROBOTO => 'Roboto',
            self::F_POPPINS => 'Poppins',
            self::F_OXYGEN => 'Oxygen',
            self::F_OPEN_SANS => 'Open Sans',
            self::F_HIND => 'Hind',
            self::F_RAJDHANI => 'Rajdhani',
            self::F_NUNITO => 'Nunito',
            self::F_RALEWAY => 'Releway',
            self::F_ARSENAL => 'Arsenal',
            self::F_JOSEFIN_SANS => 'Josefin Sans',
        ];
    }

    function moveLoginLogoFile(Am_Config $before, Am_Config $after)
    {
        $this->moveFile($before, $after, 'login_logo', 'login_logo_path');
    }

    function moveBgFile(Am_Config $before, Am_Config $after)
    {
        $this->moveFile($before, $after, 'bg_img', 'bg_path');
    }

    function moveHeaderBgFile(Am_Config $before, Am_Config $after)
    {
        $this->moveFile($before, $after, 'header_bg_img', 'header_bg_path');
    }

    public function updateVersion(Am_Config $before, Am_Config $after)
    {
        $t = "themes.{$this->getId()}.version";
        $_ = $after->get($t);
        $after->set($t, ++$_);
    }

    public function updateLoginCss(Am_Config $before, Am_Config $after)
    {
        //no header
        $t_id = "themes.{$this->getId()}.login_no_header";
        $t_new = "themes.{$this->getId()}.login_header_display";

        $after->set($t_new, $after->get($t_id) ? 'none' : 'block');

        //form logo
        $t_id = "themes.{$this->getId()}.login_logo_path";
        $t_new = "themes.{$this->getId()}.login_legend_bg";

        $url = $this->getDi()->url("data/public/{$after->get($t_id)}", false);

        $after->set($t_new, $after->get($t_id) ?
            "url('{$url}') #141414 center 1em no-repeat" :
            "#141414");

        //padding
        $t_id = "themes.{$this->getId()}.login_logo";
        $t_new = "themes.{$this->getId()}.login_legend_padding_top";

        $h = null;
        if ($_ = $after->get($t_id)) {
            $upload = $this->getDi()->uploadTable->load($_);
            $i = new Am_Image($upload->getFullPath(), $upload->getType());
            $h = $i->height();
        }

        $after->set($t_new, $after->get($t_id) ?
            "calc(3em + {$h}px)" :
            '1em');

    }

    public function updateFile(Am_Config $before, Am_Config $after)
    {
        $this->config = $after->get("themes.{$this->getId()}") + $this->getDefaults();

        $this->config['img_checkmark_path'] = $this->getDi()->view->_scriptImg('checkmark.svg');

        $css = $this->parsePublicWithVars('css/theme.css');
        $filename = "{$this->getDi()->public_dir}/{$this->getId()}/theme.css";
        mkdir(dirname($filename), 0755, true);
        file_put_contents($filename, $css);
    }

    public function getDefaults()
    {
        return parent::getDefaults() + [
            'bg' => '#f1f5f9',
            'color' => '#f1f5f9',
            'link_color'=> '#3f7fb0',
            'btn_color' => '#4e80a6',
            'text_color' => '#303030',
            'logo_align' => 'left',
            'max_width' => 800,
            'logo_width' => 'auto',
            'font_size' => 14,
            'font_family' => self::F_ROBOTO,
            'version' => '',
            'login_layout' => 'layout.phtml',
            'login_bg' => 'none',
            'login_bg_color' => 'none',
            'login_no_header' => 0,
            'login_legend_padding_top' => '1em',
            'login_header_display' => 'none',
            'login_type' => '',
            'menu_color' => '#eb6653',
            'menu_dashboard' => 'icon',
            'dashboard_layout' => 'two-col',
            'identity_align' => 'left',
            'identity_type' => 'login',
            'sm_size' => '18',
            'sm_color' => '#9f9f9f',
            'sm_icons' => [
                'twitter' => 'twitter',
                'instagram' => 'instagram',
                'youtube' => 'youtube',
                'twitch' => 'twitch',
                'facebook' => 'facebook',
                'tiktok' => 'tiktok',
                'wechat' => 'weixin',
                'snapchat' => 'snapchat',
                'vk' => 'vk',
                'telegram' => 'telegram-plane',
                'whatsapp' => 'whatsapp',
                'github' => 'github',
                'bitbucket' => 'bitbucket',
                'behance' => 'behance',
            ]
        ];
    }
}

class Am_Form_Theme_Black extends Am_Form_Theme_Default
{
    public function getTemplates()
    {
        $formTheme = $this->_di->theme->getConfig('form_theme');
        $cssClass = "";
        foreach (array_filter(explode(',', $formTheme)) as $k)
            $cssClass .= "am-form-$k ";
        // override form template
        return parent::getTemplates() . <<<CUT
\n==
TemplateForClass:html_quickform2
<div class="am-form $cssClass">
    {errors}
    <form{attributes}>{content}{hidden}</form>
    <qf:reqnote><div class="reqnote">{reqnote}</div></qf:reqnote>
</div>
CUT;
    }

    public function beforeRenderElement(HTML_QuickForm2_Node &$element, & $elTpl)
    {
        switch ($element->getType())
        {
            case 'text':
            case 'password':
                $attr = $element->getAttributes();
                if (!isset($attr['placeholder']) && ($label = $this->getElementLabel($element, true)))
                {
                    $element->setAttribute('placeholder', $label);
                }
                break;
            case 'checkbox':
            case 'advcheckbox':
                if (!$element->getData('content')) {
                    $label = $this->getElementLabel($element, false);
                    if ($label) {
                        $elTpl = str_replace('{element}', $label . '&nbsp;' . '{element}', $elTpl);
                    }
                }
                break;
        }
    }

    function getElementLabel(HTML_QuickForm2_Node $element, $onlyFirst)
    {
        $label = $element->getLabel();
        if (!$label && $element->getContainer() && empty($element->getContainer()->_theme_label_used))
        { // try to find label in parent
            $label = $element->getContainer()->getLabel();
            $element->getContainer()->_theme_label_used = true;
        }
        return $label ? ($onlyFirst ? current($label) : implode(" ", $label)) : null;
    }
}