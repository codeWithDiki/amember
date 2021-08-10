<?php

/**
 * @title Acelle Mail
 * @visible_link https://acellemail.com
 */
class Am_Newsletter_Plugin_Acellemail extends Am_Newsletter_Plugin
{

    const CONTACT_ID = 'acelle-mail-id';

    function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addText('url', ['class' => 'am-el-wide'])
            ->setLabel("API Endpoint")
            ->addRule('required');
        $form->addSecretText('api_token', ['class' => 'am-el-wide'])
            ->setLabel("Your API token")
            ->addRule('required');
        $form->addAdvCheckbox('debug')
            ->setLabel("Debug logging\nRecord debug information in the log");
    }

    function getTitle()
    {
        return 'Acelle Mail';
    }

    public function isConfigured()
    {
        return $this->getConfig('api_token') && $this->getConfig('url');
    }

    public function changeSubscription(User $user, array $addLists, array $deleteLists)
    {
        $map = json_decode($user->data()->getBlob(self::CONTACT_ID) ?: '[]', true);

        foreach ($addLists as $list_uid) {
            if (empty($map[$list_uid])) {
                $_ = $this->sendRequest("lists/{$list_uid}/subscribers/store", [
                    'EMAIL' => $user->email,
                    'FIRST_NAME' => $user->name_f,
                    'LAST_NAME' => $user->name_l,
                ], Am_HttpRequest::METHOD_POST);
                if (!empty($_['subscriber_uid'])) {
                    $map[$list_uid] = $_['subscriber_uid'];
                }
            } else {
                $this->sendRequest("lists/{$list_uid}/subscribers/{$map[$list_uid]}/subscribe", [], 'PATCH');
            }
        }

        foreach ($deleteLists as $list_uid)
        {
            if (!empty($map[$list_uid])) {
                $this->sendRequest("lists/{$list_uid}/subscribers/{$map[$list_uid]}/unsubscribe", [], 'PATCH');
            }
        }

        $user->data()->setBlob(self::CONTACT_ID, json_encode($map));
        $user->data()->update();

        return true;
    }

    public function getLists()
    {
        $res = $this->sendRequest('lists');
        $lists = [];
        foreach ($res as $l) {
            $lists[$l['uid']] = ['title' => $l['name']];
        }
        return $lists;
    }

    function sendRequest($path, $params = [], $method = Am_HttpRequest::METHOD_GET)
    {
        $params['api_token'] = $this->getConfig('api_token');

        $req = new Am_HttpRequest(null, $method);

        if ($method == Am_HttpRequest::METHOD_POST) {
            $req->setUrl(rtrim($this->getConfig('url'), '/') .  "/$path");
            $req->addPostParameter($params);
        } else {
            $req->setUrl(rtrim($this->getConfig('url'), '/') .  "/$path?" . http_build_query($params));
        }

        $ret = $req->send();
        $this->debug($req, $ret, $path);
        return json_decode($ret->getBody(), true);
    }
}