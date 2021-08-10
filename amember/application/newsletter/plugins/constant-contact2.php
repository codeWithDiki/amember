<?php

class Am_Newsletter_Plugin_ConstantContact2 extends Am_Newsletter_Plugin
{
    protected $_isDebug = false;

    function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addSecretText('apikey', array('class' => 'am-el-wide'))
            ->setLabel("Constant Contact2 API Key\n".'API v2 key')
            ->addRule('required');
        $form->addSecretText('token', array('class' => 'am-el-wide'))
            ->setLabel("Constant Contact2 Access Token")
            ->addRule('required');

        $form->addAdvCheckbox('disable_double_optin')
            ->setLabel("Disable Double Opt-in");
        $form->addAdvCheckbox('debug')
            ->setLabel("Debug logging\nRecord debug information in the log");
    }

    function isConfigured()
    {
        return strlen($this->getConfig('apikey')) && strlen($this->getConfig('token'));
    }

    /** @return Am_ConstantContact2_Api */
    function getApi()
    {
        return new Am_ConstantContact2_Api($this);
    }

    public function changeEmail(User $user, $oldEmail, $newEmail)
    {
        $contacts = $this->getApi()->sendRequest('contacts', ['email' => $oldEmail]);
        if(count($contacts['results']))
        {
            $contact = array_shift($contacts['results']);
            foreach($contact['email_addresses'] as $k => $v)
            {
                if($v['email_address'] == $oldEmail) {
                    $contact['email_addresses'][$k]['email_address'] = $newEmail;
                }
            }
            $this->getApi()->sendRequest('contacts/'.$contact['id'], $contact, Am_HttpRequest::METHOD_PUT,
                $this->getConfig('disable_double_optin') ? 'ACTION_BY_VISITOR' : 'ACTION_BY_OWNER');
            return false;
        }
    }

    public function changeSubscription(User $user, array $addLists, array $deleteLists)
    {
        $contacts = $this->getApi()->sendRequest('contacts', ['email' => $user->email]);
        if(count($contacts['results']))
        {
            $contact = array_shift($contacts['results']);
            $current = [];
            foreach($contact['lists'] as $list) {
                $current[] = $list['id'];
            }
            $lists_ = array_merge($current, $addLists);
            $lists_ = array_diff($lists_, $deleteLists);
            $lists_ = array_unique($lists_);
            $lists = [];
            foreach($lists_ as $list_id) {
                $lists[] = ['id' => $list_id];
            }
            if(!$this->getApi()->sendRequest('contacts/'.$contact['id'],
                array_merge(
                    [
                        'email_addresses' => [['email_address' => $user->email]],
                        'first_name' => $user->name_f,
                        'last_name' => $user->name_l,
                    ],
                    ($lists ? ['lists' => $lists] : [])
                ),
                Am_HttpRequest::METHOD_PUT,
                ($lists ? 'ACTION_BY_VISITOR' : 'ACTION_BY_OWNER'))) {

                return false;
            }
        } else {
            if($addLists)
            {
                $lists = [];
                foreach($addLists as $list_id) {
                    $lists[] = ['id' => $list_id];
                }
                if(!$this->getApi()->sendRequest('contacts',
                    array_merge(
                        [
                            'email_addresses' => [['email_address' => $user->email]],
                            'first_name' => $user->name_f,
                            'last_name' => $user->name_l,
                        ],
                        ($lists ? ['lists' => $lists] : [])
                    ),
                    Am_HttpRequest::METHOD_POST, $this->getConfig('disable_double_optin') ? 'ACTION_BY_VISITOR' : 'ACTION_BY_OWNER')) {

                    return false;
                }
            }
        }
        return true;
    }

    public function getLists()
    {
        $res = [];
        foreach($this->getApi()->sendRequest('lists') as $list) {
            $res[$list['id']] = [
                'title' => $list['name']
            ];
        }
        return $res;
    }

    function log($req, $resp, $title)
    {
        if (!$this->_isDebug)
            return;
        $l = $this->getDi()->invoiceLogRecord;
        $l->paysys_id = $this->getId();
        $l->title = $title;
        $l->add($req);
        $l->add($resp);
    }

}

class Am_ConstantContact2_Api extends Am_HttpRequest
{
    protected $plugin;
    protected $vars = []; // url params
    protected $params = []; // request params

    public function __construct(Am_Newsletter_Plugin_ConstantContact2 $plugin)
    {
        $this->plugin = $plugin;
        parent::__construct();
    }

    public function sendRequest($path, $params = [], $method = self::METHOD_GET, $type = 'ACTION_BY_OWNER')
    {
        $body = json_encode($params);
        if($method == self::METHOD_GET)
        {
            $params['api_key'] = $this->plugin->getConfig('apikey');
            $this->setUrl($url = "https://api.constantcontact.com/v2/{$path}?" . http_build_query($params));
        }
        else
        {
            if($params)
                $this->setBody($body);
            $this->setUrl($url = "https://api.constantcontact.com/v2/{$path}?api_key=".$this->plugin->getConfig('apikey').'&action_by='.$type);
        }
        $this->setMethod($method);
        $this->setHeader('Content-Type', 'application/json');
        $this->setHeader('Authorization', 'Bearer '.$this->plugin->getConfig('token'));
        $ret = parent::send();
        $this->plugin->debug($this, $ret);
        if($ret->getStatus() != 200)
        {
            $this->plugin->getDi()->logger->error("ConstantContact2 API Error - $url , $method , $body - [".$ret->getStatus()."]" . $ret->getBody());
            return false;
        }
        $arr = json_decode($ret->getBody(), true);
        return $arr;
    }
}