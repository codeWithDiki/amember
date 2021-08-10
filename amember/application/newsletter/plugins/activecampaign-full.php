<?php

class Am_Newsletter_Plugin_ActivecampaignFull extends Am_Newsletter_Plugin
{
    protected $api;

    const ACTIVE_SUB = 'active-sub';
    const ACTIVE_UNSUB = 'active-unsub';
    const INACTIVE_SUB = 'inactive-sub';
    const INACTIVE_UNSUB = 'inactive-unsub';
    const EXPIRE_SUB = 'expire-sub';
    const EXPIRE_UNSUB = 'expire-unsub';

    const ACTIVE_SUB_TAG = 'active-sub-tag';
    const ACTIVE_UNSUB_TAG = 'active-unsub-tag';
    const INACTIVE_SUB_TAG = 'inactive-sub-tag';
    const INACTIVE_UNSUB_TAG = 'inactive-unsub-tag';
    const PENDING_SUB_TAG = 'pending-sub-tag';
    const PENDING_UNSUB_TAG = 'pending-unsub-tag';
    const EXPIRE_SUB_TAG = 'expire-sub-tag';
    const EXPIRE_UNSUB_TAG = 'expire-unsub-tag';

    protected $activecampaign = null;

    static function getDbXml()
    {
        return <<<CUT
<schema version="4.0.0">
    <table name="coupon_batch">
        <field name="activecampaign_add_tag" type="varchar" len="255" notnull="0" />
        <field name="activecampaign_remove_tag" type="varchar" len="255" notnull="0" />
    </table>
</schema>
CUT;
    }

    function _initSetupForm(Am_Form_Setup $form)
    {
        $el = $form->addAdvRadio('api_type')
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
        $form
            ->addText('api_url', ['class' => 'am-el-wide'])
            ->setLabel("Activecampaign API url\n" .
                        "it should be with http://");
        $form->addSecretText('api_key', ['class' => 'am-el-wide'])->setLabel('Activecampaign API Key');

        $form->addText('api_user', ['class' => 'am-el-wide'])->setLabel('Activecampaign Admin Login');
        $form->addSecretText('api_password', ['class' => 'am-el-wide'])->setLabel('Activecampaign Admin Password');
        $form->addAdvCheckbox('debug')
            ->setLabel("Debug logging\nRecord debug information in the log");
    }

