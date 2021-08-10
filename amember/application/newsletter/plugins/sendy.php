<?php

class Am_Newsletter_Plugin_Sendy extends Am_Newsletter_Plugin
{
    public function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addText('url', ['class' => 'am-el-wide'])
            ->setLabel("Sendy URL\n" .
                'url of your setup of Sendy')
            ->addRule('required');
        $form->addSecretText('api_key')->setlabel(___('Sendy API key
        Your API key can be obtained from your main settings'));

        $form->addTextarea('custom_fields', ['rows' => 5, 'class' => 'am-el-wide'])
            ->setLabel("Additional Fields\n" . "sendy_field|amember_field\n"
                . "eg:\n\n<strong>FirstName|name_f</strong>\n\n"
                . "one link - one string");

        $form->addAdvCheckbox('debug')
            ->setLabel("Debug logging\nRecord debug information in the log");
    }


    public function changeEmail(User $user, $oldEmail, $newEmail)
    {
        $ef = 'email';
        $list_ids = $this->getDi()->newsletterUserSubscriptionTable->getSubscribedIds($user->pk());
        $lists = [];
        foreach ($this->getDi()->newsletterListTable->loadIds($list_ids) as $list)
        {
            if ($list->plugin_id != $this->getId()) continue;
            $lists[] = $list->plugin_list_id;
        }
        $user->set($ef, $oldEmail)->toggleFrozen(true);
        $this->changeSubscription($user, [], $lists);
        // subscribe again
        $user->set($ef, $newEmail)->toggleFrozen(false);
        $this->changeSubscription($user, $lists, []);
    }

    function isConfigured()
    {
        return (bool) $this->getConfig('url');
    }

    public function changeSubscription(User $user, array $addLists, array $deleteLists)
    {
        if (!$email = $user->get($this->getConfig('email_field', 'email'))) return true;

        foreach ($addLists as $listId) {
            $ret = $this->doRequest('/subscribe', [
                'name' => $user->getName(),
                'email' => $email,
                'list' => $listId,
                'boolean' => 'true'
            ] + $this->getCustomFields($user));
            if (!in_array($ret, ['1', 'Already subscribed.'])) return false;

        }
        foreach ($deleteLists as $listId) {
            $ret = $this->doRequest('/api/subscribers/delete.php', [
                'email' => $email,
                'list_id' => $listId
            ]);
            if (!in_array($ret, ['1', 'Subscriber does not exist.'])) return false;
        }
        return true;
    }

    protected function getCustomFields(User $user)
    {
        $customFields = [];
        $cfg = $this->getConfig('custom_fields');
        if(!empty($cfg))
        {
            foreach (explode("\n", str_replace("\r", "", $cfg)) as $str)
            {
                if(!$str) continue;
                list($k, $v) = explode("|", $str);
                if(!$v) continue;

                if(($value = $user->get($v)) || ($value = $user->data()->get($v)))
                {
                    $customFields[$k] = $value;
                }
            }
        }
        return $customFields;
    }

    function doRequest($path, array $vars)
    {
        $req = new Am_HttpRequest($this->getConfig('url') . $path, Am_HttpRequest::METHOD_POST);
        if($key = $this->getConfig('api_key')){
            $vars['api_key'] = $key;
        }
        $req->addPostParameter($vars);

        $res = $req->send();
        $this->debug($req, $res);
        return $res->getBody();
    }
}