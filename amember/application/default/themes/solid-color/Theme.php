<?php

class Am_Theme_SolidColor extends Am_Theme_Default
{
    protected $publicWithVars = ['css/theme.css'];
    protected $formThemeClassUser = 'Am_Form_Theme_SolidColor';

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
        F_JOSEFIN_SANS = 'Josefin Sans',
        F_LATO = 'Lato',
        F_JOST = 'Jost',

        SHADOW = '0px 0px 5px #00000022;';

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
            ->appendStylesheet('https://fonts.googleapis.com/css?family=Josefin+Sans:400,700')
            ->appendStylesheet('https://fonts.googleapis.com/css?family=Lato:400,700')
            ->appendStylesheet('https://fonts.googleapis.com/css?family=Jost:400,700');

        $fs = $form->addAdvFieldset(null, ['id'=>'sct-header'])
            ->setLabel('Header');

        $this->addElementLogo($fs);
        $this->addElementHeaderBg($fs);
        $this->addElementHeaderMenu($fs);

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

        $fs = $form->addAdvFieldset(null, ['id'=>'sct-layout'])
            ->setLabel('Layout');

        $this->addElementBg($fs);

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

        $gr = $fs->addGroup()
            ->setLabel(___('Layout Max Width'));
        $gr->setSeparator(' ');
        $gr->addText('max_width', ['size' => 3]);
        $gr->addHtml()->setHtml('px');

        $gr = $fs->addGroup()
            ->setLabel(___('Border Radius'));
        $gr->setSeparator(' ');
        $gr->addText('border_radius', ['size' => 3, 'placeholder' => 0]);
        $gr->addHtml()->setHtml('px');

        $fs->addAdvCheckbox('drop_shadow')
            ->setLabel(___('Drop Shadow'));

        $gr = $fs->addGroup()
            ->setLabel(___("Font\nSize and Family"));
        $gr->setSeparator(' ');

        $gr->addText('font_size', ['size' => 3]);
        $gr->addHtml()->setHtml('px');
        $gr->addSelect('font_family')
            ->loadOptions($this->getFontOptions());
        $fs->addHtml()
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

        $this->addElementColor($fs, 'page_bg_color',  "Page Background Color\n" .
            'you can use any valid <a href="http://www.w3schools.com/html/html_colors.asp" class="link" target="_blank" rel="noreferrer">HTML color</a>, you can find useful color palette <a href="http://www.w3schools.com/TAGS/ref_colornames.asp" class="link" target="_blank" rel="noreferrer">here</a>, keep it empty to make transparent');

        $this->addElementColor($fs, 'link_color', "Links Color\n" .
            'you can use any valid <a href="http://www.w3schools.com/html/html_colors.asp" class="link" target="_blank" rel="noreferrer">HTML color</a>, you can find useful color palette <a href="http://www.w3schools.com/TAGS/ref_colornames.asp" class="link" target="_blank" rel="noreferrer">here</a>');

        $this->addElementColor($fs, 'btn_color', "Button Color\n" .
            'you can use any valid <a href="http://www.w3schools.com/html/html_colors.asp" class="link" target="_blank" rel="noreferrer">HTML color</a>, you can find useful color palette <a href="http://www.w3schools.com/TAGS/ref_colornames.asp" class="link" target="_blank" rel="noreferrer">here</a>');

        $this->addElementColor($fs, 'text_color', "Text Color\n" .
            'you can use any valid <a href="http://www.w3schools.com/html/html_colors.asp" class="link" target="_blank" rel="noreferrer">HTML color</a>, you can find useful color palette <a href="http://www.w3schools.com/TAGS/ref_colornames.asp" class="link" target="_blank" rel="noreferrer">here</a>');


        $fs = $form->addAdvFieldset(null, ['id'=>'sct-footer'])
            ->setLabel('Footer');

        $fs->addHtmlEditor('footer', null, ['showInPopup' => true])
            ->setLabel(___("Footer\nthis content will be included to footer"))
            ->setMceOptions(['placeholder_items' => [
                ['Current Year', '%year%'],
                ['Site Title', '%site_title%'],
            ]])->default = '';

