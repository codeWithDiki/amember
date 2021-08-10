<?php
/*
*
*
*     Author: Alex Scott
*      Email: alex@cgi-central.net
*        Web: http://www.cgi-central.net
*    Details: Admin accounts
*    FileName $RCSfile$
*    Release: 6.3.6 ($Revision$)
*
* Please direct bug reports,suggestions or feedback to the cgi-central forums.
* http://www.cgi-central.net/forum/
*
* aMember PRO is a commercial software. Any distribution is strictly prohibited.
*
*/

class AdminAdminsController extends Am_Mvc_Controller_Grid
{
    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->isSuper();
    }

    public function createGrid()
    {
        $ds = new Am_Query($this->getDi()->adminTable);
        $grid = new Am_Grid_Editable('_admin', ___('Admin Accounts'), $ds, $this->_request, $this->view);
        $grid->setRecordTitle([$this, 'getRecordTitle']);
        $grid->setEventId('gridAdmin');
        $grid->addField('admin_id', '#', true, '', null, '1%');
        $grid->addField('login', ___('Username'));
        $grid->addField('email', ___('E-Mail'));
        $grid->addField(new Am_Grid_Field_IsDisabled())
            ->addDecorator(new Am_Grid_Field_Decorator_EmptyIf($this->getDi()->authAdmin->getUserId()), Am_Grid_Field::DEC_LAST);
        $grid->addField('super_user', ___('Super Admin'))
            ->setRenderFunction(function($rec) use($grid){
                return $grid->renderTd($rec->super_user?___('Yes') : ___('No'));
            });
        $grid->addField('last_login', ___('Last login'))
            ->setRenderFunction([$this, 'renderLoginAt']);
        $grid->setForm([$this, 'createForm']);
        $grid->addCallback(Am_Grid_Editable::CB_VALUES_TO_FORM, [$this, 'valuesToForm']);
        $grid->addCallback(Am_Grid_Editable::CB_VALUES_FROM_FORM, [$this, 'valuesFromForm']);
        $grid->addCallback(Am_Grid_Editable::CB_BEFORE_SAVE, [$this, 'beforeSave']);
        $grid->addCallback(Am_Grid_Editable::CB_BEFORE_DELETE, [$this, 'beforeDelete']);
        return $grid;
    }

    public function getRecordTitle(Admin $user = null)
    {
        return $user ? sprintf('%s (%s)', ___('Admin'), $user->login) : ___('Admin');
    }

    public function checkSelfPassword($pass)
    {
        return $this->getDi()->authAdmin->getUser()->checkPassword($pass);
    }

    public function createForm()
    {
        $record = $this->grid->getRecord();
        $mainForm = new Am_Form_Admin('admin-' . ($record->isLoaded() ? $record->pk() : 'new'));

        $form = $mainForm->addFieldset()->setLabel(___('Admin Settings'));
        if (!defined('HP_ROOT_DIR'))
        {
            $login = $form->addText('login')
                ->setLabel(___('Admin Username'));

            $login->addRule('required')
                ->addRule('length', ___('Length of username must be from %d to %d', 4, 32), [4,32])
                ->addRule('regex', ___('Admin username must be alphanumeric in small caps'), '/^[a-z][a-z0-9_-]+$/')
                ->addRule('callback2', '-error-', [$this, 'checkUniqLogin']);

            $set = $form->addGroup()->setLabel(___('First and Last Name'));
            $set->setSeparator(' ');
            $set->addText('name_f');
            $set->addText('name_l');

            $pass = $form->addPassword('_passwd')
                ->setLabel(___('New Password'));
            $pass->addRule('minlength', ___('The admin password should be at least %d characters long', 6), 6);
            if ($this->getDi()->config->get('admin_require_strong_password')) {
                $pass->addRule('regex', ___('Password should contain at least 2 capital letters, 2 or more numbers and 2 or more special chars'),
                    $this->getDi()->userTable->getStrongPasswordRegex());
            }
            $pass->addRule('neq', ___('Password must not be equal to username'), $login);
            $pass0 = $form->addPassword('_passwd0')
                ->setLabel(___('Confirm New Password'));
            $pass0->addRule('eq', ___('Passwords must be the same'), $pass);
            if(!$record->pk())
            {
                $pass->addRule('required');
                $pass0->addRule('required');
            }
            $form->addText('email')
                ->setLabel(___('E-Mail Address'))
                ->addRule('required')
                ->addRule('callback2', '-error-', [$this, 'checkUniqEmail']);;
            $super = $form->addAdvCheckbox('super_user')
                ->setId('super-user')
                ->setLabel(___('Super Admin'));
        }

        //Only Super Admin has access to this page
        if ($this->getDi()->authAdmin->getUserId() == $record->get('admin_id')) {
            $super->toggleFrozen(true);
        }

        if ($this->getDi()->authAdmin->getUserId() != $record->get('admin_id')) {
            $group = $form->addGroup('perms')
                ->setId('perms')
                ->setLabel(___('Permissions'));
            $group->addHtml()
                ->setHtml(<<<CUT
<div style="float:right"><label for="perm-check-all"><input type="checkbox" id="perm-check-all" onchange="jQuery('[name^=perm]').prop('checked', this.checked).change();" /> Check All</lable></div>
<script type="text/javascript">
    jQuery(function(){
        jQuery('[name^=perm]').change(function(){
            jQuery(this).closest('label').css({opacity: this.checked ? 1 : 0.8})
        }).change();
    });
</script>
CUT
                );
            foreach ($this->getDi()->authAdmin->getPermissionsList() as $perm => $title)
            {
                if (is_string($title)) {
                    $group->addCheckbox($perm)->setContent($title);
                    $group->addStatic()->setContent('<br />');
                } else {
                    $gr = $group->addGroup($perm);
                    $gr->addStatic()->setContent('<div>');
                    $gr->addStatic()->setContent('<strong>' . $title['__label'] . '</strong>');
                    $gr->addStatic()->setContent('<div style="padding-left:1em">');
                    unset($title['__label']);
                    foreach ($title as $k => $v) {
                        $gr->addCheckbox($k)->setContent($v);
                        $gr->addStatic()->setContent(' ');
                    }
                    $gr->addStatic()->setContent('</div></div><br />');
                }
            }

            $mainForm->addScript()
                ->setScript(<<<CUT
jQuery('#super-user').change(function(){
    jQuery('#row-perms').toggle(!this.checked);
}).change();
CUT
            );
        }

        $self_password = $mainForm->addFieldset(null, ['id' => 'auth-confirm'])
                ->setLabel(___('Authentication'))
                ->addPassword('self_password')
                ->setLabel(___("Your Password\n".
                    "enter your current password ".
                    "in order to edit admin record"));
        $self_password->addRule('callback', ___('Wrong password'), [$this, 'checkSelfPassword']);
        return $mainForm;
    }

    function checkUniqLogin($login)
    {
        $r = $this->grid->getRecord();

        if(!$r->isLoaded() || (strcasecmp($r->login, $login)!==0)) {
            if ($this->getDi()->adminTable->findFirstByLogin($login)) {
                return ___('Username %s is already taken. Please choose another username', Am_Html::escape($login));
            }
        }
    }

    function checkUniqEmail($email)
    {
        $r = $this->grid->getRecord();

        if(!$r->isLoaded() || (strcasecmp($r->email, $email)!==0)) {
            if ($this->getDi()->adminTable->findFirstByEmail($email)) {
                return ___('An Admin Account with the same email already exists.');
            }
        }
    }

    function renderLoginAt(Admin $a)
    {
        return $this->renderTd($a->last_login ? $a->last_ip . ___(' at ') . amDatetime($a->last_login) : null);
    }

    function valuesToForm(& $values, Admin $record)
    {
        $values['perms'] = $record->getPermissions();
    }

    function valuesFromForm(& $values, Admin $record)
    {
        $values['perms'] || ($values['perms'] = []);
        $record->setPermissions($values['perms']);
        unset($values['perms']);
    }

    public function beforeSave(array & $values, $record)
    {
        check_demo();
        unset($values['self_password']);
        if (!$values['super_user']) { $values['super_user'] = 0; }
        if (!empty($values['_passwd'])) {
            $record->setPass($values['_passwd']);
        }
    }

    public function beforeDelete($record)
    {
        if ($this->getDi()->authAdmin->getUserId() == $record->admin_id) {
            throw new Am_Exception_InputError(___('You can not delete your own account'));
        }
    }
}

class Am_Grid_Field_Decorator_EmptyIf extends Am_Grid_Field_Decorator_Abstract
{
    protected $id;

    public function __construct($id)
    {
        $this->id = $id;
        parent::__construct();
    }

    public function render(&$out, $obj, $controller)
    {
        if ($obj->pk() == $this->id) {
            $out = preg_replace('|(<td.*?>)(.+)(</td>)|i', '\1\3', $out);
        }
    }
}