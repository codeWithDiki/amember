<?php

trait Am_Mvc_Controller_User_Create
{
    /**
     * Create  user record, execute all necessary hooks and send all necessary emails;
     * @param array $vars
     * @return User $user;
     */
    function createUser(array $vars)
    {
        $user = $this->getDi()->userRecord;
        $user->setForInsert($vars); // vars are filtered by the form !

        if (empty($user->login)) {
            $user->generateLogin();
        }

        if (empty($vars['pass'])) {
            $user->generatePassword();
        } else {
            $user->setPass($vars['pass']);
        }

        if (empty($user->lang)) {
            $user->lang = $this->getDi()->locale->getLanguage();
        }

        $user->insert();

        if ($this->getDi()->config->get('registration_mail')) {
            $user->sendRegistrationEmail();
        }

        if ($this->getDi()->config->get('registration_mail_admin')) {
            $user->sendRegistrationToAdminEmail();
        }

        if (!$user->isApproved()) {
            $user->sendNotApprovedEmail();
        }

        return $user;
    }
}