        $this->addElementColor($fs, 'footer_bg_color',  "Footer Background Color\n" .
            'you can use any valid <a href="http://www.w3schools.com/html/html_colors.asp" class="link" target="_blank" rel="noreferrer">HTML color</a>, you can find useful color palette <a href="http://www.w3schools.com/TAGS/ref_colornames.asp" class="link" target="_blank" rel="noreferrer">here</a>, keep it empty to make transparent');
        $this->addElementColor($fs, 'footer_text_color',  "Footer Text Color\n" .
            'you can use any valid <a href="http://www.w3schools.com/html/html_colors.asp" class="link" target="_blank" rel="noreferrer">HTML color</a>, you can find useful color palette <a href="http://www.w3schools.com/TAGS/ref_colornames.asp" class="link" target="_blank" rel="noreferrer">here</a>');
        $this->addElementColor($fs, 'footer_link_color',  "Footer Link Color\n" .
            'you can use any valid <a href="http://www.w3schools.com/html/html_colors.asp" class="link" target="_blank" rel="noreferrer">HTML color</a>, you can find useful color palette <a href="http://www.w3schools.com/TAGS/ref_colornames.asp" class="link" target="_blank" rel="noreferrer">here</a>');
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

        $fs->addHtml()
            ->setLabel(___('Social Media'))
            ->setHtml(<<<CUT
<a href="javascript:;" class="link local link-toggle"
    onclick="jQuery('#sm-settings').toggle(); jQuery(this).closest('.am-row').toggleClass('am-row-head'); jQuery(this).toggleClass('link-toggle-on');">{$l_settings}</a>
CUT
            );

        $fs->addRaw()
            ->setContent(<<<CUT
                <div id="sm-settings">
CUT
            );

        $gr = $fs->addGroup(null, ['class' => 'am-row-highlight'])
            ->setLabel(___("Glyphs Size"));
        $gr->setSeparator(' ');

        $gr->addText('sm_size', ['size' => 3]);
        $gr->addHtml()->setHtml('px');

