<?php

/**
 * @title Send Fox
 * @visible_link https://sendfox.com
 */
class Am_Newsletter_Plugin_Sendfox extends Am_Newsletter_Plugin
{
    protected $_isDebug = false;

    const API_ENDPOINT = 'https://api.sendfox.com/';
    const SENDFOX_ID = 'sendfox-id';

    function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addSecretText('api_key', ['class' => 'am-el-wide'])
            ->setLabel("Personal Access Tokens\nYou can find it in your SendFox account Settings -> API")
            ->addRule('required');
        $form->addAdvCheckbox('debug')
            ->setLabel("Debug logging\nRecord debug information in the log");
    }

    function getTitle()
    {
        return 'Send Fox';
    }

    public function isConfigured()
    {
        return $this->getConfig('api_key');
    }

    public function changeSubscription(User $user, array $addLists, array $deleteLists)
    {
        $id = $user->data()->get(self::SENDFOX_ID);
        if (!$id || $addLists) {
            $contact = $this->sendRequest('contacts', [
                'email' => $user->email,
                'first_name' => $user->name_f,
                'last_name' => $user->name_l,
                'lists' => $addLists,
            ], Am_HttpRequest::METHOD_POST);

            if (empty($contact['id'])) return false;

            $id = $contact['id'];
            $user->data()->set(self::SENDFOX_ID, $id)->update();
        }

        foreach ($deleteLists as $list_id) {
            $this->sendRequest("lists/$list_id/contacts/$id", [],  Am_HttpRequest::METHOD_DELETE);
        }

        return true;
    }

    public function getLists()
    {
        $res = $this->sendRequest('lists');
        $lists = [];
        foreach ($res['data'] as $l) {
            $lists[$l['id']] = ['title' => $l['name']];
        }
        return $lists;
    }

    function sendRequest($path, $params = [], $method = Am_HttpRequest::METHOD_GET)
    {
        $req = new Am_HttpRequest(self::API_ENDPOINT . $path, $method);
        $req->setHeader("Authorization", "Bearer {$this->getConfig('api_key')}");

        if ($params) {
            $req->addPostParameter($params);
        }

        $ret = $req->send();
        $this->debug($req, $ret);
        return json_decode($ret->getBody(), true);
    }
}