<?php

/**
 * An admin UI element to handle visual bricks configuration
 *
 * @package Am_SavedForm
 */
class Am_Form_Element_BricksEditor extends HTML_QuickForm2_Element
{
    const ALL = 'all';
    const ENABLED = 'enabled';
    const DISABLED = 'disabled';

    protected $bricks = [];
    protected $value = [];
    /** @var Am_Form_Bricked */
    protected $brickedForm = null;

    public function __construct($name, $attributes, Am_Form_Bricked $form)
    {
        $attributes['class'] = 'am-no-label';
        parent::__construct($name, $attributes, null);
        $this->brickedForm = $form;
        class_exists('Am_Form_Brick', true);
        foreach ($this->brickedForm->getAvailableBricks() as $brick)
            $this->bricks[$brick->getClass()][$brick->getId()] = $brick;
    }

    public function getType()
    {
        return 'hidden'; // we will output the row HTML too
    }

    public function getRawValue()
    {
        $value = [];
        foreach ($this->value as $row)
        {
            if ($brick = $this->getBrick($row['class'], $row['id']))
                $value[] = $brick->getRecord();
        }
        return json_encode($value);
    }

    public function setValue($value)
    {
        if (is_string($value))
            $value = json_decode($value, true);
        $this->value = (array)$value;
        foreach ($this->value as & $row)
        {
            if (empty($row['id']))
                continue;
            if (isset($row['config']) && is_string($row['config']))
            {
                parse_str($row['config'], $c);
                $row['config'] = $c;
            }
            if ($brick = $this->getBrick($row['class'], $row['id']))
            {
                $brick->setFromRecord($row);
            }
        }
        // handle special case - where there is a "multiple" brick and that is enabled
        // we have to insert additional brick to "disabled", so new bricks of same
        // type can be added in editor
        $disabled = $this->getBricks(self::DISABLED);
        foreach ($this->getBricks(self::ENABLED) as $brick)
        {
            if (!$brick->isMultiple()) continue;
            $found = false;
            foreach ($disabled as $dBrick)
                if ($dBrick->getClass() == $brick->getClass()) { $found = true; break;};
            // create new disabled brick of same class
            if (!$found)
                $this->getBrick($brick->getClass(), null);
        }
    }

    /**
     * Clones element if necessary (if id passed say as "id-1" and it is not found)
     * @return Am_Form_Brick|null
     */
    public function getBrick($class, $id)
    {
        if
        (  !isset($this->bricks[$class][$id])
            && isset($this->bricks[$class])
            && current($this->bricks[$class])->isMultiple()
        )
        {
            if ($id === null)
                for ($i = 0; $i<100; $i++)
                    if (!array_key_exists($class . '-' . $i, $this->bricks[$class]))
                    {
                        $id = $class . '-' . $i;
                        break;
                    }
            $this->bricks[$class][$id] = Am_Form_Brick::createFromRecord(['class' => $class, 'id' => $id]);
        }
        return empty($this->bricks[$class][$id]) ? null : $this->bricks[$class][$id];
    }

    public function getBricks($where = self::ALL)
    {
        $enabled = [];
        foreach ($this->value as $row)
            if (!empty($row['id']))
                $enabled[ ] = $row['id'];

        $ret = [];
        foreach ($this->bricks as $class => $bricks)
            foreach ($bricks as $id => $b)
            {
                if ($where == self::ENABLED && !in_array($id, $enabled))
                    continue;
                if ($where == self::DISABLED && in_array($id, $enabled))
                    continue;
                $ret[$id] = $b;
            }
        // if we need enabled element, we need to maintain order according to value
        if ($where == self::ENABLED)
        {
            $ret0 = $ret;
            $ret = [];
            foreach ($enabled as $id)
                if (isset($ret0[$id]))
                    $ret[$id] = $ret0[$id];
        }
        return $ret;
    }