        $this->addElementColor($fs, 'sm_color', "Glyphs Color\n" .
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
            $g = $fs->addGroup(null, ['class' => 'am-row-highlight'])
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


        $fs->addRaw()
            ->setContent('</div>');

        $fs = $form->addAdvFieldset(null, ['id'=>'sct-dashboard'])
            ->setLabel('Dashboard');

        $this->addElementColor($fs, 'menu_color', "User Menu Color\n" .
            'you can use any valid <a href="http://www.w3schools.com/html/html_colors.asp" class="link" target="_blank" rel="noreferrer">HTML color</a>, you can find useful color palette <a href="http://www.w3schools.com/TAGS/ref_colornames.asp" class="link" target="_blank" rel="noreferrer">here</a>');

        $label = ___('manage');
        $url = $this->getDi()->url('admin-menu');
        $fs->addHtml()
            ->setLabel(___("User Menu Items\nyou can edit items that appear in user menu"))
            ->setHtml(<<<CUT
<a class="link" href="{$url}" target="_blank">{$label}</a>
CUT
            );

        $gr = $fs->addGroup()
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

        $gr = $fs->addGroup()
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

        $fs->addAdvRadio('identity_type')
            ->setLabel(___("Identity Type\nwhat you want to display in identity block"))
            ->loadOptions([
                'login' => ___('Username'),
                'full_name' => ___('Full Name'),
                'email' => ___('E-Mail'),
            ]);

        $gr = $fs->addGroup()
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

        $gr = $fs->addGroup()
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

        $fs = $form->addAdvFieldset(null, ['id'=>'sct-form'])
            ->setLabel('Form');

        $fs->addSelect('form_theme')
            ->setLabel(___('Form Theme'))
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
        $fs->addAdvRadio('login_bg')
            ->setLabel(___('Login Page Background'))
            ->loadOptions([
                'none' => ___('Transparent'),
                'white' => ___('Page Background')
            ]);
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

        $this->addElementColor($fs, 'login_form_bg_color', "Login Form Background Color\n" .
            'you can use any valid <a href="http://www.w3schools.com/html/html_colors.asp" class="link" target="_blank" rel="noreferrer">HTML color</a>, you can find useful color palette <a href="http://www.w3schools.com/TAGS/ref_colornames.asp" class="link" target="_blank" rel="noreferrer">here</a>');

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
        $form->addSaveCallbacks([$this, 'moveBgFile'], null);
        $form->addSaveCallbacks([$this, 'moveLoginLogoFile'], null);
        $form->addSaveCallbacks([$this, 'updateBg'], null);
        $form->addSaveCallbacks([$this, 'moveHeaderBgFile'], null);
        $form->addSaveCallbacks([$this, 'updateHeaderBg'], null);
        $form->addSaveCallbacks([$this, 'updateFooterBg'], null);
        $form->addSaveCallbacks([$this, 'findInverseColor'], null);
        $form->addSaveCallbacks([$this, 'findDarkenColor'], null);
        $form->addSaveCallbacks([$this, 'updateVersion'], null);
        $form->addSaveCallbacks([$this, 'updateShadow'], null);
        $form->addSaveCallbacks([$this, 'normalize'], null);
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
        $f = $form;
        while($_ = $f->getContainer()) {
            $f = $_;
        }

        $f->addProlog(<<<CUT
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

    function addElementHeaderBg($form)
    {
        $f = $form;
        while($_ = $f->getContainer()) {
            $f = $_;
        }

        $f->addProlog(<<<CUT
<style type="text/css">
<!--
    #header-bg-settings {
        display:none;
        border-bottom:1px solid #d5d5d5;
    }
    #header-bg-settings div.am-row {
        border-bottom: none;
    }
-->
</style>
CUT
        );

        $l_settings = Am_Html::escape(___('Settings'));

        $form->addHtml()
            ->setLabel(___('Header Background'))
            ->setHtml(<<<CUT
<a href="javascript:;" class="link local link-toggle"
    onclick="jQuery('#header-bg-settings').toggle(); jQuery(this).closest('.am-row').toggleClass('am-row-head'); jQuery(this).toggleClass('link-toggle-on');">{$l_settings}</a>
CUT
            );

        $form->addRaw()
            ->setContent(<<<CUT
                <div id="header-bg-settings">
CUT
                );

        $this->addElementColor($form, 'header_bg_color', "Background Color\n" .
                'you can use any valid <a href="http://www.w3schools.com/html/html_colors.asp" class="link" target="_blank" rel="noreferrer">HTML color</a>, you can find useful color palette <a href="http://www.w3schools.com/TAGS/ref_colornames.asp" class="link" target="_blank" rel="noreferrer">here</a>, leave it empty if you do not want to have separate background for header', null, ['class' => 'am-row-highlight']);

        $form->addUpload('header_bg_img', ['class' => 'am-row-highlight'], ['prefix' => 'theme-default'])
                ->setLabel(___("Background Image"))->default = '';

        $form->addAdvRadio("header_bg_size", ['class' => 'am-row-highlight'])
            ->setLabel(___("Background Size"))
            ->loadOptions([
                'auto' => 'As Is',
                '100%' => '100% Width',
                'cover' => 'Cover',
                'contain' => 'Contain'
            ]);

        $form->addAdvRadio("header_bg_repeat", ['class' => 'am-row-highlight'])
            ->setLabel(___("Background Repeat"))
            ->loadOptions([
                'no-repeat' => 'No Repeat',
                'repeat' => 'Repeat',
                'repeat-x' => 'Repeat X',
                'repeat-y' => 'Repeat Y',
            ]);

        $form->addRaw()
            ->setContent('</div>');
    }

