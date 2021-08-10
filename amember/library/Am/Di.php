<?php

/**
 * Dependency injector - holds references to "global" objects
 * @package Am_Utils
 * @property \Psr\Log\LoggerInterface $logger
 * @property Am_Session $session
 * @property Am_BackupProcessor $backupProcessor
 * @property Am_Interval $interval
 * @property DbSimple_Interface $db database
 * @property Am_Crypt $crypt crypt class
 * @property Am_CountryLookup_Abstract $countryLookup service
 * @property Am_Hook $hook hook manager
 * @property Am_Blocks $blocks blocks - small template pieces to insert
 * @property Am_Config $config configuration
 * @property Am_Auth_User $auth user-side authentication
 * @property User $user currently authenticated customer or throws exception if no auth
 * @property Am_Auth_Admin $authAdmin admin-side authentication
 * @property Am_Paysystem_List $paysystemList list of paysystems
 * @property Am_Store $store permanent data storage
 * @property Am_StoreRebuild $storeRebuild permanent data storage
 * @property Am_Upload_Acl $uploadAcl upload acl list
 * @property Am_Recaptcha $recaptcha Re-Captcha API
 * @property Am_Navigation_UserTabs $navigationUserTabs User Tabs in Admin CP
 * @property Am_Navigation_UserTabs $navigationAdmin Admin Menu
 * @property Am_Navigation_UserTabs $navigationUser Member Page menu
 * @property Am_Theme $theme User-side theme
 * @property Am_Form_Theme_Abstract $formThemeUser Form-theme
 * @property Am_Form_Theme_Abstract $formThemeAdmin Form-theme
 * @property array $viewPath paths to templates
 * @property Am_Plugins[] $plugins modules, misc, payment, protect,storage
 * @property Am_Plugins_Modules $modules
 * @property Am_Plugins $plugins_protect
 * @property Am_Plugins $plugins_payment
 * @property Am_Plugins $plugins_misc
 * @property Am_Plugins_Tax $plugins_tax (new plugins to this array may be added via "misc" plugins)
 * @property Am_Plugins_Storage $plugins_storage
 * @property array $languagesListUser list of available languages -> their self-names
 * @property array $languagesListAdmin list of available languages -> their self-names
 * @property Zend_Cache_Backend_ExtendedInterface $cacheBackend cache backend
 * @property Zend_Cache_Core $cache cache
 * @property Zend_Cache_Frontend_Function $cacheFunction cache function call results
 * @property Am_App $app application-specific routines
 * @property Am_Locale $locale locale
 * @property Am_Mvc_Request $request current request (get from front)
 * @property Am_Mvc_Response $response current response (get from front)
 * @property Am_Mvc_Helper_Url $url return URL for given path
 * @property Am_View_Sprite $sprite get icon offset
 * @property Am_View $view view object (not shared! each call returns new instance)
 * @property Am_Mail $mail mail object (not shared! each call returns new instance)
 * @property Am_Security $security security utility functions
 * @property Am_Nonce $nonce CSRF protection nonce
 * @property Composer\Autoload\ClassLoader $autoloader
*
 * @property Am_Mvc_Router $router router to add routes
 * @property Zend_Controller_Front $front Zend_Controller front to add plugins
 * @property int $time current time (timestamp)
 * @property string $sqlDate current date in SQL format yyyy-mm-dd
 * @property string $sqlDateTime current datetime in SQL format yyyy-mm-dd hh:ii:ss
 *
 * @property DateTime $dateTime current DateTime object with default timezone (created from @link time)
 * @property string $root_dir ROOT_DIR constant
 * @property string $data_dir DATA_DIR constant
 * @property string $public_dir data_dir/public constant
 * @property string $upload_dir Upload directory for aMember
 * @property string $upload_dir_disk Directory where customers can FTP files for aMember use
 * @property ArrayObject $includePath
 * @property Am_Mail_Transport_Iface $mailTransport by default set to $mailQueue
 * @property Am_Mail_Transport_Iface $mailQueueTransport real mail transport used by queue
 * @property Am_Mail_Queue $mailQueue mail queue - shall be used as Am_Mail Transport
 * @property Am_Mail_UnsubscribeLink $unsubscribeLink - service to add unsubscribe links to emails
 *
 * /// tables
 * @property AccessLogTable $accessLogTable
 * @property AccessTable $accessTable
 * @property AddressTable $addressTable
 * @property Address $addressRecord
 * @property Agreement $agreementRecord
 * @property AgreementTable $agreementTable
 * @property AdminLogTable $adminLogTable
 * @property AdminTable $adminTable
 * @property BanTable $banTable
 * @property BillingPlanTable $billingPlanTable
 * @property CcRecordTable $CcRecordTable
 * @property CcRebillTable $ccRebillTable
 * @property CountryTable $countryTable
 * @property CouponBatchTable $couponBatchTable
 * @property CouponTable $couponTable
 * @property CurrencyExchangeTable $currencyExchangeTable
 * @property EmailSentTable $emailSentTable
 * @property EmailTemplateTable $emailTemplateTable
 * @property EmailTemplateLayoutTable $emailTemplateLayoutTable
 * @property ErrorLogTable $errorLogTable
 * @property FileTable $fileTable
 * @property FileDownloadTable $fileDownloadTable
 * @property FolderTable $folderTable
 * @property IntegrationTable $integrationTable
 * @property InvoiceItemTable $invoiceItemTable
 * @property InvoiceLogTable $invoiceLogTable
 * @property InvoicePaymentTable $invoicePaymentTable
 * @property InvoiceRefundTable $invoiceRefundTable
 * @property InvoiceTable $invoiceTable
 * @property LinkTable $linkTable
 * @property MailQueueTable $mailQueueTable
 * @property PageTable $pageTable
 * @property ProductCategoryTable $productCategoryTable
 * @property ProductTable $productTable
 * @property ProductUpgradeTable $productUpgradeTable
 * @property ResourceAccessTable $resourceAccessTable
 * @property ResourceCategoryTable $resourceCategoryTable
 * @property SavedFormTable $savedFormTable
 * @property SavedReportTable $savedReportTable
 * @property SavedPassTable $savedPassTable
 * @property StateTable $stateTable
 * @property TranslationTable $translationTable
 * @property UploadTable $uploadTable
 * @property UserGroupTable $userGroupTable
 * @property UserConsentTable $userConsentTable
 * @property UserStatusTable $userStatusTable
 * @property UserTable $userTable
 * /// affiliate module tables
 * @property AffBannerTable $affBannerTable
 * @property AffClickTable $affClickTable
 * @property AffCommissionRuleTable $affCommissionRuleTable
 * @property AffCommissionTable $affCommissionTable
 * @property AffLeadTable $affLeadTable
 * @property AffPayoutDetailTable $affPayoutDetailTable
 * @property AffPayoutTable $affPayoutTable
 * // helpdesk
 * @property HelpdeskMessageTable $helpdeskMessageTable
 * @property HelpdeskTicketTable $helpdeskTicketTable
 * @property HelpdeskSnippetTable $helpdeskSnippetTable
 * @property HelpdeskFaqTable $helpdeskFaqTable
 * // newsletter
 * @property NewsletterListTable $newsletterListTable
 * @property NewsletterUserSubscriptionTable $newsletterUserSubscriptionTable
 *
 * @property-read Access $accessRecord creates new record on each access!
 * @property-read AccessLog $accessLogRecord creates new record on each access!
 * @property-read Admin $adminRecord creates new record on each access!
 * @property-read AdminLog $adminLogRecord creates new record on each access!
 * @property-read AffBanner $affBannerRecord creates new record on each access!
 * @property-read AffClick $affClickRecord creates new record on each access!
 * @property-read AffCommission $affCommissionRecord creates new record on each access!
 * @property-read AffCommissionRule $affCommissionRuleRecord creates new record on each access!
 * @property-read AffLead $affLeadRecord creates new record on each access!
 * @property-read AffPayout $affPayoutRecord creates new record on each access!
 * @property-read AffPayoutDetail $affPayoutDetailRecord creates new record on each access!
 * @property-read Ban $banRecord creates new record on each access!
 * @property-read BillingPlan $billingPlanRecord creates new record on each access!
 * @property-read CcRecord $CcRecordRecord creates new record on each access!
 * @property-read CcRebill $ccRebillRecord creates new record on each access!
 * @property-read Country $countryRecord creates new record on each access!
 * @property-read Coupon $couponRecord creates new record on each access!
 * @property-read CouponBatch $couponBatchRecord creates new record on each access!
 * @property-read CurrencyExchange $currencyExchangeRecord creates new record on each access!
 * @property-read EmailSent $emailSentRecord creates new record on each access!
 * @property-read EmailTemplate $emailTemplateRecord creates new record on each access!
 * @property-read EmailTemplateLayout $emailTemplateLayoutRecord creates new record on each access!
 * @property-read ErrorLog $errorLogRecord error message record!
 * @property-read DebugLog $debugLogRecord debug message record!
 * @property-read File $fileRecord creates new record on each access!
 * @property-read FileDownload $fileDownloadRecord creates new record on each access!
 * @property-read Folder $folderRecord creates new record on each access!
 * @property-read HelpdeskMessage $helpdeskMessageRecord creates new record on each access!
 * @property-read HelpdeskTicket $helpdeskTicketRecord creates new record on each access!
 * @property-read HelpdeskSnippet $helpdeskSnippetRecord creates new record on each access!
 * @property-read HelpdeskFaq $helpdeskFaqRecord creates new record on each access!
 * @property-read Integration $integrationRecord creates new record on each access!
 * @property-read InviteCampaign $inviteCampaignRecord creates new record on each access!
 * @property-read InviteCode $inviteCodeRecord creates new record on each access!
 * @property-read Invoice $invoiceRecord creates new record on each access!
 * @property-read InvoiceItem $invoiceItemRecord creates new record on each access!
 * @property-read InvoiceLog $invoiceLogRecord creates new record on each access!
 * @property-read InvoicePayment $invoicePaymentRecord creates new record on each access!
 * @property-read InvoiceRefund $invoiceRefundRecord creates new record on each access!
 * @property-read Link $linkRecord creates new record on each access!
 * @property-read MailQueue $mailQueueRecord creates new record on each access!
 * @property-read NewsletterList $newsletterListRecord creates new record on each access!
 * @property-read NewsletterUserSubscription $newsletterUserSubscriptionRecord creates new record on each access!
 * @property-read Page $pageRecord creates new record on each access!
 * @property-read Product $productRecord creates new record on each access!
 * @property-read ProductCategory $productCategoryRecord creates new record on each access!
 * @property-read ProductUpgrade $productUpgradeRecord creates new record on each access!
 * @property-read ProductOption $productOption creates new record on each access!
 * @property-read ResourceAbstract $resourceAbstractRecord creates new record on each access!
 * @property-read ResourceAccess $resourceAccessRecord creates new record on each access!
 * @property-read ResourceCategoryAccess $resourceCategoryRecord creates new record on each access!
 * @property-read SavedForm $savedFormRecord creates new record on each access!
 * @property-read SavedReport $savedReportRecord creates new record on each access!
 * @property-read SavedPass $savedPassRecord creates new record on each access!
 * @property-read State $stateRecord creates new record on each access!
 * @property-read Translation $translationRecord creates new record on each access!
 * @property-read Upload $uploadRecord creates new record on each access!
 * @property-read User $userRecord creates new record on each access!
 * @property-read UserConsent $userConsentRecord user consent
 * @property-read UserGroup $userGroupRecord creates new record on each access!
 * @property-read UserStatus $userStatusRecord creates new record on each access!
 *
 */
