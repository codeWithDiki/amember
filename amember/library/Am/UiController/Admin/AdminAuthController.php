<?php

class Admin_RestorePassForm extends Am_Form_Admin
{
    public function init()
    {
        $fs = $this->addFieldset()
            ->setLabel(___('Restore Password'));

        if (Am_Recaptcha::isConfigured() && $this->getDi()->config->get('recaptcha')) {
            $captcha = $fs->addGroup(null, ['class' => 'am-row-wide']);
            $captcha->addRule('callback', ___('Anti Spam check failed'), [$this, 'validateCaptcha']);
            $captcha->addStatic('captcha')->setContent($this->getDi()->recaptcha->render());
        }

        $fs->addText('login', ['class' => 'am-el-wide'])
            ->setLabel(___('Username/Email'));

        $this->addSubmit('_', ['value' => ___('Get New Password'), 'class'=>'am-row-wide']);
    }

    public function validateCaptcha()
    {
        $resp = '';
        foreach ($this->getDataSources() as $ds) {
            if ($resp = $ds->getValue('g-recaptcha-response'))
                break;
        }
        return $this->getDi()->recaptcha->validate($resp);
    }

    public function getDi()
    {
        return Am_Di::getInstance();
    }
}

class AdminAuthController extends Am_Mvc_Controller_Auth
{
    protected $loginField = 'am_admin_login';
    protected $passField = 'am_admin_passwd';
    protected $loginType = Am_Auth_BruteforceProtector::TYPE_ADMIN;

    const EXPIRATION_PERIOD = 2; //hrs
    const CODE_STATUS_VALID = 1;
    const CODE_STATUS_EXPIRED = -1;
    const CODE_STATUS_INVALID = 0;
    const SECURITY_CODE_STORE_PREFIX ='admin-restore-password-request-';

    protected function checkAdminAuthorized()
    {
        // nop
    }

    public function getAuth()
    {
        return $this->getDi()->authAdmin;
    }

    public function changePassAction()
    {
        $s = $this->getRequest()->getFiltered('s');
        if (!$this->checkCode($s, $admin)) {
            $this->view->title = ___('Security code is invalid');
            $url = $this->getDi()->url('admin-auth/send-pass');
            $this->view->content = '<div class="form-login-wrapper"><div class="form-login">' .
                ___('Security code is invalid') .
                " <a href='$url'>" .
                ___('Continue') . "</a></div></div>";
            $this->view->display('admin/layout-login.phtml');
            return;
        }

        $form = $this->createSetPassForm();
        $form->addDataSource(new HTML_QuickForm2_DataSource_Array([
            's' => $s,
            'email' => $admin->email,
        ]));

        if ($form->isSubmitted() && $form->validate())
        {
            $admin->setPass($this->getParam('pass'));
            $admin->save();
            $this->getDi()->store->delete(self::SECURITY_CODE_STORE_PREFIX . $s);

            if ($this->getDi()->config->get('send_password_admin')) {
                $et = Am_Mail_Template::load('send_password_admin', $admin->lang);
                $et->setAdmin($admin);
                $et->send($admin);
            }

            $msg = ___('Your password has been changed successfully. '
                . 'You can %slogin to your account%s with new password.',
                sprintf('<a href="%s">', $this->getDi()->url('admin', ['am_admin_login' => $admin->login])),
                '</a>'
            );

            $this->view->title = ___('Password changed');
            $this->view->content = <<<CUT
<div class="form-login-wrapper">
    <div class="form-login">
        {$msg}
    </div>
</div>
CUT;
            $this->view->display('admin/layout-login.phtml');
        } else {
            $this->view->title = ___('Change Password');
            $this->view->content = <<<CUT
<div class="form-login-wrapper">
    <div class="form-login">
        {$form}
    </div>
</div>
CUT;
            $this->view->display('admin/layout-login.phtml');
        }
    }

    public function sendPassAction()
    {
        $form = new Admin_RestorePassForm;

        if ($form->isSubmitted() && $form->validate()) {
            $vars = $form->getValue();
            $login = $vars['login'];

            if ($error = $this->checkIpLimits())
            {
                $this->view->message = $error;
                return $this->view->display('admin/send-pass.phtml');
            }

            //admin may be not found as we made no login form validation
            $admin = $this->getDi()->adminTable->findFirstByLogin($login);
            if (!$admin) {
                $admin = $this->getDi()->adminTable->findFirstByEmail($login);
            }
            if ($admin->is_disabled)
            {
                $admin = null; // we will not send email
            }
            if ($admin)
            {
                if ($error = $this->checkLimits($admin))
                {
                    $this->view->message = $error;
                    return $this->view->display('admin/send-pass.phtml');
                }
                $this->sendSecurityCode($admin);
                $this->decreaseLimits($admin);
            } else {
                usleep(rand(10000, 30000)); // make it hard to detect if password was sent by response delay
                $this->decreaseLimits(null);
            }
            $this->view->title =  ___('Lost Password Sent');
            $this->view->message = ___('If you entered a valid email / username, a message has been sent with instructions on how to reset your password.');
            return $this->view->display('admin/send-pass.phtml');
        } else {
            $this->view->form = $form;
            $this->view->message = ___("Please enter your username or email\n" .
                "address. You will receive a link to create\n" .
                "a new password via email.");
            return $this->view->display('admin/send-pass.phtml');
        }
    }