    public function init()
    {
        $lists = ['' => '*** None'];

        $app_lists = $this
            ->getDi()
            ->newsletterListTable
            ->findByPluginId('activecampaign-full');

        foreach ($app_lists as $l)
        {
            $lists[$l->plugin_list_id] = $l->title;
        }

        class_exists('Am_Record_WithData', true);

        // AFTER PURCHASE PRODUCT
        // subscribe to
        $f = new Am_CustomFieldSelect(
            self::ACTIVE_SUB,
            "SUBSCRIBE to Activecampaign List\n"
            . "after ACTIVATE this product");
        $f->options = $lists;
        $this->getDi()->productTable->customFields()->add($f);

        // unsubscribe from
        $f = new Am_CustomFieldSelect(
            self::ACTIVE_UNSUB,
            "UNSUBSCRIBE from Activecampaign List\n"
            . "after ACTIVATE this product");
        $f->options = $lists;
        $this->getDi()->productTable->customFields()->add($f);

        // AFTER NON PAID PRODUCT
        // subscribe to
        $f = new Am_CustomFieldSelect(
            self::INACTIVE_SUB,
            "SUBSCRIBE to Activecampaign List\nafter NON PAID this product");
        $f->options = $lists;
        $this->getDi()->productTable->customFields()->add($f);

        // unsubscribe from
        $f = new Am_CustomFieldSelect(
            self::INACTIVE_UNSUB,
            "UNSUBSCRIBE from Activecampaign List\n"
            . "after NON PAID this product");
        $f->options = $lists;
        $this->getDi()->productTable->customFields()->add($f);

        // AFTER EXPIRE PRODUCT
        // subscribe to
        $f = new Am_CustomFieldSelect(
            self::EXPIRE_SUB,
            "SUBSCRIBE to Activecampaign List\n"
            . "after EXPIRE this product");
        $f->options = $lists;
        $this->getDi()->productTable->customFields()->add($f);

        // unsubscribe from
        $f = new Am_CustomFieldSelect(
            self::EXPIRE_UNSUB,
            "UNSUBSCRIBE from Activecampaign List\n"
            . "after EXPIRE this product");
        $f->options = $lists;
        $this->getDi()->productTable->customFields()->add($f);
        //**********************************************************************
        // AFTER PURCHASE PRODUCT
        // subscribe to
        $f = new Am_CustomFieldText(
            self::ACTIVE_SUB_TAG,
            "Add TAG\n"
            . "after ACTIVATE this product");
        $this->getDi()->productTable->customFields()->add($f);

        // unsubscribe from
        $f = new Am_CustomFieldText(
            self::ACTIVE_UNSUB_TAG,
            "Remove TAG\n"
            . "after ACTIVATE this product");
        $f->options = $lists;
        $this->getDi()->productTable->customFields()->add($f);

        // AFTER NON PAID PRODUCT
        // subscribe to
        $f = new Am_CustomFieldText(
            self::INACTIVE_SUB_TAG,
            "Add TAG\n"
            . "after NON PAID this product");
        $this->getDi()->productTable->customFields()->add($f);

        // AFTER NON PAID PRODUCT
        // subscribe to
        $f = new Am_CustomFieldText(
            self::INACTIVE_UNSUB_TAG,
            "Remove TAG\n"
            . "after NON PAID this product");
        $this->getDi()->productTable->customFields()->add($f);

        // AFTER EXPIRE PRODUCT
        // subscribe to
        $f = new Am_CustomFieldText(
            self::EXPIRE_SUB_TAG,
            "Add TAG\n"
            . "after EXPIRE this product");
        $this->getDi()->productTable->customFields()->add($f);

        // AFTER EXPIRE PRODUCT
        // subscribe to
        $f = new Am_CustomFieldText(
            self::EXPIRE_UNSUB_TAG,
            "Remove TAG\n"
            . "after EXPIRE this product");
        $this->getDi()->productTable->customFields()->add($f);

        $this->getDi()->productTable->customFields()->add(
            new Am_CustomFieldText(
            self::PENDING_SUB_TAG,
            "Pending Invoice Add TAG\n"
            . "if invoice with that product wasn't paid within one hour"));

        $this->getDi()->productTable->customFields()->add(
            new Am_CustomFieldText(
                self::PENDING_UNSUB_TAG,
                "Pending Invoice Remove TAG\n"
                . "if invoice with that product wasn't paid within one hour"));

    }

    function isConfigured()
    {
        return ($this->getConfig('api_type')  == 0 &&
                $this->getConfig('api_user') &&
                $this->getConfig('api_password')) ||
                ($this->getConfig('api_type')  == 1 &&
                $this->getConfig('api_key'));
    }

    function onGridCouponBatchInitForm(Am_Event_Grid $e)
    {
        $grid = $e->getGrid();
        $fs = $grid->getForm()->getElementById('coupon-batch');

        $fs->addText('activecampaign_add_tag', ['class' => 'am-el-wide'])
            ->setLabel("Add Active Campaign Tag\non coupon used");
        $fs->addText('activecampaign_remove_tag', ['class' => 'am-el-wide'])
            ->setLabel("Remove Active Campaign Tag\non coupon used");
    }

    /** @return Am_ActivecampaignFull_Api */
    function getApi()
    {
        return new Am_ActivecampaignFull_Api($this);
    }

