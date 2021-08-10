<?php

/**
 * Admin Form - adds the following:
 * - addSaveButton() method
 * - initRenderer() - special init for default renderer
 *
 * @package Am_Form
 */
class Am_Form_Admin extends Am_Form
{
    protected $epilog = null;

    /** @var HTML_QuickForm2_Renderer_Default */
    protected $renderer;

    public function __construct($id = null, $attributes = null, $skipCsrf = false)
    {
        parent::__construct($id, $attributes);
        if(!$skipCsrf)
            $this->addCsrf();

        // fetch help-id from docblock
        // * @help-id "PluginDocs/Modules/Subusers"
        $rc = new ReflectionClass($this);
        $docBlock = $rc->getDocComment();
        if (preg_match('#^[\/*\s]+@help-id\s+(.+?)(\*\/\s*)?$#m', $docBlock, $regs))
        {
            $this->data['help-id'] = trim($regs[1], " \t\"'");
        }
    }

    public function render(HTML_QuickForm2_Renderer $renderer)
    {
        if (method_exists($renderer->getJavascriptBuilder(), 'addValidateJs'))
            $renderer->getJavascriptBuilder()->addValidateJs('errorElement: "span"');
        return parent::render($renderer);
    }

    public function __toString()
    {
        try {
            $t = new Am_View;
            $t->form = $this;
            return $t->render('admin/_form.phtml');
        } catch (Exception $e) {
            user_error('Exception caught: ' . get_class($e) . ':' . $e->getMessage(), E_USER_ERROR);
        }
    }

    public function renderEpilog()
    {
        return $this->epilog;
    }

    public function addEpilog($code)
    {
        $this->epilog .= $code;
    }
}