    public function __toString()
    {
        $enabled = $disabled = "";
        $enabled_child = [];
        $enabled_root = [];
        foreach ($this->getBricks(self::ENABLED) as $brick) {
            if ($_ = $brick->getContainer()) {
                if (!isset($enabled_child[$_])) {
                    $enabled_child[$_] = [];
                }
                $enabled_child[$_][] = $brick;
            } else {
                $enabled_root[] = $brick;
            }
        }
        foreach ($enabled_root as $brick)
        {
            $enabled .= $this->renderBrick($brick, true, isset($enabled_child[$brick->getId()]) ? $enabled_child[$brick->getId()] : []) . "\n";
        }

        foreach ($this->getBricks(self::DISABLED) as $brick) {
            $disabled .= $this->renderBrick($brick, false) . "\n";
        }

        $hidden = is_string($this->value) ? $this->value : json_encode($this->value);
        $hidden = Am_Html::escape($hidden);

        $name = $this->getName();
        $formBricks = ___("Form Bricks (drag to right to remove)");
        $availableBricks = ___("Available Bricks (drag to left to add)");
        $comments = nl2br(
            ___("To add fields into the form, move item from 'Available Bricks' to 'Form Bricks'.\n".
            "To remove fields, move it back to 'Available Bricks'.\n".
            "To make form multi-page, insert 'Form Page Break' item into the place where you want page to be split.")
           );

        $filter = $this->renderFilter();
        return $this->getCss() . $this->getJs() . $this->getConditionalJs() . <<<CUT
<div class="brick-editor">
    <input type="hidden" name="$name" value="$hidden">
    <div class="brick-section">
        <div class='brick-header'><h3>$formBricks</h3> $filter</div>
        <div id='bricks-enabled' class='connectedSortable'>
        $enabled
        </div>
    </div>
    <div class="brick-section brick-section-available">
        <div class='brick-header'><h3>$availableBricks</h3> $filter</div>
        <div id='bricks-disabled' class='connectedSortable'>
        $disabled
        </div>
    </div>
<div style='clear: both'></div>
</div>
<div class='brick-comment'>$comments</div>
CUT;
    }

    public function renderConfigForms()
    {
        $out = "<!-- brick config forms -->";
        foreach ($this->getBricks(self::ALL) as $brick)
        {
            if (!$brick->haveConfigForm())
                continue;
            $form = new Am_Form_Admin(null,null,true);
            $brick->initConfigForm($form);
            $form->setDataSources([new Am_Mvc_Request($brick->getConfigArray())]);
            $out .= "<div id='brick-config-{$brick->getId()}' class='brick-config' style='display:none'>\n";
            $out .= (string) $form;
            $out .= "</div>\n\n";
        }

        $form = new Am_Form_Admin;
        $form->addTextarea('_tpl', ['rows' => 2, 'class' => 'am-el-wide'])->setLabel('-label-');
        $out .= "<div id='brick-labels' style='display:none'>\n";
        $out .= (string)$form;
        $out .= "</div>\n";
        $out .= "<!-- end of brick config forms -->";

        $form = new Am_Form_Admin;
        $form->addText('_tpl', ['class' => 'am-el-wide am-no-label']);
        $out .= "<div id='brick-alias' style='display:none'>\n";
        $out .= (string)$form;
        $out .= "</div>\n";
        $out .= "<!-- end of alias forms -->";
        return $out;
    }

    public function renderBrick(Am_Form_Brick $brick, $enabled, $childs = [])
    {
        $configure = $labels = null;
        $attr = [
            'id' => $brick->getId(),
            'class' => "brick {$brick->getClass()}",
            'data-class' => $brick->getClass(),
            'data-title' => strtolower($brick->getName()),
            'data-alias' => $brick->getAlias(),
        ];
        if ($brick->haveConfigForm())
        {
            $attr['data-config'] = json_encode($brick->getConfigArray());
            $configure = "<a class='configure local' href='javascript:;' title='" .
                Am_Html::escape($brick->getName() . ' ' . ___('Configuration')) . "'>" . ___('configure') . "</a>";
        }
        if ($brick->getStdLabels())
        {
            $attr['data-labels'] = json_encode($brick->getCustomLabels());
            $attr['data-stdlabels'] = json_encode($brick->getStdLabels());
            $class = $brick->getCustomLabels() ? 'labels custom-labels' : 'labels';
            $labels = "<a class='$class local' href='javascript:;' title='" . Am_Html::escape(___('Edit Brick Labels')) . "'>" . ___('labels') . "</a>";
        }

        if ($brick->isMultiple())
            $attr['data-multiple'] = "1";

        if ($brick->hideIfLoggedInPossible() == Am_Form_Brick::HIDE_DESIRED)
            $attr['data-hide'] = $brick->hideIfLoggedIn() ? 1 : 0;

        $attrString = "";
        foreach ($attr as $k => $v)
            $attrString .= " $k=\"".htmlentities($v, ENT_QUOTES, 'UTF-8', true)."\"";

        $checkbox = $this->renderHideIfLoggedInCheckbox($brick);

        $c = '';
        foreach($childs as $c_brick) {
            $c .= $this->renderBrick($c_brick, $enabled);
        }

        $class = $brick->getAlias() ? 'brick-has-alias' : '';
        $container = $brick->getClass() == 'fieldset' ? "<div class=\"connectedSortable fieldset-fields\">{$c}</div>" : '';
        return "<div $attrString>
        <a class=\"brick-head $class\" href='javascript:;'>
            <span class='brick-title' title='" . Am_Html::escape($brick->getName()) . "'><span class='brick-title-title'>{$brick->getName()}</span></span>
            <span class='brick-alias' title='" . Am_Html::escape("{$brick->getAlias()} ({$brick->getName()})") . "'><span class='brick-alias-alias'>{$brick->getAlias()}</span> <small>{$brick->getName()}</small></span>
        </a>
        $configure
        $labels
        $checkbox
        $container
        </div>";
    }