    function onSubscriptionChanged(
        Am_Event_SubscriptionChanged $event,
        User $oldUser = null)
    {
        $pAdded = $event->getAdded();
        $pDeleted = $event->getDeleted();
        $user = $event->getUser();
        $lAdded = $lDeleted = [];

        $tags_add = [];
        $tags_remove = [];

        foreach ($pAdded as $pId)
        {
            $product = $this->getDi()->productTable->load($pId);

            if($list = $product->data()->get(self::ACTIVE_SUB))
            {
                if(!in_array($list, $lAdded))
                    $lAdded[] = $list;
            }

            if($list = $product->data()->get(self::ACTIVE_UNSUB))
            {
                if(!in_array($list, $lDeleted))
                    $lDeleted[] = $list;
            }

            if($tag = $product->data()->get(self::ACTIVE_SUB_TAG))
                $tags_add = array_merge($tags_add, explode (',', $tag));

            if($tag = $product->data()->get(self::ACTIVE_UNSUB_TAG))
                $tags_remove = array_merge($tags_remove, explode (',', $tag));
        }

        foreach ($pDeleted as $pId)
        {
            $product = $this->getDi()->productTable->load($pId);

            if(
                // expired after all rebill times:
                ($invoiceId = $this
                    ->getDi()
                    ->db
                    ->selectCell(""
                        . "SELECT invoice_id "
                        . "FROM ?_access "
                        . "WHERE user_id=?d "
                        . "AND product_id=?d "
                        . "ORDER BY expire_date "
                        . "DESC",
                    $user->pk(),
                    $product->pk()))
                && ($invoice = $this->getDi()->invoiceTable->load($invoiceId))
                && ($invoice->status == Invoice::RECURRING_FINISHED)
            ){
                if($list = $product->data()->get(self::EXPIRE_SUB))
                {
                    if(!in_array($list, $lAdded)) {
                        $lAdded[] = $list;
                    }
                }

                if($list = $product->data()->get(self::EXPIRE_UNSUB))
                {
                    if(!in_array($list, $lDeleted)) {
                        $lDeleted[] = $list;
                    }
                }

                if($tag = $product->data()->get(self::EXPIRE_SUB_TAG))
                {
                    $tags_add = array_merge($tags_add, explode (',', $tag));
                }

                if($tag = $product->data()->get(self::EXPIRE_UNSUB_TAG))
                {
                    $tags_remove = array_merge($tags_remove, explode (',', $tag));
                }

            } else {
                if($list = $product->data()->get(self::INACTIVE_SUB))
                {
                    if(!in_array($list, $lAdded)) {
                        $lAdded[] = $list;
                    }
                }

                if($list = $product->data()->get(self::INACTIVE_UNSUB))
                {
                    if(!in_array($list, $lDeleted)) {
                        $lDeleted[] = $list;
                    }
                }

                if($tag = $product->data()->get(self::INACTIVE_SUB_TAG))
                {
                    $tags_add = array_merge($tags_add, explode (',', $tag));
                }

                if($tag = $product->data()->get(self::INACTIVE_UNSUB_TAG))
                {
                    $tags_remove = array_merge($tags_remove, explode (',', $tag));
                }
            }
        }

        foreach($lAdded as $list) {
            $am_list = $this->getDi()->newsletterListTable->findFirstBy([
                'plugin_id' => $this->getId(),
                'plugin_list_id' => $list
            ]);

            $this->getDi()->newsletterUserSubscriptionTable->add(
                $user,
                $am_list,
                NewsletterUserSubscription::TYPE_AUTO);
        }

        foreach($lDeleted as $list)
        {
            $am_list = $this->getDi()->newsletterListTable->findFirstBy([
                'plugin_id' => $this->getId(),
                'plugin_list_id' => $list
            ]);

            $table = $this
                ->getDi()
                ->newsletterUserSubscriptionTable;

            /* @var $record NewsletterUserSubscription */
            if ($record = $table
                ->findFirstBy([
                    'user_id' => $user->pk(),
                    'list_id' => $am_list->pk()
                ])) {

            //error_log($record->subscription_id);

                $record->disable();
            }
        }

        if(count($tags_add) || count($tags_remove))
        {
            $api = $this->getApi();

            $acuser = $api->sendRequest(
                    'contact_view_email',
                    ['email' => $user->email],
                    Am_HttpRequest::METHOD_GET);

            if(!@$acuser['id'])
                $ret = $api->sendRequest('contact_add', [
                        'email' => $user->email,
                        'first_name' => $user->name_f,
                        'last_name' => $user->name_l
                ]);

            if(count($tags_add))
            {
                $api->sendRequest('contact_tag_add',
                    ['email' => $user->email, 'tags' => $tags_add],
                    Am_HttpRequest::METHOD_POST);

            }
            if(count($tags_remove))
            {
                $api->sendRequest('contact_tag_remove',
                    ['email' => $user->email, 'tags' => $tags_remove],
                    Am_HttpRequest::METHOD_POST);

            }
        }
    }