    function addElementHeaderMenu($form)
    {
        $f = $form;
        while($_ = $f->getContainer()) {
            $f = $_;
        }

        $f->addProlog(<<<CUT
<style type="text/css">
<!--
    #header-menu-settings {
        display:none;
        border-bottom:1px solid #d5d5d5;
    }
    #header-menu-settings div.am-row {
        border-bottom: none;
    }
-->
</style>
CUT
        );

        $l_settings = Am_Html::escape(___('Settings'));

        $form->addHtml()
            ->setLabel(___('Header Menu'))
            ->setHtml(<<<CUT
<a href="javascript:;" class="link local link-toggle"
    onclick="jQuery('#header-menu-settings').toggle(); jQuery(this).closest('.am-row').toggleClass('am-row-head'); jQuery(this).toggleClass('link-toggle-on');">{$l_settings}</a>
CUT
            );

        $form->addRaw()
            ->setContent(<<<CUT
                <div id="header-menu-settings">
CUT
            );

        $label = ___('manage');
        $url = $this->getDi()->url('admin-menu', ['menu_id' => '_header']);
        $form->addHtml(null, ['class' => 'am-row-highlight'])
            ->setLabel(___("Menu Items\nyou can edit items that appear in user menu"))
            ->setHtml(<<<CUT
<a class="link" href="{$url}" target="_blank">{$label}</a>
CUT
            );

        $this->addElementColor($form, 'header_menu_link_color', "Link Color\n" .
            'you can use any valid <a href="http://www.w3schools.com/html/html_colors.asp" class="link" target="_blank" rel="noreferrer">HTML color</a>, you can find useful color palette <a href="http://www.w3schools.com/TAGS/ref_colornames.asp" class="link" target="_blank" rel="noreferrer">here</a>, leave it empty if you do not want to have separate background for header', null, ['class' => 'am-row-highlight']);

        $this->addElementColor($form, 'header_menu_link2_color', "Subitems Link Color\n" .
            'you can use any valid <a href="http://www.w3schools.com/html/html_colors.asp" class="link" target="_blank" rel="noreferrer">HTML color</a>, you can find useful color palette <a href="http://www.w3schools.com/TAGS/ref_colornames.asp" class="link" target="_blank" rel="noreferrer">here</a>, leave it empty if you do not want to have separate background for header', null, ['class' => 'am-row-highlight']);
        $this->addElementColor($form, 'header_menu_bg_color', "Subitems Background Color\n" .
            'you can use any valid <a href="http://www.w3schools.com/html/html_colors.asp" class="link" target="_blank" rel="noreferrer">HTML color</a>, you can find useful color palette <a href="http://www.w3schools.com/TAGS/ref_colornames.asp" class="link" target="_blank" rel="noreferrer">here</a>, leave it empty if you do not want to have separate background for header', null, ['class' => 'am-row-highlight']);

        $form->addRaw()
            ->setContent('</div>');
    }

    function addElementBg($form)
    {
        $f = $form;
        while($_ = $f->getContainer()) {
            $f = $_;
        }

        $f->addProlog(<<<CUT
<style type="text/css">
<!--
    #bg-settings {
        display:none;
        border-bottom:1px solid #d5d5d5;
    }
    #bg-settings div.am-row {
        border-bottom: none;
    }
-->
</style>
CUT
        );

        $l_settings = Am_Html::escape(___('Settings'));

        $form->addHtml()
            ->setLabel(___('Layout Background'))
            ->setHtml(<<<CUT
<a href="javascript:;" class="link local link-toggle"
    onclick="jQuery('#bg-settings').toggle(); jQuery(this).closest('.am-row').toggleClass('am-row-head'); jQuery(this).toggleClass('link-toggle-on');">{$l_settings}</a>
CUT
            );

        $form->addRaw()
            ->setContent(<<<CUT
                <div id="bg-settings">
CUT
                );

        $this->addElementColor($form, 'color', "Background Color\n" .
                'you can use any valid <a href="http://www.w3schools.com/html/html_colors.asp" class="link" target="_blank" rel="noreferrer">HTML color</a>, you can find useful color palette <a href="http://www.w3schools.com/TAGS/ref_colornames.asp" class="link" target="_blank" rel="noreferrer">here</a>', 'theme-color', ['class' => 'am-row-highlight']);

        $form->addUpload('bg_img', ['class' => 'am-row-highlight'], ['prefix' => 'theme-default'])
                ->setLabel(___("Backgroud Image"))->default = '';

        $form->addAdvRadio("bg_size", ['class' => 'am-row-highlight'])
            ->setLabel(___("Background Size"))
            ->loadOptions([
                'auto' => 'As Is',
                '100%' => '100% Width',
                'cover' => 'Cover',
                'contain' => 'Contain'
            ]);

        $form->addAdvRadio("bg_attachment", ['class' => 'am-row-highlight'])
            ->setLabel(___("Background Attachment"))
            ->loadOptions([
                'scroll' => 'Scroll',
                'fixed' => 'Fixed',
            ]);

        $form->addAdvRadio("bg_repeat", ['class' => 'am-row-highlight'])
            ->setLabel(___("Background Repeat"))
            ->loadOptions([
                'no-repeat' => 'No Repeat',
                'repeat' => 'Repeat',
                'repeat-x' => 'Repeat X',
                'repeat-y' => 'Repeat Y',
            ]);

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
        if ($this->getConfig('font_family') == self::F_LATO) {
            $view->headLink()->appendStylesheet('https://fonts.googleapis.com/css?family=Lato:400,700');
        }
        if ($this->getConfig('font_family') == self::F_JOST) {
            $view->headLink()->appendStylesheet('https://fonts.googleapis.com/css?family=Jost:400,700');
        }
        $_ = $this->getConfig('version');
        if (file_exists("{$this->getDi()->public_dir}/{$this->getId()}/theme.css")) {
            $view->headLink()
                ->appendStylesheet($this->getDi()->url("data/public/{$this->getId()}/theme.css", strval($_), false));
        } else {
            $view->headLink()
                ->appendStylesheet($this->urlPublicWithVars("css/theme.css" . ($_ ? "?$_" : "")));
        }

        if ($this->hasParent() && file_exists($this->getFullPath('public/css/child-theme.css')))
            $view->headLink()->appendStylesheet($view->_scriptCss('child-theme.css'));

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