    public function renderFilter()
    {
        $l_filter = Am_Html::escape(___('filter'));
        $l_placeholder = Am_Html::escape(___('type part of brick name to filter…'));
        return <<<CUT
<span><a href="javascript:;" class="input-brick-filter-link local closed">$l_filter</a></span>
<div class="input-brick-filter-wrapper">
    <div class="input-brick-filter-inner-wrapper">
        <input class="input-brick-filter"
               type="text"
               name="q"
               autocomplete="off"
               placeholder="$l_placeholder" />
        <div class="input-brick-filter-empty">&nbsp;</div>
    </div>
</div>
CUT;
    }

    protected function renderHideIfLoggedInCheckbox(Am_Form_Brick $brick)
    {
        if (($this->brickedForm->isHideBricks()))
        {
            if ($brick->hideIfLoggedInPossible() != Am_Form_Brick::HIDE_DONT)
            {
                static $checkbox_id = 0;
                $checkbox_id++;
                if ($brick->hideIfLoggedInPossible() == Am_Form_Brick::HIDE_ALWAYS)
                {
                    $checked = "checked='checked'";
                    $disabled = "disabled='disabled'";
                } else {
                    $disabled = "";
                    $checked = $brick->hideIfLoggedIn() ? "checked='checked'" : '';
                }
                return
                    "<span class='hide-if-logged-in'><input type='checkbox'".
                    " id='chkbox-$checkbox_id' value=1 $checked $disabled />" .
                    " <label for='chkbox-$checkbox_id'>" . ___('hide if logged-in') . "</label></span>\n";
            }
        }
    }

    public function getJs()
    {
        return <<<CUT
<script type="text/javascript">
jQuery(function(){
    jQuery('.input-brick-filter-link').click(function(){
        jQuery('.input-brick-filter-wrapper', jQuery(this).closest('.brick-section')).toggle();
        if (jQuery(this).hasClass('closed'))
            jQuery('.input-brick-filter-wrapper input', jQuery(this).closest('.brick-section')).focus();
        jQuery(this).toggleClass('opened closed')
        jQuery('.input-brick-filter', jQuery(this).closest('.brick-section')).val('').change();
    });
    jQuery(document).on('keyup change','.input-brick-filter', function(){
         var \$context = jQuery(this).closest('.brick-section');
         jQuery('.input-brick-filter-empty', \$context).toggle(jQuery(this).val().length != 0);

         if (jQuery(this).val()) {
             jQuery('.brick', \$context).hide();
             jQuery('.brick[data-title*="' + jQuery(this).val().toLowerCase() + '"], .brick[id*="' + jQuery(this).val().toLowerCase() + '"]', \$context).show();
         } else {
             jQuery('.brick', \$context).show();
         }
    })

    jQuery('.input-brick-filter-empty').click(function(){
        jQuery(this).closest('.input-brick-filter-wrapper').find('.input-brick-filter').val('').change();
        jQuery(this).hide();
    })
});
</script>
CUT;
    }

