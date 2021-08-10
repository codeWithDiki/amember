<?php

/** @help-id "Setup/Global" */
class Am_Form_Setup_Global extends Am_Form_Setup
{
    protected $order = 100;

    function __construct()
    {
        parent::__construct('global');
        $this->setTitle(___('Global'))
        ->setComment('');
    }

    function validateCurl($val)
    {
        if (!$val) return;
        exec("$val http://www.yahoo.com/ 2>&1", $out, $return);
        if ($return)
            return "Couldn't execute '$val http://www.yahoo.com/'. Exit code: $return, $out";
    }

    function initElements()
    {
        $this->addText('site_title', [
                'class' => 'am-el-wide',
        ], ['help-id' => '#Setup.2FEdit_Site_Title'])
        ->setLabel(___('Site Title'));

        $this->addStatic(null, null, ['help-id' => '#Root_URL_and_License_Key'])->setContent(
                '<a href="' . Am_Di::getInstance()->url('admin-license') . '" target="_top" class="link">'
                . ___('change')
                . '</a>')->setLabel(___('Root Url and License Keys'));

        $g = $this->addGroup(null, ['help-id' => '#Setup.2FEdit_User_Pages_Theme'])
            ->setLabel(___('User Pages Theme'));

        $g->setSeparator(' ');
        $g->addSelect('theme')
            ->loadOptions(Am_Di::getInstance()->view->getThemes('user'));

        if (Am_Di::getInstance()->view->theme->hasSetupForm()) {
            $themeId = Am_Di::getInstance()->view->theme->getId();
            $config_link = Am_Di::getInstance()->url("admin-setup/themes-$themeId");
            $g->addHtml()
                ->setHtml(<<<CUT
<a href="$config_link" class="link" id="theme-config-link">configure</a>
<script type="text/javascript">
    jQuery(function(){
        jQuery('[name=theme]').change(function(){
            jQuery('#theme-config-link').hide();
        });
    })
</script>
CUT
                );
        }

        $g = $this->addGroup()
            ->setLabel(___('Tax'));

        $g->setSeparator(' ');
        $tax_plugins = Am_Di::getInstance()->plugins_tax->getAvailable();
        $g->addSelect('plugins.tax', ['size' => 1, 'id'=>'plugins-tax'])
            ->loadOptions(['' => ___('No Tax')] + $tax_plugins);

        if ($taxId = Am_Di::getInstance()->config->get('plugins.tax')) {
            $config_link = Am_Di::getInstance()->url("admin-setup/$taxId");
            $g->addHtml()
                ->setHtml(<<<CUT
<a href="$config_link" class="link" id="tax-config-link">configure</a>
<script type="text/javascript">
    jQuery(function(){
        jQuery('#plugins-tax').change(function(){
            jQuery('#tax-config-link').hide();
        });
    })
</script>
CUT
                );
        }

        $fs = $this->addAdvFieldset('##02')
            ->setLabel(___('Signup Form Configuration'));

        $this->setDefault('login_min_length', 5);
        $this->setDefault('login_max_length', 16);

        $loginLen = $fs->addGroup(null, null, ['help-id' => '#Setup.2FEdit_Username_Rules'])->setLabel(___('Username Length'));
        $loginLen->addInteger('login_min_length', ['size'=>3])->setLabel('min');
        $loginLen->addStatic('')->setContent(' &mdash; ');
        $loginLen->addInteger('login_max_length', ['size'=>3])->setLabel('max');

        $fs->addAdvCheckbox('login_disallow_spaces', null, ['help-id' => '#Setup.2FEdit_Username_Rules'])
            ->setLabel(___('Do not Allow Spaces in Username'));

        $fs->addAdvCheckbox('login_dont_lowercase', null, ['help-id' => '#Setup.2FEdit_Username_Rules'])
            ->setLabel(___("Do not Lowercase Username\n".
                "by default, aMember automatically lowercases entered username\n".
                "here you can disable this function"));

        $this->setDefault('pass_min_length', 6);
        $this->setDefault('pass_max_length', 25);
        $passLen = $fs->addGroup(null, null, ['help-id' => '#Setup.2FEdit_Password_Length'])->setLabel(___('Password Length'));
        $passLen->addInteger('pass_min_length', ['size'=>3])->setLabel('min');
        $passLen->addStatic('')->setContent(' &mdash; ');
        $passLen->addInteger('pass_max_length', ['size'=>3])->setLabel('max');

        $fs->addAdvCheckbox('require_strong_password')
            ->setLabel(___("Require Strong Password\n" .
                'password should contain at least 2 capital letters, 2 or more numbers and 2 or more special chars'));

        $fs->addAdvCheckbox('login_generate_only_random')
            ->setLabel(___("Purely random usernames\n" .
                "if username is generated, do not use email address or name for generation"));

        $fs = $this->addFieldset('##03')
            ->setLabel(___('Miscellaneous'));

        $this->setDefault('admin.records-on-page', 10);
        $fs->addInteger('admin.records-on-page')
            ->setLabel(___('Records per Page (for grids)'));

        $fs->addAdvCheckbox('disable_rte')
            ->setLabel(___('Disable Visual HTML Editor'));

        $this->setDefault('currency', 'USD');
        $currency = $fs->addSelect('currency', [
                'size' => 1, 'class' => 'am-combobox'
        ], ['help-id' => '#Set_Up.2FEdit_Base_Currency'])
            ->setLabel(___("Base Currency\n".
                "base currency to be used for reports and affiliate commission. ".
                "It could not be changed if there are any invoices in database.")
        )
        ->loadOptions(Am_Currency::getFullList());
        if (Am_Di::getInstance()->db->selectCell("SELECT COUNT(*) FROM ?_invoice")) {
            $currency->toggleFrozen(true);
        }

        $url = Am_Di::getInstance()->url('admin-currency-exchange');
        $label = Am_Html::escape(___('Edit'));
        $this->addHtml()
            ->setLabel(___('Currency Exchange Rates'))
            ->setHtml(<<<CUT
<a href="$url" class="link">$label</a>
CUT
                );

        $this->addSelect('404_page')
            ->setLabel(___("Page Not Found (404)\n" .
                "%sthis page will be public and do not require any login/password%s\n" .
                'you can create new pages %shere%s', '<strong>', '</strong>',
                '<a class="link" href="' . Am_Di::getInstance()->url('default/admin-content/p/pages') . '">', '</a>'))
            ->loadOptions([''=>___('Default Not Found Page')] +
                Am_Di::getInstance()->db->selectCol("SELECT page_id AS ?, title FROM ?_page", DBSIMPLE_ARRAY_KEY));

        $this->addSelect('403_page')
            ->setLabel(___("Access Forbidden (403)\n" .
                "%sthis page will be public and do not require any login/password%s\n" .
                'you can create new pages %shere%s', '<strong>', '</strong>',
                '<a class="link" href="' . Am_Di::getInstance()->url('default/admin-content/p/pages') . '">', '</a>'))
            ->loadOptions([''=>___('Default Forbidden Page')] +
                Am_Di::getInstance()->db->selectCol("SELECT page_id AS ?, title FROM ?_page", DBSIMPLE_ARRAY_KEY));

        $this->addSelect('dashboard_page')
            ->setLabel(___("Dashboard Page\n" .
                "%sany logged in user will see it (protection settings is ignored)%s\n" .
                'you can create new pages %shere%s', '<strong>', '</strong>',
                '<a class="link" href="' . Am_Di::getInstance()->url('default/admin-content/p/pages') . '">', '</a>'))
            ->loadOptions([''=>___('Default Dashboard Page')] +
                Am_Di::getInstance()->db->selectCol("SELECT page_id AS ?, title FROM ?_page", DBSIMPLE_ARRAY_KEY));

        if (!empty(Am_Di::getInstance()->session->plugin_enabled)) {
            $ids = json_encode(Am_Di::getInstance()->session->plugin_enabled);

            $this->addScript()
                ->setScript(<<<CUT
jQuery(function(){
    var ids = {$ids};
    for (id of ids) {
        jQuery("#setup-form-" + id).addClass('tab-highlight');
    }
})
CUT
                );
            Am_Di::getInstance()->session->plugin_enabled = null;
        }
    }

    public function beforeSaveConfig(Am_Config $before, Am_Config $after)
    {
        $enabled = [];

        if ($after->get('plugins.tax') && $before->get('plugins.tax') != $after->get('plugins.tax')) {
            $enabled[] = $after->get('plugins.tax');
        }

        if ($after->get('theme') && $before->get('theme') != $after->get('theme')) {
            $enabled[] = "themes-{$after->get('theme')}";
        }

        Am_Di::getInstance()->session->plugin_enabled = $enabled;
    }
}

/** @help-id "Setup/Email" */
class Am_Form_Setup_Email extends Am_Form_Setup
{
    protected $order = 300;

    function __construct()
    {
        parent::__construct('email');
        $this->setTitle(___('E-Mail'))
            ->setComment('');
    }

    function checkSMTPHost($val){
        $res = ($val['email_method'] == 'smtp') ?
        (bool)strlen($val['smtp_host']) : true;

        if (!$res) {
            $elements = $this->getElementsByName('smtp_host');
            $elements[0]->setError(___('SMTP Hostname is required if you have enabled SMTP method'));
        }

        return $res;
    }

