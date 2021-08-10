<?php

trait Am_Form_Brick_Conditional
{
    function addCondConfig($form)
    {
        if ($this->hasEnumFields()) {
            $gr = $form->addGroup();
            $gr->setLabel(___('Conditional Display'));
            $gr->setSeparator(' ');
            $gr->addHtml()->setHtml('<div class="conditional-group">');
            $gr->addText('cond_rules', ['style' => 'display:none']);
            $gr->addAdvCheckbox('cond_enabled');
            $gr->addSelect('cond_type')
                ->loadOptions([
                    '1' => ___('Show'),
                    '-1' => ___('Hide')
                ]);
            $l_if = ___('if');
            $gr->addHtml()
                ->setHtml("<span> $l_if </span>");
            $gr->addSelect('cond_concat')
                ->loadOptions([
                    '&&' => ___('All Match'),
                    '||' => ___('Any Match')
                ]);
            $gr->addHtml()
                ->setHtml("<div class='cond_rules_container'>");
            $gr->addHtml()
                ->setHtml('</div><a href="javascript:;" class="local add-cond" style="display:inline-block;margin-top:.4em">Add Condition</a></div>');
        }
    }

    function addConditionalIfNecessary($form, $sel, $hide_row = false)
    {
        if ($this->getConfig('cond_enabled') && ($_ = json_decode($this->getConfig('cond_rules')))) {
            $c_cond = $this->getConfig('cond_type') > 0 ? '' : '!';
            $c_type = $this->getConfig('cond_concat') ?: '&&';
            $c_initial = $c_type == '&&' ? 'true' : 'false';

            $c_fields = [];
            foreach ($_ as $cond) {
                $c_fields[] = $cond[0];
            }
            $c_fields = array_unique($c_fields);
            $selector = [];
            foreach ($c_fields as $fn) {
                if ($fn == 'product_id') {
                    $selector[] = "[name^={$fn}]";
                } else {
                    $selector[] = "[name={$fn}], [name=\"{$fn}[]\"]";
                }
            }
            $selector = implode(",\\\n", $selector);
            $conds = $this->getConfig('cond_rules');

            $toggel_code = $hide_row ?
                "jQuery('{$sel}').closest('.am-row').toggle($c_cond v);" :
                "jQuery('{$sel}').toggle($c_cond v);";

            $form->addScript()
                ->setScript(<<<CUT
jQuery(function(){
    var conds = {$conds};
    function checkConds()
    {
        var res = {$c_initial};
        for (var i in conds) {
            res = res {$c_type} checkCond(conds[i][0], conds[i][1]);
        }
        return res;
    }
    function checkCond(field, value)
    {
        var el;
        var val;
        if (field == 'product_id') {
            val = [];
            jQuery('select[name^=product_id] option:checked,' +
                '[type=radio][name^=product_id]:checked,' +
                '[type=checkbox][name^=product_id]:checked,' +
                '[type=hidden][name^=product_id]').each(function(){
                    val.push(jQuery(this).val());
                });
            return val.includes(value);
        } else {
            el = jQuery('select[name=' + field + '],' +
                '[type=radio][name=' + field + '],' +
                '[type=hidden][name=' + field + '],' +
                '[type=checkbox][name="' + field + '[]"],' +
                '[type=checkbox][name="' + field + '"]').get(0);
            switch (el.type) {
                case 'radio':
                    val = jQuery("[name='" + el.name + "']:checked").val();
                    break;
                case 'hidden':
                case 'select':
                case 'select-one':
                    val = jQuery("[name='" + el.name + "']").val();
                    break;
                case 'checkbox':
                    var el = jQuery("[name='" + el.name + "']:checked");
                    val = el.length > 1 ?
                        el.filter("[value='" + value + "']").val() :
                        el.val();
                    break;
            }
            val = val || 0;
            return value == val;
        }
    }
    var update = function(){
        var v = checkConds();
        {$toggel_code}
    }
    update();
    jQuery('$selector').change(update);
});
CUT
                );
        }
    }

    function hasEnumFields()
    {
        return true;
    }

    function _setConfigArray(& $config)
    {
        // Deal with old style Conditional
        if (!empty($config['cond_enabled']) && !isset($config['cond_rules'])) {
            $config['cond_rules'] = json_encode([[$config['cond_field'], $config['cond_field_val']]]);
            unset($config['cond_field']);
            unset($config['cond_field_val']);
        }
    }
}