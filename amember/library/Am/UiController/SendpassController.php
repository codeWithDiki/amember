<?php
/*
*   Send lost password
*
*
*     Author: Alex Scott
*      Email: alex@cgi-central.net
*        Web: http://www.cgi-central.net
*    Details: Send lost password page
*    FileName $RCSfile$
*    Release: 6.3.6 ($Revision$)
*
* Please direct bug reports,suggestions or feedback to the cgi-central forums.
* http://www.cgi-central.net/forum/
*
* aMember PRO is a commercial software. Any distribution is strictly prohibited.
*
*/

class SendpassController extends Am_Mvc_Controller
{
    const EXPIRATION_PERIOD = 36; //hrs
    const STORE_PREFIX = 'sendpass-';
    const SECURITY_VAR = '_s';

    public function indexAction()
    {
        if ($code = $this->getFiltered(self::SECURITY_VAR)) {
            if ($this->checkCode($code, $user)) {
                $this->doChangePass($user, $code);
            } else {
                $this->doShowError();
            }
        } elseif ($this->getRequest()->isPost()) {
            $this->doSend();
        } else {
            Am_Mvc_Response::redirectLocation($this->url('login?sendpass'));
        }
    }

    public function doSend()
    {
        $res = [];

        if (Am_Recaptcha::isConfigured() && $this->getDi()->config->get('recaptcha')) {
            $res['recaptcha_key'] = $this->getDi()->recaptcha->getPublicKey();
            $res['recaptcha_theme'] = $this->getDi()->config->get('recaptcha-theme', 'light');
            $res['recaptcha_size'] = $this->getDi()->config->get('recaptcha-size', 'normal');
            $res['recaptcha_hl'] = $this->getDi()->locale->getId();
        }

        do {
            if (Am_Recaptcha::isConfigured() &&
                $this->getDi()->config->get('recaptcha') &&
                !$this->getDi()->recaptcha->validate($this->getParam('g-recaptcha-response'))) {

                $ok = false;
                $text = ___('Anti Spam check failed');
                break;
            }

            if (!$login = trim($this->getParam('login'))) {
                $ok = false;
                $text = ___('Please enter either login or email');
                break;
            }
            $user = $this->getDi()->userTable->findFirstByLogin($login);

            if (!$user) {
                $user = $this->getDi()->userTable->findFirstByEmail($login);
            }
            if (!$user) {
                $this->getDi()->hook->call($event = new Am_Event(Am_Event::AUTH_LOST_PASS_USER_EMPTY, ['login' => $login]));
                $user = $event->getReturn();
            }

            if (!$user) {
                if ($this->getDi()->config->get('reset_pass_no_disclosure')) {
                    $ok = true;
                    $title = ___('Lost Password Sent');
                    $text = ___('If you entered a valid email / username, a message has been sent with instructions on how to reset your password.');
                } else {
                    $ok = false;
                    $title = ___('Lost Password Sending Error');
                    $text = ___('The information you have entered is incorrect. ' .
                        'Username [%s] does not exist in database', $this->getEscaped('login'));
                }
                break;
            }

            if ($error = $this->checkLimits($user)) {
                $title = ___('Lost Password Sending Error');
                $text = $error;
                $ok = false;
                break;
            }

            if ($user->is_locked > 0) {
                $title = ___('Lost Password Sending Error');
                $text = ___('Your account is locked');
                $ok = false;
                break;
            }

            $this->sendSecurityCode($user);
            $title = ___('Lost Password Sent');
            if ($this->getDi()->config->get('reset_pass_no_disclosure')) {
                $text = ___('If you entered a valid email / username, a message has been sent with instructions on how to reset your password.');
            } else {
                $text = ___('A link to reset your password has been emailed to you. Please check your mailbox.');
            }
            $ok = true;

        } while (false);

        if ($this->_request->isXmlHttpRequest())
        {
            $res['ok'] = $ok;
            $res['error'] = [$text];
            return $this->_response->ajaxResponse($res);
        }

        $this->view->title = $title;
        $this->view->content = $text;
        $this->view->display('layout.phtml');
    }