    function initElements()
    {
        $this->addText('admin_email', [
                'class' => 'am-el-wide',
        ], ['help-id' => '#Email_Address_Configuration'])
        ->setLabel(___("Admin E-Mail Address\n".
                "used to send email notifications to admin\n".
                "and as default outgoing address")
        )
        ->addRule('callback', ___('Please enter valid e-mail address'), ['Am_Validate', 'email']);

        $this->addText('technical_email', ['class' => 'am-el-wide'])
        ->setLabel(___("Technical E-Mail Address\n".
                "shown on error pages. If empty, [Admin E-Mail Address] is used"))
        ->addRule('callback', ___('Please enter valid e-mail address'), ['Am_Validate', 'empty_or_email']);

        $this->addText('admin_email_from', [
                'class' => 'am-el-wide',
        ], ['help-id' => '#Email_Address_Configuration'])
        ->setLabel(___(
                "Outgoing Email Address\n".
                "used as From: address for sending e-mail messages\n".
                "to customers. If empty, [Admin E-Mail Address] is used"
        ))
        ->addRule('callback', ___('Please enter valid e-mail address'), ['Am_Validate', 'empty_or_email']);

        $this->addText('admin_email_name', [
                'class' => 'am-el-wide',
        ], ['help-id' => '#Email_Address_Configuration'])
        ->setLabel(___(
                "E-Mail Sender Name\n" .
                "used to display name of sender in outgoing e-mails"
        ));

        $fs = $this->addFieldset('##19')
            ->setLabel(___('E-Mail System Configuration'));

        $fs->addSelect('email_method', null, ['help-id' => '#Email_System_Configuration'])
            ->setLabel(___(
                "Email Sending method\n" .
                "PLEASE DO NOT CHANGE if emailing from aMember works"))
                ->loadOptions([
                        'mail' => ___('Internal PHP mail() function (default)'),
                        'smtp' => ___('SMTP'),
                        'ses' => ___('Amazon SES'),
                        'sendgrid' => ___('Send Grid (Web API v2)'),
                        'sendgrid3' => ___('Send Grid (Web API v3)'),
                        'campaignmonitor' => ___('CampaignMonitor (Transactional API)'),
                        'mailjet' => ___('Mail Jet'),
                        'postmark' => ___('Postmark'),
                        'mailgun' => ___('MailGun'),
                        'disabled' => ___('Disabled')
                ]);

        $fs->addText('smtp_host', ['class' => 'am-el-wide'], ['help-id' => '#SMTP_Mail_Settings'])
            ->setLabel(___('SMTP Hostname'));
        $this->addRule('callback', ___('SMTP Hostname is required if you have enabled SMTP method'), [$this, 'checkSMTPHost']);

        $fs->addInteger('smtp_port', ['size' => 4],  ['help-id' => '#SMTP_Mail_Settings'])
            ->setLabel(___('SMTP Port'));
        $fs->addSelect('smtp_security', null,  ['help-id' => '#SMTP_Mail_Settings'])
            ->setLabel(___('SMTP Security'))
            ->loadOptions([
                ''     => 'None',
                'ssl'  => 'SSL',
                'tls'  => 'TLS',
            ]);
        $fs->addSelect('smtp_auth', null,  ['help-id' => '#SMTP_Mail_Settings'])
            ->setLabel(___('Authentication Type'))
            ->loadOptions([
                'login' => 'Login',
                'plain'  => 'Plain',
            ]);
        $fs->addText('smtp_user', ['autocomplete'=>'off'],  ['help-id' => '#SMTP_Mail_Settings'])
            ->setLabel(___('SMTP Username'));
        $fs->addSecretText('smtp_pass', ['autocomplete'=>'off'],  ['help-id' => '#SMTP_Mail_Settings'])
            ->setLabel(___('SMTP Password'));

        $fs->addText('ses_id', ['class' => 'am-el-wide'])
            ->setLabel(___('Amazon SES Access Id'));
        $fs->addPassword('ses_key', ['class' => 'am-el-wide'])
            ->setLabel(___('Amazon SES Secret Key'));
        $fs->addSelect('ses_region', '', [
            'options' => [
                Am_Mail_Transport_Ses::REGION_US_EAST_1 => 'US East (N. Virginia)',
                Am_Mail_Transport_Ses::REGION_US_EAST_2 => 'US East (Ohio)',
                Am_Mail_Transport_Ses::REGION_US_WEST_2 => 'US West (Oregon)',
                Am_Mail_Transport_Ses::REGION_AP_SOUTH_1 => 'Asia Pacific (Mumbai)',
                Am_Mail_Transport_Ses::REGION_AP_SOUTHEAST_1 => 'Asia Pacific (Sydney)',
                Am_Mail_Transport_Ses::REGION_CA_CENTRAL_1 => 'Canada (Central)',
                Am_Mail_Transport_Ses::REGION_EU_CENTRAL_1 => 'Europe (Frankfurt)',
                Am_Mail_Transport_Ses::REGION_EU_WEST_1 => 'EU (Ireland)',
                Am_Mail_Transport_Ses::REGION_EU_WEST_2 => 'EU (London)',
                Am_Mail_Transport_Ses::REGION_SA_EAST_1 => 'South America (São Paulo)',
                Am_Mail_Transport_Ses::REGION_US_GOV_WEST_1 => 'AWS GovCloud (US)',
            ]
        ])
            ->setLabel(___('Amazon SES Region'));

        $fs->addText('sendgrid_user')
            ->setLabel(___('SendGrid Username'))
            ->addRule('required', ___('This field is required'), null, HTML_QuickForm2_Rule::CLIENT);

        $fs->addPassword('sendgrid_key')
            ->setLabel(___('SendGrid Password'))
            ->addRule('required', ___('This field is required'), null, HTML_QuickForm2_Rule::CLIENT);

        $fs->addPassword('sendgrid3_key', ['class' => 'am-el-wide'])
            ->setLabel(___('SendGrid API Key'))
            ->addRule('required', ___('This field is required'), null, HTML_QuickForm2_Rule::CLIENT);

        $fs->addText('campaignmonitor_apikey', ['class' => 'am-el-wide'])
            ->setLabel(___('Campaignmonitor API Key'))
            ->addRule('required', ___('This field is required'), null, HTML_QuickForm2_Rule::CLIENT);

        $fs->addText('campaignmonitor_clientid', ['class' => 'am-el-wide'])
            ->setLabel(___('Client API ID'))
            ->addRule('required', ___('This field is required'), null, HTML_QuickForm2_Rule::CLIENT);

        $fs->addText('mailjet_apikey_public', ['class' => 'am-el-wide'])
            ->setLabel(___('Mail Jet API Public Key'))
            ->addRule('required', ___('This field is required'), null, HTML_QuickForm2_Rule::CLIENT);

        $fs->addPassword('mailjet_apikey_private', ['class' => 'am-el-wide'])
            ->setLabel(___('Mail Jet API Private Key'))
            ->addRule('required', ___('This field is required'), null, HTML_QuickForm2_Rule::CLIENT);

        $fs->addPassword('postmark_token', ['class' => 'am-el-wide'])
            ->setLabel(___('Postmark Server API token'))
            ->addRule('required', ___('This field is required'), null, HTML_QuickForm2_Rule::CLIENT);

        $fs->addAdvRadio('mailgun_account')
            ->setLabel(___('MailGun Account'))
            ->loadOptions([
                'none-eu' => 'None EU',
                'eu' => 'EU'
            ])
            ->addRule('required', ___('This field is required'), null, HTML_QuickForm2_Rule::CLIENT);

        $fs->addText('mailgun_domain', ['class' => 'am-el-wide'])
            ->setLabel(___('MailGun Domain'))
            ->addRule('required', ___('This field is required'), null, HTML_QuickForm2_Rule::CLIENT);

        $fs->addPassword('mailgun_token', ['class' => 'am-el-wide'])
            ->setLabel(___('MailGun Private API Key'))
            ->addRule('required', ___('This field is required'), null, HTML_QuickForm2_Rule::CLIENT);

        $test = ___('Test E-Mail Settings');
        $em = ___('E-Mail Address to Send to');
        $se = ___('Send Test E-Mail');
        $fs->addStatic('email_test', null,  ['help-id' => '#Test_Email_Settings'])->setContent(<<<CUT
<div style="text-align: center">
<span class="red">$test</span><span class="admin-help"><a href="http://www.amember.com/docs/Setup/Email#Test_Email_Settings" target="_blank"><sup>?</sup></a></span>
<input type="text" name="email" size=30 placeholder="$em" />
<input type="button" name="email_test_send" value="$se" />
<div id="email-test-result" style="display:none"></div>
</div>
CUT
        );

        $se = ___('Sending Test E-Mail...');
        $this->addScript()->setScript(<<<CUT
jQuery(function(){
    jQuery("#row-email_test-0 .am-element-title").hide();
    jQuery("#row-email_test-0 .am-element").css({ 'margin-left' : '0px'});

    jQuery("input[name='email_test_send']").click(function(){
        var btn = jQuery(this);
        var vars = btn.parents('form').serializeArray();

        var dialogOpts = {
              modal: true,
              bgiframe: true,
              autoOpen: true,
              width: 450,
              draggable: true,
              resizeable: true
           };

        var savedVal = btn.val();
        btn.val("$se").prop("disabled", "disabled");
        var url = amUrl("/admin-email/test", 1);
        jQuery.post(url[0], jQuery.merge(vars, url[1]), function(data){
            jQuery("#email-test-result").html(data).dialog(dialogOpts);
            btn.val(savedVal).prop("disabled", "");
        });

    });

    jQuery("#email_method-0").change(function(){
        jQuery(".am-row[id*='smtp_']").toggle(jQuery(this).val() == 'smtp');
        jQuery(".am-row[id*='ses_']").toggle(jQuery(this).val() == 'ses');
        jQuery(".am-row[id*='sendgrid_']").toggle(jQuery(this).val() == 'sendgrid');
        jQuery(".am-row[id*='sendgrid3_']").toggle(jQuery(this).val() == 'sendgrid3');
        jQuery(".am-row[id*='campaignmonitor_']").toggle(jQuery(this).val() == 'campaignmonitor');
        jQuery(".am-row[id*='mailjet_']").toggle(jQuery(this).val() == 'mailjet');
        jQuery(".am-row[id*='postmark_']").toggle(jQuery(this).val() == 'postmark');
        jQuery(".am-row[id*='mailgun_']").toggle(jQuery(this).val() == 'mailgun');
    }).change();
});
CUT
        );

        $this->setDefault('email_log_days', 0);
        $fs->addText('email_log_days', [
                'size' => 6,
        ], ['help-id' => '#Outgoing_Messages_Log'])
            ->setLabel(___('Log Outgoing E-Mail Messages for ... days'));

        $fs->addAdvCheckbox('email_queue_enabled', null, ['help-id' => '#Using_the_Email_Throttle_Queue'])
            ->setLabel(___('Use E-Mail Throttle Queue'));
        $fs->addScript()->setScript(<<<CUT
jQuery(function(){
    jQuery("#email_queue_enabled-0").change(function(){
        jQuery("#email_queue_period-0").closest(".am-row").toggle(this.checked);
        jQuery("#email_queue_limit-0").closest(".am-row").toggle(this.checked);
    }).change();
});
CUT
        );

        $fs->addSelect('email_queue_period')
            ->setLabel(___(
                "Allowed E-Mails Period\n" .
                "choose if your host is limiting e-mails per day or per hour"))
                ->loadOptions(
                        [
                            3600 => 'Hour',
                            86400 => 'Day',
                        ]
                );

        $this->setDefault('email_queue_limit', 100);
        $fs->addInteger('email_queue_limit', ['size' => 6])
            ->setLabel(___(
                "Allowed E-Mails Count\n" .
                "enter number of emails allowed within the period above"));

        $fs->addAdvCheckbox('disable_unsubscribe_link', null, ['help-id' => '#Miscellaneous_Email_Settings'])
            ->setLabel(___('Do not include Unsubscribe Link into e-mails'));

        $this->addScript()->setScript(<<<CUT
jQuery(function(){
    jQuery('input[type=checkbox][name=disable_unsubscribe_link]').change(function(){
        jQuery('#row-unsubscribe_html-0, #row-unsubscribe_txt-0').toggle(!this.checked)
    }).change();
})
CUT
        );

        $fs->addTextarea('unsubscribe_html', ['class' => 'am-el-wide', 'rows'=>6],  ['help-id' => '#Miscellaneous_Email_Settings'])
            ->setLabel(___("HTML E-Mail Unsubscribe Link\n" .
                "%link% will be replaced to actual unsubscribe URL"));
        $this->setDefault('unsubscribe_html', Am_Mail_UnsubscribeLink::UNSUBSCRIBE_HTML);

        $fs->addTextarea('unsubscribe_txt', ['class' => 'am-el-wide', 'rows'=>6],  ['help-id' => '#Miscellaneous_Email_Settings'])
            ->setLabel(___("Text E-Mail Unsubscribe Link\n" .
                "%link% will be replaced to actual unsubscribe URL"));
        $this->setDefault('unsubscribe_txt', Am_Mail_UnsubscribeLink::UNSUBSCRIBE_TXT);

        $fs->addAdvCheckbox('disable_unsubscribe_block', null, ['help-id' => '#Miscellaneous_Email_Settings'])
            ->setLabel(___('Do not Show Unsubscribe Block on Member Page'));

        $fs->addText('copy_admin_email', ['class' => 'am-el-wide'], ['help-id' => '#Miscellaneous_Email_Settings'])
            ->setLabel(___("Send Copy of All Admin Notifications\n" .
                'will be used to send copy of email notifications to admin ' .
                'you can specify more then one email separated by comma: ' .
                'test@email.com,test1@email.com,test2@email.com'))
            ->addRule('callback', 'Please enter valid e-mail address', ['Am_Validate', 'emails']);

    }

}

