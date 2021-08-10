<?php

class Am_Newsletter_Plugin_Convertkit extends Am_Newsletter_Plugin
{
    const CK_SUBSCRIBER_ID = 'ck-subscriber-id';

    function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addSecretText('api_key', array('size' => 30))
            ->setLabel('Convertkit API Key')
            ->addRule('required');

        $form->addSecretText('api_secret', array('size' => 50))
            ->setLabel('Convertkit API Secret')
            ->addRule('required');
        $form->addAdvCheckbox('disable_unsubscribe')->setLabel('Do not unsubscribe
        due to API limitations, in order to unsubscribe user even from one  list,
        plugin first try to unsubscribe user completely and then add other subscriptions back.
        That could reset automations for lists that user should have subscribed.
        When you disable unsubscribe, user will not be removed from lists, instead,
        plugin will remove tags associated with lists');
        $form->addAdvCheckbox('debug')
            ->setLabel("Debug logging\nRecord debug information in the log");
    }

    /** @return Am_Newsletter_Plugin_Convertkit */
    protected function getApi()
    {
        return new Am_Convertkit_Api($this);
    }

    public function isConfigured()
    {
        return (bool) $this->getConfig('api_key') && $this->getConfig('api_secret');
    }

    public function onUserAfterUpdate(Am_Event_UserAfterUpdate $event)
    {
        $user = $event->getUser();
        if(!($subscriberId = $user->data()->get(self::CK_SUBSCRIBER_ID)))
        {
            return;
        }

        $oldUser = $event->getOldUser();
        $vars = array();
        if($user->email != $oldUser->email) $vars['email_address'] = $user->email;
        if($user->name_f != $oldUser->name_f) $vars['first_name'] = $user->name_f;
        if(!empty($vars))
        {
            $vars['state'] = 'active';
            $this->getApi()->update($subscriberId, $vars);
        }
    }
    function getTagsForList($list_id)
    {
        $list = $this->getDi()->newsletterListTable->findFirstBy(['plugin_id' => $this->getId(), 'plugin_list_id' => $list_id]);
        
        if(!$list)
            return [];
        
        $vars = $list->getVars();
        
        if(empty($vars['convertkit-tags']))
            return [];
        
        return (array)$vars['convertkit-tags'];
    }

    public function changeSubscription(User $user, array $addLists, array $deleteLists)
    {
        $api = $this->getApi();
        if(!empty($deleteLists) && !$this->getConfig('disable_unsubscribe'))
        {
            $list_ids = $this->getDi()->newsletterUserSubscriptionTable->getSubscribedIds($user->pk());
            foreach ($this->getDi()->newsletterListTable->loadIds($list_ids) as $list)
            {
                if (
                    $list->plugin_id == $this->getId()
                    && !in_array($list->plugin_list_id, $addLists)
                    && !in_array($list->plugin_list_id, $deleteLists)
                ) {
                    $addLists[] = $list->plugin_list_id;
                }
            }
            if (!$api->unsubscribe($user->email))
                return false;
        }
        if(!empty($deleteLists))
        {
            foreach($deleteLists as $list_id)
            {
                foreach($this->getTagsForList($list_id) as $tag_id)
                {
                    $this->getApi()->removeTag($tag_id, $user->data()->get(self::CK_SUBSCRIBER_ID));
                }
            }
        }

        foreach ($addLists as $list_id)
        {
            $ret = $api->subscribe($list_id, $user->email, $user->name_f);
            if (!$ret)
            {
                return false;
            }
            if(!$user->data()->get(self::CK_SUBSCRIBER_ID))
                $user->data()->set(self::CK_SUBSCRIBER_ID, $ret['subscription']['subscriber']['id'])->update();
            
            foreach($this->getTagsForList($list_id) as $tag_id){
                $this->getApi()->addTag($tag_id, $user->email);
            }
        }
        return true;
    }

    public function getLists()
    {
        $res = $this->getApi()->getFormsList();
        $ret = array();
        foreach ($res['forms'] as $f)
        {
            $ret[$f['id']] = array('title' => $f['name']);
        }
        return $ret;
    }
    function onGridNewsletterInitGrid(Am_Event_Grid $event)
    {
        $grid = $event->getGrid();
        $grid->addCallback(Am_Grid_Editable::CB_VALUES_FROM_FORM, function(&$values, NewsletterList $record){
            if(isset($values['_tags']))
            {
                $vars = $record->getVars();
                $vars['convertkit-tags'] = $values['_tags'];
                $record->setVars($vars);
            }
            
        });
        $grid->addCallback(Am_Grid_Editable::CB_VALUES_TO_FORM, function(&$values, NewsletterList $record){
            $vars = $record->getVars();
            $values['_tags'] = !empty($vars['convertkit-tags'])?$vars['convertkit-tags'] : [];
        });
        
        $grid->addCallback(Am_Grid_Editable::CB_INIT_FORM, function(Am_Form_Admin $form) use($grid){
            $list = $grid->getRecord();
            if($list && ($list->plugin_id == $this->getId()) && $list->isLoaded()){
                $list_id = $list->plugin_list_id;
                $ret = $this->getTagOptions();
                if(!empty($ret)){
                    
                    $fs = $form->addAdvFieldset();
                    $fs->setLabel(___('Define Tags Integration'));
                    $fs->addSelect('_tags')
                        ->setLabel(___('Add tag'))
                        ->loadOptions($ret);
                    
                }
            }
            
        });
        
    }
    function getTagOptions()
    {
        $api = $this->getApi();
        $ret = $api->getTags();
        $options = ['' => ___('-- Please select tag --')];
        if(!empty($ret['tags'])){
            foreach($ret['tags'] as $tag){
                $options[$tag['id']] = $tag['name'];
            }
        }
        return $options;
    }
    

}