class Am_Di extends sfServiceContainerBuilder
{
    static $instance;

    /*
     * Default crypt class used by aMember
     */
    function getCryptClass()
    {
        return 'Am_Crypt_Aes128';
    }

    function init()
    {
        $this->register('logger', '\Monolog\Logger')
            ->addArgument("amember-core")
            ->addMethodCall('pushProcessor', [new sfServiceReference('logger_message_processor')])
            ->addMethodCall('pushProcessor', [new sfServiceReference('logger_message_extra')])
            ->addMethodCall('pushHandler', [new sfServiceReference('ErrorLogTable')]);

        $this->register('logger_message_processor', 'Am_Logger_MessageProcessor');
        $this->register('logger_message_extra', 'Am_Logger_MessageExtra');

        $this->setService('front', Zend_Controller_Front::getInstance());

        $_ = dirname(__DIR__). '/vendor/autoload.php';
        $this->setService('autoloader', include $_ );

        $this->register('crypt', $this->getCryptClass())
            ->addMethodCall('checkKeyChanged');
        $this->register('security', 'Am_Security')
            ->addArgument(new sfServiceReference('service_container'));
        $this->register('nonce', 'Am_Nonce')
            ->addArgument(new sfServiceReference('service_container'));
        $this->register('hook', 'Am_Hook')
            ->addArgument($this->getService('service_container'));
        $this->register('config', 'Am_Config')
            ->addMethodCall('read');
        $this->register('paysystemList', 'Am_Paysystem_List')
            ->addArgument(new sfServiceReference('service_container'));
        $this->register('store', 'Am_Store');
        $this->register('storeRebuild', 'Am_StoreRebuild');
        $this->register('uploadAcl', 'Am_Upload_Acl');
        $this->register('recaptcha', 'Am_Recaptcha');
        $this->register('mail', 'Am_Mail')
            ->setShared(false)
            ->setConfigurator([$this, 'configureMailService']);

        $this->register('session', 'Am_Session');
        $this->register('sprite', 'Am_View_Sprite');
        $this->register('view', 'Am_View')
            ->addArgument(new sfServiceReference('service_container'))
            ->setShared(false);
        $this->register('navigationUserTabs', 'Am_Navigation_UserTabs')
            ->addMethodCall('addDefaultPages');
        $this->register('navigationUser', 'Am_Navigation_User')
            ->addMethodCall('addDefaultPages');
        $this->register('navigationAdmin', 'Am_Navigation_Admin')
            ->addMethodCall('addDefaultPages');

        $this->register('backupProcessor', 'Am_BackupProcessor')
            ->setArguments([new sfServiceReference('db'), $this])
            ->setShared(false);

        $this->register('invoice', 'Invoice')->setShared(false);

        $this->setServiceDefinition('TABLE', new sfServiceDefinition('Am_Table',
            [new sfServiceReference('db')]))
            ->addMethodCall('setDi', [$this]);
        $this->setServiceDefinition('RECORD', new sfServiceDefinition('Am_Record'))
            ->setShared(false); // new object created on each access !

        $this->setServiceDefinition('modules', new sfServiceDefinition('Am_Plugins_Modules',
            [
                new sfServiceReference('service_container'),
                'modules', AM_APPLICATION_PATH, 'Bootstrap_%s', '%2$s', ['%s/Bootstrap.php']
            ]))
            ->addMethodCall('setTitle', ['Enabled Modules']);
        $this->setServiceDefinition('plugins_protect', new sfServiceDefinition('Am_Plugins',
            [
                new sfServiceReference('service_container'),
                'protect', AM_PLUGINS_PATH . '/protect', 'Am_Protect_%s'
            ]))
            ->addMethodCall('setTitle', ['Integration']);
        $paymentPlugins = $this->setServiceDefinition('plugins_payment', new sfServiceDefinition('Am_Plugins_Payment',
            [
                new sfServiceReference('service_container'),
                'payment', AM_PLUGINS_PATH . '/payment', 'Am_Paysystem_%s'
            ]))
            ->addMethodCall('setTitle', ['Payment']);
        if (!defined('HP_ROOT_DIR'))
            $paymentPlugins->addMethodCall('addPath', [AM_APPLICATION_PATH . '/cc/plugins']);

        $this->setServiceDefinition('plugins_misc', new sfServiceDefinition('Am_Plugins',
            [
                new sfServiceReference('service_container'),
                'misc', AM_PLUGINS_PATH . '/misc', 'Am_Plugin_%s'
            ]))
            ->addMethodCall('setTitle', ['Other']);
        $this->setServiceDefinition('plugins_storage', new sfServiceDefinition('Am_Plugins_Storage',
            [
                new sfServiceReference('service_container'),
                'storage', AM_PLUGINS_PATH . '/storage', 'Am_Storage_%s'
            ]))
            ->addMethodCall('setTitle', ['File Storage']);
        $this->setServiceDefinition('plugins_tax', new sfServiceDefinition('Am_Plugins_Tax',
            [
                new sfServiceReference('service_container'),
                'tax', AM_PLUGINS_PATH . '/misc', 'Am_Invoice_Tax_%s'
            ]))
            ->addMethodCall('setTitle', ['Tax Plugins']);

        $this->register('cache', 'Zend_Cache_Core')
            ->addArgument([
                'lifetime'=>3600,
                'automatic_serialization' => true,
                'cache_id_prefix' => sprintf('%s_',
                        $this->security->siteHash($this->config->get('db.mysql.db') . $this->config->get('db.mysql.prefix'), 10)
                    )
            ])
            ->addMethodCall('setBackend', [new sfServiceReference('cacheBackend')]);
        $this->register('cacheFunction', 'Zend_Cache_Frontend_Function')
            ->addArgument(['lifetime'=>3600])
            ->addMethodCall('setBackend', [new sfServiceReference('cacheBackend')]);

        $this->register('countryLookup', 'Am_CountryLookup');

        $this->register('app', 'Am_App')
            ->addArgument(new sfServiceReference('service_container'));

        $this->register('url', 'Am_Mvc_Helper_Url');

        $this->register('interval', 'Am_Interval');

        $this->register('includePath', '\ArrayObject');

        $this->register('mailQueue', 'Am_Mail_Queue')
            ->addArgument(new sfServiceReference('mailQueueTransport'))
            ->setConfigurator([$this, 'configureMailQueue']);

        $this->register('unsubscribeLink', 'Am_Mail_UnsubscribeLink')
            ->addArgument(new sfServiceReference('service_container'));

        $this->register('formThemeAdmin', 'Am_Form_Theme_Default')
            ->addArgument(new sfServiceReference('service_container'));

        $this->register('formThemeUser', 'Am_Form_Theme_Default')
            ->addArgument(new sfServiceReference('service_container'));

        if (function_exists('am_after_di_init'))
            am_after_di_init($this);
    }