/** @help-id Setup/Email-Templates */
class Am_Form_Setup_EmailTemplates extends Am_Form_Setup
{
    protected $order = 340;

    function __construct()
    {
        parent::__construct('email-templates');
        $this->setTitle(___('E-Mail Templates'))
            ->setComment('');
    }

    function initElements()
    {
        $edit = ___('Edit');
        $url = $this->getDi()->url('admin-email-template-layout');

        $this->addHtml()
            ->setLabel(___('E-Mail Layouts'))
            ->setHtml(<<<CUT
<a href="{$url}" class="link">{$edit}</a>
CUT
            );

        $fs = $this->addFieldset('##10')
            ->setLabel(___('Validation Messages to Customer'));

        $fs->addElement('email_link', 'verify_email_signup', null, ['help-id' => '#Validation_Message_Configuration'])
            ->setLabel(___("Verify E-Mail Address On Signup Page\n".
                "e-mail verification may be enabled for each signup form separately\n".
                "at aMember CP -> Forms Editor -> Edit, click \"configure\" on E-Mail brick"));

        $fs->addElement('email_link', 'verify_email_profile', null, ['help-id' => '#Validation_Message_Configuration'])
            ->setLabel(___("Verify New E-Mail Address On Profile Page\n".
                "e-mail verification for profile form may be enabled\n".
                "at aMember CP -> Forms Editor -> Edit, click \"configure\" on E-Mail brick"));

        $fs = $this->addFieldset('##11')
            ->setLabel(___('Signup Messages'));

        $fs->addElement('email_checkbox', 'registration_mail')
            ->setLabel(___("Send Registration E-Mail\n".
                "once customer completes signup form (before payment)"));
        $fs->addElement('email_checkbox', 'registration_mail_admin')
            ->setLabel(___("Send Registration E-Mail to Admin\n".
                "once customer completes signup form (before payment)"));

        $fs = $this->addFieldset('##12')
            ->setLabel(___("Pending Invoice Notification Rules"));

        $fs->addElement(new Am_Form_Element_PendingNotificationRules('pending_to_user'))
            ->setLabel(___("Pending Invoice Notifications to User\n".
                "only one email per user will be send for each defined day.\n".
                "all email for specific user and day will be selected and conditions will be checked.\n".
                "First email with matched condition will be send and other ignored"));

        $fs->addElement(new Am_Form_Element_PendingNotificationRules('pending_to_admin'))
            ->setLabel(___("Pending Invoice Notifications to Admin\n".
                "only one email per user will be send for each defined day.\n".
                "all email for specific user and day will be selected and conditions will be checked.\n".
                "First email with matched condition will be send and other ignored"));

        $fs = $this->addFieldset('##13')
            ->setLabel(___('Messages to Customer after Payment'));

        $fs->addElement('email_checkbox', 'send_signup_mail', null, ['help-id' => '#Email_Messages_Configuration'])
            ->setLabel(___("Send Signup E-Mail\n".
                "once FIRST subscripton is completed"));

        $fs->addElement('email_checkbox', 'send_payment_mail', null, ['help-id' => '#Email_Messages_Configuration'])
            ->setLabel(___("E-Mail Payment Receipt to User\n".
                'every time payment is received'));

        $fs->addElement('email_checkbox', 'send_payment_admin', null, ['help-id' => '#Email_Messages_Configuration'])
            ->setLabel(___("Admin Payment Notifications\n".
                "to admin once payment is received"));

        $fs->addElement('email_checkbox', 'send_free_payment_admin', null, ['help-id' => '#Email_Messages_Configuration'])
            ->setLabel(___("Admin Free Subscription Notifications\n".
                "to admin once free signup is completed"));

        $fs = $this->addFieldset('##14')
            ->setLabel(___('Messages to Customer after Refund'));

        $fs->addElement('email_checkbox', 'send_refund_mail', null, ['help-id' => '#Email_Messages_Configuration'])
            ->setLabel(___("E-Mail Refund Receipt to User\n".
                'every time payment is refunded'));

        $fs->addElement('email_checkbox', 'send_refund_admin', null, ['help-id' => '#Email_Messages_Configuration'])
            ->setLabel(___("Admin Refund Notifications\n".
                "to admin once payment is refunded"));

        $fs = $this->addFieldset('##15')
            ->setLabel(___('E-Mails by User Request'));

        $fs->addElement('email_checkbox', 'mail_cancel_member')
            ->setLabel(___("Send Cancel Notifications to User\n" .
                'send email to member when he cancels recurring subscription.'));

        $fs->addElement('email_checkbox', 'mail_upgraded_cancel_member')
            ->setLabel(___("Send Cancel (due to upgrade) Notifications to User\n" .
                'send email to member when he cancels recurring subscription due to upgrade.'));

        $fs->addElement('email_checkbox', 'mail_cancel_admin')
            ->setLabel(___("Send Cancel Notifications to Admin\n" .
                'send email to admin when recurring subscription cancelled by member'));

        $fs->addElement('email_link', 'send_security_code')
            ->setLabel(___("Remind Password to Customer"));

        $fs->addElement('email_checkbox', 'changepass_mail')
            ->setLabel(___("Change Password Notification\n" .
                'send email to user after password change'));

        if($this->haveCronRebillPlugins())
        {
            $fs = $this->addFieldset('##17')
                ->setLabel(___('E-Mail Messages on Rebilling Event', ''));

            $fs->addElement('email_checkbox', 'cc.admin_rebill_stats')
                ->setLabel(___("Send Credit Card Rebill Stats to Admin\n" .
                    "Credit Card Rebill Stats will be sent to Admin daily. It works for payment processors like Authorize.Net and PayFlow Pro only"));

            $fs->addElement('email_checkbox', 'cc.rebill_failed')
                ->setLabel(___("Credit Card Rebill Failed\n" .
                    "if credit card rebill failed, user will receive the following e-mail message. It works for payment processors like Authorize.Net and PayFlow Pro only"));

            $fs->addElement('email_checkbox', 'cc.rebill_success')
                ->setLabel(___("Credit Card Rebill Successfull\n" .
                    "if credit card rebill was sucessfull, user will receive the following e-mail message. It works for payment processors like Authorize.Net and PayFlow Pro only"));

            if($this->haveStoreCreditCardPlugins())
            {
                $gr = $fs->addGroup()
                    ->setLabel(___("Credit Card Expiration Notice\n" .
                        "if saved customer credit card expires soon, user will receive the following e-mail message. It works for payment processors like Authorize.Net and PayFlow Pro only"));
                ;
                $gr->addElement('email_checkbox', 'cc.card_expire');
                $gr->addHTML()->setHTML(' ' . ___('Send message') . ' ');
                $gr->addText('cc.card_expire_days', ['size'=>2, 'value'=>5]);
                $gr->addHTML()->setHTML(' ' . ___('days before rebilling'));

            }
        }
        $fs = $this->addFieldset('##16')
            ->setLabel(___('E-Mails by Admin Request'));

        $fs->addElement('email_link', 'send_security_code_admin', null, ['help-id' => '#Forgotten_Password_Templates'])
            ->setLabel(___('Remind Password to Admin'));

        $fs->addElement('email_checkbox', 'send_password_admin')
            ->setLabel(___("Change Admin Password Notification\n" .
                'send email to admin after password change'));

        $fs = $this->addFieldset('##18')
            ->setLabel(___('Miscellaneous'));

        $fs->addElement('email_link', 'invoice_pay_link')
            ->setLabel(___('Email Template with Payment Link'));

        $fs->addElement('email_checkbox', 'profile_changed', null, ['help-id' => '#Configuring_Advanced_Options'])
            ->setLabel(___("Send Notification to Admin When Profile is Changed\n".
                "admin will receive an email if user has changed profile"
            ));

    }

