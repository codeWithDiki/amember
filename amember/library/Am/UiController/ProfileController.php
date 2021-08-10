<?php

class ProfileController extends Am_Mvc_Controller
{
    use Am_Mvc_Controller_User_Update;

    /** @var int */
    protected $user_id;
    /** @var User */
    protected $user;

    function init()
    {
        if (!class_exists('Am_Form_Brick', false)) {
            class_exists('Am_Form_Brick', true);
            Am_Di::getInstance()->hook->call(Am_Event::LOAD_BRICKS);
        }
        parent::init();
    }

    function preDispatch()
    {
        if ($this->getRequest()->getActionName() != 'confirm-email') {
            $c = $this->getFiltered('c');
            $url = $c ? "profile/$c" : "profile";

            $this->getDi()->auth->requireLogin($this->getDi()->url($url, false));
            $this->user = $this->getDi()->userTable->load($this->getDi()->auth->getUserId());
            $this->view->assign('user', $this->user->toArray());
            $this->user_id = $this->user->user_id;
        }
    }

    function indexAction()
    {
        $this->form = new Am_Form_Profile();
        $this->form->addCsrf();

        if ($c = $this->getFiltered('c')) {
            $record = $this->getDi()->savedFormTable->findFirstBy([
                    'code' => $c,
                    'type' => SavedForm::T_PROFILE,
            ]);
        } else {
            $record = $this->getDi()->savedFormTable->getDefault(SavedForm::D_PROFILE);
        }

        $record = $this->getDi()->hook->filter($record, Am_Event::LOAD_PROFILE_FORM, [
            'request' => $this->getRequest(),
            'user' => $this->getDi()->auth->getUser(),
        ]);

        if (!$record)
            throw new Am_Exception_Configuration("No profile form configured");

        if ($record->meta_title)
            $this->view->meta_title = $record->meta_title;
        if ($record->meta_keywords)
            $this->view->headMeta()->setName('keywords', $record->meta_keywords);
        if ($record->meta_description)
            $this->view->headMeta()->setName('description', $record->meta_description);
        if ($record->meta_robots)
            $this->view->headMeta()->setName('robots', $record->meta_robots);
        $this->view->code = $record->code;
        $this->view->record = $record;

        $this->form->initFromSavedForm($record);
        $this->form->setUser($this->user);

        $u = $this->user->toArray();
        unset($u['pass']);

        $dataSources = [
            new HTML_QuickForm2_DataSource_Array($u)
        ];

        if ($this->form->isSubmitted()) {
            array_unshift($dataSources, $this->_request);
        }

        $this->form->setDataSources($dataSources);

        if ($this->form->isSubmitted() && $this->form->validate())
        {
            $vars = $this->form->getValue();

            $u = $this->updateUser($this->user, $vars, $this->form);

            $this->getDi()->auth->setUser($u);
            $this->getDi()->hook->call(new Am_Event_AuthSessionRefresh($u));

            $msg = $this->isVerifyEmailState($u) ? ___('Verification email has been sent to your address.
                    E-mail will be changed in your account after confirmation') :
                    ___('Your profile has been updated successfully');
            return $this->_response->redirectLocation($this->_request->assembleUrl(false,true) . '?_msg='.  urlencode($msg));
        }

        $this->view->title = ___($record->title);
        $this->view->form = $this->form;
        $this->view->display('member/profile.phtml');
    }

    public function confirmEmailAction()
    {
        $di = $this->getDi();
        /* @var $user User */
        $em = $this->getRequest()->getParam('em');
        list($user_id, $code) = explode('-', $em);
        if (!$user_id = $this->getDi()->security->reveal($user_id)) {
            throw new Am_Exception_QuietError(___('Link is either expired or invalid'));
        }

        $data = $this->getDi()->store->getBlob('member-verify-email-profile-' . $user_id);
        if (!$data) {
            throw new Am_Exception_QuietError(___('Security code is invalid'));
        }

        $data = unserialize($data);
        $user = $this->getDi()->userTable->load($user_id);

        if ($user && //user exist
            $data['security_code'] && //security code exist
            ($data['security_code'] == $code)) {//security code is valid

            $form = new Am_Form;
            $form->addCsrf();
            $form->addHidden('em')->setValue($this->getRequest()->getParam('em'));
            $form->addHtml()
                ->setHtml(Am_Html::escape($user->login))
                ->setLabel(___('Username'));
            $form->addHtml()
                ->setHtml(Am_Html::escape($data['email']))
                ->setLabel(___('New Email'));
            $form->addPassword('_pass')
                ->setLabel("Password\nplease enter your password to confirm email change")
                ->addRule('required')
                ->addRule('callback2', null, function($v) use ($user, $di) {
                    $protector = new Am_Auth_BruteforceProtector(
                            $di->db,
                            $di->config->get('protect.php_include.bruteforce_count', 5),
                            $di->config->get('protect.php_include.bruteforce_delay', 120),
                            Am_Auth_BruteforceProtector::TYPE_USER);

                    if ($wait = $protector->loginAllowed($_SERVER['REMOTE_ADDR'])) {
                        return ___('Please wait %d seconds before next attempt', $wait);
                    }

                    if (!$user->checkPassword($v)) {
                        $protector->reportFailure($_SERVER['REMOTE_ADDR']);
                        return ___('Current password entered incorrectly, please try again');
                    }
                });
            $form->addSaveButton(___('Confirm'));

            if ($form->isSubmitted() && $form->validate()) {
                $user->email = $data['email'];
                $user->email_confirmed = true;
                $user->email_confirmation_date = $this->getDi()->sqlDateTime;
                $user->save();

                $this->getDi()->store->delete('member-verify-email-profile-' . $user_id);

                $url = $this->getUrl('member', 'index');
                $this->_response->redirectLocation($url);
            } else {
                $this->view->title = ___('Email change confirmation');
                $this->view->layoutNoMenu = true;
                $this->view->content = (string) $form;
                $this->view->display('layout.phtml');
            }
        } else {
            throw new Am_Exception_QuietError(___('Security code is invalid'));
        }
    }

}