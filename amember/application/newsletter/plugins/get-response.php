<?php

class Am_Newsletter_Plugin_GetResponse extends Am_Newsletter_Plugin
{
    const ENDPOINT = 'http://api2.getresponse.com';

    function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addSecretText('api_key', ['class' => 'am-el-wide'])
            ->setLabel("API Key\n" .
                'You can get your API Key <a target="_blank" rel="noreferrer" href="https://app.getresponse.com/manage_api.html">here</a>')
            ->addRule('required');
        $form->addAdvCheckbox('360', ['id' => 'get-response-360'])
            ->setLabel('I have GetResponse360 Account');
        $form->addText('api_url', ['class' => 'am-el-wide am-row-required', 'id' => 'get-response-360-url'])
            ->setLabel("API URL\n" .
                "contact your GetResponse360 account manager to get API URL");
        $form->addAdvCheckbox('debug')
            ->setLabel("Debug logging\nRecord debug information in the log");
        $form->addScript()
            ->setScript(<<<CUT
jQuery(function(){
    jQuery('#get-response-360').change(function(){
        jQuery('#get-response-360-url').closest('.am-row').toggle(this.checked);
    }).change();
})
CUT
                );
        $form->addRule('callback', 'API URL is required for GetResponse360 account', function($v) {
            return !($v["newsletter.{$this->getId()}.360"] && !$v["newsletter.{$this->getId()}.api_url"]);
        });
    }

    function  isConfigured()
    {
        return (bool)$this->getConfig('api_key');
    }

    function getApi()
    {
        $endpoint = $this->getConfig('360') ? $this->getConfig('api_url') : self::ENDPOINT;
        return new Am_GetResponse_Api($this->getConfig('api_key'), $endpoint, $this);
    }

    function changeEmail(User $user, $oldEmail, $newEmail)
    {
        $lists = $this->getDi()->newsletterUserSubscriptionTable->getSubscribedIds($user->pk());
        $campaigns = [];
        foreach($lists as $v){
            $list = $this->getDi()->newsletterListTable->load($v);
            $campaigns[] = $list->plugin_list_id;
        }

        $user->email = $oldEmail;
        $this->changeSubscription($user, [], $campaigns);
        $user->email = $newEmail;
        $this->changeSubscription($user, $campaigns, []);
    }

    public function changeSubscription(User $user, array $addLists, array $deleteLists)
    {
        $api = $this->getApi();
        foreach ($addLists as $list_id)
        {
            try{
                $res = $api->call('get_contacts', [
                    "campaigns" => [$list_id],
                    'email' => [
                        'EQUALS' => $user->email
                    ]
                ]);

                if(empty($res))
                {
                    $api->call('add_contact', [
                        'campaign' => $list_id,
                        'name' => $user->getName() ? $user->getName() : $user->login,
                        'email' => $user->email,
                        'cycle_day' => 0,
                        'ip' => filter_var($user->remote_addr, FILTER_VALIDATE_IP, ['flags' => FILTER_FLAG_IPV4]) ? $user->remote_addr : (filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, ['flags' => FILTER_FLAG_IPV4]) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1')
                    ]);
                }
            }
            catch(Am_Exception_InternalError $e)
            {
                if(
                    (strpos($e->getMessage(), 'Contact already added to target campaign')=== false)
                    &&
                    (strpos($e->getMessage(), 'Contact already queued for target campaign')===false)
                    )
                    throw $e;

            }
        }

        if (!empty($deleteLists)) {
            $res = $api->call('get_contacts', [
                "campaigns" => $deleteLists,
                'email' => [
                        'EQUALS' => $user->email
                ]
            ]);

            foreach ($res as $id => $contact) {
                $api->call('delete_contact', [
                    'contact' => $id
                ]);
            }
        }

        return true;
    }

    public function getLists()
    {
        $api = $this->getApi();
        $ret = [];
        $lists = $api->call('get_campaigns');
        foreach ($lists as $id => $l)
            $ret[$id] = [
                'title' => $l['name'],
            ];
        return $ret;
    }
}

class Am_GetResponse_Api extends Am_HttpRequest
{
    protected $api_key = null, $endpoint = null;
    protected $lastId = 1;
    protected $plugin;

    public function __construct($api_key, $endpoint, $plugin)
    {
        $this->api_key = $api_key;
        $this->endpoint = $endpoint;
        $this->plugin = $plugin;
        parent::__construct($this->endpoint, self::METHOD_POST);
    }

    public function call($method,  $params = null)
    {
        $this->setBody(json_encode($this->prepCall($method, $params)));
        $this->setHeader('Expect', '');
        $ret = parent::send();
        $this->plugin->debug($this, $ret);
        if ($ret->getStatus() != '200')
            throw new Am_Exception_InternalError("GetResponse API Error, is configured API Key is wrong");

        $arr = json_decode($ret->getBody(), true);
        if (!$arr)
            throw new Am_Exception_InternalError("GetResponse API Error - unknown response [" . $ret->getBody() . "]");

        if (isset($arr['error']))
            throw new Am_Exception_InternalError("GetResponse API Error - {$arr['error']['code']} : {$arr['error']['message']}");

        return $arr['result'];
    }

    protected function prepCall($method, $params = null) {
        $p = [$this->api_key];
        if (!is_null($params)) array_push($p, $params);

        $call = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $p,
            'id' => $this->lastId++
        ];

        return $call;
    }
}