    public function getConditionalJs()
    {
        list($fields, $allOp) = $this->getEnumFieldOptions();
        if ($fields) {
            $fields = json_encode($fields);
            $allOp = json_encode($allOp);

            return <<<CUT
<script type="text/javascript">
jQuery(function(){
    var fOpt = $fields;
    var opt = $allOp;
    function addRule(c, field=false, val=false)
    {
        var sel_f = jQuery('<select name="_field[]"></select>');
        var sel_v = jQuery('<select name="_field_val[]"></select>');
        var cond = jQuery('<div style="margin-top:.4em"></div>').
            append(sel_f).
            append('<span> = </span>').
            append(sel_v).
            append(' <a href="javascript:;" class="del-cond" style="text-decoration:none;color:#ba2727">&Cross;</a>');
        c.append(cond);
        for (var k in fOpt) {

            sel_f.append(
                jQuery('<option>').text(fOpt[k]).attr('value', k)
            );
            field && sel_f.val(field);
        }
        var cOpt = opt[sel_f.val()];
        for (var k in cOpt) {
            sel_v.append(
                jQuery('<option>').text(cOpt[k]).attr('value', k)
            );
            val && sel_v.val(val);
        }
    }
    function sync(ctx)
    {
        var res = [];
        jQuery(".cond_rules_container div", ctx).each(function(){
            res.push([jQuery('[name="_field[]"]',this).val(), jQuery('[name="_field_val[]"]',this).val()]);
        });
        jQuery("[name=cond_rules]", ctx).val(JSON.stringify(res));
    }
    jQuery("[name=cond_rules]").each(function(){
        if (jQuery(this).val()) {
            var ctx = jQuery(this).closest('.conditional-group');
            val = JSON.parse(jQuery(this).val());
            for (var i in val) {
                addRule(jQuery(".cond_rules_container", ctx), val[i][0], val[i][1]);
            }
        }
    });

    jQuery(document).on('click', '.conditional-group .add-cond', function(){
        var ctx = jQuery(this).closest('.conditional-group');
        addRule(jQuery(".cond_rules_container", ctx));
        sync(ctx);
    });
    jQuery(document).on('click', '.conditional-group .del-cond', function(){
        var ctx = jQuery(this).closest('.conditional-group');
        jQuery(this).closest('div').remove();
        sync(ctx);
    });
    jQuery(document).on('change', "[name=cond_enabled]", function(){
        jQuery(this).nextAll().toggle(this.checked);
    });
    jQuery("[name=cond_enabled]").change();

    jQuery(document).on('change', '.conditional-group [name="_field[]"]', function(){
        var ctx = jQuery(this).closest('div');
        jQuery('[name="_field_val[]"]', ctx).empty();
        var cOpt = opt[jQuery('[name="_field[]"]', ctx).val()];
        for (var k in cOpt) {
            jQuery('[name="_field_val[]"]').append(
                jQuery('<option>').text(cOpt[k]).attr('value', k)
            );
        }
    });
    jQuery(document).on('change', '.conditional-group .cond_rules_container select', function(){
        sync(jQuery(this).closest('.conditional-group'));
    });
});
</script>
CUT;
        }
    }

    public function getCss()
    {
        $declined = Am_Di::getInstance()->view->_scriptImg('icons/decline-d.png');
        $decline = Am_Di::getInstance()->view->_scriptImg('icons/decline.png');
        $magnify = Am_Di::getInstance()->view->_scriptImg('icons/magnify.png');
        return <<<CUT
<style type="text/css">
.brick {
    border: solid 1px #e7e7e7;
    margin: 4px;
    padding: 0.4em;
    background: #f1f1f1;
    cursor: move;
    -webkit-border-radius: 2px;
    -moz-border-radius: 2px;
    border-radius: 2px;
    box-sizing: content-box;
    overflow: hidden;
}

.brick::before {
    content: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAoAAAAQCAYAAAAvf+5AAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH5AwJDgYokIhzyQAAACRJREFUKM9jYCAFHDhw4P+BAwf+4+IzMDAwMDEMLBh141B2IwCPCTP5nTc2QQAAAABJRU5ErkJggg==);
    float: left;
    margin-right: .5em;
}

.brick:hover {
    border-color: #777;
}

a.brick-head {
    font-weight: normal;
    color: black;
    text-decoration: none;
}
a.brick-head small {
    opacity: .5;
    text-transform: lowercase;
}


.page-separator {
    background: #FFFFCF;
}

.invoice-summary {
    background: #afccaf;
}

.product {
    background: #d3dce3;
}

.paysystem {
    background: #ffd963;
}

.brick.fieldset {
    background:#c5cae9;
}

.brick.credit-card-token {
    background: #feb0a6;
}

.manual-access,
.user-group {
    opacity: .5;
}

.brick-section {
    width: 40%;
    padding: 10px;
    float: left;
}

.brick-section.brick-section-available {
    width: 55%;
    position: sticky;
    top: 0;
}

.brick-comment {
    padding: 10px;
}

.hide-if-logged-in {
    margin-left: 20px;
    float: right;
    font-size: .8rem;
}

#bricks-enabled .page-separator {
    margin-bottom: 20px;
}

