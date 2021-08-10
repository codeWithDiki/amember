<?php

class Am_Form_Admin_HelpdeskCategory extends Am_Form_Admin
{
    function init()
    {
        $this->addText('title', ['size' => 40])
            ->setLabel(___('Title'));

        $options = [];
        foreach (Am_Di::getInstance()->adminTable->findBy() as $admin) {
            $options[$admin->pk()] = sprintf('%s (%s %s)', $admin->login, $admin->name_f, $admin->name_l);
        }

        $this->addSelect('owner_id')
            ->setLabel(___("Owner\n".
                'set the following admin as owner of ticket'))
            ->loadOptions(['' => ''] + $options);

        $this->addMagicSelect('watcher_ids')
            ->setLabel(___("Watchers\n" .
                'notify the following admins ' .
                'about new messages in this category'))
            ->loadOptions($options);

        $options = [];
        foreach(Am_Di::getInstance()->helpdeskTicketTable->customFields()->getAll()
            as $f) {
            $options[$f->getName()] = $f->title;
        }

        $url = Am_Di::getInstance()->url('helpdesk/admin-fields');
        $this->addSortableMagicSelect('fields')
            ->setLabel(___("Fields\n" .
                "You can add new fields %shere%s",
                '<a href="' . $url . '" class="link" target="_top">', '</a>'))
            ->loadOptions($options);
        $this->addElement(new Am_Form_Element_ResourceAccess)
            ->setName('_access')
            ->setLabel(___("Access Permissions\n" .
                    'this category will be available only for users ' .
                    'with proper access permission'))
            ->setAttribute('without_free_without_login', 'true');
    }
}

class Am_Grid_Action_Sort_HelpdeskCategory extends Am_Grid_Action_Sort_Abstract
{
    protected function setSortBetween($item, $after, $before)
    {
        $this->_simpleSort(Am_Di::getInstance()->helpdeskCategoryTable, $item, $after, $before);
    }
}

class Helpdesk_AdminCategoryController extends Am_Mvc_Controller_Grid
{
    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->hasPermission(Bootstrap_Helpdesk::ADMIN_PERM_CATEGORY);
    }

    public function createGrid()
    {
        $ds = new Am_Query($this->getDi()->helpdeskCategoryTable);
        $ds->leftJoin('?_admin', 'a', 't.owner_id=a.admin_id')
            ->addField("CONCAT(a.login, ' (',a.name_f, ' ', a.name_l, ')')", 'owner');
        $ds->setOrder('sort_order');

        $grid = new Am_Grid_Editable('_helpdesk_category', ___("Ticket Categories"), $ds, $this->_request, $this->view);

        $grid->addField('title', ___('Title'));
        $grid->addField('fields', ___('Fields'))
            ->setGetFunction(function($r, $grid, $fieldname, $field){
                return implode(', ', $r->unserializeList($r->{$fieldname}));
            });
        $grid->addField('owner_id', ___('Owner'), true, '', [$this, 'renderOwner']);
        $grid->addField(new Am_Grid_Field_IsDisabled);
        $grid->setForm('Am_Form_Admin_HelpdeskCategory');
        $grid->addCallback(Am_Grid_Editable::CB_BEFORE_SAVE, [$this, 'beforeSave']);
        $grid->setPermissionId(Bootstrap_Helpdesk::ADMIN_PERM_CATEGORY);
        $grid->actionAdd(new Am_Grid_Action_Sort_HelpdeskCategory());
        $grid->setFormValueCallback('watcher_ids', ['RECORD', 'unserializeIds'], ['RECORD', 'serializeIds']);
        $grid->setFormValueCallback('fields', ['RECORD', 'unserializeList'], ['RECORD', 'serializeList']);

        $grid->addCallback(Am_Grid_Editable::CB_AFTER_DELETE, [$this, 'afterDelete']);
        $grid->addCallback(Am_Grid_Editable::CB_AFTER_SAVE, [$this, 'afterSave']);
        $grid->addCallback(Am_Grid_Editable::CB_VALUES_TO_FORM, [$this, 'valuesToForm']);

        return $grid;
    }

    public function renderOwner($record)
    {
        return $record->owner_id ?
            sprintf('<td>%s</td>', Am_Html::escape($record->owner)) :
            '<td></td>';
    }

    public function beforeSave(& $values, $record)
    {
        $values['owner_id'] = $values['owner_id'] ? $values['owner_id'] : null;
    }

    public function valuesToForm(& $ret, $record)
    {
        $ret['_access'] = $record->isLoaded() ?
            $this->getDi()->resourceAccessTable->getAccessList($record->pk(), HelpdeskCategory::ACCESS_TYPE) :
            [
                ResourceAccess::FN_FREE => [
                    json_encode([
                        'start' => null,
                        'stop' => null,
                        'text' => ___('Free Access')
                    ])
                ]
            ];
    }

    public function afterSave(array & $values, $record)
    {
        $this->getDi()->resourceAccessTable->setAccess($record->pk(), HelpdeskCategory::ACCESS_TYPE, $values['_access']);
    }

    public function afterDelete($record)
    {
        $this->getDi()->resourceAccessTable->clearAccess($record->pk(), HelpdeskCategory::ACCESS_TYPE);
    }
}