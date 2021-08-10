<?php

trait Am_Mvc_Controller_User_Update
{

    function updateUser(User $user, array $vars, Am_Form_Profile $form)
    {
        $oldUser = clone $user;
        $oldUser->toggleFrozen(true);

        unset($vars['user_id']);

        if (!empty($vars['pass']))
            $user->setPass($vars['pass']);

        unset($vars['pass']);

        $user->_ve = $this->handleEmail($form->getRecord(), $vars, $user) ? 1 : 0;

        $u = $user->setForUpdate($vars);

        $this->emailChangesToAdmin($u, $oldUser);

        $u->update();

        $this->getDi()->hook->call(Am_Event::PROFILE_USER_UPDATED, [
                'vars' => $vars,
                'oldUser' => $oldUser,
                'user' => $u,
                'form' => $form,
                'savedForm' => $form->getRecord()
        ]);

        return $u;
    }
    /**
     * True if email confirmation message was sent to user's email address.
     * @param User $user
     * @return type
     */
    function isVerifyEmailState(User $user)
    {
        return $user->_ve;
    }

    function emailChangesToAdmin(User $user, User $oldUser)
    {
        if(!$this->getDi()->config->get('profile_changed')) {
            return;
        }
        $changes = '';
        $oldUserArray = $oldUser->toArray();

        foreach($user->toArray() as $k => $v){
            if($k=='pass') continue;
            if(($o=$oldUserArray[$k])!=$v) {
                is_scalar($o) || ($o = serialize($o));
                is_scalar($v) || ($v = serialize($v));
                $changes.="- $k: $o\n";
                $changes.="+ $k: $v\n";
            }
        }
        if(!strlen($changes)) {
            return;
        }
        $et = Am_Mail_Template::load('profile_changed');
        $et->setChanges($changes);
        $et->setUser($user);
        $et->sendAdmin();
    }

    protected function handleEmail(SavedForm $form, & $vars, User $user)
    {
        $bricks = $form->getBricks();
        foreach ($bricks as $brick) {
            if ($brick->getClass() == 'email'
                    && $brick->getConfig('validate')
                    && $vars['email'] != $user->email) {

                $code = $this->getDi()->security->randomString(10);

                $data = [
                    'security_code' => $code,
                    'email' => $vars['email']
                ];

                $this->getDi()->store->setBlob(
                    'member-verify-email-profile-' . $user->user_id,
                    serialize($data),
                    sqlTime($this->getDi()->time + 48 * 3600)
                );

                $tpl = Am_Mail_Template::load('verify_email_profile', get_first($user->lang,
                    $this->getDi()->app->getDefaultLocale(false)), true);

                $cur_email = $user->email;
                $user->email = $vars['email'];

                $tpl->setUser($user);
                $tpl->setCode($code);
                $tpl->setUrl(
                    $this->getDi()->url('profile/confirm-email',
                        ['em'=>$this->getDi()->security->obfuscate($user->pk()) . '-' . $code]
                    , false, true)
                );
                $tpl->send($user);

                $user->email = $cur_email;

                unset($vars['email']);
                return true;
            }
        }

        return false;
    }


}