    function haveCronRebillPlugins()
    {
        foreach(Am_Di::getInstance()->plugins_payment->getAllEnabled() as $p)
        {
            if($p->getRecurringType() == Am_Paysystem_Abstract::REPORTS_CRONREBILL)
                return true;
        }
    }

    function haveStoreCreditCardPlugins()
    {
        foreach(Am_Di::getInstance()->plugins_payment->getAllEnabled() as $p)
        {
            if($p->storesCcInfo())
                return true;
        }
    }

}

/** @help-id "HowTo/Customize_PDF_invoice_output" */
class Am_Form_Setup_Pdf extends Am_Form_Setup
{
    protected $order = 500;

    function __construct()
    {
        parent::__construct('pdf');
        $this->setTitle(___('PDF Invoice'))
        ->setComment('');

        $info = ___('You can find info regarding pdf invoice customization %shere%s', '<a class="link" target="_blank" href="https://docs.amember.com/docs/HowTo/Customize_PDF_invoice_output">', '</a>');
        $this->addProlog(<<<CUT
<div class="info">$info</div>
CUT
            );
    }

    function initElements()
    {
        $this->addAdvCheckbox('send_pdf_invoice', null, ['help-id' => '#Enabling_PDF_Invoices'])
            ->setLabel(___("Enable PDF Invoice"));

        $g = $this->addGroup()
            ->setLabel(___('Display Options'))
            ->setSeparator('<br />');

        $g->addAdvCheckbox('pdf_invoice_sent_user', null, ['content' => ___('Attach invoice file (.pdf) to Payment Receipt to User')]);
        $g->addAdvCheckbox('pdf_invoice_sent_admin', null, ['content' => ___('Attach invoice file (.pdf) to Payment Receipt to Admin')]);
        $g->addAdvCheckbox('pdf_invoice_link', null, ['content' => ___('Allow user to download PDF invoice in his account')]);
        $g->addAdvCheckbox('pdf_refund_sent_user', null, ['content' => ___('Attach invoice file (.pdf) to Refund Receipt to User')]);
        $g->addAdvCheckbox('pdf_refund_sent_admin', null, ['content' => ___('Attach invoice file (.pdf) to Refund Receipt to Admin')]);

        $this->addText('invoice_filename', ['size'=>30, 'class' => 'am-el-wide'])
            ->setLabel(___("Filename for Invoice\n" .
            '%public_id% will be replaced with real public id of invoice, %receipt_id% will be replaced with payment receipt, ' .
            'also you can use the following placehoders %payment.date%, %user.name_f%, %user.name_l%'));

        $this->setDefault('invoice_filename', 'amember-invoice-%public_id%.pdf');

        $this->addAdvRadio('invoice_format', null, ['help-id' => '#PDF_Invoice_Format'])
            ->setLabel(___('Paper Format'))
            ->loadOptions([
                    Am_Pdf_Invoice::PAPER_FORMAT_LETTER => ___('USA (Letter)'),
                    Am_Pdf_Invoice::PAPPER_FORMAT_A4 => ___('European (A4)')
            ]);

        $this->setDefault('invoice_format', Am_Pdf_Invoice::PAPER_FORMAT_LETTER);

        $this->addAdvcheckbox('invoice_include_access')
            ->setLabel(___('Include Access Periods to PDF Invoice'));

        $this->addAdvcheckbox('invoice_include_description')
            ->setLabel(___('Include Product Description to PDF Invoice'));

        if (Am_Di::getInstance()->plugins_tax->getEnabled()) {
            $this->addAdvcheckbox('invoice_always_tax')
                ->setLabel(___('Show Tax even it is 0'));
        }

        $this->addAdvcheckbox('invoice_do_not_include_terms')
            ->setLabel(___('Do not Include Subscription Terms to PDF Invoice'));

        $this->addAdvcheckbox('different_invoice_for_refunds')
            ->setLabel(___("Display Separate Invoice for Refunds"));

        $upload = $this->addUpload('invoice_custom_template',
                [], ['prefix'=>'invoice_custom_template', 'help-id' => '#PDF_Invoice_Template']
            )->setLabel(___('Custom PDF Template for Invoice (optional)')
            )->setAllowedMimeTypes([
                    'application/pdf'
        ]);

        $this->setDefault('invoice_custom_template', '');

        $upload->setJsOptions(<<<CUT
{
    onChange : function(filesCount) {
        jQuery('fieldset#template-custom-settings').toggle(filesCount>0);
        jQuery('fieldset#template-generated-settings').toggle(filesCount==0);
    }
}
CUT
        );

        $fsCustom = $this->addFieldset('template-custom')
            ->setLabel(___('Custom Template Settings'))
            ->setId('template-custom-settings');

        $this->setDefault('invoice_skip', 150);
        $fsCustom->addText('invoice_skip')
            ->setLabel(___(
                "Top Margin\n".
                "How much [pt] skip from top of template before start to output invoice\n".
                "1 pt = 0.352777 mm"));

        $fsGenerated = $this->addFieldset('template-generated')
            ->setLabel(___('Auto-generated Template Settings'))
            ->setId('template-generated-settings');

        $invoice_logo = $fsGenerated->addUpload('invoice_logo', [],
                ['prefix'=>'invoice_logo', 'help-id' => '#Company_Logo_for_Invoice']
            )->setLabel(___("Company Logo for Invoice\n".
                "it must be png/jpeg/tiff file"))
                ->setAllowedMimeTypes([
                        'image/png', 'image/jpeg', 'image/tiff'
                ]);

        $this->setDefault('invoice_logo', '');

        $fsGenerated->addAdvRadio('invoice_logo_position')
            ->setLabel('Logo Postion')
            ->loadOptions([
                'left' => ___('Left'),
                'right' => ___('Right')
            ]);
        $this->setDefault('invoice_logo_position', 'left');

        $fsGenerated->addTextarea('invoice_contacts', [
                'rows' => 5, 'class' => 'am-el-wide'
        ], ['help-id' => '#Invoice_Contact_Information'])
            ->setLabel(___("Invoice Contact information\n" .
                "included to header"));

        $fsGenerated->addTextarea('invoice_footer_note', [
                'rows' => 5, 'class' => 'am-el-wide'
        ], ['help-id' => '#Invoice_Footer_Note'])
            ->setLabel(___("Invoice Footer Note\n" .
                "This text will be included at bottom to PDF Invoice. " .
                "You can use all user specific placeholders here ".
                "eg. %user.login%, %user.name_f%, %user.name_l% etc."));

        $script = <<<CUT
(function($){
    jQuery(function() {
        function change_template_type(obj) {
            var show = parseInt(obj.val()) > 0;
            jQuery('fieldset#template-custom-settings').toggle(show);
            jQuery('fieldset#template-generated-settings').toggle(!show);
        }

        jQuery('input[name=send_pdf_invoice]').change(function(){
            if (!this.checked) {
                jQuery(this).closest('.am-row').nextAll().not('script').hide()
                jQuery(this).closest('form').find('input[type=submit]').closest('.am-row').show();
            } else {
                jQuery(this).closest('.am-row').nextAll().not('script').show();
                change_template_type(jQuery('input[name=invoice_custom_template]:enabled').last());
            }
        }).change();
    });
})(jQuery)
CUT;
        $this->addScript()->setScript($script);

        $gr = $this->addAdvFieldset('invoice_custom_font')
                ->setLabel(___('Advanced'));

        $gr->addUpload('invoice_custom_ttf',
                [], ['prefix'=>'invoice_custom_ttf']
            )->setLabel(___("Custom Font for Invoice (optional)\n".
                "Useful for invoices with non-Latin symbols " .
                "when there is a problem with displaying such symbols in the PDF invoice. " .
                "Please upload .ttf file only."));
        $this->setDefault('invoice_custom_ttf', '');
        $gr->addUpload('invoice_custom_ttfbold',
                [], ['prefix'=>'invoice_custom_ttfbold']
            )->setLabel(___("Custom Bold Font for Invoice (optional)\n".
                "Useful for invoices with non-Latin symbols " .
                "when there is a problem with displaying such symbols in the PDF invoice." .
                "Please upload .ttf file only."));
        $this->setDefault('invoice_custom_ttfbold', '');
        $gr->addAdvCheckbox('store_pdf_file')->setLabel(___("Store PDF invoices in file system\n".
            "once generated file will be saved and further changes for example of customer's profile will not affect it"));
    }
}

class Am_Form_Setup_VideoPlayer extends Am_Form_Setup
{
    protected $order = 800;

