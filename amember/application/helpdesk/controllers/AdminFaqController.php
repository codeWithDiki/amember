<?php

class Am_Form_Admin_HelpdeskFaq extends Am_Form_Admin
{
    function init()
    {
        $this->addCategory($this);
        $this->addText('title', ['class' => 'am-el-wide'])
            ->setLabel(___('Title'));
        $this->addHtmlEditor('content')
            ->setLabel(___('Content'));
    }

    function addCategory($form)
    {
        $gr = $form->addGroup()
            ->setLabel(___('Category'));
        $gr->setSeparator(' ');

        $sel = $gr->addSelect('faq_category_id')
            ->loadOptions(['' => '-- No Category'] + Am_Di::getInstance()->helpdeskFaqCategoryTable->getOptions());
        $id = $sel->getId();

        $gr->addHtml()->setHtml(sprintf('<div><a class="local" id="edit-%s-link" href="%s" target="_top">%s</a></div>',
            $id, Am_Di::getInstance()->url('helpdesk/admin-faq-categories'),
            ___('Edit Categories')));

        $title = ___('FAQ Categories');
        $url = json_encode(Am_Di::getInstance()->url("helpdesk/admin-faq-categories/options", false));
        $this->addScript()
            ->setScript(<<<CUT
jQuery('#edit-$id-link').click(function(){
    var div = jQuery('<div id="am-category-manage"></div>');
    jQuery('body').append(div);
    div.load(this.href, function(){
        div.dialog({
            title: '{$title}',
            modal: true,
            autoOpen : true,
            width: Math.min(700, Math.round(jQuery(window).width() * 0.7)),
            close : function(){
                jQuery('#node-form').dialog('destroy');
                jQuery('#am-category-manage').dialog('destroy');
                jQuery('#am-category-manage').remove();
                var val = jQuery('#$id').val();
                jQuery.get($url, function(options){
                    var select = jQuery('#$id').empty();
                    select.append($('<option></option>').attr('value', '').text('-- No Category'));
                    jQuery.each(options, function(i, v) {
                        select.append($('<option></option>').attr('value', v[0]).text(v[1]));
                    });
                    if (val) {
                        select.val(val);
                    }
                })
            }
        })
    })
    return false;
})
CUT
            );
    }
}

class Am_Grid_Filter_HelpdeskFaq extends Am_Grid_Filter_Abstract
{
    protected function applyFilter()
    {
        if ($this->isFiltered()) {
            $q = $this->grid->getDataSource();
            /* @var $q Am_Query */
            $q->addWhere('title LIKE ? OR category LIKE ?',
                '%' . $this->vars['filter'] . '%',
                '%' . $this->vars['filter'] . '%');
        }
    }

    public function renderInputs()
    {
        return $this->renderInputText([
            'placeholder' => ___('Filter By Title or Category')
        ]);
    }
}

class Am_Grid_Action_EditFaqCategory extends Am_Grid_Action_Abstract
{
    protected $type = self::NORECORD;
    protected $url;
    protected $attributes = [
        'target' => '_top'
    ];

    public function getUrl($record = null, $id = null)
    {
        return Am_Di::getInstance()->url('helpdesk/admin-faq-categories', false);
    }

    public function run()
    {
        //nop
    }
}

class Helpdesk_AdminFaqController extends Am_Mvc_Controller_Grid
{
    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->hasPermission(Bootstrap_Helpdesk::ADMIN_PERM_FAQ);
    }

    public function createGrid()
    {
        $ds = new Am_Query($this->getDi()->helpdeskFaqTable);
        $ds->setOrder('sort_order');
        $grid = new Am_Grid_Editable('_helpdesk_faq', ___('FAQ'), $ds, $this->_request, $this->view);

        $grid->addField('title', ___('Title'), true, '', null, '50%');
        $grid->addField(new Am_Grid_Field_Enum('faq_category_id', ___('Category')))
            ->setTranslations($this->getDi()->helpdeskFaqCategoryTable->getOptions());
        $grid->addField('_link', ___('Link'), false)->setRenderFunction([$this, 'renderLink']);
        $grid->setForm('Am_Form_Admin_HelpdeskFaq');
        $grid->setFilter(new Am_Grid_Filter_HelpdeskFaq());
        $grid->addCallback(Am_Grid_Editable::CB_VALUES_FROM_FORM, [$this, 'valuesFromForm']);
        $grid->setPermissionId(Bootstrap_Helpdesk::ADMIN_PERM_FAQ);
        $grid->actionAdd(new Am_Grid_Action_Sort_HelpdeskFaq());

        $grid->actionAdd(new Am_Grid_Action_EditFaqCategory('faq-edit-category', ___('Edit Categories')))
            ->setCssClass('link');

        return $grid;
    }

    function valuesToForm(& $ret, Am_Record $record)
    {
        if ($record->isLoaded()) {
            $ret['_categories'] = $record->getCategories();
        }
    }

    function valuesFromForm(& $ret, Am_Record $record)
    {
        $ret['faq_category_id'] = $ret['faq_category_id'] ?: null;
    }

    public function renderLink(Am_Record $record)
    {
        $url = Am_Di::getInstance()->url('helpdesk/faq/i/'.urlencode($record->title));
        return $this->renderTd(sprintf('<a class="link" href="%s" target="_blank">%s</a>',
                $url, ___('link')), false);
    }
}

class Am_Grid_Action_Sort_HelpdeskFaq extends Am_Grid_Action_Sort_Abstract
{
    protected function setSortBetween($item, $after, $before)
    {
        $this->_simpleSort(Am_Di::getInstance()->helpdeskFaqTable, $item, $after, $before);
    }
}