    function onHourly()
    {
        $begin_date = date('Y-m-d H:i:s', mktime(date('H')-2, 0, 0, date('m'), date('d'), date('Y')));
        $end_date = date('Y-m-d H:i:s', mktime(date('H')-1, 0, 0, date('m'), date('d'), date('Y')));
        $query = new Am_Query($this->getDi()->invoiceTable);
        $query = $query->addWhere('status=?', Invoice::PENDING)
            ->addWhere('tm_added>?', $begin_date)
            ->addWhere('tm_added<?', $end_date);
        $t = $query->getAlias();
        $query->addWhere("NOT EXISTS
          (SELECT * FROM ?_invoice_payment ip
           WHERE ip.user_id = $t.user_id
             AND ip.dattm>=?
             AND ip.dattm<GREATEST(?, $t.tm_added + INTERVAL 48 HOUR)
                 LIMIT 1
         )", $begin_date, $end_date);

        if ($count = $query->getFoundRows()) {
            $invoices = $query->selectPageRecords(0, $count);
            $api = $this->getApi();

            foreach($invoices as $invoice)
            {
                $tags_add = $tags_remove = [];
                foreach($invoice->getProducts() as $product)
                {
                    if($tag = $product->data()->get(self::PENDING_SUB_TAG)){
                        $tags_add[]  =  $tag;
                    }
                    if($tag = $product->data()->get(self::PENDING_UNSUB_TAG)){
                        $tags_remove[]  =  $tag;
                    }
                }

                if(count($tags_add) || count($tags_remove))
                {
                    $acuser = $api->sendRequest(
                        'contact_view_email',
                        ['email' => $invoice->getUser()->email],
                        Am_HttpRequest::METHOD_GET);

                    if(!@$acuser['id'])
                        $ret = $api->sendRequest('contact_add', [
                            'email' => $invoice->getUser()->email,
                            'first_name' => $invoice->getUser()->name_f,
                            'last_name' => $invoice->getUser()->name_l
                        ]);

                }

                if(count($tags_add))
                {
                    $api->sendRequest('contact_tag_add',
                        ['email' => $invoice->getUser()->email, 'tags' => $tags_add],
                        Am_HttpRequest::METHOD_POST);

                }
                if(count($tags_remove))
                {
                    $api->sendRequest('contact_tag_remove',
                        ['email' => $invoice->getUser()->email, 'tags' => $tags_remove],
                        Am_HttpRequest::METHOD_POST);

                }
            }
        }
    }

    function changeSubscription(
        User $user,
        array $addLists,
        array $deleteLists)
    {
        //error_log('here');
        $api = $this->getApi();
        $acuser = $api->sendRequest(
                'contact_view_email',
                ['email' => $user->email],
                Am_HttpRequest::METHOD_GET);

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
            $ret = $api->sendRequest('contact_edit', array_merge([
                    'id' => $acuser['subscriberid'],
                    'email' => $user->email,
                    'first_name' => $user->name_f,
                    'last_name' => $user->name_l,
                    'overwrite' => 0
            ], $lists));

            if (!$ret)
                return false;
        } else {
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

            if (!$ret) return false;
        }

        return true;
    }

