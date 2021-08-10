<?php

/*
 *
 *
 *     Author: Alex Scott
 *      Email: alex@cgi-central.net
 *        Web: http://www.cgi-central.net
 *    Details: New fields
 *    FileName $RCSfile$
 *    Release: 6.3.6 ($Revision$)
 *
 * Please direct bug reports,suggestions or feedback to the cgi-central forums.
 * http://www.cgi-central.net/forum/
 *
 * aMember PRO is a commercial software. Any distribution is strictly prohibited.
 *
 */

class Am_Form_Admin_CustomField_User extends Am_Form_Admin_CustomField
{
    function init()
    {
        parent::init();
        $this->addElement(new Am_Form_Element_ResourceAccess)->setName('_access')
            ->setLabel(___("Access Permissions\n" .
                    'this field will be removed from form if access permission ' .
                    'does not match and user will not be able to update this field'));
    }
}

class AdminFieldsController extends CustomFieldController
{
    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->hasPermission(Am_Auth_Admin::PERM_ADD_USER_FIELD);
    }

    protected function getTable()
    {
        return $this->getDi()->userTable;
    }

    public function createGrid()
    {
        $grid = parent::createGrid();
        $grid->setEventId('gridUserField');
        $grid->addCallback(Am_Grid_Editable::CB_AFTER_DELETE, [$this, 'afterDelete']);
        $grid->addCallback(Am_Grid_Editable::CB_AFTER_SAVE, [$this, 'afterSave']);
        $grid->setPermissionId(Am_Auth_Admin::PERM_ADD_USER_FIELD);
        return $grid;
    }

    public function createForm()
    {
        $form = new Am_Form_Admin_CustomField_User($this->grid->getRecord());
        $form->setTable($this->getTable());
        return $form;
    }

    public function valuesToForm(& $ret, $record)
    {
        parent::valuesToForm($ret, $record);

        $ret['_access'] = $record->name ?
            $this->getDi()->resourceAccessTable->getAccessList(amstrtoint($record->name), Am_CustomField::ACCESS_TYPE) :
            [
            ResourceAccess::FN_FREE_WITHOUT_LOGIN => [
                json_encode([
                    'start' => null,
                    'stop' => null,
                    'text' => ___('Free Access without log-in')
                ])
            ]
            ];
    }

    public function afterSave(array & $values, $record)
    {
        $record->name = $record->name ? $record->name : $values['name'];
        $this->getDi()->resourceAccessTable->setAccess(amstrtoint($record->name), Am_CustomField::ACCESS_TYPE, $values['_access']);
    }

    public function afterDelete($record)
    {
        $this->getDi()->resourceAccessTable->clearAccess(amstrtoint($record->name), Am_CustomField::ACCESS_TYPE);

        foreach ($this->getDi()->savedFormTable->findBy() as $savedForm) {
            if ($row = $savedForm->findBrickById('field-' . $record->name)) {
                $savedForm->removeBrickConfig($row['class'], $row['id']);
                $savedForm->update();
            }
        }
    }
}