<?php

abstract class Am_Form_Theme_Abstract
{
    /** @var Am_Di */
    protected $_di;
    protected $id;
    protected $_idPrefix = 'Am_Form_Theme_';
    protected $config = [];

    function __construct(Am_Di $di) { $this->_di = $di; }
    function setConfig(array $config) { $this->config = $config; return $this;}
    function getId() {
        if (null == $this->id)
            $this->id = str_ireplace($this->_idPrefix, '', get_class($this));
        return fromCamelCase($this->id, '-');
    }
    function getTitle() {
        return ucfirst($id);
    }


    /**
     * This method can modify $element or $elTpl just before standard rendering, or
     * it may return rendered HTML instead of default
     *
     * @param HTML_QuickForm2_Node $element
     * @param $elTpl
     * @return null|string
     */
    function beforeRenderElement(HTML_QuickForm2_Node & $element, & $elTpl) {}

    function applyToView(Am_View $view) {}

    /**
     * Add fields to form setup
     * @param HTML_QuickForm2_Container $form
     */
    function initSetupForm(HTML_QuickForm2_Container $form) { }

    /**
     * Return list of variables
     * In templates use tags <qf:v=varName> to get variable substituted
     * @return array
     */
    function getVariables() { return []; }

    /**
     * function must return string in format:
     *
     *  TemplateForClass:html_quickform2
     *  <div class="am-form">
     *  {errors}
     *  <form{attributes}>{content}{hidden}</form>
     *  <qf:reqnote><div class="reqnote">{reqnote}</div></qf:reqnote>
     *  </div>
     *  ==
     *  TemplateForClass:html_quickform2_container_fieldset
     *  <fieldset{attributes}>
     *  <qf:label><legend id="{id}-legend">{label}</legend></qf:label>
     *  <div class="fieldset">{content}</div>
     *  </fieldset>
     *
     * the string will be parsed and apply to renderer by @see $this->>applyToRenderer
     * @return string
     */
    abstract function getTemplates();
    /**
     * Get array from @see $this->getParsedTemplates and apply to renderer
     * @param Am_Form_Renderer $renderer
     * @access private
     */
    function applyToRenderer(Am_Form_Renderer $renderer)
    {
        foreach ($this->getParsedTemplates() as $args) {
            $m = 'set' . array_shift($args);
            call_user_func_array([$renderer, $m], $args);
        }
    }
    /**
     * Utility function to parse templates from string format to class of setTemplateForElement, etc...
     * @access private
     */
    public function getParsedTemplates()
    {
        $linenum = 0;
        $tpl = ""; $state = 0; $args = [];
        $ret = [];
        foreach (explode("\n", $this->getTemplates()) as $l)
        {
            $linenum++;
            switch ($state)
            {
                case 0:
                    if ($l = trim($l))
                    {
                        $args = explode(":", $l);
                        $state = 1;
                    }
                    break;
                case 1:
                    if (trim($l) == '==')
                    {
                        array_push($args, $tpl);
                        $ret[] = $args;
                        $tpl = ""; $state = 0; $args = [];
                    } else {
                        $tpl .= $l;
                    }
                    break;
            }
        }
        if ($state == 1)
        {
            array_push($args, $tpl);
            $ret[] = $args + [$tpl];
        }
        return $ret;
    }

    /**
     * If rowClass contains element with given $cssClass from left, an additional css
     * class from right side will be added to the rowClass
     * @return array
     */
    function getPassClassUp()
    {
        return [
            'am-no-label' => 'am-no-label',
            'am-row-wide' => 'am-row-wide',
            'am-row-highlight' => 'am-row-highlight',
            'am-row-head' => 'am-row-head',
        ];
    }


}