class Am_Convertkit_Api extends Am_HttpRequest
{
    /** @var $plugin Am_Newsletter_Plugin_Convertkit */
    protected $plugin;
    const API_URL = 'https://api.convertkit.com/v3/';

    protected $vars = array(); // url params
    protected $params = array(); // request params

    public function __construct(Am_Newsletter_Plugin_Convertkit $plugin)
    {
        $this->plugin = $plugin;
        parent::__construct();
    }

    public function send()
    {
        $ret = parent::send();
        $this->plugin->debug($this, $ret);
        if (!in_array($ret->getStatus(), array(200, 201)))
        {
            $this->plugin->getDi()->logger->error("Convertkit API Error - wrong status [{$ret->getStatus()}]; response [" . $ret->getBody() . "]");
            return false;
        }
        $arr = json_decode($ret->getBody(), true);
        if (!$arr)
        {
            $this->plugin->getDi()->logger->error("Convertkit API Error - unknown response [" . $ret->getBody() . "]");
            return false;
        }
        return $arr;
    }

    public function sendPut($url, $vars)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($vars));
        $resp = curl_exec($ch);
        curl_close($ch);

        if (!$resp)
        {
            $this->plugin->getDi()->logger->error("Convertkit API Error - null response");
            return false;
        }
        $arr = json_decode($resp, true);
        if (!$arr)
        {
            $this->plugin->getDi()->logger->error("Convertkit API Error - unknown response [$resp]");
            return false;
        }
        return $arr;
    }

    public function getFormsList()
    {
        $this->setMethod(self::METHOD_GET);
        $this->setUrl(self::API_URL . "forms?api_key=" . $this->plugin->getConfig('api_key'));
        return $this->send();
    }
    public function getTags()
    {
        $this->setMethod(self::METHOD_GET);
        $this->setUrl(self::API_URL . "tags?api_key=" . $this->plugin->getConfig('api_key'));
        return $this->send();
    }

    public function getSubscriberList($vars = array())
    {
        $this->setMethod(self::METHOD_GET);
        $vars['api_secret'] = $this->plugin->getConfig('api_secret');
        $this->setUrl(self::API_URL . "subscribers?" . http_build_query($vars, '', '&'));
        return $this->send();
    }

    public function getSubscriber($subscriberId)
    {
        $this->setMethod(self::METHOD_GET);
        $vars['api_secret'] = $this->plugin->getConfig('api_secret');
        $this->setUrl(self::API_URL . "subscribers/$subscriberId/?" . http_build_query($vars, '', '&'));
        return $this->send();
    }

    public function subscribe($fId, $email, $fName)
    {
        $this->setMethod(self::METHOD_POST);
        $this->setUrl(self::API_URL . "forms/$fId/subscribe?api_key=" . $this->plugin->getConfig('api_key'));
        $this->addPostParameter(array(
            'email' => $email,
            'name' => $fName,
            'state' => 'active',
        ));
        return $this->send();
    }
    public function addTag($tag_id, $email)
    {
        $this->setMethod(self::METHOD_POST);
        $this->setUrl(self::API_URL . "tags/$tag_id/subscribe?api_key=".$this->plugin->getConfig('api_key'));
        $this->addPostParameter([
            'email' => $email,
            'api_key' => $this->plugin->getConfig('api_key')
        ]);
        return $this->send();
    }
    public function removeTag($tag_id, $subscriber_id)
    {
        $this->setMethod(self::METHOD_DELETE);
        $this->setUrl(self::API_URL . "subscribers/{$subscriber_id}/tags/{$tag_id}?api_secret=".$this->plugin->getConfig('api_secret'));
        return $this->send();
    }
    

    public function unsubscribe($email)
    {
        return $this->sendPut(self::API_URL . "unsubscribe?api_secret=" . $this->plugin->getConfig('api_secret'), array('email' => $email));
    }

    public function update($subscriberId, $vars)
    {
        return $this->sendPut(self::API_URL . "subscribers/$subscriberId?api_secret=" . $this->plugin->getConfig('api_secret'), $vars);
    }
}