    function __construct()
    {
        parent::__construct('video-player');
        $this->setTitle(___('Video Player'))
        ->setComment('');
    }

    function initElements()
    {
        $this->setupElements($this, 'flowplayer.');

        $this->setDefault('flowplayer.logo_postion', 'top-right');
        $this->setDefault('flowplayer.autoPlay', 0);
    }

    public function setupElements(Am_Form $form, $prefix = null)
    {
        $form->addUpload($prefix . 'logo_id', null, ['prefix' => 'video-poster'])
            ->setLabel(___("Logo Image\n" .
                "watermark on video, logo can be a JPG or PNG file"));

        $form->addAdvRadio($prefix . 'logo_position')
            ->setLabel(___('Logo Position'))
            ->loadOptions([
                'top-right' => ___('Top Right'),
                'top-left' => ___('Top Left'),
                'bottom-right' => ___('Bottom Right'),
                'bottom-left' => ___('Bottom Left')
            ]);

        $form->addUpload($prefix . 'poster_id', null, ['prefix' => 'video-poster'])
            ->setLabel(___("Poster Image\n" .
                "default poster image"));

        $gr = $form->addGroup()
            ->setLabel(___("Default Size\n" .
                "width&times;height"));

        $gr->addText($prefix . 'width', ['size' => 4]);
        $gr->addStatic()->setContent(' &times ');
        $gr->addText($prefix . 'height', ['size' => 4]);

        $form->addSelect($prefix . 'autoPlay')
            ->setLabel(___("Auto Play\n" .
                'whether the player should start playback immediately upon loading'))
            ->loadOptions([
                0 => ___('No'),
                1 => ___('Yes')
            ]);
    }
}

/** @help-id "Setup/Advanced" */
class Am_Form_Setup_Advanced extends Am_Form_Setup
{
    protected $order = 400;

    function __construct()
    {
        parent::__construct('advanced');
        $this->setTitle(___('Advanced'))
        ->setComment('');
    }

    function checkBackupEmail($val)
    {
        $res = $val['email_backup_frequency'] ?
        Am_Validate::email($val['email_backup_address']) : true;

        if (!$res) {
            $elements = $this->getElementsByName('email_backup_address');
            $elements[0]->setError(___('This field is required'));
        }

        return $res;
    }

    function initElements()
    {
        $this->addAdvCheckbox('use_cron', null, ['help-path' => 'Cron'])
            ->setLabel(___('Use External Cron'));

        $gr = $this->addGroup(null, null, ['help-id' => '#Configuring_Advanced_Settings'])->setLabel([
            ___('Maintenance Mode'), ___('put website offline, making it available for admins only')
        ]);
        $gr->setSeparator(' ');
        $gr->addCheckbox('', [
            'id' => 'maint_checkbox',
                'data-text' => ___('Site is temporarily disabled for maintenance')
        ]);
        $gr->addTextarea('maintenance', ['id' => 'maint_textarea', 'rows'=>3, 'cols'=>80]);
        $gr->addScript()->setScript(<<<CUT
jQuery(function(){
    var checkbox = jQuery('#maint_checkbox');
    var textarea = jQuery('#maint_textarea');
    jQuery('#maint_checkbox').click(function(){
        textarea.toggle(checkbox.prop('checked'));
        if (textarea.is(':visible'))
        {
            textarea.val(checkbox.data('text'));
        } else {
            checkbox.data('text', textarea.val());
            textarea.val('');
        }
    });
    checkbox.prop('checked', !!textarea.val());
    textarea.toggle(checkbox.is(':checked'));
});
CUT
        );

        $gr = $this->addGroup(null, null, ['help-id'=>'#Configuring_Advanced_Settings'])->setLabel(___("Clear Access Log"));
        $gr->addAdvCheckbox('clear_access_log', null, ['help-id' => '#Configuring_Advanced_Settings']);
        $gr->addStatic()->setContent(sprintf('<span class="clear_access_log_days"> %s </span>', ___("after")));
        $gr->addText('clear_access_log_days', ['class'=>'clear_access_log_days', 'size' => 4]);
        $gr->addStatic()->setContent(sprintf('<span class="clear_access_log_days"> %s </span>', ___("days")));

        $this->setDefault('clear_access_log_days', 7);

        $this->addScript()->setScript(<<<CUT
jQuery(function(){
    jQuery('input[name=clear_access_log]').change(function(){
        jQuery('.clear_access_log_days').toggle(this.checked);
    }).change();
})

CUT
            );


        $this->addText('clear_debug_log_days', ['size' => 4])
            ->setLabel(___('Log Debug Information for ... days'));
        $this->setDefault('clear_debug_log_days', 7);

        $gr = $this->addGroup()->setLabel(___('Clear Incomplete Invoices'));
        $gr->addAdvCheckbox('clear_inc_payments');
        $gr->addStatic()->setContent(sprintf('<span class="clear_inc_payments_days"> %s </span>', ___("after")));
        $gr->addInteger('clear_inc_payments_days', ['class'=>'clear_inc_payments_days', 'size'=>4]);
        $gr->addStatic()->setContent(sprintf('<span class="clear_inc_payments_days"> %s </span>', ___("days")));

        $this->setDefault('clear_inc_payments_days', 7);

        $this->addScript()->setScript(<<<CUT
jQuery(function(){
    jQuery('input[name=clear_inc_payments]').change(function(){
        jQuery('.clear_inc_payments_days').toggle(this.checked);
    }).change();
})

CUT
            );

        $gr = $this->addGroup()->setLabel(___('Clear Incomplete Users'));
        $gr->addAdvCheckbox('clear_inc_users');
        $gr->addStatic()->setContent(sprintf('<span class="clear_inc_users_days"> %s </span>', ___("after")));
        $gr->addInteger('clear_inc_users_days', ['class'=>'clear_inc_users_days', 'size'=>4]);
        $gr->addStatic()->setContent(sprintf('<span class="clear_inc_users_days"> %s </span>', ___("days")));

        $this->setDefault('clear_inc_users_days', 7);

        $this->addScript()->setScript(<<<CUT
jQuery(function(){
    jQuery('input[name=clear_inc_users]').change(function(){
        jQuery('.clear_inc_users_days').toggle(this.checked);
    }).change();
})

CUT
            );

        $gr = $this->addGroup()->setLabel(___('Clear Invoice Log'));
        $gr->addAdvCheckbox('clear_invoice_log');
        $gr->addStatic()->setContent(sprintf('<span class="clear_invoice_log_days"> %s </span>', ___("after")));
        $gr->addInteger('clear_invoice_log_days', ['class'=>'clear_invoice_log_days', 'size'=>4]);
        $gr->addStatic()->setContent(sprintf('<span class="clear_invoice_log_days"> %s </span>', ___("days")));

        $this->setDefault('clear_invoice_log_days', 14);

        $this->addScript()->setScript(<<<CUT
jQuery(function(){
    jQuery('input[name=clear_invoice_log]').change(function(){
        jQuery('.clear_invoice_log_days').toggle(this.checked);
    }).change();
})

CUT
        );

        $this->setDefault('multi_title', ___('Membership'));
        $this->addText('multi_title', ['class' => 'am-el-wide'], ['help-id' => '#Configuring_Advanced_Settings'])
            ->setLabel(___("Multiple Order Title\n".
                "when user ordering multiple products,\n".
                "display the following on payment system\n".
                "instead of product name"));

        if (!Am_Di::getInstance()->modules->isEnabled('cc')) {
            $fs = $this->addFieldset('##3')
                ->setLabel(___('E-Mail Database Backup'));

            $fs->addSelect('email_backup_frequency', null, ['help-id' => '#Enabling.2FDisabling_Email_Database_Backup'])
                ->setLabel(___('Email Backup Frequency'))
                ->setId('select-email-backup-frequency')
                ->loadOptions([
                        '0' => ___('Disabled'),
                        'd' => ___('Daily'),
                        'w' => ___('Weekly')
                ]);

            $di = Am_Di::getInstance();
            $backUrl = $di->rurl("backup/cron/k/{$di->security->siteHash('backup-cron', 10)}");

            $text = ___('It is required to setup a cron job to trigger backup generation');
            $html = <<<CUT
<div id="email-backup-note-text">
</div>
<div id="email-backup-note-text-template" style="display:none">
    $text <br />
    <strong>%EXECUTION_TIME% /usr/bin/curl $backUrl</strong><br />
</div>
CUT;

            $fs->addHtml('email_backup_note')->setHtml($html);

            $fs->addText('email_backup_address')
                ->setLabel(___('E-Mail Backup Address'));

            $this->addRule('callback', ___('Email is required if you have enabled Email Backup Feature'), [$this, 'checkBackupEmail']);

            $script = <<<CUT
(function($) {
    function toggle_frequency() {
        if (jQuery('#select-email-backup-frequency').val() == '0') {
            jQuery("input[name=email_backup_address]").closest(".am-row").hide();
        } else {
            jQuery("input[name=email_backup_address]").closest(".am-row").show();
        }

        switch (jQuery('#select-email-backup-frequency').val()) {
            case 'd' :
                jQuery('#email-backup-note-text').empty().append(
                    jQuery('#email-backup-note-text-template').html().
                        replace(/%FREQUENCY%/, 'daily').
                        replace(/%EXECUTION_TIME%/, '15 0 * * *')
                )
                jQuery('#email-backup-note-text').closest('.am-row').show();
                break;
            case 'w' :
                jQuery('#email-backup-note-text').empty().append(
                    jQuery('#email-backup-note-text-template').html().
                        replace(/%FREQUENCY%/, 'weekly').
                        replace(/%EXECUTION_TIME%/, '15 0 * * 1')
                )
                jQuery('#email-backup-note-text').closest('.am-row').show();
                break;
            default:
                jQuery('#email-backup-note-text').closest('.am-row').hide();
        }
    }

    toggle_frequency();

    jQuery('#select-email-backup-frequency').bind('change', function(){
        toggle_frequency();
    })

})(jQuery)
CUT;

            $this->addScript()->setScript($script);
        }

        $fs = $this->addFieldset()
                ->setLabel(___('Manually Approve'));

        $fs->addAdvCheckbox('manually_approve', null, ['help-id' => '#Configuring_Advanced_Options'])
            ->setLabel(___("Manually Approve New Users\n" .
            "manually approve all new users (first payment)\n" .
            "don't enable it if you have huge users base already\n" .
            "- all old members become not-approved"));

        $fs->addElement('email_link', 'manually_approve', ['rel'=>'manually_approve'], ['help-id' => '#Configuring_Advanced_Options'])
            ->setLabel(___('Require Approval Notification to User  (New Signup)'));

        $fs->addElement('email_link', 'manually_approve_admin', ['rel'=>'manually_approve'], ['help-id' => '#Configuring_Advanced_Options'])
            ->setLabel(___('Require Approval Notification to Admin (New Signup)'));

        $fs->addAdvCheckbox('manually_approve_invoice', null, ['help-id' => '#Configuring_Advanced_Options'])
            ->setLabel(___("Manually Approve New Invoices\n" .
                'manually approve all new invoices'));
        $maPc = [];
        foreach (Am_Di::getInstance()->productCategoryTable->getAdminSelectOptions() as $id => $title) {
            $maPc['c' . $id] = $title;
        }
        if ($maPc) {
            $maOptions = [
                ___('Products') => Am_Di::getInstance()->productTable->getOptions(),
                ___('Product Categories') => $maPc
            ];
        } else {
            $maOptions = Am_Di::getInstance()->productTable->getOptions();
        }

        $fs->addMagicSelect('manually_approve_invoice_products', ['rel'=>'manually_approve_invoice'], ['help-id' => '#Configuring_Advanced_Options'])
            ->setLabel(___("Require Approval Only if Invoice has these Products (Invoice)\n" .
                'By default each invoice will be set as "Not Approved" ' .
                'although you can enable this functionality only for selected products'))
            ->loadOptions($maOptions);

        $fs->addElement('email_link', 'invoice_approval_wait_admin', ['rel'=>'manually_approve_invoice'], ['help-id' => '#Configuring_Advanced_Options'])
            ->setLabel('Require Approval Notification to Admin (Invoice)');

        $fs->addElement('email_link', 'invoice_approval_wait_user', ['rel'=>'manually_approve_invoice'], ['help-id' => '#Configuring_Advanced_Options'])
            ->setLabel('Require Approval Notification to User  (Invoice)');

        $fs->addElement('email_link', 'invoice_approved_user', ['rel'=>'manually_approve_invoice'], ['help-id' => '#Configuring_Advanced_Options'])
            ->setLabel(___('Invoice Approved Notification to User (Invoice)'));

        $fs->addTextarea('manually_approve_note', ['rows' => 8, 'class' => 'am-el-wide'])
            ->setId('form-manually_approve_note')
            ->setLabel(___("Manually Approve Note (New Signup/Invoice)\n" .
                'this message will be shown for customer after purchase. ' .
                'you can use html markup here'));

        $this->setDefault('manually_approve_note', <<<CUT
<strong>IMPORTANT NOTE: We review  all new payments manually, so your payment is under review currently.<br/>
You will get  email notification after payment will be approved by admin. We are sorry  for possible inconvenience.</strong>
CUT
            );

        $fs->addScript()->setScript(<<<CUT
jQuery(function(){
    jQuery('[name=manually_approve_invoice], [name=manually_approve]').change(function(){
        jQuery('#form-manually_approve_note').closest('.am-row').
            toggle(jQuery('[name=manually_approve_invoice]:checked, [name=manually_approve]:checked').length > 0);
    }).change();
    jQuery("#manually_approve_invoice-0").change(function(){
        jQuery("[rel=manually_approve_invoice]").closest(".am-row").toggle(this.checked);
    }).change();
    jQuery("#manually_approve-0").change(function(){
        jQuery("[rel=manually_approve]").closest(".am-row").toggle(this.checked);
    }).change();
});
CUT
        );

        $fs = $this->addFieldset('##5')
            ->setLabel(___('Miscellaneous'));

        $fs->addAdvCheckbox('admin_require_strong_password')
            ->setLabel(___("Require Strong Password for Admins\n" .
                'password should contain at least 2 capital letters, 2 or more numbers and 2 or more special chars'));

        $fs->addAdvCheckbox('dont_check_updates', null, ['help-id' => '#Configuring_Advanced_Options'])
            ->setLabel(___("Disable Checking for aMember Updates"));

        $fs->addAdvCheckbox('signup_disable')
            ->setLabel(___("Disable New Signups"));

        $fs->addAdvCheckbox('disable_invoice_log')
            ->setLabel(___("Disable Payment System Action Log\n" .
                "it is impossible to troubleshoot issue with payments in event of you disable this log"));

        $fs->addAdvCheckbox('product_paysystem')
            ->setLabel(___("Assign Paysystem to Product"));

        $fs->addAdvCheckbox('am3_urls', null, ['help-id' => '#Configuring_Advanced_Options'])
            ->setLabel(___("Use aMember3 Compatible Urls\n".
                    "Enable old style urls (ex.: signup.php, profile.php)\n".
                    "Usefull only after upgrade from aMember v3 to keep old links working.\n"
            ));

        $fs->addAdvCheckbox('allow_coupon_upgrades')
            ->setLabel(___("Allow usage of coupons for %sUpgrade paths%s",
                '<a href="'.Am_Di::getInstance()->url('admin-products/upgrades').'" class="link">', '</a>'));

        $fs->addAdvCheckbox('allow_restore')
            ->setLabel(___("Allow resume cancelled recurring subscription"));

        $fs->addAdvCheckbox('allow_cancel')
            ->setLabel(___("Allow cancel recurring subscription from user account"));

        if (!Am_Di::getInstance()->config->get('disable_resource_category')) {
            $fs->addInteger('resource_category_records_per_page', ['placeholder' => 15])
                ->setLabel(___('Resource Category Items per Page'));
        }

        if(!ini_get('suhosin.session.encrypt')) {
            $fs->addSelect('session_storage', null, ['help-id' => '#Configuring_Advanced_Options'])
            ->setLabel(___("Session Storage"))
            ->loadOptions([
                    'db' => ___('aMember Database (default)'),
                    'php' => ___('Standard PHP Sessions'),
            ]);
        } else {
            $fs->addHTML('session_storage')
            ->setLabel(___('Session Storage'))
            ->setHTML('<strong>'.___('Standard PHP Sessions').'</strong> <em>'.___("Can't be changed because your server have suhosin extension enabled")."</em>");
        }
    }
}

