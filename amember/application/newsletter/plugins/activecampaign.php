<?php

class Am_Newsletter_Plugin_Activecampaign extends Am_Newsletter_Plugin
{
    protected $api;

    function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addAdvRadio('api_type')
            ->setLabel(___('Version of script'))
            ->loadOptions([
                '0' => ___('Downloaded on your own server'),
                '1' => ___('Hosted at Activecampaing\'s server')
            ]);
        $form->addScript()->setScript(<<<CUT
jQuery(document).ready(function() {
    function api_ch(val){
        jQuery("input[id^=api_key]").parent().parent().toggle(val == '1');
        jQuery("input[id^=api_user]").parent().parent().toggle(val == '0');
        jQuery("input[id^=api_password]").parent().parent().toggle(val == '0');
    }
    jQuery("input[type=radio]").change(function(){ api_ch(jQuery(this).val()); }).change();
    api_ch(jQuery("input[type=radio]:checked").val());
});
CUT
        );
        $form->addText('api_url', ['class' => 'am-el-wide'])
            ->setLabel("Activecampaign API url\nit should be with http://");
        $form->addSecretText('api_key', ['class' => 'am-el-wide'])
            ->setLabel('Activecampaign API Key');
        $form->addText('api_user', ['class' => 'am-el-wide'])
            ->setLabel('Activecampaign Admin Login');
        $form->addSecretText('api_password', ['class' => 'am-el-wide'])
            ->setLabel('Activecampaign Admin Password');
        $form->addAdvCheckbox('debug')
            ->setLabel("Debug logging\nRecord debug information in the log");
    }

    function isConfigured()
    {
        return ($this->getConfig('api_type')  == 0 && $this->getConfig('api_user') && $this->getConfig('api_password')) ||
            ($this->getConfig('api_type')  == 1 && $this->getConfig('api_key'));
    }

    /**
     * @return Am_Activecampaign_Api
     */
    function getApi()
    {
        return new Am_Activecampaign_Api($this);
    }

    public function changeSubscription(User $user, array $addLists, array $deleteLists, $dates = [], $update = false)
    {
        $api = $this->getApi();
        $acuser = $api->sendRequest('contact_view_email', ['email' => $user->email], Am_HttpRequest::METHOD_GET);
        if ($acuser['id'])
        {
            $lists = [];
            foreach ($addLists as $id)
            {
                $lists["p[$id]"] = $id;
                $lists["status[$id]"] = 1;
            }
            foreach ($deleteLists as $id)
            {
                $lists["p[$id]"] = $id;
                $lists["status[$id]"] = 2;
            }
            //user exists in ActiveCampaign
            $contact = [
                'id' => $acuser['subscriberid'],
                'email' => $user->email,
                'overwrite' => 0,
            ];
            if ($user->name_f) {
                $contact['first_name'] = $user->name_f;
            }
            if ($user->name_l) {
                $contact['last_name'] = $user->name_l;
            }

            $ret = $api->sendRequest('contact_edit', array_merge($contact, $lists));
            if (!$ret)
                return false;
        } else {
            if ($update)
                return;
            $lists = [];
            foreach ($addLists as $id)
            {
                $lists["p[$id]"] = $id;
                $lists["status[$id]"] = 1;
            }
            foreach ($deleteLists as $id)
            {
                $lists["p[$id]"] = $id;
                $lists["status[$id]"] = 2;
            }
            //user does no exist in ActiveCampaign
            $ret = $api->sendRequest('contact_add', array_merge([
                    'email' => $user->email,
                    'first_name' => $user->name_f,
                    'last_name' => $user->name_l
                ], $lists));
            if (!$ret)
                return false;
        }
        return true;
    }

    public function changeEmail(User $user, $oldEmail, $newEmail)
    {
        $api = $this->getApi();
        $acuser = $api->sendRequest('contact_view_email', ['email' => $oldEmail], Am_HttpRequest::METHOD_GET);
        if ($acuser['subscriberid'])
        {
            // fetch all user subscribed ARP lists, unsubscribe
            $list_ids = $this->getDi()->newsletterUserSubscriptionTable->getSubscribedIds($user->pk());
            $lists = [];
            foreach ($this->getDi()->newsletterListTable->loadIds($list_ids) as $list)
            {
                if ($list->plugin_id != $this->getId()) continue;
                $id = $list->plugin_list_id;
                $lists["p[$id]"] = $id;
                $lists["status[$id]"] = 1;
            }
            //user exists in ActiveCampaign
            $contact = [
                'id' => $acuser['subscriberid'],
                'email' => $newEmail,
                'overwrite' => 0,
            ];
            if ($user->name_f) {
                $contact['first_name'] = $user->name_f;
            }
            if ($user->name_l) {
                $contact['last_name'] = $user->name_l;
            }
            $ret = $api->sendRequest('contact_edit', array_merge($contact, $lists));
        }
    }

    function onUserAfterUpdate(Am_Event_UserAfterUpdate $event)
    {
        $user = $event->getUser();
        $this->changeSubscription($user, [], [], [], true);
    }

    public function getLists()
    {
        $api = $this->getApi();
        $ret = [];
        $lists = $api->sendRequest('list_list', ['ids' => 'all'], Am_HttpRequest::METHOD_GET);
        foreach ($lists as $l)
        {
            $ret[$l['id']] = [
                'title' => $l['name'],
            ];
        }
        return $ret;
    }
}

class Am_Activecampaign_Api extends Am_HttpRequest
{
    /** @var Am_Newsletter_Plugin */
    protected $plugin;
    protected $vars = []; // url params
    protected $params = []; // request params

    public function __construct(Am_Newsletter_Plugin $plugin)
    {
        $this->plugin = $plugin;
        parent::__construct();
    }

    public function sendRequest($api_action, $params, $method = self::METHOD_POST)
    {
        $this->setMethod($method);
        $this->setHeader('Expect', '');

        $this->params = $params;
        if ($this->plugin->getConfig('api_type') == 0) {
            $this->vars['api_user'] = $this->plugin->getConfig('api_user');
            $this->vars['api_pass'] = $this->plugin->getConfig('api_password');
        } else {
            $this->vars['api_key'] = $this->plugin->getConfig('api_key');
        }
        $this->vars['api_action'] = $api_action;
        $this->vars['api_output'] = 'serialize';

        if ($method == self::METHOD_POST) {
            $this->addPostParameter(array_merge($this->vars, $this->params));
            $url = $this->plugin->getConfig('api_url') . '/admin/api.php?api_action=' . $this->vars['api_action'];
        } else {
            $url = $this->plugin->getConfig('api_url') . '/admin/api.php?' . http_build_query($this->vars + $this->params, '', '&');
        }
        $this->setUrl($url);
        $ret = parent::send();
        $this->plugin->debug($this, $ret, $url);

        if (!in_array($ret->getStatus(), [200,404])) {
            throw new Am_Exception_InternalError("Activecampaign API Error, configured API Key is wrong");
        }
        $arr = unserialize($ret->getBody());
        if (!$arr)
            throw new Am_Exception_InternalError("Activecampaign API Error - unknown response [" . $ret->getBody() . "]");
        unset($arr['result_code'], $arr['result_message'], $arr['result_output']);
        return $arr;
    }
}