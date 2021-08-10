<?php

/**
 * Javascript validation builder based on HTML_QF2 and Jquery.Validate
 * @package Am_Form
 */
class Am_Form_JavascriptBuilder extends HTML_QuickForm2_JavascriptBuilder
{
    protected $rules = [];
    protected $messages = [];
    protected $scripts = [];

    protected $addValidateJs = [];
    protected $isDisabled = false; // do not render anything

    public function __construct($defaultWebPath = 'js/', $defaultAbsPath = null)
    {
        $this->scripts = [];
        $this->libraries = [];
    }

    function _getCompare(HTML_QuickForm2_Rule $rule, HTML_QuickForm2_Node $el)
    {
        $config = $rule->getConfig();
        if ($config['operator'] == '===' && $config['operand'] instanceof HTML_QuickForm2_Element_InputPassword)
            return ['equalTo', '#' . $config['operand']->getId()];
        if ($config['operator'] == '>=')
            return ['min', (float)$config['operand']];
        if ($config['operator'] == '<=')
            return ['max', (float)$config['operand']];
    }

    function _getLength(HTML_QuickForm2_Rule $rule, HTML_QuickForm2_Node $el)
    {
        $config = $rule->getConfig();
        if ($config['min'] && $config['max']) {
            return ['rangelength', [$config['min'], $config['max']]];
        } elseif ($config['min']) {
            return ['minlength', $config['min']];
        } elseif ($config['max']) {
            return ['maxlength', $config['max']];
        } else {
            return [null,null];
        }
    }

    function _getNonempty(HTML_QuickForm2_Rule $rule, HTML_QuickForm2_Node $el)
    {
        return ['required', true];
    }

    function _getRequired(HTML_QuickForm2_Rule $rule, HTML_QuickForm2_Node $el)
    {
        return ['required', true];
    }

    function _getEmail(HTML_QuickForm2_Rule $rule, HTML_QuickForm2_Node $el)
    {
        return ['email', true];
    }

    function _getRegex(HTML_QuickForm2_Rule $rule, HTML_QuickForm2_Node $el)
    {
        if ($el instanceof Am_Form_Element_Date) return; // @todo fix it up!
        if (preg_match('{^(/|\|)(.+)(\\1)([giDmu]*)$}', $rule->getConfig(), $regs)) {
            $params = [$regs[2], str_replace('D', '', $regs[4])];
        } else {
            throw new Am_Exception_InternalError("Cannot parse regexp [$params] for use in " .__METHOD__);
        }
        return ['regex', $params];
    }

    function _getRemote(HTML_QuickForm2_Rule $rule, HTML_QuickForm2_Node $el)
    {
        return ['remote', $rule->getConfig()];
    }

    /**
     * @return null|array(rule_id, array|string JqueryValidateRuleDef)
     */
    function translateRule(HTML_QuickForm2_Rule $rule, HTML_QuickForm2_Node $el)
    {
        $ret = null;
        $method = '_get' . preg_replace('/^HTML_QuickForm2_Rule_/', '', get_class($rule));
        if (method_exists($this, $method)) {
            $ret = $this->$method($rule, $el);
        }
        return $ret ? $ret : [null, null];
    }

    public function addRule(HTML_QuickForm2_Rule $rule, $triggers = false)
    {
        $id = $rule->getOwner()->getName();
        if ($id == '') return;
        list($ruleType, $ruleDef) = $this->translateRule($rule, $rule->getOwner());
        if (!$ruleType) return;
        $this->rules[$id][$ruleType] = $ruleDef;
        $this->messages[$id][$ruleType] = $rule->getMessage();
    }

    public function addElementJavascript($script)
    {
        $this->scripts[] = (string)$script;
    }

    public function addValidateJs($script)
    {
        $this->addValidateJs[] = $script;
    }

    public static function encode($value)
    {
        return json_encode($value);
    }

    public function setFormId($formId)
    {
        //nop
    }

    public function getFormJavascript($formId = null, $addScriptTags = true)
    {
        if ($this->isDisabled) return null;
        if (!$this->rules && !$this->scripts && !$this->addValidateJs) return null;

        $rules = json_encode($this->rules);
        $messages = json_encode($this->messages);
        $formSelector = json_encode('form#'. $formId);

        if ($this->rules) {
            /**
             * we send special submit handler for multi page form (signup)
             * to avoide issue when clicked button do not included to request
             * (it contain info about next action for multi page action)
             * in case of valid field has remote validation and validation
             * is not finished when user click submit button.
             */
            $submitHanlder = "";
            if (preg_match('/^page.*|^cc$|^profile$/', $formId))  // special submitHandler for signup and creditcard forms
            {
                $submitHanlder = <<<CUT
                ,submitHandler: function(form, event) {
                    event.stopPropagation();
                    var ev = jQuery.Event( "amFormSubmit", {
                        originalEvent : event,
                        callback: function(){
                        form.submit();
                    }
                    });
                    // function shall replace callback to your own code
                    jQuery(form).trigger(ev);
                    if (ev.callback)
                        ev.callback();
                }
CUT;
            }
            $output = <<<CUT
<script type="text/javascript">
jQuery(document).ready(function($) {
    if (jQuery && jQuery.validator)
    {
        jQuery.validator.addMethod("regex", function(value, element, params) {
            return this.optional(element) || new RegExp(params[0],params[1]).test(value);
        }, "Invalid Value");

        jQuery($formSelector).validate({
            ignore: ':hidden'
            ,errorClass: "am-error"
            ,rules: $rules
            ,messages: $messages
            //,debug : true
            ,errorPlacement: function(error, element) {
                error.appendTo( element.parent());
            }
            $submitHanlder
            // custom validate js code start
            //-CUSTOM VALIDATE JS CODE-//
            // custom validate js code end
        });
    }
    // custom js code start
    //-CUSTOM JS CODE-//
    // custom js code end
});
</script>
CUT;
            $addValidateJs = implode(",\n", $this->addValidateJs);
            if ($addValidateJs != '') {
                $output = str_replace('//-CUSTOM VALIDATE JS CODE-//', "\n," . $addValidateJs, $output);
            }
        } else {
            $output = <<<CUT
<script type="text/javascript">
jQuery(document).ready(function($) {
    // custom js code start
    //-CUSTOM JS CODE-//
    // custom js code end
});
</script>
CUT;
        }
        $output = str_replace('//-CUSTOM JS CODE-//', implode(";\n", $this->scripts), $output);
        return $output;
    }

    /**
     * Configure the builder to do not output any JS
     */
    public function disable()
    {
        $this->isDisabled = true;
        return $this;
    }
}