    protected function createSetPassForm()
    {
        $form = new Am_Form_Admin();

        $form->addCsrf();
        $form->addText('email')
            ->setLabel(___('E-Mail'))
            ->toggleFrozen(true);

        $pass = $form->addPassword('pass')
            ->setLabel(___('New Password'));
        $pass->addRule('required');
        $pass->addRule('minlength', ___('The admin password should be at least %d characters long', 6), 6);
        if ($this->getDi()->config->get('admin_require_strong_password')) {
            $pass->addRule(
                'regex',
                ___('Password should contain at least 2 capital letters, 2 or more numbers and 2 or more special chars'),
                $this->getDi()->userTable->getStrongPasswordRegex()
            );
        }
        $pass0 = $form->addPassword('_pass0')
            ->setLabel(___('Confirm New Password'));
        $pass0->addRule('eq', ___('Passwords must be the same'), $pass);

        $form->addHidden('s');
        $form->addSubmit('save', ['value' => ___('Change Password'), 'class' => 'am-row-wide']);
        return $form;
    }

    protected function decreaseLimits(Admin $admin = null)
    {
        if ($admin)
        {
            $attempt = $this->getDi()->store->get('remind-password-admin_'.$admin->pk());
            $this->getDi()->store->set('remind-password-admin_'.$admin->pk(), ++$attempt, '+3 hours');
        }

        $attempt_ip = $this->getDi()->store->get('remind-password-admin_ip_' . $this->getRequest()->getClientIp(true));
        $this->getDi()->store->set('remind-password-admin_ip_' . $this->getRequest()->getClientIp(true), ++$attempt_ip, '+3 hours');
    }

    protected function checkIpLimits()
    {
        // Check limits by IP address.
        $attempt_ip = $this->getDi()->store->get('remind-password-admin_ip_' . $this->getRequest()->getClientIp(true));
        if ( $attempt_ip >= 10 ) { // stop automated requests from same ip without breaking normal users
            return ___('Too many Lost Password requests. Please wait 180 minutes for retrying');
        }
    }

    protected function checkLimits(Admin $admin)
    {
        $attempt = $this->getDi()->store->get('remind-password-admin_'.$admin->pk());
        if ($attempt >= 2)
        {
            return ___('The message containing your password has already been sent to your inbox. Please wait 180 minutes for retrying');
        }
    }

    protected function checkCode($code, &$admin)
    {
        $data = $this->getDi()->store->get(self::SECURITY_CODE_STORE_PREFIX . $code);
        if (!$data) {
            return false;
        }

        list($admin_id, $pass, $email) = explode('-', $data, 3);
        $admin = $this->getDi()->adminTable->load($admin_id);

        if ($admin->pass != $pass || $admin->email != $email) {
            return false;
        }

        return true;
    }

    private function sendSecurityCode(Admin $admin)
    {
        $security_code = $this->getDi()->security->randomString(16);
        $securitycode_expire = sqlTime(time() + self::EXPIRATION_PERIOD * 60 * 60);

        $et = Am_Mail_Template::load('send_security_code_admin', null, true);
        $et->setUser($admin);
        $et->setAdmin($admin);
        $et->setIp($_SERVER['REMOTE_ADDR']);
        $et->setUrl($this->getDi()->surl('admin-auth/change-pass', ['s'=> $security_code], false));
        $et->setHours(self::EXPIRATION_PERIOD);
        $et->send($admin);

        $data = [
            $admin->pk(),
            $admin->pass,
            $admin->email
        ];

        $this->getDi()->store->set(
            self::SECURITY_CODE_STORE_PREFIX . $security_code, implode('-', $data), $securitycode_expire
        );
    }

    function indexAction()
    {
        if ($this->_request->isXmlHttpRequest() && !$this->_request->isPost()) {
            header('Content-type: text/plain; charset=UTF-8');
            header('HTTP/1.0 402 Admin Login Required');
            return $this->_response->ajaxResponse(['err' => ___('Admin Login Required'), 'ok' => false]);
        }

        if ($this->getDi()->authAdmin->getUserId()) {
            Am_Mvc_Response::redirectLocation($this->getDi()->url('admin', false));
        }

        // only store if GET, nothing already stored, and no params in URL
        if ($this->_request->isGet() && empty($this->getSession()->admin_redirect) &&
            !$this->_request->getQuery() && $this->checkUri($this->_request->getRequestUri())) {
            $this->getSession()->admin_redirect = $this->_request->getRequestUri();
        }

        $this->getAuth()->plaintextPass = $this->getPass();

        return parent::indexAction();
    }

    protected function checkUri($uri)
    {
        //allow only valid uri without parameters.
        $uri = trim(substr($uri, strlen($this->getDi()->url(''))), '/');
        if (strpos('admin-auth', $uri) !== false) return false; //protect against endless redirect loop
        return preg_match('/^[-a-zA-Z0-9]+(\/[-a-zA-Z0-9]*)*$/', $uri);
    }

    public function renderLoginForm($authResult)
    {
        return $this->view->render('admin/_login.phtml');
    }

    public function renderLoginPage($html)
    {
        $this->view->content = $html;
        return $this->view->render('admin/login.phtml');
    }

    protected function createAdapter()
    {
        return new Am_Auth_Adapter_AdminPassword(
            $this->getLogin(),
            $this->getPass(),
            $this->getDi()->adminTable);
    }

    public function getLogoutUrl()
    {
        return $this->getDi()->url('admin', false);
    }

    public function getOkUrl()
    {
        $uri = $this->getUriFromSession();
        return $uri ? $uri : $this->getDi()->url('admin', false);
    }

    public function redirectOk()
    {
        if ($this->_request->isXmlHttpRequest()) {
            header("Content-type: text/plain; charset=UTF-8");
            header('HTTP/1.0 200 OK');
            echo json_encode(['ok' => true, 'adminLogin' => $this->getAuth()->getUsername()]);
        } else {
            parent::redirectOk();
        }
    }

    protected function getUriFromSession()
    {
        $uri = $this->getSession()->admin_redirect;
        $this->getSession()->admin_redirect = null;
        return ($uri && $this->checkUri($uri)) ? $uri : null;
    }
}