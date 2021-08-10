<?php

class Am_Form_Renderer extends HTML_QuickForm2_Renderer_Default
{
    const UI_USER = 'user';
    const UI_ADMIN = 'admin';
    protected $uiType = self::UI_USER;
    /** @var Am_View */
    protected $_view = null; // not always available!
    protected $_theme = null;

    /**
     * When seek for template for class at right, return template for class at left
     * That is done to decrease templates mess if templates must be the same
     * @var array
     */
    protected $templateClassAlias = [
        'html_quickform2_container_fieldset' => ['am_form_container_prefixfieldset'],
    ];

    public function __construct($uiType = self::UI_USER)
    {
        parent::__construct();
        $this->uiType = $uiType;
        $this->setOption([
            'errors_prefix' => null,
            'errors_suffix' => null,
            'required_note' => null,
        ]);
        $this->setJavascriptBuilder(new Am_Form_JavascriptBuilder);
        $this->setTemplateForClass('am_form_element_raw', '{element}');
    }

    /**
     * @return self::UI_USER | self::UI_ADMIN
     */
    public function getUiType()
    {
        return $this->uiType;
    }

    public function renderElement(HTML_QuickForm2_Node $element)
    {
        $elTpl = $this->prepareTemplate($this->findTemplate($element), $element);
        $elContent = $this->_theme->beforeRenderElement($element, $elTpl);
        if (!$elContent) $elContent = (string)$element;
        $this->html[count($this->html) - 1][] = str_replace(['{element}', '{id}'],
            [$elContent, $element->getId()], $elTpl);
    }

    public function setTemplateForClass($className, $template)
    {
        // dirty fix for HP - add am to class names prefix without am (html_qf2 moved to am ns)
        if (defined('HP_ROOT_DIR') && (stripos($className, 'html')!==false) && (strpos($className, 'am_') !== 0))
            $className = 'am_' . $className;
        if (!empty($this->templateClassAlias[$className]))
            foreach ($this->templateClassAlias[$className] as $to)
                parent::setTemplateForClass($to, $template);
        return parent::setTemplateForClass($className, $template);
    }

    /**
     * @param array $elementTemplatesForGroupClass
     */
    public function setElementTemplateForGroupClass($groupClass, $elementClass, $template)
    {
        if (defined('HP_ROOT_DIR') && (stripos($elementClass, 'html')!==null) && (strpos($elementClass, 'am_') !== 0))
            $elementClass = 'am_' . $elementClass;
        return parent::setElementTemplateForGroupClass(
            $groupClass,
            $elementClass,
            $template
        );
    }

    public function replaceVariables($tpl, HTML_QuickForm2_Node $element)
    {
        // if $element has $css class, add same css class for its container
        foreach ($this->_theme->getPassClassUp() as $elClass => $cntClass)
        {
            if ($element->hasClass($elClass)) {
                $tpl = str_replace('<qf:v=rowClass>', '<qf:v=rowClass> ' . $cntClass, $tpl);
            }
        }
        //
        static $vars;
        if (empty($vars)) { // call only once
            $vars = $this->_theme->getVariables();
        }
        // replace <qf:v=...>
        $tpl = preg_replace_callback('#<qf:include=([a-zA-Z0-9_-]+)>#', function ($match) use ($vars) {
            return array_key_exists($match[1], $this->templatesForClass) ?
                $this->templatesForClass[$match[1]] :
                '_form_include_missing_' . $match[1];
        }, $tpl);
        // replace <qf:include=...>
        // NESTED includes are not supported! and should not be by performance reasons
        $tpl = preg_replace_callback('#<qf:v=([a-zA-Z0-9_-]+)>#', function ($match) use ($vars) {
            return array_key_exists($match[1], $vars) ? $vars[$match[1]] : '_form_theme_var_missing_' . $match[1];
        }, $tpl);
        if ($element->hasClass('am-row-required')) {
            $tpl = preg_replace('/(<label[^>]*>)/', '\1<span class="required" aria-required="true">* </span>', $tpl);
        }
        return $tpl;
    }

    public function finishForm(HTML_QuickForm2_Node $form)
    {
        // a bug in QF2 - form errors are not added to array
        if ($form->getError()) {
            $this->errors[] = $form->getError();
        }
        parent::finishForm($form);
        $this->html[0][0] =
            $form->renderProlog() .
            join("\n", $this->getJavascriptBuilder()->getLibraries()) .
            $this->html[0][0] .
            $form->renderEpilog();
    }

    public function renderHidden(HTML_QuickForm2_Node $element)
    {
        if ($err = $element->getError()) {
            $this->errors[] = $err;
        }
        return parent::renderHidden($element);
    }

    public function findTemplate(HTML_QuickForm2_Node $element, $default = null)
    {
        return $this->replaceVariables(parent::findTemplate($element, $default), $element);
    }

    /**
     * If called with null, default theme will be applied
     * @param Am_Form_Theme_Abstract|null $theme
     * @return $this
     */
    public function setTheme(Am_Form_Theme_Abstract $theme = null, $form = null)
    {
        if ($theme === null)
        {
            if (defined('AM_ADMIN') && AM_ADMIN)
                $theme = Am_Di::getInstance()->formThemeAdmin;
            else
                $theme = Am_Di::getInstance()->formThemeUser;
        }
        $this->_theme = $theme;
        $theme->applyToRenderer($this);
        if ($this->_view)
            $theme->applyToView($this->_view);
        return $this;
    }

    /**
     * format multi-line labels
     */
    public function startForm(HTML_QuickForm2_Node $form)
    {
        if (empty($this->_theme)) {
            $this->setTheme(null);
        }
        foreach ($form->getRecursiveIterator() as $el) {
            $label = (array)$el->getLabel();
            if (empty($label)) continue;
            if (count($label)==1) {
                $label = explode("\n", $label[0], 2);
            }
            if (count($label) > 1) {
                $label[1] = nl2br($label[1]);
            }
            if ($url = $this->findHelpUrl($el)) {
                $help_id = Am_Html::escape($el->getData()['help-id']);
                $label[0] .= sprintf("&nbsp;<span class='admin-help' data-help_id='$help_id'><a href='%s' target='_blank'><sup>?</sup></a></span>", Am_Html::escape($url));
            }
            $el->setLabel($label);
        }
        return parent::startForm($form);
    }

    function findHelpUrl(HTML_QuickForm2_Node $el)
    {
        /// find help id
        $data = $el->getData();
        if (!empty($data['help-id']))
        {
            $url = "";
            do {
                $data = $el->getData();
                if (!empty($data['help-id']))
                    $url = $data['help-id'] . $url;
            } while ($el = $el->getContainer());
            if (substr($url, 0, 4) != 'http') {
                $url =  Am_View_Helper_Help::helpUrl($url);
            }
            return $url;
        }
    }

    function setView(Am_View $view)
    {
        $this->_view = $view;
    }
}