/** @help-id "Setup/Login_Page" */
class Am_Form_Setup_Loginpage extends Am_Form_Setup
{
    protected $order = 600;

    function __construct()
    {
        parent::__construct('loginpage');
        $this->setTitle(___('Login Page'));
    }

    function initElements()
    {
        $gr = $this->addGroup(null, null, ['help-id' => '#Login_Page_Options'])
            ->setLabel(___("Redirect After Login\n".
                "where customer redirected after successful\n".
                "login at %s", '<strong>'.Am_Di::getInstance()->url('login') . '</strong>'));
        $sel = $gr->addSelect('protect.php_include.redirect_ok',
                ['size' => 1, 'id' => 'redirect_ok-sel'], [
                'options' => [
                        'first_url' => ___('First available protected url'),
                        'last_url' => ___('Last available protected url'),
                        'single_url' => ___('If only one protected URL, go directly to the URL. Otherwise go to membership page'),
                        'member' => ___('Membership Info Page'),
                        'url' => ___('Fixed Url'),
                        'referer' => ___('Page Where Log In Link was Clicked'),
                ]
            ]);
        $gr->setSeparator(' ');
        $txt = $gr->addText('protect.php_include.redirect_ok_url',
                ['size' => 40, 'style'=>'display:none', 'id' => 'redirect_ok-txt']);
        $this->setDefault('protect.php_include.redirect_ok_url', ROOT_URL);
        $gr->addScript()->setScript(<<<CUT
jQuery(function(){
    jQuery("#redirect_ok-sel").change(function(){
        jQuery("#redirect_ok-txt").toggle(jQuery(this).val() == 'url');
    }).change();
});
CUT
        );

        $gr = $this->addGroup(null, null, ['help-id' => '#Login_Page_Options'])
            ->setLabel(___('Redirect After Logout'));

        $gr->setSeparator(' ');

        $gr->addSelect('protect.php_include.redirect_logout')
            ->setId('redirect_logout')
            ->loadOptions([
                'home' => ___('Home Page'),
                'url' => ___('Fixed Url'),
                'referer' => ___('Page Where Logout Link was Clicked')
            ]);

        $gr->addText('protect.php_include.redirect', 'size=40')
            ->setId('redirect');

        $gr->addScript()->setScript(<<<CUT
jQuery(function(){
    jQuery("#redirect_logout").change(function(){
        jQuery("#redirect").toggle(jQuery(this).val() == 'url');
    }).change();
});
CUT
        );

        $this->addAdvCheckbox('protect.php_include.remember_login', null, ['help-id' => '#Login_Page_Options'])
            ->setId('remember-login')
            ->setLabel(___("Remember Login\n".
                "remember username/password in cookies"));

        $this->addAdvCheckbox('protect.php_include.remember_auto', ['rel' => 'remember-login'], ['help-id' => '#Login_Page_Options'])
            ->setLabel(___("Always Remember\n".
                "if set to Yes, don't ask customer - always remember"));

        $this->setDefault('protect.php_include.remember_period', 60);
        $this->addInteger('protect.php_include.remember_period', ['rel' => 'remember-login'], ['help-id' => '#Login_Page_Options'])
        ->setLabel(___("Remember Period\n" .
                "cookie will be stored for ... days"));

        $this->addScript()
            ->setScript(<<<CUT
jQuery('#remember-login').change(function(){
    jQuery('[rel=remember-login]').closest('.am-row').toggle(this.checked)
}).change();
CUT
            );

        $gr = $this->addGroup();
        $gr->setSeparator(' ');
        $gr->setLabel(___("Force Change Password\n" .
            "ask user to change password every XX days"));
        $gr->addAdvCheckbox('force_change_password', ['id' => 'force_change_password'])
            ->setLabel(___('Force Change Password'));
        $gr->addStatic()->setContent('<span>' . ___('every'));
        $gr->addText('force_change_password_period', ['placeholder' => 30, 'size'=>3]);
        $gr->addStatic()->setContent(___('days') . '</span>');

        $gr->addScript()
            ->setScript(<<<CUT
jQuery('#force_change_password').change(function(){
    jQuery(this).nextAll().toggle(this.checked);
}).change();
CUT
            );

        $this->addAdvCheckbox('auto_login_after_signup', null, ['help-id' => '#Login_Page_Options'])
            ->setLabel(___('Automatically Login Customer After Signup'));
        $this->addAdvCheckbox('auto_login_after_pass_reset')
            ->setLabel(___('Automatically Login Customer After Password Reset'));

        $this->setDefault('login_session_lifetime', 120);
        $this->addInteger('login_session_lifetime', null, ['help-id' => '#Login_Page_Options'])
            ->setLabel(___("User Session Lifetime (minutes)\n".
                "default - 120"))
            ->addRule('regex', ___('Please specify number greater then zero'), '/^[1-9][0-9]*$/');

        $gr = $this->addGroup(null, null, ['help-id' => '#Account_Sharing_Prevention'])
            ->setLabel(___("Account Sharing Prevention"));

        $gr->addStatic()->setContent('<div>');
        $gr->addStatic()->setContent(___('if customer uses more than') . ' ');
        $gr->addInteger('max_ip_count', ['size' => 4]);
        $gr->addStatic()->setContent(' ' . ___('IP within') . ' ');
        $gr->addInteger('max_ip_period', ['size' => 5]);
        $gr->addStatic()->setContent(' ' . ___('minutes %sdeny access for user%s and do the following', '<strong>', '</strong>'));
        $gr->addStatic()->setContent('<br /><br />');
        $ms = $gr->addMagicSelect('max_ip_actions')
            ->loadOptions([
                        'disable-user' => ___('Disable Customer Account'),
                        'email-admin' => ___('Email Admin Regarding Account Sharing'),
                        'email-user' => ___('Email User Regarding Account Sharing'),
            ]);
        $ms->setJsOptions('{onChange:function(val){
                jQuery("#max_ip_actions_admin").toggle(val.hasOwnProperty("email-admin"));
                jQuery("#max_ip_actions_user").toggle(val.hasOwnProperty("email-user"));
        }}');
        $gr->addStatic()->setContent('<br />');
        $gr->addStatic()->setContent('<div id="max_ip_actions_admin" style="display:none;">');
        $gr->addElement('email_link', 'max_ip_actions_admin')
            ->setLabel(___('Email Admin Regarding Account Sharing'));
        $gr->addStatic()->setContent('<div>'.___('Admin notification').'</div><br /></div><div id="max_ip_actions_user" style="display:none;">');
        $gr->addElement('email_link', 'max_ip_actions_user')
            ->setLabel(___('Email User Regarding Account Sharing'));
        $gr->addStatic()->setContent('<div>'.___('User notification').'</div><br /></div>');
        $gr->addSelect('max_ip_octets')->loadOptions([
            0 => ___('Count all IPv4 as different'),
            1 => ___('Use first %d IP address octets to determine different IP (%s)', 3, '123.32.22.xx'),
            2 => ___('Use first %d IP address octets to determine different IP (%s)', 2, '123.32.xx.xx'),
            3 => ___('Use first %d IP address octets to determine different IP (%s)', 1, '123.xx.xx.xx'),
        ]);
        $gr->addSelect('max_ip6_blocks')->loadOptions([
            0 => ___('Count all IPv6 as different'),
            4 => ___('Use first %d blocks  to determine different IP (%s)', 4, 'ffff:ffff:ffff:ffff:xxxx:xxxx:xxxx:xxxxx'),
        ]);

