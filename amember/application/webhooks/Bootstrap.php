<?php
/**
 * @title Webhooks
 * @desc send data to remote addresses for configured events
 * @setup_url webhooks/admin
 *
 * @todo cron to remove old sent hooks
 */
class Bootstrap_Webhooks extends Am_Module
{
    const ADMIN_PERM_ID = 'webhooks';
    const MAX_FAILURES = 10;
    const FAILURE_DELAY = 300; // 5 minutes

    const EVENT_WEBHOOK_TYPES = 'webhookTypes';

    protected $webhooks = [];
    protected $webhooksLoaded = false;
    protected $types = [
            Am_Event::ACCESS_AFTER_INSERT =>
                [
                    'title' => 'Access record inserted',
                    'description' => '',
                    'params' => ['access'],
                    'nested' => ['user'],
                ],
            Am_Event::ACCESS_AFTER_DELETE =>
                [
                    'title' => 'Access record deleted',
                    'description' => '',
                    'params' => ['access'],
                    'nested' => ['user'],
                ],
            Am_Event::ACCESS_AFTER_UPDATE =>
                [
                    'title' => 'Access record updated',
                    'description' => '',
                    'params' => ['access','old'],
                    'nested' => ['user'],
                ],
            Am_Event::INVOICE_AFTER_CANCEL =>
                [
                    'title' => 'Called after invoice cancelation',
                    'description' => '',
                    'params' => ['invoice'],
                    'nested' => ['user'],
                ],
            Am_Event::INVOICE_AFTER_DELETE =>
                [
                    'title' => 'Called after invoice deletion',
                    'description' => '',
                    'params' => ['invoice'],
                    'nested' => ['user'],
                ],
            Am_Event::INVOICE_AFTER_INSERT =>
                [
                    'title' => 'Called after invoice insertion',
                    'description' => '',
                    'params' => ['invoice'],
                    'nested' => ['user'],
                ],
            Am_Event::INVOICE_PAYMENT_REFUND =>
                [
                    'title' => 'Called after invoice payment refund (or chargeback)',
                    'description' => '',
                    'params' => ['invoice','refund'],
                    'nested' => ['user'],
                ],
            Am_Event::INVOICE_STARTED =>
                [
                    'title' => 'Called when an invoice becomes active_recuirring or paid, or free trial is started',
                    'description' => '',
                    'params' => ['user','invoice','transaction','payment']
                ],
            Am_Event::INVOICE_STATUS_CHANGE =>
                [
                    'title' => 'Called when invoice status is changed',
                    'description' => '',
                    'params' => ['invoice','status','oldStatus'],
                    'nested' => ['user'],
                ],
            Am_Event::PAYMENT_AFTER_INSERT =>
                [
                    'title' => 'Payment record insered into database. Is not called for free subscriptions',
                    'description' => '',
                    'params' => ['invoice','payment','user'],
                    'nested' => ['items']
                ],
            /*Am_Event::PAYMENT_WITH_ACCESS_AFTER_INSERT =>
                array(
                    'title' => 'Payment record with access insered into database. Is not called for free subscriptions. Required to get access records',
                    'description' => '',
                    'params' => array('invoice','payment','user'),
                    ),*/
            Am_Event::USER_AFTER_INSERT =>
                [
                    'title' => 'Called after user record is inserted into table',
                    'description' => '',
                    'params' => ['user'],
                ],
            Am_Event::USER_AFTER_UPDATE =>
                [
                    'title' => 'Called after user record is updated in database',
                    'description' => '',
                    'params' => ['user','oldUser'],
                ],
            Am_Event::USER_AFTER_DELETE =>
                [
                    'title' => 'Called after customer record deletion',
                    'description' => '',
                    'params' => ['user'],
                ],
            Am_Event::SUBSCRIPTION_ADDED => [
                'title' => 'Called when user receives a subscription to product he was not subscribed earlier',
                'description' => '',
                'params' => ['user', 'product'],
            ],
            Am_Event::SUBSCRIPTION_DELETED => [
                'title' => 'Called when user subscription access is expired',
                'description' => '',
                'params' => ['user', 'product'],
            ],
            Am_Event::USER_NOTE_AFTER_INSERT => [
                'title' => 'Called when admin add new note to user account',
                'description' => '',
                'params' => ['user', 'note'],
            ],
            'cancelFeedback' => [
                'title' => 'Called when user submits a cancel feedback',
                'description' => 'the cancel-feedback plugin must be enabled to utilize this hook',
                'params' => ['user', 'invoice', 'reason', 'comment'],
            ],
    ];

    function onGetPermissionsList(Am_Event $event)
    {
        $event->addReturn(___('Can manage webhooks'), self::ADMIN_PERM_ID);
    }

    function onInitFinished(Am_Event $event)
    {
        foreach ($this->getTypes() as $k => $v)
            $this->getDi()->hook->add($k, [$this, 'doWork']);
    }

    function onAdminMenu(Am_Event $event)
    {
        $event->getMenu()->addPage([
            'id' => 'webhooks',
            'uri' => 'javascript:;',
            'label' => ___('Webhooks'),
            'resource' => self::ADMIN_PERM_ID,
            'pages' => array_merge([
                    [
                        'id' => 'webhooks-configuration',
                        'controller' => 'admin',
                        'module' => 'webhooks',
                        'label' => ___('Configuration'),
                        'resource' => self::ADMIN_PERM_ID,
                    ],
                    [
                        'id' => 'webhooks-queue',
                        'controller' => 'admin-queue',
                        'module' => 'webhooks',
                        'label' => ___("Queue"),
                        'resource' => self::ADMIN_PERM_ID,
                    ]
                ]
        )
        ]);
    }

