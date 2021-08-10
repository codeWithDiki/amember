<?php

class Am_Newsletter_Plugin_AweberEmail extends Am_Newsletter_Plugin
{
    public function _initSetupForm(Am_Form_Setup $form)
    {
        parent::_initSetupForm($form);
    }
    public function changeSubscription(User $user, array $addLists, array $deleteLists)
    {
        foreach ($addLists as $listId)
        {
            $mail = $this->getDi()->mail;
            $mail->addTo($listId . '@aweber.com');
            $mail->setSubject("aMember Pro v4 Subscribe Parser");
            $mail->setBodyText(
                "SUBSCRIBE\n" . 
                "Email: " . $user->email . "\n".
                "Name: "  . $user->getName() . "\n".
                "Login: " . $user->login . "\n"
            );
            $mail->send();
        }
        foreach ($deleteLists as $listId)
        {
            $mail = $this->getDi()->mail;
            $mail->addTo($listId . '@aweber.com');
            $why = "";
            $mail->setSubject("REMOVE#".$user->email."#$why#".$listId);
            $mail->send();
        }
        return true;
    }
}