        $gr->addStatic()->setContent('</div>');

        $gr = $this->addGroup(null, null, ['help-id' => '#Bruteforce_Protection'])
        ->setLabel(___('Bruteforce Protection'));
        $gr->addStatic()->setContent('<div>');
        $this->setDefault('bruteforce_count', '5');
        $gr->addStatic()->setContent(___('if user enters wrong password') . ' ');
        $gr->addInteger('bruteforce_count', ['size' => 4]);
        $gr->addStatic()->setContent(' ' . ___('times within') . ' ');
        $this->setDefault('bruteforce_delay', '120');
        $gr->addInteger('bruteforce_delay', ['size'=>5]);
        $gr->addStatic()->setContent(' ' . ___('seconds, he will be forced to wait until next try'));
        $gr->addStatic()->setContent('</div>');

        $this->addElement('email_checkbox', 'bruteforce_notify')
            ->setLabel(___("Bruteforce Notification\n".
                "notify admin when bruteforce attack is detected"));

        $this->addEnableRecaptcha('recaptcha')
            ->setLabel(___("Enable ReCaptcha\n".
                    "on login and restore password forms for both admin and user interfaces"));

        $this->addAdvCheckbox('reset_pass_no_disclosure')
            ->setLabel(___("Do not provide feedback on Reset Password Form\n" .
                'do not give info about existence of user with such email/login, it improve security but decrease quality of user experience'));

        $this->addAdvCheckbox('skip_index_page')
            ->setLabel(___("Skip Index Page if User is Logged-in\n" .
                'When logged-in user try to access /amember/index page, he will be redirected to /amember/member'))
            ->setId('skip-index-page');

        $this->addSelect('index_page')
            ->setLabel(___("Index Page\n" .
                "%sthis page will be public and do not require any login/password%s\n" .
                'you can create new pages %shere%s', '<strong>', '</strong>',
                '<a class="link" href="' . Am_Di::getInstance()->url('default/admin-content/p/pages') . '">', '</a>'))
            ->loadOptions([''=> '** ' . ___('Default Index Page'), '-1' => '** ' . ___('Login Page')] +
                Am_Di::getInstance()->db->selectCol("SELECT page_id AS ?, title FROM ?_page", DBSIMPLE_ARRAY_KEY))
            ->setId('index-page');

         $this->addSelect('video_non_member')
            ->setLabel(___("Video for Guest User\n" .
                'this video will be shown instead of actual video in case of ' .
                'guest (not logged in) user try to access protected video content. %sThis video ' .
                'will be public and do not require any login/password%s. ' .
                'You can add new video %shere%s', '<strong>', '</strong>',
                '<a class="link" href="' . Am_Di::getInstance()->url('default/admin-content/p/video') . '">', '</a>'))
            ->loadOptions([''=>___('Show Error Message')] +
                Am_Di::getInstance()->db->selectCol("SELECT video_id AS ?, title FROM ?_video", DBSIMPLE_ARRAY_KEY));
         $this->addSelect('video_not_proper_level')
            ->setLabel(___("Video for User without Proper Membership Level\n" .
                "this video will be shown instead of actual video in case of " .
                "user without proper access try to access protected video " .
                "content. %sThis video will be public and do not require any login/password%s. " .
                "You can add new video %shere%s", '<strong>', '</strong>',
                '<a class="link" href="' . Am_Di::getInstance()->url('default/admin-content/p/video') . '">', '</a>'))
            ->loadOptions([''=>___('Show Error Message')] +
                Am_Di::getInstance()->db->selectCol("SELECT video_id AS ?, title FROM ?_video", DBSIMPLE_ARRAY_KEY));

        $this->addAdvCheckbox('other_domains_redirect')
            ->setLabel(___("Allow Redirects to Other Domains\n".
                        "By default aMember does not allow to redirect to foreign domain names via 'amember_redirect_url' parameter.\n".
                        "These redirects are only allowed for urls within your domain name.\n".
                        "This is restricted to avoid potential security issues.\n"
                ));

        $this->addAdvCheckbox('allow_auth_by_savedpass')
            ->setLabel(___("Allow to Use Password Hash from 3ty part Scripts to Authenticate User in aMember\n" .
                "you need to enable this option only if you imported users from 3ty part script without known plain text password"));

        $fs = $this->addAdvFieldset()
            ->setLabel(___("Login Page Meta Data"));

        $fs->addText('login_meta_title', ['class' => 'am-el-wide'])
            ->setLabel(___("Login Page Title\nmeta data (used by search engines)"));
        $fs->addText('login_meta_keywords', ['class' => 'am-el-wide'])
            ->setLabel(___("Login Page Keywords\nmeta data (used by search engines)"));
        $fs->addText('login_meta_description', ['class' => 'am-el-wide'])
            ->setLabel(___("Login Page Description\nmeta data (used by search engines)"));
    }
}

/** @help-id "Setup/Languages" */
class Am_Form_Setup_Language extends Am_Form_Setup
{
    function __construct()
    {
        parent::__construct('language');
        $this->setTitle(___('Languages'));
    }

