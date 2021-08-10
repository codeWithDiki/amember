<?php

class Am_Newsletter_Plugin_Klaviyo extends Am_Newsletter_Plugin
{
    const API_URL = 'https://a.klaviyo.com/api/v1/';

    protected $_isDebug = false;

    function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addSecretText('api_key', ['class' => 'am-el-wide'])
            ->setLabel('API Private Key')
            ->addRule('required');
        $form->addAdvCheckbox('double_optin')
            ->setLabel(___('Enable double opt-in'));
    }

    function isConfigured()
    {
        return $this->getConfig('api_key');
    }

    function getLists()
    {
        $resp = $this->doRequest('lists');
        $ret = [];
        foreach ($resp['data'] as $l) {
            $ret[$l['id']] = ['title' => $l['name']];
        }
        return $ret;
    }

    function changeSubscription(User $user, array $addLists, array $deleteLists)
    {
        foreach ($addLists as $ID) {
            $this->doRequest("list/{$ID}/members", [
                'email' => $user->email,
                'properties' => json_encode([
                    '$first_name' => $user->name_f,
                    '$last_name' => $user->name_l,
                    '$phone_number' => $user->phone
                ]),
                'confirm_optin' => $this->getConfig('double_optin') ? 'true' : 'false',
            ], 'POST');
        }
        foreach ($deleteLists as $ID) {
            $this->doRequest("list/{$ID}/members/exclude", [
                'email' => $user->email
            ], 'POST');
        }
        return true;
    }

    function doRequest($method, $params = [], $verb = 'GET')
    {
        $params['api_key'] = $this->getConfig('api_key');

        $req = new Am_HttpRequest();
        $req->setMethod($verb);
        switch ($verb) {
            case 'GET' :
                $req->setUrl(self::API_URL . $method . '?' . http_build_query($params));
                break;
            case 'POST' :
                $req->setUrl(self::API_URL . $method);
                $req->addPostParameter($params);
                break;
        }

        $resp = $req->send();
        $this->debug($req, $resp);
        if (!$body = $resp->getBody())
            return [];

        return json_decode($body, true);
    }

}