    // Trick with invoke does not work for virtual vars, so we do a dirtry workaround for a while
    function url($path, $params = null, $encode = true, $absolute = false)
    {
        return call_user_func_array($this->url, func_get_args());
    }

    function surl($path, $params = null, $encode = true)
    {
        return call_user_func_array([$this->url, 'surl'], func_get_args());
    }

    function rurl($path, $params = null, $encode = true)
    {
        return call_user_func_array([$this->url, 'rurl'], func_get_args());
    }

    function __sleep()
    {
        return [];
    }

    function _neverCall_() // expose strings to translation
    {
        ___('Enabled Modules');
        ___('Payment');
        ___('Integration');
        ___('Other');
        ___('File Storage');
    }

    function _setTime($time)
    {
        if (!is_int($time))
            $time = strtotime($time);
        $this->time = $time;
        $this->sqlDate = date('Y-m-d', $time);
        $this->sqlDateTime = date('Y-m-d H:i:s', $time);
        return $this;
    }

    public function getService($id)
    {
        if (empty($this->services[$id]))
            switch ($id)
            {
                case 'time':
                    return time();
                case 'sqlDate':
                    return date('Y-m-d', $this->time);
                case 'sqlDateTime':
                    return date('Y-m-d H:i:s', $this->time);
                case 'dateTime':
                    $tz = new DateTimeZone(date_default_timezone_get());
                    $d = new DateTime('@'.$this->time, $tz);
                    $d->setTimezone($tz);
                    return $d;
                case 'plugins':
                    $plugins = new ArrayObject();
                    foreach ([
                        $this->modules,
                        $this->plugins_payment,
                        $this->plugins_protect,
                        $this->plugins_misc,
                        $this->plugins_storage,
                             ] as $pl)
                        $plugins[$pl->getId()] = $pl;
                    $this->services[$id] = $plugins;
                    return $this->services[$id];
                case 'mailQueueTransport' :
                    $this->services[$id] = $this->createMailTransport();
                    return $this->services[$id];
                case 'mailTransport' : // returns mailQueue is not overriden
                    return AM_APPLICATION_ENV == 'testing' ?
                        Am_Mail_TransportTesting::getInstance() : $this->getService('mailQueue');
                default:
            }
        return parent::getService($id);
    }
    protected function getRouterService()
    {
        return $this->getService('front')->getRouter();
    }
    protected function getRequestService()
    {
        return $this->getService('front')->getRequest();
    }
    protected function getResponseService()
    {
        return $this->getService('front')->getResponse();
    }
    protected function getUserService()
    {
        $user = $this->getService('auth')->getUser();
        if (empty($user))
            throw new Am_Exception_AccessDenied(___("You must be authorized to access this area"));
        return $user;
    }
    protected function getAuthService()
    {
        if (!isset($this->services['auth']))
        {
            $ns = $this->session->ns('amember_auth');
            if ($this->session->isWritable() && !empty($this->services['config']))
                $ns->setExpirationSeconds($this->config->get('login_session_lifetime', 120) * 60);
            $this->services['auth'] = new Am_Auth_User($ns, $this);
        }
        return $this->services['auth'];
    }
    protected function getAuthAdminService()
    {
        if (!isset($this->services['authAdmin']))
        {
            $ns = $this->session->ns('amember_admin_auth');
            $ns->setExpirationSeconds(3600); // admin session timeout is 1 hour
            $this->services['authAdmin'] = new Am_Auth_Admin($ns, $this);
        }
        return $this->services['authAdmin'];
    }