    function initElements()
    {
        $this->addAdvCheckbox('lang.display_choice', null, ['help-id' => '#Enabling.2FDisabling_Language_Choice_Option'])
            ->setLabel(___('Display Language Choice'));
        $list = Am_Di::getInstance()->languagesListUser;

        $this->setDefault('lang.default', 'en');
        $this->addSelect('lang.default', ['class' => 'am-combobox'], ['help-id' => '#Selecting.2FEditing_Default_Language'])
            ->setLabel(___('Default Locale'))
            ->loadOptions($list);

        $this->addSortableMagicSelect('lang.enabled', ['class' => 'am-combobox'], ['help-id' => '#Selecting_Languages_to_Offer'])
            ->setLabel(___("Available Locales\ndefines both language and date/number formats, default locale is always enabled"))
            ->loadOptions($list);

        $formats = [];
        foreach ([
            "M j, Y",
            "j M Y",
            "F j, Y",
            "Y-m-d",
            "m/d/Y",
            "m/d/y",
            "d/m/Y",
            "d.m.Y",
            "d/m/y"
                 ] as $f) {
            $formats[$f] = date($f) . " <span style=\"color:#c2c2c2; padding-left:.5em\">{$f}</span>";
        }

        $this->addAdvRadio('date_format')
            ->setLabel(___('Date Format'))
            ->loadOptions(['' => ___('Use Locale Preference')] + $formats);

        $formats = [];
        foreach ([
            "g:i a",
            "g:i A",
            "H:i"
                 ] as $f) {
            $formats[$f] = date($f) . " <span style=\"color:#c2c2c2; padding-left:.5em\">{$f}</span>";;
        }

        $this->addAdvRadio('time_format')
            ->setLabel(___('Time Format'))
            ->loadOptions(['' => ___('Use Locale Preference')] + $formats);
    }

    public function beforeSaveConfig(Am_Config $before, Am_Config $after)
    {
        $enabled = $after->get('lang.enabled');
        $default = $after->get('lang.default');
        if (!in_array($default, $enabled)) {
            $enabled[] = $default;
            $after->set('lang.enabled', $enabled);
        }
    }
}

class Am_Form_Setup_Theme extends Am_Form_Setup
{
    protected $themeId;

    public function __construct($themeId)
    {
        $this->themeId = $themeId;
        parent::__construct('themes-'.$themeId);
    }

    public function prepare()
    {
        parent::prepare();
        $this->addFieldsPrefix('themes.'.$this->themeId.'.');
    }
}

/** @help-id Setup/ReCaptcha */
class Am_Form_Setup_Recaptcha extends Am_Form_Setup
{
    function __construct()
    {
        parent::__construct('recaptcha');
        $this->setTitle(___('reCAPTCHA'));
    }

    function initElements()
    {
        $this->addText("recaptcha-public-key", ['class'=>'am-el-wide'], ['help-id' => 'Setup/ReCaptcha'])
            ->setLabel("reCAPTCHA v2 Site key\n" .
            "you can get it in your account on <a href='http://www.google.com/recaptcha' class='link' target='_blank' rel=\"noreferrer\">reCAPTCHA site</a>, you may need to sign up (it is free) if you have no account yet")
        ->addRule('required', ___('This field is required'));
        $this->addText("recaptcha-private-key", ['class'=>'am-el-wide'], ['help-id' => 'Setup/ReCaptcha'])
            ->setLabel("reCAPTCHA v2 Secret key\n" .
            "you can get it in your account on <a href='http://www.google.com/recaptcha' class='link' target='_blank' rel=\"noreferrer\">reCAPTCHA site</a>, you may need to sign up (it is free) if you have no account yet")
        ->addRule('required', ___('This field is required'));
        $this->addAdvRadio('recaptcha-theme')
            ->loadOptions([
                'light' =>  'light',
                'dark' =>  'dark'
            ])->setLabel(___('reCAPTCHA Theme'));
        $this->addAdvRadio('recaptcha-size')
            ->loadOptions([
                'normal' =>  'normal',
                'compact' =>  'compact'
            ])->setLabel(___('reCAPTCHA Size'));
        $this->setDefault('recaptcha-size', 'normal');
        $this->setDefault('recaptcha-theme', 'light');
    }
}
/** @help-id Setup/PersonalData */
class Am_Form_Setup_PersonalData extends Am_Form_Setup
{
    protected $helpId = "Setup/PersonalData";

    function __construct()
    {
        parent::__construct('personal-data');
        $this->setTitle(___('Personal Data'));
    }

    function initElements()
    {
        $this->addAdvCheckbox('enable-account-delete', ['id'=>'enable-account-delete'])
            ->setLabel(___("Enable 'Delete Personal Data' functionality for admins/users\n"
                . "Allow for user to request 'Personal Data' to be removed from system\n"
                ));

        $fs = $this->addFieldset('', ['id' => 'personal-data-removal'])->setLabel('Personal Data Removal Settings');

        $fs->addAdvCheckbox('hide-delete-link', ['id'=>'hide-delete-link'])
            ->setLabel(___("Hide  'Delete Personal Data' link in member's area\n"
                . "Do not allow for users to delete personal data on their own\n"
                . "Only admins should have ability to do this"
                ));

        $fs->addSelect('account-removal-method', ['class'=>'am-combobox', 'id'=>'account-removal-method'])
            ->setLabel(___('Removal Method'))
            ->loadOptions([
                'delete'         => ___('Automatically remove account and all associated Personal Data'),
                'delete-request' => ___('Send removal request to Site Admin'),
                'anonymize'      => ___('Automatically anonymize Personal Data')
            ]);

        $fs->addMagicSelect('keep-personal-fields', ['class' => 'am-combobox', 'id'=>'keep-fields'])
            ->setLabel(___("Do not delete/anonymize  these fields\n"
            . "For legal reasons you  may need to keep some information about user\n"
            . "Select Fields that should not be deleted or anonymized"))
            ->loadOptions($this->getDi()->userTable->getPersonalDataFieldOptions());

        if($this->getDi()->modules->isEnabled('aff'))
        {
            $fs->addAdvCheckbox('keep-payout-info', ['id'=>'keep-payout'])->setLabel(___("Do not delete payout details\n"
                . "For legal reasons you may need to keep user's payout details\n"
                . "aMember will not delete user's payout detail fields, user name  and  address\n"
                . "if user receive affiliate payout from you prevously"));
        }
            $fs->addAdvCheckbox('keep-access-log', ['id'=>'keep-access-log'])->setLabel(___("Do not delete user's access log\n"
                . "For legal reasons you may need to keep user's access log\n"
                . "aMember will not delete user's access log (urls that user visited and user's IP address)\n"));

        $fs->addElement('email_link', 'delete_personal_data_notification')
            ->setLabel(___("Admin Notification Message\n"
                . "Notification wil lbe sent if you choose \n"
                . "'Send removal request to Site Admin' method\n"
                . "or if amember was unable to remove Personal Data automatically"));


        $fs = $this->addFieldset('', ['id' => 'personal-data-download'])->setLabel('Allow To Download Personal Data');
        $fs->addAdvCheckbox('enable-personal-data-download', ['id'=>'enable-data-download'])
            ->setLabel(___("Show Personal Data Download Link in Useful Links Block\n"
                . "user will see Personal Data Download link \n"
                . "and will be able to get XML document with Personal Data\n"
                . "below you can select what fields will be included in document"));

        $fs->addMagicSelect('personal-data-download-fields', ['class' => 'am-combobox', 'id'=>'download-fields'])
            ->setLabel(___("These fields will be inclulded in XML document\n"
            . "If none selected, aMember will include all listed fields"))
            ->loadOptions($this->getDi()->userTable->getPersonalDataFieldOptions());


        $fs = $this->addFieldset('', ['id' => 'agreement-documents'])->setLabel('Agreement Documents');
        $this->addHTML()->setHTML(
            ___('Use %sAgreement  Editor%s to create "End User Agreement",  "Privacy Policy" or "Terms of Use"',
                sprintf("<a href='%s'>", $this->getDi()->url('admin-agreement')), '</a>')
            )->setLabel('Agreement Editor');

        /**
        $fs = $this->addFieldset('', ['personal-data-recs'])->setLabel(___('Information'));
        $fs->addHTML()->setHTML($this->checkSignupForms())->setLabel(___("Signup Forms\n"
            . "Please check description below"
            ));

        if($this->getDi()->modules->isEnabled('newsletter'))
        {
            $fs->addHTML()->setHTML($this->checkNewsleterLists())->setLabel(___("Newsletter Lists\n"
                . "Please check descripiton below"));

        }
           **/

        $this->addScript()->setScript(<<<PDJS
jQuery(function(){
    jQuery("#enable-account-delete").on("change", function(){
            jQuery("#personal-data-removal").toggle(jQuery(this).is(":checked"));
    }).trigger('change');
    jQuery('#account-removal-method').on('change', function(){
        jQuery("#keep-fields, #keep-payout, #keep-access-log").closest('.am-row').toggle(jQuery(this).val() == 'anonymize' || jQuery(this).val() == 'delete-request');
    }).trigger('change');
    jQuery('#enable-data-download').on('change', function(){
            jQuery("#download-fields").closest('.am-row').toggle(jQuery(this).is(':checked'));
    }).trigger('change');

});
PDJS
            );

    }

    function checkSignupForms()
    {
        $formsCount = $termsMissing = 0;
        foreach($this->getDi()->savedFormTable->selectObjects("select * from ?_saved_form") as $form)
        {
            $formsCount++;
            if($form->findBrickById('agreement'))
            {
                $termsMissing++;
            }
        }
        if($termsMissing){
            $out="<p style='color:red'>".___("%s out of %s Signup Forms doesn't have agreement brick included", $termsMissing, $formsCount)."</p>";
        }else{
            $out="<p style='color:green'>".___("All Signup Forms have agreement bricks")."</p>";
        }
        return $out;
    }

    function checkNewsleterLists()
    {
        $wrongLists = $this->getDi()->newsletterListTable->findBy(['auto_subscribe' => 1, 'disabled'=>0]);
        if(count($wrongLists)){
            $out.="<p style='color:red'>".___("You have %s newsletter lists that auto-subscribe without user attention", count($wrongLists))."</p>";
        }else{
            $out="<p style='color:green'>".___("You do not have auto-subscribe lists")."</p>";
        }
        return $out;
    }
}