    function onUserAfterUpdate(Am_Event_UserAfterUpdate $event)
    {
        $api = $this->getApi();
        $user = $event->getUser();

        $acuser = $api->sendRequest(
                    'contact_view_email',
                    ['email' => $user->email],
                    Am_HttpRequest::METHOD_GET);

        if (isset($acuser['id']))
        {
            $id = $acuser['id'];
            //error_log($id);
            $api->sendRequest(
                    'contact_edit',
                    [
                        'id' => $id,
                        'email' => $user->email,
                        'overwrite' => 0,
                        'first_name' => $user->name_f,
                        'last_name' => $user->name_l,
                        'phone' => $user->phone
                    ],
                    Am_HttpRequest::METHOD_GET);
        }
    }

    function onInvoiceStarted(Am_Event $e)
    {
        /** @var Invoice $invoice */
        $invoice = $e->getInvoice();
        $user = $invoice->getUser();

        $tags_add = $tags_remove = [];

        /** @var Coupon $coupon */
        if ($coupon = $invoice->getCoupon()) {
            $batch = $coupon->getBatch();
            if ($batch->activecampaign_add_tag) {
                $tags_add = explode(',', $batch->activecampaign_add_tag);
            }
            if ($batch->activecampaign_remove_tag) {
                $tags_remove = explode(',', $batch->activecampaign_remove_tag);
            }
        }

        if (count($tags_add) || count($tags_remove)) {
            $api = $this->getApi();

            $acuser = $api->sendRequest(
                'contact_view_email',
                ['email' => $user->email],
                Am_HttpRequest::METHOD_GET);

            if(!@$acuser['id'])
                $ret = $api->sendRequest('contact_add', [
                    'email' => $user->email,
                    'first_name' => $user->name_f,
                    'last_name' => $user->name_l
                ]);

            if(count($tags_add))
            {
                $api->sendRequest('contact_tag_add',
                    ['email' => $user->email, 'tags' => $tags_add],
                    Am_HttpRequest::METHOD_POST);

            }
            if(count($tags_remove))
            {
                $api->sendRequest('contact_tag_remove',
                    ['email' => $user->email, 'tags' => $tags_remove],
                    Am_HttpRequest::METHOD_POST);

            }
        }
    }

    public function getLists()
    {
        $api = $this->getApi();
        $ret = [];

        $lists = $api->sendRequest(
            'list_list',
            ['ids' => 'all'],
            Am_HttpRequest::METHOD_GET);

        foreach ($lists as $l)
        {
            $ret[$l['id']] = [
                'title' => $l['name'],
            ];
        }

        return $ret;
    }
}

class Am_ActivecampaignFull_Api extends Am_HttpRequest
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

    public function sendRequest(
        $api_action,
        $params,
        $method = self::METHOD_POST)
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
            $this->addPostParameter($this->params);

            $url = $this->plugin->getConfig('api_url') .
                    '/admin/api.php?' .
                    http_build_query($this->vars, '', '&');
        } else {
            $url = $this->plugin->getConfig('api_url') .
                    '/admin/api.php?' .
                    http_build_query($this->vars + $this->params, '', '&');
        }

        $this->setUrl($url);

        $ret = parent::send();
        $this->plugin->debug($this, $ret, $url);

        if (!in_array($ret->getStatus(), [200,404])) {
            throw new Am_Exception_InternalError("Activecampaign API Error, configured API Key is wrong");
        }

        $arr = unserialize($ret->getBody());

        if (!$arr) {
            throw new Am_Exception_InternalError("Activecampaign API Error - unknown response [{$ret->getBody()}]");
        }

        unset(
            $arr['result_code'],
            $arr['result_message'],
            $arr['result_output']);

        return $arr;
    }
}