    protected function getPluginsService()
    {
        return [
            'modules' => $this->modules,
            'protect' => $this->plugins_protect,
            'payment' => $this->plugins_payment,
            'misc' => $this->plugins_misc,
            'storage' => $this->plugins_storage,
        ];
    }

    public function pharAddFolders(Am_Plugins $amPlugins)
    {
        /* disabled until PHP will really support Phar
           this function kept here for compat if it was called somewhere externally

        if (!AM_PHAR && !defined('AM_FORCE_PHAR')) return;
        $path = [];
        switch ($amPlugins->getId())
        {
            case 'modules':
                $path = $this->root_dir . '/application';
                $amPlugins->setPharTemplate($this->root_dir . "/amember-module-%s.phar",
                    ['application/%s/Bootstrap.php']);
                break;
            case 'protect':
                $path = $this->root_dir . '/application/default/plugins/protect';
                $amPlugins->setPharTemplate($this->root_dir . "/amember-protect-%s.phar",
                    ['application/default/plugins/protect/%s.php', 'application/default/plugins/protect/%s/%1$s.php']);
                break;
            case 'payment':
                $amPlugins->addPath($this->root_dir .'/application/cc/plugins'); // real cc/plugins folder
                $path = $this->root_dir . '/application/default/plugins/payment';
                $amPlugins->setPharTemplate($this->root_dir . "/amember-payment-%s.phar",
                    ['application/default/plugins/payment/%s.php', 'application/default/plugins/payment/%s/%1$s.php',
                     'application/cc/plugins/%s.php', 'application/cc/plugins/%s/%1$s.php',
                    ]);
                break;
            case 'storage':
                $path = $this->root_dir . '/application/default/plugins/storage';
                $amPlugins->setPharTemplate($this->root_dir . "/amember-storage-%s.phar",
                    ['application/default/plugins/storage/%s.php', 'application/default/plugins/storage/%s/%1$s.php']);
                break;
            case 'misc':
                $path = $this->root_dir . '/application/default/plugins/misc';
                $amPlugins->setPharTemplate($this->root_dir . "/amember-misc-%s.phar",
                    ['application/default/plugins/misc/%s.php', 'application/default/plugins/misc/%s/%1$s.php']);
                break;
        }
        if (!$path) return;
        // "real" path must be second - loading from phar get high priority!
        $amPlugins->addPath($path);
        */
    }

