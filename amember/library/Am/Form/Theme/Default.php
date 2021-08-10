<?php
/**
 * Theme implements default behaviour of forms in aMember 4,5 before customizations
 */
class Am_Form_Theme_Default extends Am_Form_Theme_Abstract
{
    function getTemplates()
    {
        return <<<CUT
TemplateForClass:_label
    <qf:label>
        <div class="<qf:v=elTitleClass>">
            <label for="{id}">
            <qf:required><span class="<qf:v=requiredClass>">* </span></qf:required>
            {label}
            </label>
            <qf:label_2><div class="<qf:v=commentClass>">{label_2}</div></qf:label_2>
        </div>
    </qf:label>
==
TemplateForClass:html_quickform2_element
<div class="<qf:v=rowClass>" id="row-{id}">
    <qf:include=_label>
    <div class="<qf:v=elClass><qf:error> <qf:v=errorClass></qf:error>">
        {element}
        <qf:error><span class="<qf:v=errorClass>">{error}</span></qf:error>
    </div>
</div>
==
TemplateForClass:html_quickform2_container_group
<div class="<qf:v=rowClass>" id="row-{id}">
    <qf:include=_label>
    <div class="<qf:v=elClass> group<qf:error> <qf:v=errorClass></qf:error>">
        {content}
        <qf:error><span class="<qf:v=errorClass>">{error}</span></qf:error>
    </div>
</div>
==
TemplateForClass:html_quickform2
<div class="am-form">
    {errors}
    <form{attributes}>{content}{hidden}</form>
    <qf:reqnote><div class="reqnote">{reqnote}</div></qf:reqnote>
</div>
==
TemplateForClass:html_quickform2_container_fieldset
<fieldset{attributes}>
    <qf:label><legend id="{id}-legend"><span>{label}</span></legend></qf:label>
    <div class="fieldset">{content}</div>
</fieldset>
==
ElementTemplateForGroupClass:am_form_container_prefixfieldset:html_quickform2_element
<div class="<qf:v=rowClass>" id="row-{id}">
    <div class="<qf:v=elTitleClass>">
    <label for="{id}"><qf:required><span class="<qf:v=requiredClass>">* </span></qf:required>{label}</label>
    <qf:label_2><div class="<qf:v=commentClass>">{label_2}</div></qf:label_2>
    </div>
    <div class="<qf:v=elClass><qf:error> error</qf:error>">
    {element}
    <qf:error><span class="<qf:v=errorClass>">{error}</span></qf:error>
    </div>
</div>
CUT;
    }

    function getVariables()
    {
        $ret = [
            'requiredClass' => 'required',
            'commentClass' => 'comment',
            'errorClass' => 'am-error',
        ];
        if (defined('HP_ROOT_DIR') || defined('AM_USE_NEW_CSS'))
        {
            $ret += [
                'rowClass' => 'am-row',
                'elClass' => 'am-element',
                'elTitleClass' => 'am-element-title',
            ];
        } else {
            $ret += [
                'rowClass' => 'am-row row',
                'elClass' => 'am-element element',
                'elTitleClass' => 'am-element-title element-title',
            ];
        }
        return $ret;
    }
}