    public function doChangePass(User $user, $code)
    {
        $form = $this->createForm();
        $form->addDataSource(new HTML_QuickForm2_DataSource_Array([
            self::SECURITY_VAR => $code,
            'login' => $user->login,
            'email' => $user->email,
        ]));

        if ($form->isSubmitted() && $form->validate())
        {
            $user->setPass($this->getParam('pass0'));
            $user->save();
            $this->getDi()->store->delete(self::STORE_PREFIX . $code);

            if ($this->getDi()->config->get('auto_login_after_pass_reset')) {
                $adapter = new Am_Auth_Adapter_User($user);
                $this->getDi()->auth->login($adapter, $this->getRequest()->getClientIp());
            }

            $msg = $this->getDi()->config->get('auto_login_after_pass_reset') ?
                    ___('Your password has been changed successfully.') :
                    ___('Your password has been changed successfully. ' .
                    'You can %slogin to your account%s with new password.',
                        sprintf('<a href="%s">', $this->getDi()->url('login',
                            ['amember_login' => $user->login])), '</a>');
            $this->view->title = ___('Change Password');
            $this->view->content = <<<CUT
   <div class="am-info">$msg</div>
CUT;
            $this->view->display('layout.phtml');
        } else {
            $this->view->form = $form;
            $this->view->display('changepass.phtml');
        }
    }

    function doShowError()
    {
        $this->view->title = ___('This link is either invalid or expired');
        $url = $this->getDi()->url('login?sendpass');
        $text = ___('Please fill out %sthis form%s to get a new one!', "<a href='{$url}'>", '</a>');
        $this->view->content = <<<CUT
<p>{$text}</p>
CUT;
        $this->view->display('layout.phtml');
    }

    protected function createForm()
    {
        $form = new Am_Form();

        $form->addCsrf();
        $form->addText('login')
            ->setLabel(___('Username'))
            ->toggleFrozen(true);
        $form->addText('email')
            ->setLabel(___('E-Mail'))
            ->toggleFrozen(true);

        $pass0 = $form->addPassword('pass0')
            ->setLabel(___('New Password'));
        $pass0->addRule('minlength',
                ___('The password should be at least %d characters long', $this->getDi()->config->get('pass_min_length', 4)),
                $this->getDi()->config->get('pass_min_length', 4));
        $pass0->addRule('maxlength',
                ___('Your password is too long'),
                $this->getDi()->config->get('pass_max_length', 32));
        $pass0->addRule('required', ___('This field is required'));
        if ($this->getDi()->config->get('require_strong_password')) {
            $pass0->addRule('regex', ___('Password should contain at least 2 capital letters, 2 or more numbers and 2 or more special chars'),
                $this->getDi()->userTable->getStrongPasswordRegex());
        }

        $pass1 = $form->addPassword('pass1')
            ->setLabel(___('Confirm Password'));

        $pass1->addRule('eq', ___('Passwords do not match'), $pass0);

        $form->addHidden(self::SECURITY_VAR);
        $form->addSaveButton(___('Change Password'));
        return $form;
    }

    protected function checkLimits(User $user)
    {
        // Check limits by email.
        $attempt = $this->getDi()->store->get('remind-password_' . $user->email);
        if ($attempt>=2) {
            return ___('The message containing your password has already been sent to your inbox. Please wait 180 minutes for retrying');
        }
        $this->getDi()->store->set('remind-password_' . $user->email, ++$attempt, '+3 hours');

        // Check limits by IP address.
        $attempt_ip = $this->getDi()->store->get('remind-password_ip_' . $this->getRequest()->getClientIp(true));
        if ($attempt_ip>=5) {
            return ___('Too many Lost Password requests. Please wait 180 minutes for retrying');
        }
        $this->getDi()->store->set('remind-password_ip_' . $this->getRequest()->getClientIp(true), ++$attempt_ip, '+3 hours');
    }

    protected function checkCode($code, &$user)
    {
        $data = $this->getDi()->store->get(self::STORE_PREFIX . $code);
        if (!$data)
            return false;

        list($user_id, $pass, $email) = explode('-', $data, 3);
        $user = $this->getDi()->userTable->load($user_id);

        if ($user->pass != $pass || $user->email != $email)
            return false;

        return true;
    }

    public function sendSecurityCode(User $user)
    {
        $security_code = $this->getDi()->security->randomString(16);

        $et = Am_Mail_Template::load('send_security_code', $user->lang, true);
        $et->setUser($user);
        $et->setIp($_SERVER['REMOTE_ADDR']);
        $et->setUrl(
           $this->getDi()->surl("sendpass", [self::SECURITY_VAR => $security_code])
        );
        $et->setHours(self::EXPIRATION_PERIOD);
        $et->send($user);

        $data = [$user->pk(), $user->pass, $user->email];

        $this->getDi()->store->set(self::STORE_PREFIX . $security_code,
            implode('-', $data), '+'.self::EXPIRATION_PERIOD.' hours');
    }
}