    public function getDbService()
    {
        static $v;
        if (!empty($v)) return $v;
        $config = $this->getParameter('db');
        try {
            $v = Am_Db::connect($config['mysql']);
        } catch (Am_Exception_Db $e) {
            if (AM_APPLICATION_ENV != 'debug')
                amDie("Error establishing a database connection. Please contact site webmaster if this error does not disappear long time");
            else
                throw $e;
        }
        return $v;
    }

    public function getLanguagesListUserService()
    {
        return $this->cacheFunction->call(['Am_Locale','getLanguagesList'], ['user']);
    }
    public function getLanguagesListAdminService()
    {
        return $this->cacheFunction->call(['Am_Locale','getLanguagesList'], ['admin']);
    }
    /**
     * @return array of enabled locales
     */
    public function getLangEnabled($addDefault = true)
    {
        return $this->config->get('lang.enabled',
            $addDefault ? [$this->config->get('lang.default', 'en')] : []
        );
    }

    public function getCacheBackendService()
    {
        if (!isset($this->services['cacheBackend']))
        {
            $config = $this->getParameter('cache');
            $fileBackendOptions = ['cache_dir' => $this->data_dir . '/cache'];
            if (!empty($config['type']) && ($config['type'] == 'apc'))
                $this->services['cacheBackend'] = Zend_Cache::_makeBackend('Apc', $config['options']);
            elseif (!empty($config['type']) && ($config['type'] == 'xcache'))
                $this->services['cacheBackend'] = Zend_Cache::_makeBackend('Xcache',$config['options']);
            elseif (!empty($config['type']) && ($config['type'] == 'memcached'))
                $this->services['cacheBackend'] = Zend_Cache::_makeBackend('Libmemcached', $config['options']);
            elseif (!empty($config['type']) && ($config['type'] == 'memcache'))
                $this->services['cacheBackend'] = Zend_Cache::_makeBackend('Memcached',$config['options']);
            elseif (!empty($config['type']) && ($config['type'] == 'two-levels'))
                $this->services['cacheBackend'] = Zend_Cache::_makeBackend('two-levels', $config['options']);
            elseif (empty($config['type']) && is_writeable($fileBackendOptions['cache_dir']))
                $this->services['cacheBackend'] = Zend_Cache::_makeBackend('two-levels', [
                    'slow_backend' => 'File',
                    'slow_backend_options' => $fileBackendOptions,
                    'fast_backend' => new Am_Cache_Backend_Array(),
                    'auto_refresh_fast_cache' => true,
                ]);
            else
                $this->services['cacheBackend'] = new Am_Cache_Backend_Null();

            if(AM_HUGE_DB)
            {
                $this->db->setCacher($this->services['cacheBackend']);
            }

        }
        return $this->services['cacheBackend'];
    }

