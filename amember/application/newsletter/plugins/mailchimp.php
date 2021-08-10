<?php

class Am_Newsletter_Plugin_Mailchimp extends Am_Newsletter_Plugin
{
    function getStoreId()
    {
        return $this->getDi()->security->siteHash('MAILCHIMP-STORE');
    }

    function _initSetupForm(Am_Form_Setup $form)
    {
        $el = $form->addSecretText('api_key', ['class' => 'am-el-wide'])->setLabel("MailChimp API Key");
        $el->addRule('required');
        $el->addRule('regex', 'API Key must be in form xxxxxxxxxxxx-xxx', '/^[a-zA-Z0-9]+-[a-zA-Z0-9]{2,4}$/');
        $form->addAdvCheckbox('disable_double_optin')->setLabel("Disable Double Opt-in\n"
            . 'read more <a href="http://kb.mailchimp.com/article/how-does-confirmed-optin-or-double-optin-work" target="_blank" rel="noreferrer" class="link">on mailchimp site</a>');

        if($this->isConfigured()) {
            $form->addAdvCheckbox('ecommerce', ['id'=>'ecommerce-trigger'])->setLabel(___('Enable Ecommerce Tracking'));
            $fs = $form->addFieldset('', ['id'=>'ecommerce-fieldset'])->setLabel('Store Configuration');
            $form->addScript()->setScript(<<<CUT
    jQuery(document).ready(function(){
        jQuery("#ecommerce-trigger").on('change', function(){
            jQuery("#ecommerce-fieldset").toggle(jQuery(this).is(':checked'));
        }).change();
        
    });
CUT
);
            $req = $this->getApi()->sendRequest('/ecommerce/stores/'.$this->getStoreId());
            if(@$req['status'] == 404)
            {
                if($this->getConfig('_list'))
                {

                    $resp = $this->getApi()->sendRequest('/ecommerce/stores', [
                        'id'=> $this->getStoreId(),
                        'list_id' => $this->getConfig('_list'),
                        'name' => $this->getDi()->config->get('site_title'),
                        'platform' => 'aMember PRO',
                        'currency_code' => $this->getDi()->config->get('currency'),
                    ], Am_HttpRequest::METHOD_POST);
                    if(!@$resp['id'])
                    {
                        $fs->addHtml()->setHtml("<div class='error'>Unable to create Store</div>")->setLabel(___('Store Setup'));
                    }

                }
                else
                {
                    $gr = $fs->addGroup()->setLabel(___('Setup Store'));
                    $gr->addHtml()->setHtml(<<<CUT
<div>Mailchimp require that each store should be associated with single audience(list).
Each list could have multiple stores associated, but store is assigned to single list, and that list can't be changed later.
Please select list and click save.</div>
CUT
                    );
                    $gr->addSelect('_list', ['id' => 'mailchimp-list'])->loadOptions($this->getListOptions());
                }
            }else
                {
                    $fs->addHtml()->setHtml("<div class='info'>Store is configured</div>")->setLabel(___('Store Setup'));


            }
        }
        $form->addAdvCheckbox('debug')
            ->setLabel("Debug logging\nRecord debug information in the log");
    }
    /** @return Am_Plugin_Mailchimp */
    function getApi()
    {
        return new Am_Mailchimp_Api($this);
    }

    function isConfigured()
    {
        return (bool) $this->getConfig('api_key');
    }