    function getTypes()
    {
        return $this->types;
    }

    function getConfiguredWebhooks()
    {
        if (!$this->webhooksLoaded)
        {
            $this->webhooks = [];
            $rows = $this->getDi()->db->select("SELECT * FROM ?_webhook WHERE is_disabled=0");
            foreach ($rows as $row)
            {
                $this->webhooks[$row['event_id']][] = $row;
            }
            $this->webhooksLoaded = true;
        }
        return $this->webhooks;
    }

    public function getObjectData($obj)
    {
        if ($obj instanceof Am_Record) {
            $ret = $obj->toRow();
            if($obj instanceof Am_Record_WithData){
                $data = $obj->data()->getAll();
                if(!empty($data)) {
                    $ret += array_combine(array_map(function ($k)
                    {
                        return "data." . $k;
                    }, array_keys($data)), array_values($data));
                }
            }
            if ($obj instanceof User) {
                unset($ret['last_session']);
                if($pass = $obj->getPlaintextPass()){
                    $ret['plain_password'] = $pass;
                }
                
            }
            return $ret;
        } elseif (is_object($obj)) {
            return get_object_vars($obj);
        } elseif (is_array($obj)) {
            $ret = [];
            foreach ($obj as $k => $v) {
                $ret[$k] = $this->getObjectData($v);
            }
            return $ret;
        } else {
            return (string)$obj;
        }
    }

    public function prepareData(Am_Event $event, $data = [])
    {
        $id = $event->getId();
        $data['am-webhooks-version'] = '1.0';
        $data['am-event'] = $id;
        $data['am-timestamp'] = date('c');
        $data['am-root-url'] = ROOT_URL;

        $types = $this->getTypes();
        $fields = $types[$id]['params'];
        $nestedFields = isset($types[$id]['nested']) ? $types[$id]['nested'] : [];

        $parent = $fields[0];
        foreach($fields as $field)
        {
            $field_ = call_user_func([$event, 'get'.ucfirst($field)]);
            if($parent == $field)
                $parent = $field_;
            $data = array_merge($data, [$field => $this->getObjectData($field_)]);
        }
        foreach ($nestedFields as $nfield)
        {
            $nfield_ = call_user_func([$parent, 'get'.ucfirst($nfield)]);
            $data = array_merge($data, [$nfield => $this->getObjectData($nfield_)]);
        }
        return $data;
    }

    public function doWork(Am_Event $event, $data = [])
    {
        $webhooks = $this->getConfiguredWebhooks();
        $id = $event->getId();
        if(empty($webhooks[$id])) return;

        $data = $this->prepareData($event, $data);
        $tmpl = new Am_SimpleTemplate();
        $tmpl->assign($data);
        foreach($webhooks[$id] as $webhook)
        {
            $queue = $this->addToQueue($tmpl->render($webhook['url']), $webhook['event_id'], $data);
            
            if (defined('AM_WEBHOOKS_INSTANT') && AM_WEBHOOKS_INSTANT) // really bad idea. do not make it documented and public setting
            {
                $this->sendRequest($queue);
            }
            
        }
    }
    
    function addToQueue($url, $event_id,  $params)
    {
        $queue = $this->getDi()->webhookQueueRecord;
        $queue->url = $url;
        $queue->event_id = $event_id;
        $queue->params = serialize($params);
        $queue->added = $this->getDi()->time;
        $queue->insert();
        return $queue;
    }

    public function runCron()
    {
        $time_limit = 50; // Executed once a minute anyway
        // get lock
        if (!$this->getDi()->db->selectCell("SELECT GET_LOCK(?, 50)", $this->getLockId())) {
            $this->getDi()->logger->error("Could not obtain MySQL's GET_LOCK() to run webhooks cron. Probably attempted to execute two cron processes simultaneously. ");
            return;
        }
        $start = time();
        foreach($this->getDi()->webhookQueueTable->findBy(['sent' => null, ['failures', '<', self::MAX_FAILURES]], 0, 1000) as $webhook_queue)
        {
            if(!empty($webhook_queue->next_attempt)&&($webhook_queue->next_attempt>$this->getDi()->time))
                continue;
            
            if(time() - $start >= $time_limit) break;
            $this->sendRequest($webhook_queue);
        }
        //release lock
        $this->getDi()->db->query("SELECT RELEASE_LOCK(?)", $this->getLockId());
    }

    protected function sendRequest(WebhookQueue $webhook_queue)
    {
        try {
            $req = new Am_HttpRequest($webhook_queue->url, Am_HttpRequest::METHOD_POST);
            $params = unserialize($webhook_queue->params);
            foreach($params as $name => $data) {
                if (is_array($data)) {
                    unset($params[$name]);
                    foreach($data as $k => $v) {
                        $params[$name . '[' . $k . ']'] = $v;
                    }
                }
            }
            $req->addPostParameter($params);
            $res = $req->send();
            $st = $res->getStatus();
            if($st == 200) {
                $webhook_queue->updateQuick(['sent' => $this->getDi()->time]);
            } else {
                $webhook_queue->updateQuick([
                    'last_error'=>$st,
                    'failures'=>$webhook_queue->failures + 1,
                    'next_attempt' => $this->getDi()->time+self::FAILURE_DELAY // 5 minutes delay
                ]);
            }
        } catch (Exception $e)  {
            $this->getDi()->logger->error("webhooks exception", ["exception" => $e]);
        }
    }


    public function getLockId()
    {
        return 'webhooks-cron-lock-' . md5(__FILE__);
    }

}