<?php

/**
 * Registry of e-mail template types and its properties
 * @package Am_Mail_Template
 */
class Am_Mail_TemplateTypes extends ArrayObject
{
    protected $tagSets = [];
    static protected $instance;

    /** @return Am_Mail_TemplateTypes */
    static function getInstance()
    {
        if (!self::$instance)
            self::$instance = self::createInstance();
        return self::$instance;
    }

    public function find($id)
    {
        return $this->offsetExists($id) ? $this->offsetGet($id) : null;
    }

    /** @return Am_Mail_TemplateTypes */
    static function createInstance()
    {
        $o = new self;

        $o->tagSets = [
            'admin' => [
                '%admin.name_f%' => ___('Admin First Name'),
                '%admin.name_l%' => ___('Admin Last Name'),
                '%admin.login%' => ___('Admin Username'),
                '%admin.email%' => ___('Admin E-Mail'),
            ],
            'user' => [
                '%user.name_f%' => ___('User First Name'),
                '%user.name_l%' => ___('User Last Name'),
                '%user.login%' => ___('Username'),
                '%user.email%' => ___('E-Mail'),
                '%user.user_id%' => ___('User Internal ID#'),
                '%user.street%' => ___('User Street'),
                '%user.street2%' => ___('User Street (Second Line)'),
                '%user.city%' => ___('User City'),
                '%user.state%' => ___('User State'),
                '%user.zip%' => ___('User ZIP'),
                '%user.country%' => ___('User Country'),
                '%user.phone%' => ___('User Phone'),
                '%user.status%' => ___('User Status (0-pending, 1-active, 2-expired)'),
            ],
            'invoice' => [
                '%invoice.invoice_id%' => ___('Invoice Internal ID#'),
                '%invoice.public_id%' => ___('Invoice Public ID#'),
                '%invoice.first_total%' => ___('Invoice First Total'),
                '%invoice.second_total%' => ___('Invoice Second Total'),
                '%invoice.rebill_date%' => ___('Invoice Rebill Date'),
            ],
            'payment' => [
                '%payment.amount%' => ___('Payment Amount'),
                '%payment.currency%' => ___('Payment Currency'),
                '%payment.receipt_id%' => ___('Payment Receipt Id'),
            ],
            'refund' => [
                '%refund.amount%' => ___('Refund Amount'),
                '%refund.currency%' => ___('Refund Currency'),
                '%refund.receipt_id%' => ___('Refund Receipt Id'),
            ],
            'product' => [
                '%product.title%' => ___('Product Title'),
                '%product.description%' => ___('Product Description'),
                '%product.url%' => ___('Product Link'),
            ],
        ];

        $table = Am_Di::getInstance()->userTable;
        $fields = $table->customFields()->getAll();
        uksort($fields, [$table, 'sortCustomFields']);
        foreach ($fields as $field) {
            if (@$field->sql && @$field->from_config) {
                $o->tagSets['user']['%user.' . $field->name . '%'] = ___('User %s', $field->title);
            }
        }

        $o->tagSets['user']['%user.unsubscribe_link%'] = ___('User Unsubscribe Link');

        $event = new Am_Event(Am_Event::EMAIL_TEMPLATE_TAG_SETS);
        $event->setReturn($o->tagSets);
        Am_Di::getInstance()->hook->call($event);
        $o->tagSets = $event->getReturn();

        $event = new Am_Event(Am_Event::SETUP_EMAIL_TEMPLATE_TYPES);
        Am_Di::getInstance()->hook->call($event);

        $res = $event->getReturn();

        $o->exchangeArray(array_merge([
            'bruteforce_notify' => [
                'id' => 'bruteforce_notify',
                'title' => ___('Bruteforce Notification'),
                'mailPeriodic' => Am_Mail::ADMIN_REQUESTED,
                'isAdmin' => true,
                'vars' => ['ip' => ___('IP Address'), 'login' => ___('Last Used Login')]
            ],
            'profile_changed' => [
                'id' => 'profile_changed',
                'title' => ___('Profile Changed'),
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'isAdmin' => true,
                'vars' => ['user', 'changes' => ___('Changes in User Profile')]
            ],
            'registration_mail' =>  [
                'id' => 'registration_mail',
                'title' => 'Registration E-Mail',
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'vars' => ['user', 'password' => ___('Plain-Text Password')],
            ],
            'registration_mail_admin' =>  [
                'id' => 'registration_mail_admin',
                'title' => 'Registration E-Mail to Admin',
                'mailPeriodic' => Am_Mail::ADMIN_REQUESTED,
                'isAdmin' => true,
                'vars' => ['user', 'password' => ___('Plain-Text Password')],
            ],
            'changepass_mail' =>  [
                'id' => 'changepass_mail',
                'title' => ___('Password Change E-Mail'),
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'vars' => ['user', 'password' => 'Plain-Text Password'],
            ],
            'send_signup_mail' =>  [
                'id' => 'send_signup_mail',
                'title' => ___('Send Signup Mail'),
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'vars' => ['user'],
            ],
            'mail_payment_admin' => [
                'id' => 'mail_payment_admin',
                'title' => ___('Mail Payment Admin'),
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'vars' => ['user'],
            ],
            'send_payment_mail' => [
                'id' => 'send_payment_mail',
                'title' => ___('Send Payment Mail'),
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'vars' => ['user','invoice','product_title'=> ___('Product(s) Title')],
            ],
            'send_payment_admin' => [
                'id' => 'send_payment_admin',
                'title' => ___('Send Payment Admin'),
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'isAdmin' => true,
                'vars' => ['user','invoice','product_title'=> ___('Product(s) Title')],
            ],
            'send_refund_mail' => [
                'id' => 'send_refund_mail',
                'title' => ___('Send Refund Mail'),
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'vars' => ['user','invoice','refund','product_title'=> ___('Product(s) Title')],
            ],
            'send_refund_admin' => [
                'id' => 'send_refund_admin',
                'title' => ___('Send Refund Admin'),
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'isAdmin' => true,
                'vars' => ['user','invoice','refund','product_title'=> ___('Product(s) Title')],
            ],
            'manually_approve' => [
                'id' => 'manually_approve',
                'title' => ___('Manually Approve'),
                'mailPeriodic' => Am_Mail::ADMIN_REQUESTED,
                'vars' => ['user'],
            ],
            'manually_approve_admin' => [
                'id' => 'manually_approve_admin',
                'title' => ___('Manually Approve Admin'),
                'mailPeriodic' => Am_Mail::ADMIN_REQUESTED,
                'isAdmin' => true,
                'vars' => ['user'],
            ],
            'invoice_approval_wait_user' => [
                'id' => 'invoice_approval_wait_user',
                'title' => ___('Manually Approve Invoice'),
                'mailPeriodic' => Am_Mail::ADMIN_REQUESTED,
                'vars' => ['user','invoice'],
            ],
            'invoice_pay_link' => [
                'id' => 'invoice_pay_link',
                'title' => 'Payment Link for Invoice',
                'mailPeriodic' => Am_Mail::ADMIN_REQUESTED,
                'vars' => [
                    'invoice_text' => ___('Invoice Text'),
                    'product_title' => ___('Product Title'),
                    'url' => ___('Payment Link'),
                    'message' => ___('Your Message'),
                    'user', 'invoice'
                ],
            ],
            'invoice_approval_wait_admin' => [
                'id' => 'invoice_approval_wait_admin',
                'title' => ___('Manually Approve Invoice Admin'),
                'mailPeriodic' => Am_Mail::ADMIN_REQUESTED,
                'isAdmin' => true,
                'vars' => ['user', 'invoice'],
            ],
            'invoice_approved_user' => [
                'id' => 'invoice_approved_user',
                'title' => ___('Invoice Approved'),
                'mailPeriodic' => Am_Mail::ADMIN_REQUESTED,
                'vars' => ['user', 'invoice'],
            ],
            'card_expires' =>
            [
                'id' => 'card_expires',
                'title' => ___('Card Expires'),
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'vars' => ['user'],
            ],
            'send_security_code' =>
            [
                'id' => 'send_security_code',
                'title' => ___('Send Security Code'),
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'vars' =>  ['user', 'code' => ___('Security Code'), 'url' => ___('Click Url'), 'ip' => ___('IP Address')],
            ],
            'verify_email_signup' =>
            [
                'id' => 'verify_email_signup',
                'title' => ___('Verify Email Signup'),
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'vars' => ['user', 'url' => ___('Email Confirmation URL')],
            ],
            'verify_email_profile' =>
            [
                'id' => 'verify_email_profile',
                'title' => ___('Verify Email Profile'),
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'vars' => ['user', 'url' => ___('Email Confirmation URL')],
            ],
            'autoresponder' =>
            [
                'id' => 'autoresponder',
                'title' => ___('Auto-Responder'),
                'mailPeriodic' => Am_Mail::REGULAR,
                'vars' => ['user', 'last_product_title' => ___('Product Title of the Latest Purchased Product')],
            ],
            'productwelcome' =>
            [
                'id' => 'productwelcome',
                'title' => ___('Product Welcome E-mail'),
                'mailPeriodic' => Am_Mail::REGULAR,
                'vars' => ['user', 'invoice', 'payment', 'last_product_title' => ___('Product Title of the Latest Purchased Product')],
            ],
            'payment' =>
            [
                'id' => 'payment',
                'title' => ___('Payment E-mail'),
                'mailPeriodic' => Am_Mail::REGULAR,
                'vars' => ['user', 'invoice', 'payment', 'product'],
            ],
            'expire' =>
            [
                'id' => 'expire',
                'title' => ___('Expiration E-Mail'),
                'mailPeriodic' => Am_Mail::REGULAR,
                'vars' => ['user', 'expires' => ___('Expiration Date'), 'product_title' => ___('Expire Product Title')],
            ],
            'pending_to_user' => [
                'id' => 'pending_to_user',
                'title' => ___('Pending Invoice Notifications to User'),
                'mailPeriodic' => Am_Mail::REGULAR,
                'vars' => ['user', 'invoice', 'invoice_text' => ___('Invoice Text'), 'day'=> ___('Day of Notification Sending'), 'product_title'=> ___('Product(s) Title'), 'paylink' => ___('Payment Link to Complete Pending Invoice')],
            ],
            'pending_to_admin' => [
                'id' => 'pending_to_admin',
                'title' => ___('Pending Invoice Notifications to Admin'),
                'mailPeriodic' => Am_Mail::REGULAR,
                'isAdmin' => true,
                'vars' => ['user', 'invoice', 'invoice_text' => ___('Invoice Text'), 'day'=>___('Day of Notification Sending'), 'product_title'=>___('Product(s) Title')],
            ],
            'max_ip_actions_admin' =>
            [
                'id' => 'max_ip_actions_admin',
                'title' => ___('Email admin regarding account sharing'),
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'isAdmin' => true,
                'vars' => ['user'],
            ],
            'max_ip_actions_user' =>
            [
                'id' => 'max_ip_actions_user',
                'title' => ___('Email user regarding account sharing'),
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'vars' => ['user'],
            ],
            'mail_cancel_member' => [
                'id' => 'mail_cancel_member',
                'title' => ___('Send Cancel Notifications to User'),
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'vars' => ['user', 'invoice'],
            ],
            'mail_cancel_admin' => [
                'id' => 'mail_cancel_admin',
                'title' => ___('Send Cancel Notifications to Admin'),
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'isAdmin' => true,
                'vars' => ['user', 'invoice'],
            ],
            'send_free_payment_admin' => [
                'id' => 'send_free_payment_admin',
                'title' => ___('Send Free Payment Admin'),
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'isAdmin' => true,
                'vars' => ['user','invoice'],
            ],

        ], $res));

        return $o;
    }

    /**
     * Return array - key => value of available options for template with given $id
     * @param type $id
     * @return array
     */
    public function getTagsOptions($id)
    {
        $record = @$this[$id];
        $ret = [
            '%site_title%' => ___('Site Title'),
            '%root_url%' => ___('aMember Root URL'),
            '%admin_email%' => ___('Admin E-Mail Address'),
            '%cur_date%' => ___('Current Date Formatted'),
            '%cur_datetime%' => ___('Current Date and Time Formatted'),

        ];
        if (!$record || empty($record['vars']))
            return $ret;
        foreach ($record['vars'] as $k => $v)
        {
            if (is_int($k)) // tag set
                $ret = array_merge($ret, $this->tagSets[$v]);
            else // single variable
                $ret['%'.$k.'%'] = $v;
        }
        return Am_Di::getInstance()->hook->filter($ret, Am_Event::EMAIL_TEMPLATE_TAG_OPTIONS, ['templateName' => $id]);
    }

    public function add($id, $title, $mailPeriodic, array $vars)
    {
        $this[$id] = ['id' => $id, 'title' => $title, 'mailPeriodic' => $mailPeriodic, 'vars' => $vars];
    }
}