<?php

class Am_Newsletter_Plugin_Mautic extends Am_Newsletter_Plugin
{
    function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addText('username')
            ->setLabel('Username')
            ->addRule('required');
        $form->addSecretText('pass')
            ->setLabel('Password')
            ->addRule('required');
        $form->addText('url', ['class' => 'am-el-wide'])
            ->setLabel('URL of Mautic Installation')
            ->addRule('required');
        $form->addAdvCheckbox('debug')
            ->setLabel("Debug logging\nRecord debug information in the log");
    }

    function isConfigured()
    {
        return $this->getConfig('username')
            && $this->getConfig('pass')
            && $this->getConfig('url');
    }

    function getLists()
    {
        $resp = $this->doRequest('segments', "GET");
        $ret = array();
        foreach ($resp['lists'] as $l) {
            $ret[$l['id']] = array('title' => $l['name']);
        }
        return $ret;
    }

    function changeSubscription(User $user, array $addLists, array $deleteLists)
    {
        if (!$user->data()->get('mautic_id')) {
            $resp = $this->doRequest("contacts/new", "POST", [
                    'firstname' => $user->name_f,
                    'lastname' => $user->name_l,
                    'email' => $user->email,
                    'ipAddress' => $user->remote_addr //only Trackable IPs stored
                ]);
            $user->data()->set('mautic_id', $resp['contact']['id']);
            $user->save();
        }

        $contactId = $user->data()->get('mautic_id');

        foreach ($addLists as $listId) {
            $this->doRequest("segments/{$listId}/contact/{$contactId}/add", "POST");
        }

        foreach ($deleteLists as $listId) {
            $this->doRequest("segments/{$listId}/contact/{$contactId}/remove", "POST");
        }

        return true;
    }

    function doRequest($method, $verb = 'GET', $params = [])
    {
        $req = new Am_HttpRequest($this->url($method), $verb);
        $req->setAuth($this->getConfig('username'), $this->getConfig('pass'));

        if ($params) {
            $req->addPostParameter($params);
        }

        $resp = $req->send();
        $this->debug($req, $resp);
        if (!$body = $resp->getBody()) {
            return [];
        }

        return json_decode($body, true);
    }

    function url($method)
    {
        return trim($this->getConfig('url'), '/') . "/api/{$method}";
    }
}