#bricks-enabled {
    min-height: 200px;
    padding-bottom:4em;
    border: 2px dashed #ddd;
}

#bricks-enabled .brick-head {
    max-width: 50%;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    float: left;
}

.brick-alias {
    display: none;
}

#bricks-enabled .brick-has-alias .brick-title {
    display: none;
}

#bricks-enabled .brick-has-alias .brick-alias {
    display: inline;
}

#bricks-enabled .brick::after {
    content: ' ';
    display: inline-block;
    clear: both;
}

#bricks-enabled .brick-alias-alias,
#bricks-enabled .brick-title-title {
    text-decoration: underline;
    text-decoration-style: dashed;
    text-decoration-color: #00000055;
}

#bricks-disabled {
    overflow: hidden;
    min-height: 50px;
}

#bricks-disabled a.configure,
#bricks-disabled a.labels,
#bricks-disabled .hide-if-logged-in,
#bricks-disabled .fieldset-fields {
    display: none;
}

#bricks-disabled .brick-head {
    cursor: move;
}

#bricks-disabled .brick {
    float: left;
    margin: 2px;
    width: 45%;
    overflow: hidden;
    white-space: nowrap
}

.fieldset-fields {
    padding-bottom: 1.5em;
    margin-top: 1em;
    border:1px dashed #82acb2;
}

a.configure,
a.labels {
    margin-left: 0.2em;
    cursor: pointer;
    color: #34536E;
}

a.labels.custom-labels {
    color: #360;
}

/* Filter */

.brick-header {
    margin-bottom:0.8em;
}
.brick-header h3 {
    display: inline;
}

.input-brick-filter-wrapper {
    overflow: hidden;
    padding: 0.4em;
    border: 1px solid #C2C2C2;
    margin-bottom: 1em;
    display: none;
}

.input-brick-filter-inner-wrapper {
    position: relative;
    padding-right:15px;
}

.input-brick-filter-empty {
    position: absolute;
    top:0;
    right:0;
    width: 20px;
    cursor: pointer;
    opacity: .3;
    background: url("{$declined}") no-repeat center center transparent;
}

.input-brick-filter-empty:hover {
   opacity: 1;
   background-image: url("{$decline}");
}

input[type=text].input-brick-filter {
    padding:0;
    margin:0;
    border: none;
    width:100%;
    padding-left: 24px;
    background: url("{$magnify}") no-repeat left center;
}
input[type=text].input-brick-filter:focus {
    border: none;
    box-shadow: none;
}
input[type=text].input-brick-filter:focus {
    border: none;
    outline: 0;
    background-color: unset;
}
#bricks-enabled .brick-editor-placeholder {
    border: 1px dashed #d3dce3;
    margin: 4px;
    height: 25px;
}
#bricks-disabled .brick-editor-placeholder {
    border: 1px dashed #d3dce3;
    margin: 2px;
    height: 25px;
    width: 45%;
    float: left;
}
</style>
CUT;
    }

    function getEnumFieldOptions()
    {
        $fields = [
            'country' => 'Country',
            'paysys_id' => 'Payment System',
            'product_id' => 'Product'
        ];
        $options = [
            'country' => Am_Di::getInstance()->countryTable->getOptions(),
            'paysys_id' => Am_Di::getInstance()->paysystemList->getOptionsPublic(),
            'product_id' => Am_Di::getInstance()->billingPlanTable->getProductPlanOptions(),
        ];
        foreach (Am_Di::getInstance()->userTable->customFields()->getAll() as $fd) {
            if (in_array($fd->type, ['radio', 'select', 'checkbox', 'single_checkbox'])) {
               $fields[$fd->name] = $fd->title;
               $options[$fd->name] = $fd->type == 'single_checkbox' ?
                   [1 => ___('Checked'), 0 => ___('Unchecked')] :
                   $fd->options;
            }
        }
        return [$fields, $options];
    }
}