    function getViewPathService()
    {
        if (!isset($this->services['viewPath']))
        {
            if (AM_APPLICATION_ENV == 'debug')
                $theme = $this->request->getFiltered('theme');
            if (empty($theme))
                $theme = $this->config->get('theme', 'default');

            $ret = [];

            if (AM_PHAR)
                $ret[] = $this->root_dir . '/application/default/views/';
            $ret[] = AM_APPLICATION_PATH . '/default/views/';

            // add module patches now
            $ret = array_merge($ret, array_values($this->modules->getViewPath()));

            if ($theme != 'default') {
                $this->theme->addViewPath($ret);
            }
            $this->services['viewPath'] = $ret;
        }
        return $this->services['viewPath'];
    }

    function getThemeService()
    {
        if (!isset($this->services['theme']))
        {
            $theme = $this->config->get('theme', 'default');
            // create theme obj
            if (file_exists($fn = AM_THEMES_PATH . '/' . Am_Theme::getParentThemeName($theme) . '/Theme.php'))
                include_once $fn;
            
            $class = class_exists($c = 'Am_Theme_' . ucfirst(toCamelCase(Am_Theme::getParentThemeName($theme)))) ? $c : 'Am_Theme';
            $this->services['theme'] = new $class($this, $theme , $this->config->get('themes.'.$theme, []));
        }
        return $this->services['theme'];
    }
    public function getBlocksService()
    {
        if (!isset($this->services['blocks']))
        {
            class_exists('Am_Widget', true); // load widget classes
            $b = new Am_Blocks();
            $this->services['blocks'] = $b;

            $event = new Am_Event(Am_Event::INIT_BLOCKS, ['blocks' => $b]);
            $this->app->initBlocks($event);
            $this->hook->call($event);
        }
        return $this->services['blocks'];
    }

