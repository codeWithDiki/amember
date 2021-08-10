<?php

class Am_Form_Admin_ChangePassForm extends Am_Form_Admin
{
    function init()
    {
        $self_password = $this->addPassword('self_password')
            ->setLabel(___("Your Password\n".
                "enter your current password ".
                "in order to edit admin record"));

        $self_password->addRule('required');
        $self_password->addRule('callback', ___('Wrong password'), [$this, 'checkCurrentPassword']);

        $pass = $this->addPassword('pass')
                ->setLabel(___('New Password'));
        $pass->addRule('minlength', ___('The admin password should be at least %d characters long', 6), 6);
        if ($this->getDi()->config->get('admin_require_strong_password')) {
            $pass->addRule('regex', ___('Password should contain at least 2 capital letters, 2 or more numbers and 2 or more special chars'),
                $this->getDi()->userTable->getStrongPasswordRegex());
        }
        $pass->addRule('neq', ___('Password must not be equal to username'), Am_Di::getInstance()->authAdmin->getUser()->login);
        $pass0 = $this->addPassword('_passwd0')
            ->setLabel(___('Confirm New Password'));
        $pass0->addRule('eq', ___('Passwords must be the same'), $pass);
        parent::init();
        $this->addSaveButton();
    }

    function checkCurrentPassword($pass)
    {
        return $this->getDi()->authAdmin->getUser()->checkPassword($pass);
    }

    function getDi()
    {
        return Am_Di::getInstance();
    }
}

class AdminChangePassController extends Am_Mvc_Controller
{
    public function checkAdminPermissions(Admin $admin)
    {
        return true;
    }

    function indexAction()
    {
        $this->view->title = ___('Change Password');

        $form = new Am_Form_Admin_ChangePassForm();

        if ($form->isSubmitted() && $form->validate()) {
            $vars = $form->getValue();
            $admin = $this->getDi()->authAdmin->getUser();
            $admin->setPass($vars['pass']);
            $admin->save();
            $this->getDi()->authAdmin->setUser($admin);
            return $this->_response->redirectLocation($this->getDi()->url('admin', false));
        }

        $this->view->form = $form;
        $this->view->display('admin/form.phtml');
    }
}