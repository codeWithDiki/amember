<?php

class Am_Newsletter_Plugin_Sendlane extends Am_Newsletter_Plugin
{
    protected $_isDebug = false;

    function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addSecretText('api_key', ['class' => 'am-el-wide'])
            ->setLabel('API Key')
            ->addRule('required');
        $form->addSecretText('hash_key', ['class' => 'am-el-wide'])
            ->setLabel('Hash Key')
            ->addRule('required');
        $g = $form->addGroup(null, ['class' => 'am-row-required'])
            ->setLabel('Subdomain');
        $g->addText('subdomain');
        $g->addHtml()->setHtml('.sendlane.com');
        $form->addAdvCheckbox('debug')
            ->setLabel("Debug logging\nRecord debug information in the log");
    }

    function isConfigured()
    {
        return $this->getConfig('api_key');
    }

    function getLists()
    {
        $resp = $this->doRequest('lists');
        $ret = [];
        if (isset($resp['error'])) return $ret;

        foreach ($resp as $l) {
            $ret[$l['list_id']] = ['title' => $l['list_name']];
        }
        return $ret;
    }

    function changeSubscription(User $user, array $addLists, array $deleteLists)
    {
        foreach ($addLists as $ID) {
            $this->doRequest("list-subscriber-add", [
                'first_name' => $user->name_f,
                'last_name' => $user->name_l,
                'email' => $user->email,
                'list_id' => $ID
            ]);
        }
        if ($deleteLists) {
            $this->doRequest("subscribers-delete", [
                'list_id' => implode(',', $deleteLists),
                'email' => $user->email
            ]);
        }
        return true;
    }

    function doRequest($method, $params = [])
    {
        $params['api'] = $this->getConfig('api_key');
        $params['hash'] = $this->getConfig('hash_key');

        $req = new Am_HttpRequest($this->url($method), 'POST');
        $req->addPostParameter($params);

        $resp = $req->send();
        $this->debug($req, $resp);
        if (!$body = $resp->getBody())
            return [];

        return json_decode($body, true);
    }

    function url($method)
    {
        return "https://{$this->getConfig('subdomain')}.sendlane.com/api/v1/{$method}";
    }

}