    //// redefines //////////////
    public function getServiceDefinition($id)
    {
        if (empty($this->definitions[$id]) && preg_match('/^([A-Za-z0-9_]+)Table$/', $id, $regs))
        {
            $class = ucfirst($id);
            if (class_exists($class, true) && is_subclass_of($class, 'Am_Table'))
            {
                $def = clone $this->getServiceDefinition('TABLE');
                $def->setClass($class);
                return $def;
            }
        }
        if (empty($this->definitions[$id]) && preg_match('/^([A-Za-z0-9_]+)Record$/', $id, $regs))
        {
            $class = ucfirst($regs[1]);
            if (class_exists($class, true) && is_subclass_of($class, 'Am_Record'))
            {
                $def = clone $this->getServiceDefinition('RECORD');
                $def->setClass($class);
                $def->addArgument(new sfServiceReference($regs[1] . 'Table'));
                return $def;
            }
        }
        return parent::getServiceDefinition($id);
    }

    protected function configureMailQueue(Am_Mail_Queue $q)
    {
        if ($this->config->get('email_queue_enabled'))
        {
            $q->enableQueue(
                $this->config->get('email_queue_period', 3600),
                $this->config->get('email_queue_limit', 100)
            );
        }
        if ($this->config->get('email_log_days') > 0)
        {
            $q->setLogDays($this->config->get('email_log_days', 7));
        }
    }