    function onGridProductInitForm(Am_Event_Grid $event)
    {
        $grid = $event->getGrid();
        $form = $grid->getForm();
        $grid->addCallback(Am_Grid_Editable::CB_VALUES_FROM_FORM, function($values, $record){
            $record->data()->setBlob('mailchimp-segments', json_encode($values['_mailchimp_segments']));
        });
        $grid->addCallback(Am_Grid_Editable::CB_VALUES_TO_FORM, function(&$values, $record){
            $values['_mailchimp_segments'] = json_decode($record->data()->getBlob('mailchimp-segments')?:'[]', true);
        });

        $form->addMagicSelect('_mailchimp_segments', ['class' => 'am-combobox'])->setLabel('Mailchimp List Segments
        tags users when user makes purchase of this product
        user will be tagged only if account  is suppose to be added
        to list according to aMember CP -> Protect Content -> Newsletters configuration')
        ->loadOptions($this->getListTagOptions());
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

    function getMergeFields(User $user, $list_id)
    {
        $default = [
                'FNAME' => $user->name_f,
                'LNAME' => $user->name_l,
                'LOGIN' => $user->login
            ];
        $list = $this->getDi()->newsletterListTable->findFirstBy(['plugin_id' => $this->getId(), 'plugin_list_id' => $list_id]);
        if(!$list)
            return $default;

        $vars = $list->getVars();
        if(empty($vars['mailchimp-merge-vars']))
            return $default;

        $ret = [];
        foreach($vars['mailchimp-merge-vars'] as $k=>$v){
            $ret[$k] = $user->get($v)?:$user->data()->get($v);
        }

        return array_filter($ret);

    }

    function getMergeTagsForList($list_id)
    {
        $list = $this->getDi()->newsletterListTable->findFirstBy(['plugin_id' => $this->getId(), 'plugin_list_id' => $list_id]);

        if(!$list)
            return [];

        $vars = $list->getVars();

        if(empty($vars['mailchimp-merge-tags']))
            return [];

        return $vars['mailchimp-merge-tags'];
    }

    function getTagsForProduct($product_id)
    {
        $product = $this->getDi()->productTable->load($product_id);
        $tags = $product->data()->getBlob('mailchimp-segments');
        if($tags)
        {
            return json_decode($tags, true);
        }
        return [];
    }
    function onSubscriptionChanged(Am_Event_SubscriptionChanged $event)
    {
        $user = $event->getUser();

        foreach($event->getAdded() as $product_id)
        {
            $tags = $this->getTagsForProduct($product_id);
            foreach($tags as $_)
            {
                [$list_id, $tag] = explode('-', $_);
                $r = $this->getApi()->sendRequest($url = "lists/{$list_id}/segments/{$tag}/members", [
                    'email_address' => $user->email
                ], Am_HttpRequest::METHOD_POST);

            }
        }
        foreach($event->getDeleted() as $product_id)
        {
            $tags = $this->getTagsForProduct($product_id);
            foreach($tags as $_)
            {
                [$list_id, $tag] = explode('-', $_);
                $this->getApi()->sendRequest("lists/{$list_id}/segments/{$tag}/members/".md5(strtolower($user->email)),null, Am_HttpRequest::METHOD_DELETE);
            }
        }

    }

    public function addUserViaApi(User $user, $list_id)
    {
        $ret = $this->getApi()->sendRequest("lists/$list_id/members/" . md5(strtolower($user->email)), [
            'status' => $this->getConfig('disable_double_optin') ? 'subscribed' : 'pending',
            'status_if_new' => $this->getConfig('disable_double_optin') ? 'subscribed' : 'pending',
            'email_address' => $user->email,
            'merge_fields' => $this->getMergeFields($user, $list_id),
            'skip_merge_validation' => true
        ], Am_HttpRequest::METHOD_PUT);
        return (bool)$ret;
    }

    public function changeSubscription(User $user, array $addLists, array $deleteLists)
    {
        foreach ($addLists as $list_id)
        {
            if (!$this->addUserViaApi($user, $list_id)) return false;

            if($tags = $this->getMergeTagsForList($list_id))
            {
                foreach($tags as $tag) {
                    $r = $this->getApi()->sendRequest($url = "lists/{$list_id}/segments/{$tag}/members", [
                        'email_address' => $user->email
                    ], Am_HttpRequest::METHOD_POST);
                }
            }

        }
        foreach ($deleteLists as $list_id)
        {
            $ret = $this->getApi()->sendRequest("lists/$list_id/members/" . md5(strtolower($user->email)), [
                'status' => 'unsubscribed',
                'status_if_new' => 'unsubscribed',
                'email_address' => $user->email,
            ], Am_HttpRequest::METHOD_PUT);
            if (!$ret) return false;

            if($tags = $this->getMergeTagsForList($list_id))
            {
                foreach($tags as $tag)
                    $this->getApi()->sendRequest("lists/{$list_id}/segments/{$tag}/members/".md5(strtolower($user->email)),null, Am_HttpRequest::METHOD_DELETE);
            }

        }
        return true;
    }

    public function getLists()
    {
        $api = $this->getApi();
        $ret = [];
        $lists = $api->sendRequest('lists', ['count' => '1000']);
        foreach ($lists['lists'] as $l)
            $ret[$l['id']] = [
                'title' => $l['name'],
            ];
        return $ret;
    }

    function getListTagOptions()
    {
        $options = [];
        foreach($this->getLists() as $list_id => $list)
        {
            foreach($this->getTagOptions($list_id) as $tag_id => $tag_name)
            {
                $options["{$list_id}-{$tag_id}"] = $list['title']."/".$tag_name;
            }
        }
        return $options;
    }



    function onGridNewsletterInitGrid(Am_Event_Grid $event)
    {
        $grid = $event->getGrid();
        $grid->addCallback(Am_Grid_Editable::CB_VALUES_FROM_FORM, function(&$values, NewsletterList $record){
            if(isset($values['_merge_vars']))
            {
                $vars = $record->getVars();
                $vars['mailchimp-merge-vars'] = $values['_merge_vars'];
                $record->setVars($vars);
            }
            if(isset($values['_merge_tags']))
            {
                $vars = $record->getVars();
                $vars['mailchimp-merge-tags'] = $values['_merge_tags'];
                $record->setVars($vars);
            }

        });
        $grid->addCallback(Am_Grid_Editable::CB_VALUES_TO_FORM, function(&$values, NewsletterList $record){
            $vars = $record->getVars();
            $values['_merge_vars'] = !empty($vars['mailchimp-merge-vars'])?$vars['mailchimp-merge-vars'] : ['FNAME' => 'name_f', 'LNAME' => 'name_l', 'LOGIN'=>'login'];
            $values['_merge_tags'] = !empty($vars['mailchimp-merge-tags'])?$vars['mailchimp-merge-tags'] : [];
        });

        $grid->addCallback(Am_Grid_Editable::CB_INIT_FORM, function(Am_Form_Admin $form) use($grid){
            $list = $grid->getRecord();
            if($list && ($list->plugin_id == $this->getId()) && $list->isLoaded()){
                $list_id = $list->plugin_list_id;
                $ret = $this->getApi()->sendRequest("lists/{$list_id}/merge-fields", [], Am_HttpRequest::METHOD_GET);
                if(!empty($ret)){
                    $mFields = [];
                    foreach($ret['merge_fields'] as $field){
                        $mFields[$field['tag']]  = "Mailchimp: ".$field['name'];
                    }
                    $amFields = [
                        "" => "-- Please Select --",
                        "login" => 'aMember: Username (login)',
                        "email" => 'aMember: E-Mail Address (email)',
                        "name_f" => 'aMember: First Name (name_f)',
                        "name_l" => 'aMember: Last Name (name_l)',
                        "street" => 'aMember: Street Address (street)',
                        "street2" => 'aMember: Street Address, Second Line (street2)',
                        "city" => 'aMember: City (city)',
                        "state" => 'aMember: State (state)',
                        "zip" => 'aMember: ZIP Code (zip)',
                        "country" => 'aMember: Country (country)',
                        "phone" => 'aMember: Phone Number (phone)',
                    ];
                    foreach ($this->getDi()->userTable->customFields()->getAll() as $fk => $fv) {
                        $amFields[$fk] = sprintf('aMember: %s (%s)', $fv->title, $fk);
                    }
                    $fs = $form->addAdvFieldset();
                    $fs->setLabel(___('Define Merge Fields Integration'));

                    foreach($mFields as $k=>$v)
                    {
                        $fs->addSelect("_merge_vars[{$k}]")
                            ->setLabel(sprintf("%s [%s]", $v, $k))
                            ->loadOptions($amFields);
                        ;
                    }
                }
                $ret = $this->getTagOptions($list_id);
                if(!empty($ret)){

                    $fs = $form->addAdvFieldset();
                    $fs->setLabel(___('Define Tags/Segments Integration'));
                    $fs->addMagicSelect('_merge_tags')
                        ->setLabel(___('Add these tags'))
                        ->loadOptions($ret);

                }
            }

        });

    }

    function getTagOptions($list_id){
        $ret = $this->getDi()->cacheFunction->call(function($list_id) {
            return $this->getApi()->sendRequest("lists/{$list_id}/segments", ['count' => 100], Am_HttpRequest::METHOD_GET);
        }, [$list_id], [], 60);

        $options = [];
        if(!empty($ret) && !empty($ret['segments'])) {

            $options = [];
            foreach ($ret['segments'] as $k => $v) {
                $options[$v['id']] = $v['name'];
            }
        }
        return $options;

    }
    public function onInitFinished()
    {
        // Do not track anything for admin pages or if tracking is disabled.
        if(defined('AM_ADMIN') || !$this->getConfig('ecommerce_tracking'))
            return;

        // mailchimp send two variables via get: mc_cid, and mc_eid both should be set in cookies.
        if($mc_cid =$this->getDi()->request->getFiltered('mc_cid'))
            Am_Cookie::set ('mc_cid', $mc_cid, time()+3600*24*30);

        if($mc_eid =$this->getDi()->request->getFiltered('mc_eid'))
            Am_Cookie::set ('mc_eid', $mc_eid, time()+3600*24*30);
    }

    public function onUserAfterInsert(Am_Event_UserAfterInsert $e)
    {
        parent::onUserAfterInsert($e);

        if($this->getConfig('ecommerce') && $this->getDi()->request->getCookie('mc_cid') && $this->getDi()->request->getCookie('mc_eid'))
        {
            $user = $e->getUser();
            $user->data()
                ->set('mc_cid', $this->getDi()->request->getCookie('mc_cid'))
                ->set('mc_eid', $this->getDi()->request->getCookie('mc_eid'))
                ->update();
        }

    }

    function onSignupUserUpdated(Am_Event $event)
    {
        if($this->getConfig('ecommerce') && $this->getDi()->request->getCookie('mc_cid') && !$event->getUser()->data()->get('mc_cid'))
        {
            $user = $event->getUser();
            $user->data()
                ->set('mc_cid', $this->getDi()->request->getCookie('mc_cid'))
                ->update();
        }

    }

    public function getVar(User $user, $name)
    {
        return ($var = $user->data()->get($name)) ? $var : $this->getDi()->request->getCookie($name);
    }

    function onPaymentAfterInsert(Am_Event_PaymentAfterInsert $e)
    {
        $payment = $e->getPayment();
        $user = $e->getUser();
        if(!$this->getConfig('ecommerce')) return;

        $ret = $this->getApi()->sendRequest(
            $url = "lists/".$this->getConfig('_list')."/members/" . md5(strtolower($user->email)),
            [],
            Am_HttpRequest::METHOD_GET
        );
        if(empty($ret['id']))
            return;

        $lines = [];
        foreach($payment->getInvoice()->getItems() as $invoiceItem)
        {
            $lines[] = [
                'id' => (string)$invoiceItem->invoice_item_id,
                'product_id' => (string)$invoiceItem->item_id,
                'product_variant_id' => (string)$invoiceItem->billing_plan_id,
                'quantity' => (int)$invoiceItem->qty,
                'price' => $invoiceItem->first_price,
                'discount' => $invoiceItem->first_discount
            ];
            $product = $invoiceItem->tryLoadProduct();
            if(empty($product))
                continue;
            if(!$product->data()->get('mailchimp-id')){
                $variants = [];
                foreach($product->getBillingPlans() as $bp){
                    /**
                     * @var BillingPlan $bp
                     */
                    $variants[] = [
                        'id' => (string) $bp->pk(),
                        'title' => $bp->title,
                        'price' => $bp->first_price
                    ];
                }
                $prResp = $this->getApi()->sendRequest('/ecommerce/stores/'.$this->getStoreId().'/products', [
                    'id' => (string) $product->getProductId(),
                    'title' => $product->getTitle(),
                    'description' => $product->getDescription(),
                    'variants' => $variants
                ], Am_HttpRequest::METHOD_POST);

                if(@$prResp['id'])
                {
                    $product->data()->set('mailchimp-id', $prResp['id'])->update();
                }
            }
        }
        $data = [
            'id' =>(string) $payment->invoice_payment_id,
            'customer' => [
                'id' => (string)$user->user_id,
                'email_address' => $user->email,
                'first_name'=> $user->name_f,
                'last_name' => $user->name_l,
                'opt_in_status' => false
            ],
            'financial_status' => 'paid',
            'currency_code' => $payment->currency,
            'order_total' => $payment->amount,
            'discount_total' => $payment->discount,
            'tax_total' => $payment->tax,
            'processed_at_foreign' => $payment->dattm,
            'lines' => $lines
        ];
        if($mc_cid = $this->getVar($user, 'mc_cid'))
        {
            $data['campaign_id'] = $mc_cid;
        }
        $req = $this->getApi()->sendRequest('/ecommerce/stores/'.$this->getStoreId().'/orders', $data, Am_HttpRequest::METHOD_POST);

    }

    public function onGetFlexibleActions(Am_Event $event)
    {
        try
        {
            $tags = $this->getDi()->cacheFunction->call([$this, 'getListTagOptions'], [], [], 60);
        } catch (Am_Exception $e) {
            return;
        }
        $lists = [];
        foreach ($tags as $list_tag => $title)
        {
            [$list_id, $tag_id] = explode('-', $list_tag, 2);
            if (empty($lists[$list_id]))
            {
                $t = preg_replace('#\/.+#', '', $title);
                $event->addReturn(new Am_FlexibleAction_MailchimpListAdd($list_id, $t, $this));
                $event->addReturn(new Am_FlexibleAction_MailchimpListDelete($list_id, $t, $this));
                $lists[$list_id] = true;
            }
            $event->addReturn(new Am_FlexibleAction_MailchimpTagAdd($list_id, $tag_id, $title, $this));
            $event->addReturn(new Am_FlexibleAction_MailchimpTagDelete($list_id, $tag_id, $title, $this));
        }
    }
}

class Am_Mailchimp_Api extends Am_HttpRequest
{
    /** @var Am_Plugin_Mailchimp */
    protected $plugin;
    protected $vars = []; // url params
    protected $params = []; // request params

    public function __construct(Am_Newsletter_Plugin_Mailchimp $plugin)
    {
        $this->plugin = $plugin;
        parent::__construct();
    }
    public function sendRequest($path, $params = [], $method = self::METHOD_GET)
    {
        [$_, $server] = explode('-', $this->plugin->getConfig('api_key'), 2);

        $server = filterId($server);

        if (empty($server))
            throw new Am_Exception_Configuration("Wrong API Key set for MailChimp");

        $url = "https://{$server}.api.mailchimp.com/3.0/".$path;

        $this->setMethod($method == self::METHOD_GET ? self::METHOD_GET : self::METHOD_POST);

        if(!in_array($method, [self::METHOD_POST, self::METHOD_GET])){
            $this->setHeader('X-HTTP-Method-Override', $method);
        }

        $this->setAuth('anystring', $this->plugin->getConfig('api_key'));
        $this->setHeader('Content-Type', 'application/json');

        if($method == self::METHOD_GET) {
            $this->setUrl($url . '?' . http_build_query($params));
        } else {
            $this->setUrl($url);
            if($params)
                $this->setBody(json_encode($params));
        }
        $ret = parent::send();
        $this->plugin->debug($this, $ret);
        $arr = json_decode($ret->getBody(), true);
        if (!$arr && ($method != self::METHOD_DELETE)) {
            Am_Di::getInstance()->logger->error("MailChimp API Error - unknown response [" . $ret->getBody() . "]");
            return false;
        }
        if(isset($arr['errors']))
        {
            Am_Di::getInstance()->logger->error("MailChimp API Error - " . json_encode($arr['errors']));
            return false;
        }
        if(isset($arr['error']))
        {
            Am_Di::getInstance()->logger->error("MailChimp API Error - [" . $arr['error'] ."]");
            return false;
        }
        return $arr;
    }
}

if (interface_exists('Am_FlexibleAction_Interface', true)):

abstract class Am_FlexibleAction_MailchimpTag implements Am_FlexibleAction_Interface
{
    protected $list_id;
    /**
     * @var Am_Newsletter_Plugin_Mailchimp
     */
    protected $plugin;

    function __construct($list_id, $title, Am_Newsletter_Plugin_Mailchimp $plugin)
    {
        $this->list_id = $list_id;
        $this->title = $title;
        $this->plugin = $plugin;
    }
    function commit() {
    }
}

class Am_FlexibleAction_MailchimpListAdd extends Am_FlexibleAction_MailchimpTag
{
    function getTitle()
    {
        return "MailChimp: Add to List: " . Am_Html::escape($this->title);
    }
    function getId() { return 'mailchimp-list-add-' . $this->list_id; }
    function run(User $user)
    {
        $this->plugin->addUserViaApi($user, $this->list_id);
    }
}

class Am_FlexibleAction_MailchimpListDelete extends Am_FlexibleAction_MailchimpTag
{
    function getTitle()
    {
        return "MailChimp: Delete From List: " . Am_Html::escape($this->title);
    }
    function getId() { return 'mailchimp-list-delete-' . $this->list_id; }
    function run(User $user)
    {
        $this->plugin->getApi()->sendRequest("lists/{$this->list_id}/members/".
            md5(strtolower($user->email)),null, Am_HttpRequest::METHOD_DELETE);
    }
}

class Am_FlexibleAction_MailchimpTagAdd extends Am_FlexibleAction_MailchimpTag
{
    protected $tag;
    function __construct($list_id, $tag, $title, Am_Newsletter_Plugin_Mailchimp $plugin)
    {
        $this->tag = $tag;
        parent::__construct($list_id, $title, $plugin);
    }

    function getTitle()
    {
        return "MailChimp: Add Tag: " . Am_Html::escape($this->title);
    }
    function getId() { return 'mailchimp-tag-add-' . $this->list_id . '__' . $this->tag; }

    function run(User $user)
    {
        if (!$this->plugin->addUserViaApi($user, $this->list_id))
            return false;
        $this->plugin->getApi()->sendRequest($url = "lists/{$this->list_id}/segments/{$this->tag}/members", [
                'email_address' => $user->email
            ], Am_HttpRequest::METHOD_POST);
    }
}

class Am_FlexibleAction_MailchimpTagDelete extends Am_FlexibleAction_MailchimpTag
{
    protected $tag;
    function __construct($list_id, $tag, $title, Am_Newsletter_Plugin_Mailchimp $plugin)
    {
        $this->tag = $tag;
        parent::__construct($list_id, $title, $plugin);
    }

    function getTitle()
    {
        return "MailChimp: Delete Tag: " . Am_Html::escape($this->title);
    }
    function getId() { return 'mailchimp-tag-delete-' . $this->list_id . '__' . $this->tag; }
    function run(User $user)
    {
        $x = $this->plugin->getApi()->sendRequest("lists/{$this->list_id}/segments/{$this->tag}/members/".
            md5(strtolower($user->email)),null, Am_HttpRequest::METHOD_DELETE);
    }
}

endif;