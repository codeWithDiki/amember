<?php

class Aff_SignupController extends Am_Mvc_Controller
{
    /** @var Am_Form_Signup */
    protected $form;
    /** @var array */
    protected $vars;
    /** @var SavedForm */
    protected $record;
    protected $msg;

    function init()
    {
        if (!class_exists('Am_Form_Brick', false)) {
            class_exists('Am_Form_Brick', true);
            Am_Di::getInstance()->hook->call(Am_Event::LOAD_BRICKS);
        }
        parent::init();
        $this->msg = ___('We review all affiliates manually, so your affiliate account status is pending. '.
                    'You will receive email when your account will be approved. Thank you for your patience.');
    }

    function indexAction()
    {
        if(!$this->getDi()->auth->getUserId())
            $this->getDi()->auth->checkExternalLogin($this->getRequest());

        if ($this->getDi()->auth->getUserId() && $this->getDi()->auth->getUser()->is_affiliate)
            $this->_redirect('aff/aff'); // there are no reasons to use this form if logged-in

        if ($this->getDi()->auth->getUserId() && $this->getDi()->auth->getUser()->data()->get('aff_await_approval')) {
            $this->view->content = '<div class="am-info">' . $this->msg . '</div>';
            $this->view->display('layout.phtml');
            return;
        }

        $form = $this->getDi()->savedFormTable->getByType(SavedForm::D_AFF);
        if (!$form) {
            throw new Am_Exception_QuietError(___('There are no form available for affiliate signup.'));
        }

        $this->record = $form;
        $this->view->title = $this->record->title;
        if ($this->record->meta_title)
            $this->view->meta_title = $this->record->meta_title;
        if ($this->record->meta_keywords)
            $this->view->headMeta()->setName('keywords', $this->record->meta_keywords);
        if ($this->record->meta_description)
            $this->view->headMeta()->setName('description', $this->record->meta_description);
        if ($this->record->meta_robots)
            $this->view->headMeta()->setName('robots', $this->record->meta_robots);
        $this->view->code = 'aff';
        $this->view->record = $this->record;

        $this->form = new Am_Form_Signup();
        $this->form->setParentController($this);
        $this->form->initFromSavedForm($this->record);
        try {
            $this->form->run();
        } catch (HTML_QuickForm2_NotFoundException $e) {
            if ($this->getDi()->auth->getUserId()) {
                $user = $this->getDi()->auth->getUser();
                if ($this->getModule()->getConfig('signup_type') == 2) {
                    $user->is_affiliate = 0;
                    $user->data()->set('aff_await_approval', 1);
                } else {
                    $user->is_affiliate = 1;
                }
                $user->save();
                if (!$user->is_affiliate) {
                    $this->getModule()->sendNotApprovedEmail($user);
                    $this->view->content = '<div class="am-info">' . $this->msg . '</div>';
                    $this->view->display('layout.phtml');
                } else {
                    $this->getModule()->sendAffRegistrationEmail($user);
                    $this->getModule()->sendAdminRegistrationEmail($user);
                    $this->_redirect('aff/aff');
                }
            } else {
                throw $e;
            }
        }
    }

    function display(Am_Form $form, $pageTitle)
    {
        $this->view->form = $form;
        $this->view->title = $this->record->title;
        if ($pageTitle) $this->view->title = $pageTitle;
        $this->view->display($this->record->tpl ? ('signup/' . basename($this->record->tpl)) : 'signup/signup.phtml');
    }

    function process(array $vars, $name, HTML_QuickForm2_Controller_Page $page)
    {
        $this->vars = $vars;
        $em = $page->getController()->getSessionContainer()->getOpaque('EmailCode');
        // do actions here
        $this->user = $this->getDi()->auth->getUser();
        if (!$this->user) {
            $this->user = $this->getDi()->userRecord;
            $this->user->setForInsert($this->vars); // vars are filtered by the form !

            if (empty($this->user->login))
                $this->user->generateLogin();

            if (empty($this->vars['pass'])) {
                $this->user->generatePassword();
            } else {
                $this->user->setPass($this->vars['pass']);
            }

            if (empty($this->user->lang)) {
                $this->user->lang = $this->getDi()->locale->getLanguage();
            }

            if ($this->getDi()->config->get('aff.signup_type') == 2) {
                $this->user->is_affiliate = 0;
                $this->user->data()->set('aff_await_approval', 1);
            } else {
                $this->user->is_affiliate = 1;
            }

            $this->user->insert();

            $this->getDi()->hook->call(Am_Event::SIGNUP_USER_ADDED, [
                'vars' => $this->vars,
                'user' => $this->user,
                'form' => $this->form,
                'savedForm' => $this->record
            ]);
        } else {
            unset($this->vars['pass']);
            unset($this->vars['login']);
            unset($this->vars['email']);
            $this->user->setForUpdate($this->vars)->update();
            if ($this->getModule()->getConfig('signup_type') == 2) {
                $this->user->is_affiliate = 0;
                $this->user->data()->set('aff_await_approval', 1);
            } else {
                $this->user->is_affiliate = 1;
            }
            $this->user->save();
            // user updated
            $this->getDi()->hook->call(Am_Event::SIGNUP_USER_UPDATED, [
                'vars' => $this->vars,
                'user' => $this->user,
                'form' => $this->form,
                'savedForm' => $this->record
            ]);
        }

        // remove verification record
        if (!empty($em))
            $this->getDi()->store->delete(Am_Form_Signup_Action_SendEmailCode::STORE_PREFIX . $em);
        $page->getController()->destroySessionContainer();

        $this->getDi()->hook->call(Am_Event::SIGNUP_AFF_ADDED, [
            'vars' => $this->vars,
            'user' => $this->user,
            'form' => $this->form,
        ]);
        if($this->user->is_affiliate && !$this->getDi()->auth->getUserId()) {
            $this->getDi()->auth->setUser($this->user, $_SERVER['REMOTE_ADDR']);
        }

        if ($this->getModule()->getConfig('registration_mail') && $this->user->is_affiliate)
        {
            $this->getModule()->sendAffRegistrationEmail($this->user);
            $this->getModule()->sendAdminRegistrationEmail($this->user);
        }

        if (!$this->user->is_affiliate) {
            $this->getModule()->sendNotApprovedEmail($this->user);
            $this->view->content = '<div class="am-info">' . $this->msg . '</div>';
            $this->view->display('layout.phtml');
        } else {
            $this->_redirect('aff/aff');
        }
        return true;
   }

   function getCurrentUrl()
   {
       return $this->_request->getScheme() . '://' .
              $this->_request->getHttpHost() .
              $this->_request->getBaseUrl() . '/' .
              $this->_request->getModuleName() . '/' .
              $this->_request->getControllerName();
   }
}