    protected function configureMailService($mail)
    {
        $mail::setDefaultFrom(
            $this->config->get('admin_email_from', $this->config->get('admin_email')),
            $this->config->get('admin_email_name', $this->config->get('site_title')));
        $mail::setDefaultTransport($this->getService('mailTransport'));
    }

    public function createMailTransport()
    {
        if (AM_APPLICATION_ENV == 'demo')
            return new Am_Mail_Transport_Null;
        if (AM_APPLICATION_ENV == 'testing')
            return new Am_Mail_Transport_Null;

        switch ($this->config->get('email_method'))
        {
            case 'disabled':
                return new Am_Mail_Transport_Null;
            case 'smtp':
                $host = $this->config->get('smtp_host');
                $config = [
                    'port' => $this->config->get('smtp_port', 25),
                    'ssl' => $this->config->get('smtp_security'),
                ];
                if ($this->config->get('smtp_user') && $this->config->get('smtp_pass')) {
                    $config['username'] = $this->config->get('smtp_user');
                    $config['password'] = $this->config->get('smtp_pass');
                    $config['auth'] = $this->config->get('smtp_auth', 'login');
                    $config['ssl'] = $this->config->get('smtp_security');
                }
                return new Am_Mail_Transport_Smtp($host, $config);
            case 'ses':
                $config = [
                    'accessKey' => $this->config->get('ses_id', 25),
                    'privateKey' => $this->config->get('ses_key'),
                    'region' => $this->config->get('ses_region'),
                ];
                return new Am_Mail_Transport_Ses($config);
            case 'sendgrid':
                $config = [
                    'api_user' => $this->config->get('sendgrid_user'),
                    'api_key' => $this->config->get('sendgrid_key'),
                ];
                return new Am_Mail_Transport_SendGrid($config);
            case 'sendgrid3':
                $config = [
                    'api_key' => $this->config->get('sendgrid3_key'),
                ];
                return new Am_Mail_Transport_SendGrid3($config);
            case 'campaignmonitor':
                $config = [
                    'api_key' => $this->config->get('campaignmonitor_apikey'),
                    'client_id' => $this->config->get('campaignmonitor_clientid'),
                ];
                return new Am_Mail_Transport_CampaignMonitor($config);
            case 'mailjet':
                $config = [
                    'apikey_public' => $this->config->get('mailjet_apikey_public'),
                    'apikey_private' => $this->config->get('mailjet_apikey_private'),
                ];
                return new Am_Mail_Transport_MailJet($config);
            case 'postmark':
                $config = [
                    'token' => $this->config->get('postmark_token'),
                ];
                return new Am_Mail_Transport_Postmark($config);
            case 'mailgun':
                $config = [
                    'account' => $this->config->get('mailgun_account'),
                    'domain' => $this->config->get('mailgun_domain'),
                    'token' => $this->config->get('mailgun_token'),
                ];
                return new Am_Mail_Transport_Mailgun($config);
            default:
                $from_email = $this->config->get('admin_email_from') ?: $this->config->get('admin_email');
                return new Am_Mail_Transport_Sendmail("-f{$from_email}");
        }
    }

    /**
     * That must be last 'getInstance' shortcut in the code !
     * @return Am_Di
     */
    static function getInstance()
    {
        if (empty(self::$instance))
            self::$instance = new self;
        return self::$instance;
    }

    /**
     * for unit testing
     * @access private
     */
    static function _setInstance($instance)
    {
        self::$instance = $instance;
    }
}