.am-layout-two-coll,
.am-layout-two-coll .am-layout-two-coll-top,
.am-layout-two-coll .am-layout-two-coll-bottom
{
    background: #f5f5f5;
}
CUT
            );
        }

        if (!array_filter($this->getConfig('sm', []))) {
            $view->headStyle()->appendStyle(<<<CUT
.am-footer .am-footer-content-content {
    display: block;
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
            self::F_LATO => 'Lato',
            self::F_JOST => 'Jost',
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

    public function normalize(Am_Config $before, Am_Config $after)
    {
        $t = "themes.{$this->getId()}.border_radius";
        $after->set($t, (int)$after->get($t));
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
        $t_c = "themes.{$this->getId()}.login_form_bg_color";

        $url = $this->getDi()->url("data/public/{$after->get($t_id)}", false);

        $after->set($t_new, $after->get($t_id) ?
            "url('{$url}') {$after->get($t_c)} center 1em no-repeat" :
            $after->get($t_c));

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

    public function updateShadow(Am_Config $before, Am_Config $after)
    {
        $t_id = "themes.{$this->getId()}.drop_shadow";
        $t_new = "themes.{$this->getId()}.content_shadow";

        $after->set($t_new, $after->get($t_id) ? self::SHADOW : 'none');

        $tt_id = "themes.{$this->getId()}.login_bg";
        $tt_new = "themes.{$this->getId()}.login_shadow";

        $after->set($tt_new, ($after->get($t_id) && $after->get($tt_id) == 'white') ? self::SHADOW : 'none');

        $ttt_new = "themes.{$this->getId()}.login_bg_color";
        $ttt_page_bg = "themes.{$this->getId()}.page_bg";

        $after->set($ttt_new, ($after->get($tt_id) == 'white') ? $after->get($ttt_page_bg) : 'unset');
    }

    public function updateBg(Am_Config $before, Am_Config $after)
    {
        $t_id = "themes.{$this->getId()}.bg_path";
        $t_new = "themes.{$this->getId()}.bg";
        $t_color = "themes.{$this->getId()}.color";
        $t_repeat = "themes.{$this->getId()}.bg_repeat";

        $url = $this->getDi()->url("data/public/{$after->get($t_id)}", false);

        $after->set($t_new, $after->get($t_id) ?
            "url('{$url}') {$after->get($t_color)} top center {$after->get($t_repeat)};" :
            $after->get($t_color));

        //Page Background
        $t_id = "themes.{$this->getId()}.page_bg_color";
        $t_new = "themes.{$this->getId()}.page_bg";

        $after->set($t_new, $after->get($t_id) ?: 'unset');
    }

    public function updateHeaderBg(Am_Config $before, Am_Config $after)
    {
        $t_id = "themes.{$this->getId()}.header_bg_path";
        $t_color = "themes.{$this->getId()}.header_bg_color";
        $t_theme_color = "themes.{$this->getId()}.color";

        $t_repeat = "themes.{$this->getId()}.header_bg_repeat";
        $t_new = "themes.{$this->getId()}.header_bg";

        $url = $this->getDi()->url("data/public/{$after->get($t_id)}", false);

        $after->set($t_new, $after->get($t_id) ?
            "url('{$url}') {$after->get($t_color)} top center {$after->get($t_repeat)};" :
            ($after->get($t_color) ?: 'none'));
    }

    public function updateFooterBg(Am_Config $before, Am_Config $after)
    {
        $t_color = "themes.{$this->getId()}.footer_bg_color";
        $t_new = "themes.{$this->getId()}.footer_bg";

        $after->set($t_new, $after->get($t_color) ?: 'none');
    }

    public function updateFile(Am_Config $before, Am_Config $after)
    {
        $this->config = $after->get("themes.{$this->getId()}") + $this->getDefaults();

        $css = $this->parsePublicWithVars('css/theme.css');
        $filename = "{$this->getDi()->public_dir}/{$this->getId()}/theme.css";
        mkdir(dirname($filename), 0755, true);
        file_put_contents($filename, $css);
    }

    public function findInverseColor(Am_Config $before, Am_Config $after)
    {
        $t_id = "themes.{$this->getId()}.color";
        $t_new = "themes.{$this->getId()}.color_c";
        $after->set($t_new, $this->inverse($after->get($t_id)));
    }

    public function findDarkenColor(Am_Config $before, Am_Config $after)
    {
        $t_id = "themes.{$this->getId()}.color";
        $t_new = "themes.{$this->getId()}.color_d";
        $after->set($t_new, $this->brightness($after->get($t_id), -50));
    }

    protected function inverse($color)
    {
        if ($color[0] != '#') return '#ffffff';

        $color = str_replace('#', '', $color);
        if (strlen($color) == 3) {
            $color = str_repeat(substr($color,0,1), 2).str_repeat(substr($color,1,1), 2).str_repeat(substr($color,2,1), 2);
        }
        $rgb = '';
        for ($x=0; $x<3; $x++){
            $c = 255 - hexdec(substr($color,(2*$x),2));
            $c = ($c < 0) ? 0 : dechex($c);
            $rgb .= (strlen($c) < 2) ? '0'.$c : $c;
        }
        return '#'.$rgb;
    }

    protected function brightness($color, $steps)
    {
        if ($color[0] != '#') return $color;

        $steps = max(-255, min(255, $steps));

        $color = str_replace('#', '', $color);
        if (strlen($color) == 3) {
            $color = str_repeat(substr($color,0,1), 2).str_repeat(substr($color,1,1), 2).str_repeat(substr($color,2,1), 2);
        }
        $rgb = '';
        for ($x=0; $x<3; $x++){
            $c = max(0, min(255, hexdec(substr($color,(2*$x),2)) + $steps));
            $c = dechex($c);
            $rgb .= (strlen($c) < 2) ? '0'.$c : $c;
        }
        return '#'.$rgb;
    }

    public function getDefaults()
    {
        return parent::getDefaults() + [
            'bg' => '#f1f5f9',
            'bg_size' => 'auto',
            'bg_attachment' => 'scroll',
            'bg_repeat' => 'no-repeat',
            'color' => '#f1f5f9',
            'link_color'=> '#3f7fb0',
            'btn_color' => '#4e80a6',
            'text_color' => '#303030',
            'color_c' => '#0e0a06',
            'color_d' => '#bfc3c7',
            'logo_align' => 'left',
            'logo_width' => 'auto',
            'max_width' => 800,
            'font_size' => 14,
            'font_family' => self::F_ROBOTO,
            'drop_shadow' => 1,
            'content_shadow' => self::SHADOW,
            'version' => '',
            'border_radius' => 0,
            'login_layout' => 'layout.phtml',
            'login_bg' => 'none',
            'login_bg_color' => 'none',
            'login_shadow' => 'none',
            'login_no_header' => 0,
            'login_legend_bg' => '#f9f9f9',
            'login_legend_padding_top' => '1em',
            'login_form_bg_color' => '#f9f9f9',
            'login_header_display' => 'none',
            'login_type' => '',
            'header_bg_color' => '',
            'header_bg_size' => 'cover',
            'header_bg_repeat' => 'no-repeat',
            'header_bg' => 'none',
            'menu_color' => '#eb6653',
            'menu_dashboard' => 'icon',
            'dashboard_layout' => 'two-col',
            'identity_align' => 'left',
            'identity_type' => 'login',
            'page_bg_color' => '#ffffff',
            'page_bg' => '#ffffff',
            'header_menu_link_color' => '#000000',
            'header_menu_link2_color' => '#000000',
            'header_menu_bg_color' => '#f1f5f9',
            'footer_bg' => 'none',
            'footer_bg_color' => '',
            'footer_text_color' => '#0d0d0d',
            'footer_link_color' => '#0d0d0d',
            'sm_size' => '18',
            'sm_color' => '#0d0d0d',
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

class Am_Form_Theme_SolidColor extends Am_Form_Theme_Default
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