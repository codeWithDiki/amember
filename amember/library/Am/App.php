<?php

/**
 * aMember API bootstrap and required classes
 * @package Am_Utils
 * @author Alex Scott <alex@cgi-central.net>
 * @license http://www.amember.com/p/Main/License
 */

/**
 * Block class - represents a renderable UI block
 * that can be injected into different views
 * @package Am_Block
 */
class Am_Block_Base
{
    protected $title = null;
    protected $id;
    /** @var Am_Plugin */
    protected $plugin;
    protected $path;
    protected $callback;

    /**
     * @param string $title title of the block
     * @param string $id unique id of the block
     * @param Am_Plugin_Base|null $plugin
     * @param string|callback $pathOrCallback
     */
    function __construct($title, $id, $plugin = null, $pathOrCallback = null)
    {
        $this->title = (string) $title;
        $this->id = $id;
        $this->plugin = $plugin;
        if (is_callable($pathOrCallback)) {
            $this->callback = $pathOrCallback;
        } else {
            $this->path = $pathOrCallback;
        }
    }

    function getTitle()
    {
        return $this->title;
    }

    function render(Am_View $view, $envelope = "%s")
    {
        if ($this->path) {
            $view->block = $this;
            // add plugin folder to search path for blocks
            $paths = $view->getScriptPaths();
            $newPaths = null;
            if ($this->plugin &&
                !($this->plugin instanceof Am_Module) &&
                $dir = $this->plugin->getDir()) {
                $newPaths = $paths;
                // we insert it to second postion, as first will be theme
                // lets keep there some place for redefenition
                array_splice($newPaths, 1, 0, [$dir]);
                $view->setScriptPath(array_reverse($newPaths));
            }
            $pluginSaved = !empty($view->plugin) ? $view->plugin : null;
            if ($this->plugin)
                $view->plugin = $this->plugin;
            $ret = $view->render("blocks/" . $this->path);
            $view->plugin = $pluginSaved;
            // remove plugin folder from view search path
            if (!empty($newPaths))
                $view->setScriptPath(array_reverse($paths));
        } elseif ($this->callback) {
            $ret = call_user_func($this->callback, $view, $this);
        } else {
            throw new Am_Exception_InternalError("Unknown block path format");
        }
        return $this->formatIntoEnvelope($ret, $envelope);
    }

    public function formatIntoEnvelope($content, $envelope)
    {
        return sprintf($envelope, $content, $this->getTitle(), $this->getId());
    }

    function getId()
    {
        return $this->id;
    }
}

/**
 * Class for backward compatibilty
 * @deprecated use Am_Block_Base or Am_Widget instead
 */
class Am_Block extends Am_Block_Base
{
    const TOP = 100;
    const MIDDLE = 500;
    const BOTTOM = 900;
    protected $targets = [];
    protected $order = self::MIDDLE;

    public function __construct($targets, $title, $id, $plugin = null, $pathOrCallback = null, $order = self::MIDDLE)
    {
        $this->targets = (array)$targets;
        $this->order = (int)$order;
        parent::__construct($title, $id, $plugin, $pathOrCallback);
    }
    public function getTargets() { return $this->targets; }
    public function getOrder()   { return $this->order; }
}

/**
 * Block registry and rendering
 * @package Am_Block
 */
class Am_Blocks
{
    const TOP = 100;
    const MIDDLE = 500;
    const BOTTOM = 900;

    protected $blocks = [];

    /**
     * can be called as
     * ->add('target', new Am_Block_Base, 100)
     * or as
     * ->add(new Am_Block()) - DEPRECATED! kept for backward compatibility
     *
     * @param string|Am_Block_Base $target
     * @param Am_Block $block
     * @param int $order
     * @return \Am_Blocks
     */
    function add($target, $block = null, $order = self::MIDDLE)
    {
        // for compatibility
        if ($target instanceof Am_Block)
        {
            $block = $target;
            $target = $block->getTargets();
            $order = $block->getOrder();
        }
        foreach ((array)$target as $t)
            $this->blocks[(string) $t][] = ['order' => $order, 'block' => $block];
        return $this;
    }

    function remove($id)
    {
        foreach ($this->blocks as $k => $target)
            foreach ($target as $kk => $block)
                if ($block['block']->getId() == $id)
                    unset($this->blocks[$k][$kk]);
        return $this;
    }

    function setOrder($id, $order)
    {
        foreach ($this->blocks as $k => & $target)
            foreach ($target as $kk => & $block)
                if ($block['block']->getId() == $id)
                {
                    $block['order'] = (int)$order;
                }
        return $this;
    }

    /**
     * Get single block by ID.
     * @param String $id
     * @return Am_Block|null
     */
    function getBlock($id)
    {
        foreach ($this->blocks as $k => $target)
            foreach ($target as $kk => $block)
                if ($block['block']->getId() == $id)
                    return $block['block'];
        return null;
    }

    /**
     * @param Zend_View_Abstract $view
     * @param $blockPattern string
     *    exact path string or wildcard string
     *    wildcard * - matches any word
     *    wildcard ** - matches any number of words and delimiters
     * @return array */
    function get($blockPattern)
    {
        $out = [];
        $blockPattern = preg_quote($blockPattern, "|");
        $blockPattern = str_replace('\*\*', '.+?', $blockPattern);
        $blockPattern = str_replace('\*', '.+?', $blockPattern);
        foreach (array_keys($this->blocks) as $target) {
            if (preg_match("|^$blockPattern\$|", $target))
                foreach ($this->blocks[$target] as $rec) {
                    $out[$rec['order']][] = $rec['block'];
                }
        }
        ksort($out);
        $ret = [];
        foreach ($out as $sort => $arr)
            $ret = array_merge($ret, $arr);
        return $ret;
    }

    function getTargets($id = null)
    {
        if (is_null($id)) {
            return array_keys($this->blocks);
        } else {
            $_ = [];
            foreach ($this->blocks as $k => $target) {
                foreach ($target as $kk => $block) {
                    if ($block['block']->getId() == $id) {
                        $_[] = $k;
                        break;
                    }
                }
            }
            return $_;
        }
    }
}

/**
 * Check, store last run time and run cron jobs
 * @package Am_Utils
 */
class Am_Cron
{
    const HOURLY = 1;
    const DAILY = 2;
    const WEEKLY = 4;
    const MONTHLY = 8;
    const YEARLY = 16;
    const KEY = 'cron-last-run';
    const LOCK = 'am-cron';

    static function getLockId()
    {
        return 'am-lock-' . md5(__FILE__);
    }

    /** @return int */
    static function needRun()
    {
        $last_runned = self::getLastRun();
        if (!$last_runned)
            $last_runned = strtotime('-2 days');
        $h_diff = date('dH') - date('dH', $last_runned);
        $d_diff = date('d') - date('d', $last_runned);
        $w_diff = date('W') - date('W', $last_runned);
        $m_diff = date('m') - date('m', $last_runned);
        $y_diff = date('y') - date('y', $last_runned);
        return ($h_diff ? self::HOURLY : 0) |
        ($d_diff ? self::DAILY : 0) |
        ($w_diff ? self::WEEKLY : 0) |
        ($m_diff ? self::MONTHLY : 0) |
        ($y_diff ? self::YEARLY : 0);
    }

    static function getLastRun()
    {
        return Am_Di::getInstance()->db->selectCell("SELECT `value` FROM ?_store WHERE name=?", self::KEY);
    }

    static function setupHook()
    {
        Am_Di::getInstance()->hook->add('afterRender', [__CLASS__, 'inject']);
    }

    static function inject(Am_Event_AfterRender $event)
    {
        static $runned = 0;
        if ($runned)
            return;
        $url = Am_Di::getInstance()->url('cron');
        if ($event->replace('|</body>|i', "\n<img src='$url' width='1' height='1' style='display:none'>\$1", 1))
            $runned++;
    }

    static function checkCron()
    {

        if (defined('AM_TEST') && AM_TEST)
            return; // do not run during unit-testing
        // get lock
        if (!Am_Di::getInstance()->db->selectCell("SELECT GET_LOCK(?, 0)", self::getLockId())) {
            Am_Di::getInstance()->logger->error("Could not obtain MySQL's GET_LOCK() to update cron run time. Probably attempted to execute two cron processes simultaneously. ");
            return;
        }

        $needRun = self::needRun();
        if ($needRun) {
           Am_Di::getInstance()->db->query("REPLACE INTO ?_store (name, `value`) VALUES (?, ?)",
               self::KEY, time());
        }

        Am_Di::getInstance()->db->query("SELECT RELEASE_LOCK(?)", self::getLockId());

        if(!$needRun){
            return;
        }

        define('AM_CRON', true);

        // Load all payment plugins here. ccBill plugin require hourly cron to be executed;
        Am_Di::getInstance()->plugins_payment->loadEnabled()->getAllEnabled();

        @ignore_user_abort(true);
        @set_time_limit(0);
        @ini_set('memory_limit', AM_HEAVY_MEMORY_LIMIT);

        if (!empty($_GET['log']))
            Am_Di::getInstance()->logger->error("cron.php started");

        $out = "";
        if ($needRun & self::HOURLY) {
            Am_Di::getInstance()->hook->call(Am_Event::HOURLY, [
                'date' => sqlDate('now'),
                'datetime' => sqlTime('now')
            ]);
            $out .= "hourly.";
        }
        if ($needRun & self::DAILY) {
            Am_Di::getInstance()->hook->call(Am_Event::DAILY, [
                'date' => sqlDate('now'),
                'datetime' => sqlTime('now')
            ]);
            $out .= "daily.";
        }
        if ($needRun & self::WEEKLY) {
            Am_Di::getInstance()->hook->call(Am_Event::WEEKLY, [
                'date' => sqlDate('now'),
                'datetime' => sqlTime('now')
            ]);
            $out .= "weekly.";
        }
        if ($needRun & self::MONTHLY) {
            Am_Di::getInstance()->hook->call(Am_Event::MONTHLY, [
                'date' => sqlDate('now'),
                'datetime' => sqlTime('now')
            ]);
            $out .= "monthly.";
        }
        if ($needRun & self::YEARLY) {
            Am_Di::getInstance()->hook->call(Am_Event::YEARLY, [
                'date' => sqlDate('now'),
                'datetime' => sqlTime('now')
            ]);
            $out .= "yearly.";
        }
        if (!empty($_GET['log']))
            Am_Di::getInstance()->logger->error("cron.php finished ($out)");
    }
}

/**
 * Read and write global application config
 * @package Am_Utils
 */
class Am_Config
{
    const DEFAULT_CONFIG_NAME = 'default';

    protected
        $config = [],
        $config_name = null;

    function get($item, $default = null)
    {
        $c = & $this->config;
        foreach (preg_split('/\./', $item) as $s) {
            $c = & $c[$s];
            if (is_null($c) || (is_string($c) && $c == ''))
                return $default;
        }
        return $c;
    }

    /** @return Am_Config provides fluent interface */
    function set($item, $value)
    {
        if (is_null($item))
            throw new Exception("Empty value passed as config key to " . __FUNCTION__);
        $this->setDotValue($item, $value);
        return $this;
    }

    function setConfigName($name)
    {
        $this->config_name = $name;
    }

    function getConfigName()
    {
        if(defined('AM_CONFIG_NAME') && AM_CONFIG_NAME)
            return AM_CONFIG_NAME;

        return is_null($this->config_name) ? self::DEFAULT_CONFIG_NAME : $this->config_name;
    }

    function read()
    {
        $configName = $this->getConfigName();
        $readDbConfigCallback = function() use ($configName) {
            return Am_Di::getInstance()->db->selectCell(
                "SELECT config FROM ?_config WHERE name in (?a) order by name <> ? limit 1",
                [$configName, self::DEFAULT_CONFIG_NAME],
                $configName
            );
        };
        try {
            // that is early hook to read config from cache, etc.
            // standard db reading function is passed as callback
            if (function_exists('am_read_config'))
            {
                $_ = am_read_config($readDbConfigCallback, $configName);
            } else {
                $_ = $readDbConfigCallback();
            }
            $this->config = substr($_, 0, 2) == 'a:' ? unserialize($_) : json_decode($_, true);
            // that is early hook that allows to rewrite config items. useful for staging enviromnents
            if (function_exists('am_set_config'))
                am_set_config($this->config);
        } catch (Am_Exception_Db $e) {
            amDie("aMember Pro is not configured, or database tables are corrupted - could not read config (sql error #" . $e->getCode() . "). You have to remove file [amember/application/configs/config.php] and reinstall aMember, or restore database tables from backup.");
        }
    }

    function save()
    {
        $cfg = serialize($this->config);
        Am_Di::getInstance()->db->query(
            "INSERT INTO  ?_config
                (name, config)
                VALUES
                (?, ?)
             ON DUPLICATE KEY UPDATE config =?
            ", $this->getConfigName(), $cfg, $cfg);
        if (function_exists('am_write_config'))
            am_write_config($cfg, $this->getConfigName());
    }

    function setArray(array $config)
    {
        $this->config = (array) $config;
    }

    function getArray()
    {
        return (array) $this->config;
    }

    protected function setDotValue($item, $value)
    {
        $c = & $this->config;
        $levels = explode('.', $item);
        $last = array_pop($levels);
        $passed = [];
        foreach ($levels as $s) {
            $passed[] = $s;
            if (isset($c[$s]) && !is_array($c[$s])) {
                trigger_error('Unsafe conversion of scalar config value [' . implode('.', $passed) . '] to array in ' . __METHOD__, E_USER_WARNING);
                $c[$s] = ['_SCALAR_' => $c[$s]];
            }
            $c = & $c[$s];
        }
        $c[$last] = $value;
        return $c;
    }

    static function saveValue($k, $v)
    {
        $config = new self;
        $config->read();
        $config->set($k, $v);
        $config->save();
    }
}

/**
 * Re-Captcha display and validation class
 * @package Am_Utils
 */
class Am_Recaptcha
{
    protected $lastErrorCodes = [];

    public function render($theme = null, $size = null, $moreVars = null)
    {
        if (!$this->isConfigured())
            throw new Am_Exception_Configuration("ReCaptcha error - recaptcha is not configured. Please go to aMember Cp -> Setup -> ReCaptcha and enter keys");
        if (empty($theme))
            $theme = Am_Di::getInstance()->config->get('recaptcha-theme', 'light');
        if (empty($size))
            $size = Am_Di::getInstance()->config->get('recaptcha-size', 'normal');
        $public = Am_Html::escape($this->getPublicKey());

        $locale = Am_Di::getInstance()->locale->getId();

        return <<<CUT
        <script type="text/javascript" src="//www.google.com/recaptcha/api.js?hl=$locale" async defer></script>
        <div class="g-recaptcha" data-sitekey="$public" data-theme="$theme" data-size="$size" $moreVars></div>
CUT;
    }

    /** @return bool true on success, false on confirmed failure, and null in case of request failure/configuration error */
    public function validate($response)
    {
        if (!$this->isConfigured())
            throw new Am_Exception_Configuration("Brick: ReCaptcha error - recaptcha is not configured. Please go to aMember Cp -> Setup -> ReCaptcha and enter keys");

        $req = new Am_HttpRequest('https://www.google.com/recaptcha/api/siteverify', Am_HttpRequest::METHOD_POST);
        $req->addPostParameter('secret', Am_Di::getInstance()->config->get('recaptcha-private-key'));
        $req->addPostParameter('remoteip', $_SERVER['REMOTE_ADDR']);
        $req->addPostParameter('response', $response);

        $response = $req->send();
        if ($response->getStatus() == '200') {
            $r = json_decode($response->getBody(), true);
            if ($r['success'])
            {
                return true;
            } elseif (!empty($r['error-codes']) && is_array($r['error-codes'])) {
                $this->lastErrorCodes = (array)$r['error-codes'];
                if (array_intersect(['missing-input-secret','invalid-input-secret'], $this->lastErrorCodes)) {
                    return null; // invalid secret! configuration error!
                }
                return false;
            }
        }
        return null; // request failed
    }

    function getLastErrorCodes()
    {
        return $this->lastErrorCodes;
    }

    function getPublicKey()
    {
        return Am_Di::getInstance()->config->get('recaptcha-public-key');
    }

    public static function isConfigured()
    {
        return Am_Di::getInstance()->config->get('recaptcha-public-key') && Am_Di::getInstance()->config->get('recaptcha-private-key');
    }
}

/**
 * Application bootstrap and common functions
 * @package Am_Utils
 */
class Am_App
{
    /** @var Am_Di */
    private $di;
    protected $config;
    public $initFinished = false;

    public function __construct($config)
    {
        $this->config = is_array($config) ? $config : (require $config);
        if(empty($this->config['cache']))
            $this->config['cache'] = [];

        if (!defined('INCLUDED_AMEMBER_CONFIG'))
            define('INCLUDED_AMEMBER_CONFIG', 1);

        if (defined('AM_DEBUG_IP') && AM_DEBUG_IP && (AM_DEBUG_IP == @$_SERVER['REMOTE_ADDR']))
            @define('AM_APPLICATION_ENV', 'debug');

        // Define application environment
        defined('AM_APPLICATION_ENV')
            || define('AM_APPLICATION_ENV',
                (getenv('AM_APPLICATION_ENV') ? getenv('AM_APPLICATION_ENV') : 'production'));

        // Amember huge database optimization
        defined('AM_HUGE_DB') ?: define('AM_HUGE_DB', false);
        defined('AM_HUGE_DB_CACHE_TIMEOUT') ?: define('AM_HUGE_DB_CACHE_TIMEOUT', 3600);

        defined('AM_HUGE_DB_CACHE_TAG') ?: define('AM_HUGE_DB_CACHE_TAG', 'am_huge_db');
        defined('AM_HUGE_DB_MIN_RECORD_COUNT') ?: define('AM_HUGE_DB_MIN_RECORD_COUNT', 10000);

        if (AM_APPLICATION_ENV == 'debug' || AM_APPLICATION_ENV == 'testing')
            if (!defined('AM_DEBUG'))
                define('AM_DEBUG', true);

        if (!defined('AM_APPLICATION_ENV'))
            define('AM_APPLICATION_ENV', 'production');
    }

    public function bootstrap()
    {
        class_exists('Am_Utils', true); // autoload functions

        defined('AM_PHAR') || define('AM_PHAR', false);
        $realAppPath = APPLICATION_PATH;

        defined('AM_PLUGINS_PATH')
            || define('AM_PLUGINS_PATH', AM_APPLICATION_PATH . '/default/plugins');
        defined('AM_CONFIGS_PATH')
            || define('AM_CONFIGS_PATH', $realAppPath . '/configs');
        defined('AM_APPLICATION_ENV')
            || define('AM_APPLICATION_ENV', APPLICATION_ENV);

        defined('AM_THEMES_PATH') ||
            define('AM_THEMES_PATH', $realAppPath . '/default/themes');

        defined('AM_KEYFILE') ||
            define('AM_KEYFILE', AM_CONFIGS_PATH . '/key.php');

        // add fake include path for duplicate plugins
        if (AM_PHAR)
        {
            set_include_path(dirname(AM_APPLICATION_PATH) . '/library/am-plugins-autoloaders' . ':' . get_include_path());
        }

        if (defined('AM_APPLICATION_ENV') && (AM_APPLICATION_ENV == 'debug')) {
            error_reporting(E_ALL | E_RECOVERABLE_ERROR | E_NOTICE | E_DEPRECATED | E_STRICT);
            @ini_set('display_errors', true);
        } else {
            error_reporting(error_reporting() & ~E_RECOVERABLE_ERROR); // that is really annoying
        }
        if (!defined('HP_ROOT_DIR'))
        {
            require_once __DIR__ .'/../password.php';
            spl_autoload_register([__CLASS__, '__autoload']);
        }

        if (!defined('AM_VERSION_HASH')) // hash related to am version to use in CSS/JS URLs
        {
            define('AM_VERSION_HASH', crc32(filemtime(__FILE__)));
        }

        $this->di = new Am_Di($this->config);
        Am_Di::_setInstance($this->di);
        $this->di->app = $this;
        set_error_handler([$this, '__error']);
        set_exception_handler([$this, '__exception']);
        // this will reset timezone to UTC if nothing configured in PHP
        date_default_timezone_set(@date_default_timezone_get());

        if (file_exists(AM_CONFIGS_PATH . "/config.network.php"))
            require_once AM_CONFIGS_PATH . "/config.network.php";

        $this->di->init();
        try {
            $this->di->getService('db');
        } catch (Am_Exception $e) {
            if (defined('AM_DEBUG') && AM_DEBUG)
                amDie($e->getMessage());
            else
                amDie($e->getPublicError());
        }

        $this->di->config;
        $this->initConstants();

        // set memory limit
        $limit = @ini_get('memory_limit');
        if (preg_match('/(\d+)M$/', $limit, $regs) && ($regs[1] <= 64))
            @ini_set('memory_limit', '64M');
        //
        if (!defined('HP_ROOT_DIR'))
            $this->initFront();
        if (!empty($_COOKIE['am_safe_mode']) && ($_COOKIE['am_safe_mode'] == $this->di->security->siteHash('am_safe_mode')))
            define('AM_SAFE_MODE', 1);

        $this->initModules();
        $this->initSession();
        Am_Locale::initLocale($this->di);
        $this->initTranslate();
        require_once __DIR__ . '/License.php';

        // Load user in order to check may be we need to refresh user's session;
        $this->di->auth->invalidate();
        $this->di->auth->getUser();
        $this->di->authAdmin->invalidate();

        $this->di->hook->call(Am_Event::INIT_FINISHED);
        $this->bindUploadsIfNecessary();
        if (!defined('HP_ROOT_DIR'))
            $this->initFinished = true;
        if (!defined('AM_SAFE_MODE') || !AM_SAFE_MODE)
            if (file_exists(AM_CONFIGS_PATH . "/site.php"))
                require_once AM_CONFIGS_PATH . "/site.php";
    }

    function bindUploadsIfNecessary()
    {
        if ($this->di->session->uploadNeedBind && ($user_id = $this->di->auth->getUserId())) {
            $this->di->db->query('UPDATE ?_upload SET user_id=?, session_id=NULL WHERE upload_id IN (?a) AND session_id=?',
                $user_id, $this->di->session->uploadNeedBind, $this->di->session->getId());
            $this->di->session->uploadNeedBind = null;
        }
    }

    /**
     * Fetch updated license from aMember Pro website
     */
    function updateLicense()
    {
        if (!$this->di->config->get('license'))
            return; // empty license. trial?
        if ($this->di->store->get('app-update-license-checked'))
            return;
        try {
            $req = new Am_HttpRequest('https://update.amember.com/license.php');
            $req->setConfig('connect_timeout', 2);
            $req->setMethod(Am_HttpRequest::METHOD_POST);
            $req->addPostParameter('license', $this->di->config->get('license'));
            $req->addPostParameter('root_url', $this->di->config->get('root_url'));
            $req->addPostParameter('root_surl', $this->di->config->get('root_surl'));
            $req->addPostParameter('version', AM_VERSION);
            $this->di->store->set('app-update-license-checked', 1, '+12 hours');
            $response = $req->send();
            if ($response->getStatus() == '200') {
                $newLicense = $response->getBody();
                if ($newLicense)
                    if (preg_match('/^L[A-Za-z0-9\/=+\n]+X$/', $newLicense))
                        Am_Config::saveValue('license', $newLicense);
                    else
                        throw new Exception("Wrong License Key Received: [" . $newLicense . "]");
            }
        } catch (Exception $e) {
            if (AM_APPLICATION_ENV != 'production')
                throw $e;
        }
    }

    function initTranslate()
    {
        /// setup test translation adapter
        if (defined('AM_DEBUG_TRANSLATE') && AM_DEBUG_TRANSLATE)
        {
            require_once __DIR__ . '/../../utils/TranslateTest.php';
            Zend_Registry::set('Zend_Translate', new Am_Translate_Test(['disableNotices' => true,]));
            return;
        }

        Am_License::getInstance()->init($this);

//        if ($cache = $this->getResource('Cache'))
//            Zend_Translate::setCache($cache);

        $locale = Zend_Locale::getDefault();
        $locale = key($locale);

        $tr = new Zend_Translate([
            'adapter' => 'Zend_Translate_Adapter_Array',
            'locale'    =>  $locale,
            'content'   => ['_'=>'_']
        ]);

        $this->loadTranslations($tr, $locale);
        Zend_Registry::set('Zend_Translate', $tr);
    }

    function loadTranslations(Zend_Translate $tr, $locale)
    {
        list($lang) = explode('_', $locale);
        $tr->addTranslation([
            'content' => AM_APPLICATION_PATH . '/default/language/user/' . $lang . '.php',
            'locale' => $locale,
        ]);

        if (file_exists($this->di->root_dir . '/application/default/language/user/site/' . $lang . '.php')) {
            $tr->addTranslation([
                'content' => $this->di->root_dir . '/application/default/language/user/site/' . $lang . '.php',
                'locale' => $locale,
            ]);
        }

        if (preg_match('/\badmin\b/', @$_SERVER['REQUEST_URI']))
        {
            $tr->addTranslation([
                'content' => AM_APPLICATION_PATH . '/default/language/admin/en.php',
                'locale' => $locale,
            ]);
            if (file_exists(AM_APPLICATION_PATH . '/default/language/admin/' . $lang . '.php')) {
                $tr->addTranslation([
                    'content' => AM_APPLICATION_PATH . '/default/language/admin/' . $lang . '.php',
                    'locale' => $locale,
                ]);
            }
            if (file_exists($this->di->root_dir . '/application/default/language/admin/site/' . $lang . '.php')) {
                $tr->addTranslation([
                    'content' => $this->di->root_dir . '/application/default/language/admin/site/' . $lang . '.php',
                    'locale' => $locale,
                ]);
            }
        }

        foreach (array_unique([$lang, $locale]) as $l) {
            if ($data = $this->di->translationTable->getTranslationData($l)) {
                $tr->addTranslation(
                    [
                        'locale' => $locale,
                        'content' => $data,
                    ]);
            }
        }
    }

    function addRoutes(Am_Mvc_Router $router)
    {
        $router->addRoute('user-logout', new Am_Mvc_Router_Route(
                'logout/*',
                [
                    'module' => 'default',
                    'controller' => 'login',
                    'action' => 'logout',
                ]
        ));
        $router->addRoute('inside-pages', new Am_Mvc_Router_Route(
                ':module/:controller/p/:page_id/:action/*',
                [
                    'page_id' => 'index',
                    'action' => 'index'
                ]
        ));

        $router->addRoute('admin-setup', new Am_Mvc_Router_Route(
                'admin-setup/:p',
                [
                    'module' => 'default',
                    'controller' => 'admin-setup',
                    'action' => 'display',
                ]
        ));
        $router->addRoute('payment', new Am_Mvc_Router_Route(
                'payment/:plugin_id/:action',
                [
                    'module' => 'default',
                    'controller' => 'direct',
                    'action' => 'index',
                    'type' => 'payment',
                ]
        ));
        /**
         *  Add separate route for clickbank plugin.
         *  Clickbank doesn't allow to use word "clickbank" in URL.
         */

        $router->addRoute('c-b', new Am_Mvc_Router_Route(
                'payment/c-b/:action',
                [
                    'module' => 'default',
                    'controller' => 'direct',
                    'action' => 'index',
                    'type' => 'payment',
                    'plugin_id'=>'clickbank'
                ]
        ));

        $router->addRoute('protect', new Am_Mvc_Router_Route(
                'protect/:plugin_id/:action',
                [
                    'module' => 'default',
                    'controller' => 'direct',
                    'action' => 'index',
                    'type' => 'protect',
                ]
        ));
        $router->addRoute('misc', new Am_Mvc_Router_Route(
                'misc/:plugin_id/:action',
                [
                    'module' => 'default',
                    'controller' => 'direct',
                    'action' => 'index',
                    'type' => 'misc',
                ]
        ));
        $router->addRoute('storage', new Am_Mvc_Router_Route(
                'storage/:plugin_id/:action',
                [
                    'module' => 'default',
                    'controller' => 'direct',
                    'action' => 'index',
                    'type' => 'storage',
                ]
        ));
        $router->addRoute('payment-link', new Am_Mvc_Router_Route(
                'pay/:secure_id',
                [
                    'module' => 'default',
                    'controller' => 'pay',
                    'action' => 'index'
                ]
        ));

        $router->addRoute('page', new Am_Mvc_Router_Route(
                'page/:path',
                [
                    'module' => 'default',
                    'controller' => 'content',
                    'action' => 'p'
                ]
        ));
        $router->addRoute('profile', new Am_Mvc_Router_Route(
                'profile/:c',
                [
                    'module' => 'default',
                    'controller' => 'profile',
                    'action' => 'index',
                    'c' => ''
                ]
        ));

        $router->addRoute('profile-email-confirm', new Am_Mvc_Router_Route(
                'profile/confirm-email',
                [
                    'module' => 'default',
                    'controller' => 'profile',
                    'action' => 'confirm-email',
                ]
        ));

        $router->addRoute('signup', new Am_Mvc_Router_Route(
                'signup/:c',
                [
                    'module' => 'default',
                    'controller' => 'signup',
                    'action' => 'index',
                    'c' => ''
                ]
        ));

        $router->addRoute('signup-compat', new Am_Mvc_Router_Route(
                'signup/index/c/:c',
                [
                    'module' => 'default',
                    'controller' => 'signup',
                    'action' => 'index'
                ]
        ));

        $router->addRoute('signup-index-compat', new Am_Mvc_Router_Route(
                'signup/index',
                [
                    'module' => 'default',
                    'controller' => 'signup',
                    'action' => 'index'
                ]
        ));

        $router->addRoute('upload-public-get', new Am_Mvc_Router_Route(
                'upload/get/:path',
                [
                    'module' => 'default',
                    'controller' => 'upload',
                    'action' => 'get'
                ]
        ));
        $router->addRoute('cron-compat', new Am_Mvc_Router_Route(
                'cron.php',
                [
                    'module' => 'default',
                    'controller' => 'cron',
                    'action' => 'index',
                ]
        ));

        $router->addRoute('content-c', new Am_Mvc_Router_Route_Regex(
                'content/([^/]*)\.(\d+)$',
                [
                    'module' => 'default',
                    'controller' => 'content',
                    'action' => 'c'
                ],
                [
                    1 => 'title',
                    2 => 'id'
                ],
                'content/%s.%d'
        ));

        $router->addRoute('buy', new Am_Mvc_Router_Route(
                'buy/:h',
                [
                    'module' => 'default',
                    'controller' => 'buy',
                    'action' => 'index'
                ]
        ));

        $router->addRoute('agreement', new Am_Mvc_Router_Route(
                'agreement/:type',
                [
                    'module' => 'default',
                    'controller' => 'agreement',
                    'action' => 'index'
                ]
        ));

        if ($this->di->config->get('am3_urls', false)) {
            $this->initAm3Routes($router);
        }
    }

    function initAm3Routes(Am_Mvc_Router $router)
    {
        $router->addRoute('v3_urls', new Am_Mvc_Router_Route_Regex(
                '(signup|member|login|logout|profile|thanks).php',
                [
                    'module' => 'default',
                    'action' => 'index'
                ],
                ['controller' => 1]
        ));

        $router->addRoute('v3_logout', new Am_Mvc_Router_Route(
                'logout.php',
                [
                    'module' => 'default',
                    'controller' => 'login',
                    'action' => 'logout',
                ]
        ));

        $router->addRoute('v3_ipn_scripts', new Am_Mvc_Router_Route_Regex(
                'plugins/payment/([0-9a-z]+)_?r?/(ipn)r?.php',
                [
                    'module' => 'default',
                    'controller' => 'direct',
                    'action' => 'index',
                    'type' => 'payment',
                ],
                [
                    'plugin_id' => 1,
                    'action' => 2
                ]));

        $router->addRoute('v3_ipn_paypal_pro', new Am_Mvc_Router_Route_Regex(
                'plugins/payment/paypal_pro/(ipn).php',
                [
                    'module' => 'default',
                    'controller' => 'direct',
                    'action' => 'index',
                    'type' => 'payment',
                    'plugin_id' => 'paypal-pro',
                ],
                [
                    'action'    =>  1
                ]
            ));

        $router->addRoute('v3_thanks_scripts', new Am_Mvc_Router_Route_Regex(
                'plugins/payment/([0-9a-z]+)_?r?/(thanks)?.php',
                [
                    'module' => 'default',
                    'controller' => 'direct',
                    'action' => 'index',
                    'type' => 'payment',
                ],
                [
                    'plugin_id' => 1,
                    'action' => 'thanks'
                ]));

        $router->addRoute('v3_affgo', new Am_Mvc_Router_Route(
                'go.php',
                [
                    'module' => 'aff',
                    'controller' => 'go',
                    'action' => 'am3go',
                ]
        ));
        $router->addRoute('v3_afflinks', new Am_Mvc_Router_Route(
                'aff.php',
                [
                    'module' => 'aff',
                    'controller' => 'aff',
                ]
        ));
    }

    /**
     * Fuzzy match 2 domain names (with/without www.)
     */
    protected function _compareHostDomains($d1, $d2)
    {
        return strcasecmp(
            preg_replace('/^www\./i', '', $d1),
            preg_replace('/^www\./i', '', $d2));
    }

    public function guessBaseUrl()
    {
        $scheme = (empty($_SERVER['HTTPS']) || ($_SERVER['HTTPS'] == 'off')) ? 'http' : 'https';
        $host = @$_SERVER['HTTP_HOST'];
        // try to find exact match for domain name
        foreach ([ROOT_URL, ROOT_SURL] as $u) {
            $p = parse_url($u);
            if (($scheme == @$p['scheme'] && $host == @$p['host'])) { // be careful to change here!! // full match required, else it is BETTER to configure RewriteBase in .htaccess
                return @$p['path'];
            }
        }
        // now try fuzzy match domain name
        foreach ([ROOT_URL, ROOT_SURL] as $u) {
            $p = parse_url($u);
            if (($scheme == @$p['scheme'] && !$this->_compareHostDomains($host, $p['host']))) { // be careful to change here!! // full match required, else it is BETTER to configure RewriteBase in .htaccess
                return @$p['path'];
            }
        }
    }

    public function initFront()
    {
        Zend_Controller_Action_HelperBroker::addPrefix('Am_Mvc_Controller_Action_Helper');
        $front = Zend_Controller_Front::getInstance();
        $front->setParam('di', $this->di);
        $front->setParam('noViewRenderer', true);
        $front->throwExceptions(true);
        $front->addControllerDirectory(__DIR__ . '/UiController');
        $front->addControllerDirectory(__DIR__ . '/UiController/Admin');
        $front->setRequest(new Am_Mvc_Request);
        $front->setResponse(new Am_Mvc_Response);
        $front->getRequest()->setBaseUrl();
        $front->setRouter(new Am_Mvc_Router);
        // if baseUrl has not been automatically detected,
        // try to get it from configured root URLs
        // it may not help in case of domain name mismatch
        // then RewriteBase is only the option!
        if ((null == $front->getRequest()->getBaseUrl())) {
            if ($u = $this->guessBaseUrl())
                $front->getRequest()->setBaseUrl($u);
        }

        if (!$front->getPlugin('Am_Mvc_Controller_Plugin'))
            $front->registerPlugin(new Am_Mvc_Controller_Plugin($this->di), 90);
        if (!defined('REL_ROOT_URL')) {
            $relRootUrl = $front->getRequest()->getBaseUrl();
            // filter it for additional safety
            $relRootUrl = preg_replace('|[^a-zA-Z0-9.\\/_+-~]|', '', $relRootUrl);
            define('REL_ROOT_URL', $relRootUrl);
        }
        $this->addRoutes(Am_Di::getInstance()->router);
    }

    function initBlocks(Am_Event $event)
    {
        $b = $event->getBlocks();

        $b->add('member/main/left', new Am_Widget_ActiveSubscriptions, 200);
        $b->add('member/main/left', new Am_Widget_ActiveResources, 250);
        $b->add(['member/main/left', 'unsubscribe'], new Am_Widget_Unsubscribe, Am_Blocks::BOTTOM + 100);

        $b->add('member/main/right', new Am_Widget_MemberLinks, 200);

        $b->add('member/identity', new Am_Block_Base(null, 'member-identity', null, 'member-identity-std.phtml'));

        $b->add('payment-history/center', new Am_Widget_DetailedSubscriptions());
        $b->add('payment-history/center', new Am_Widget_PaymentHistory());
    }

    function initModules()
    {
        $this->di->modules->loadEnabled()->getAllEnabled();
    }

    public function run()
    {
        if ($this->di->config->get('force_ssl') && !((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https'))) {
            $redirect_url = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            header("Location: $redirect_url");
            return;
        }

        $headers = $this->di->hook->filter([
            'content-type' => 'text/html; charset=utf-8',
            'cache-control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'content-security-policy' => "frame-ancestors 'self'"
                                           ], Am_Event::APP_HEADERS);

        foreach ($headers as $name => $val) {
            header("{$name}: $val");
        }

        $event = $this->di->hook->call(Am_Event::DISPATCH);
        if (!$event->mustStop()) // if not handled already
            Zend_Controller_Front::getInstance()->dispatch();
    }

    public function initHooks()
    {
        class_exists('Am_Hook', true);

        /// load plugins
        $this->di->plugins_protect
            ->loadEnabled()->getAllEnabled();
        $this->di->plugins_payment
            ->addEnabled('free');
        $this->di->plugins_misc
            ->loadEnabled()->getAllEnabled();
        $this->di->plugins_storage
            ->addEnabled('upload')->addEnabled('disk');
        $this->di->plugins_storage->loadEnabled()->getAllEnabled();

        $this->di->plugins_tax->getAllEnabled();


        $this->di->hook
            ->add(Am_Event::HOURLY, [$this->di->app, 'onHourly'])
            ->add(Am_Event::DAILY, [$this->di->app, 'onDaily'])
            ->add(Am_Event::INVOICE_AFTER_INSERT, [$this->di->emailTemplateTable, 'onInvoiceAfterInsert'])
            ->add(Am_Event::INVOICE_STARTED, ['EmailTemplateTable', 'onInvoiceStarted'])
            ->add(Am_Event::PAYMENT_WITH_ACCESS_AFTER_INSERT, ['EmailTemplateTable', 'onPaymentWithAccessAfterInsert'])
            ->add(Am_Event::INVOICE_PAYMENT_REFUND, ['EmailTemplateTable', 'onInvoicePaymentRefund'])
            ->add(Am_Event::DAILY, [$this->di->savedReportTable, 'sendSavedReports'])
            ->add(Am_Event::WEEKLY, [$this->di->savedReportTable, 'sendSavedReports'])
            ->add(Am_Event::MONTHLY, [$this->di->savedReportTable, 'sendSavedReports'])
            ->add(Am_Event::YEARLY, [$this->di->savedReportTable, 'sendSavedReports']);

        if (!$this->di->config->get('use_cron') && Am_Cron::needRun()) // we have no remote cron setup
            Am_Cron::setupHook();
    }

    /**
     * @deprecated
     * Do not add new loading code
     * This autoloader kept here only for loading xxTable.php classes from modules library/ folders - for compat
     * @param $className
     * @return mixed
     */
    static function __autoload($className)
    {
        $deprecated = [
            'Am_Controller' => 'Am_Mvc_Controller',
            'Am_Request' => 'Am_Mvc_Request',
            'Am_Controller_Grid' => 'Am_Mvc_Controller_Grid',
            'Zend_Controller_Router_Route' => 'Am_Mvc_Router_Route',
            'Am_Controller_Api' => 'Am_ApiController_Base',
            'Am_Form_Renderer_Admin' => 'Am_Form_Renderer',
            'Am_Form_Renderer_User' => 'Am_Form_Renderer',
        ];

        $deprecated_abstract = ['Am_Controller_Grid' => true];

        if (isset($deprecated[$className])) {
            $_ = debug_backtrace(false);
            $newName = $deprecated[$className];
            trigger_error(sprintf("Usage of deprecated class %s in line %d of file %s! Use %s instead", $className, $_[1]['line'], $_[1]['file'], $newName));

            $def = array_key_exists($className, $deprecated_abstract) ? 'abstract ' : '';
            return eval("{$def}class $className extends $newName {}");
        }

        if (preg_match('/^([a-zA-Z][A-Za-z0-9]+)Table$/', $className, $regs))
        {
            $className = $regs[1];
            foreach (Am_Di::getInstance()->includePath as $p)
                if (file_exists($p . DIRECTORY_SEPARATOR . $className . '.php'))
                    include_once($p . DIRECTORY_SEPARATOR . $className . '.php');
        }
    }

    public function onDaily(Am_Event $event)
    {
        $this->di->userTable->checkAllSubscriptions();
        $this->di->emailTemplateTable->sendCronExpires();
        $this->di->emailTemplateTable->sendCronAutoresponders();
        $this->di->emailTemplateTable->sendCronPayments();
        $this->di->emailTemplateTable->sendCronPendingNotifications();
        $this->di->store->cronDeleteExpired();
        $this->di->storeRebuild->cronDeleteExpired();
        Am_Auth_BruteforceProtector::cleanUp();

        if ($this->di->config->get('clear_access_log') && $this->di->config->get('clear_access_log_days') > 0) {
            $dat = sqlDate($this->di->time - $this->di->config->get('clear_access_log_days') * 3600 * 24);
            $this->di->accessLogTable->clearOld($dat);
        }

        if ($this->di->config->get('clear_debug_log_days') > 0) {
            $dat = sqlDate($this->di->time - $this->di->config->get('clear_debug_log_days') * 3600 * 24);
            $this->di->errorLogTable->clearOldDebug($dat);
        }

        if ($this->di->config->get('clear_inc_payments') && $this->di->config->get('clear_inc_payments_days') > 0) {
            $dat = sqlDate($this->di->time - $this->di->config->get('clear_inc_payments_days') * 3600 * 24);
            $this->di->invoiceTable->clearPending($dat);
        }

        if ($this->di->config->get('clear_inc_users') && $this->di->config->get('clear_inc_users_days') > 0) {
            $dat = sqlDate($this->di->time - $this->di->config->get('clear_inc_users_days') * 3600 * 24);
            $this->di->userTable->clearPending($dat);
        }

        if ($this->di->config->get('clear_invoice_log') && $this->di->config->get('clear_invoice_log_days') > 0) {
            $dat = sqlDate($this->di->time - $this->di->config->get('clear_invoice_log_days') * 3600 * 24);
            $this->di->invoiceLogTable->clearOld($dat);
        }

        $this->di->uploadTable->cleanUp();
        $this->di->mailQueue->cleanUp();
    }

    public function onHourly(Am_Event $event)
    {
        $this->di->emailTemplateTable->sendCronHourlyPendingNotifications();
        $this->di->mailQueue->sendFromQueue();
        $this->di->db->query("DELETE FROM ?_session WHERE modified < ?", strtotime('-1 day'));
    }

    public function setSessionCookieDomain()
    {
        if (ini_get('session.cookie_domain') != '')
            return; // already configured

        $domain = @$_SERVER['HTTP_HOST'];
        $domain = strtolower(trim(preg_replace('/(\:\d+)$/', '', $domain)));

        if (!$domain || $domain == 'localhost') {
            return;
        }

        if (preg_match('/\.(dev|local)$/', $domain)) {
            @ini_set('session.cookie_domain', ".$domain");
            return;
        }

        /*
         *  If domain is valid IP address do not change session.cookie_domain;
         */
        if (filter_var($domain, FILTER_VALIDATE_IP)) {
            return $domain;
        }

        try {
            $min = Am_License::getMinDomain($domain);
        } catch (Exception $e) {
            return;
        }
        @ini_set('session.cookie_domain', ".$min");
    }

    public function initSession()
    {
        @ini_set('session.use_trans_sid', false);
        @ini_set('session.cookie_httponly', true);

        // lifetime must be bigger than admin and user auth timeout
        $lifetime = (int) ini_get('session.gc_maxlifetime');
        if ($lifetime < ($max = max($this->di->config->get('login_session_lifetime', 120) * 60, 7200))) {
            @ini_set('session.gc_maxlifetime', $max);
        }

        $session = $this->di->session;

        $this->setSessionCookieDomain();
        if (!defined('HP_ROOT_DIR') && ('db' == $this->getSessionStorageType()))
            Zend_Session::setSaveHandler(new Am_Session_SaveHandler($this->di->db));

        if (defined('AM_SESSION_NAME') && AM_SESSION_NAME) {
            $session->setOptions(['name' => AM_SESSION_NAME]);
        }

        try {
            $session->start();
        } catch (Exception $e) {
            // fix for Error #1009 - Internal error when disable shopping cart module
            if (strpos($e->getMessage(), "Failed opening 'Am/ShoppingCart.php'") !== false) {
                $session->destroy();
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit();
            }
            // process other session issues
            if (strpos($e->getMessage(), 'This session is not valid according to') === 0) {
                $session->destroy();
                $session->regenerateId();
                $session->writeClose();
            }
            if (defined('AM_TEST') && AM_TEST) {
                // just ignore error
            } else
                throw $e;
        }

        // Workaround to fix bug: https://bugs.php.net/bug.php?id=68063
        // Sometimes php starts session with empty session_id()
        if(!defined('AM_TEST') && !$session->getId())
        {
            $session->destroy();
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit();
        }
    }

    public function getSessionStorageType()
    {
        if (ini_get('suhosin.session.encrypt'))
            return 'php';
        else
            return $this->di->config->get('session_storage', 'db');
    }

    function initConstants()
    {
        @ini_set('magic_quotes_runtime', false);
        @ini_set('magic_quotes_sybase', false);

        mb_internal_encoding("UTF-8");
        @ini_set('iconv.internal_encoding', 'UTF-8');
        if (!defined('ROOT_URL'))
            define('ROOT_URL', $this->di->config->get('root_url'));
        if (!defined('ROOT_SURL'))
            define('ROOT_SURL', $this->di->config->get('root_surl'));
        if (!defined('AM_WIN'))
            define('AM_WIN', (bool) preg_match('/Win/i', PHP_OS)); // true if on windows
        if (!defined('ROOT_DIR'))
        {
            define('ROOT_DIR', realpath(dirname(dirname(dirname(__FILE__)))));
        }
        $this->di->root_dir = ROOT_DIR;
        if (!defined('DATA_DIR'))
        {
            define('DATA_DIR', ROOT_DIR . '/data');
        }
        if (!defined('DATA_DIR_DISK')) {
            define('DATA_DIR_DISK', DATA_DIR . '/upload');
        }
        if (!defined('DATA_PUBLIC_DIR')) {
            define('DATA_PUBLIC_DIR', DATA_DIR . '/public');
        }
        $this->di->data_dir = DATA_DIR;
        $this->di->upload_dir = DATA_DIR;
        $this->di->upload_dir_disk = DATA_DIR_DISK;
        $this->di->public_dir = DATA_PUBLIC_DIR;

        if (!defined('AM_VERSION'))
            define('AM_VERSION', '6.3.6');
        if (!defined('AM_BETA'))
            define('AM_BETA', '0' == 1);
        if (!defined('AM_HEAVY_MEMORY_LIMIT'))
            define('AM_HEAVY_MEMORY_LIMIT', '256M');
        if (!defined('AM_HEAVY_MAX_EXECUTION_TIME'))
            define('AM_HEAVY_MAX_EXECUTION_TIME', 20);
    }

    function __exception404(Zend_Controller_Response_Abstract $response)
    {
        try {
            $p = $this->di->pageTable->load($this->di->config->get('404_page'));
            $body = $p->render($this->di->view, $this->di->auth->getUserId() ? $this->di->auth->getUser() : null);
        } catch (Exception $e) {
            $body = 'HTTP/1.1 404 Not Found';
        }

        $response
            ->setHttpResponseCode(404)
            ->setBody($body)
            ->setRawHeader('HTTP/1.1 404 Not Found')
            ->sendResponse();
    }

    function __exception($e)
    {
        if ($e instanceof Zend_Controller_Dispatcher_Exception
            && (preg_match('/^Invalid controller specified/', $e->getMessage()))) {
            return $this->__exception404(Zend_Controller_Front::getInstance()->getResponse());
        }
        if ($e->getCode() == 404) {
            return $this->__exception404(Zend_Controller_Front::getInstance()->getResponse());
        }

        try {
            static $in_fatal_error; //!
            $in_fatal_error++;
            if ($in_fatal_error > 2) {
                echo(nl2br("<b>\n\n" . __METHOD__ . " called twice\n\n</b>"));
                exit();
            }
            if (!$this->initFinished) {
                $isApiError = false;
            } else {
                $request = Zend_Controller_Front::getInstance()->getRequest();
                $isApiError = (preg_match('#^/api/#', $request->getPathInfo()) && !preg_match('#^/api/admin($|/)#', $request->getPathInfo()));
            }
            if (!$isApiError)
                    $last_error = $e . ':' . $e->getMessage();
            if (!$isApiError && ((defined('AM_DEBUG') && AM_DEBUG) || (AM_APPLICATION_ENV == 'testing'))) {
                $display_error = "<pre>" . ($e) . ':' . $e->getMessage() . "</pre>";
            } else {
                if ($e instanceof Am_Exception) {
                    $display_error = $e->getPublicError();
                    $display_title = $e->getPublicTitle();
                } elseif ($e instanceof Zend_Controller_Dispatcher_Exception) {
                    $display_error = ___("Error 404 - Not Found");
                    header("HTTP/1.0 404 Not Found");
                } else
                    $display_error = ___('An internal error happened in the script, please contact webmaster for details');
            }
            /// special handling for API errors

            if ($isApiError) {
                $format = $request->getParam('_format', 'json');
                if (!empty($display_title))
                    $display_error = $display_title . ':' . $display_error;
                $display_error = trim($display_error, " \t\n\r");
                if ($format == 'xml') {
                    $xml = new SimpleXMLElement('<error />');
                    $xml->ok = 'false';
                    $xml->message = $display_error;
                    echo (string) $xml;
                } else {
                    echo json_encode(['ok' => false, 'error' => true, 'message' => $display_error]);
                }
                exit();
            }
            if (!$this->initFinished)
                amDie($display_error, false, @$last_error);

            // fixes http://bt.amember.com/issues/597
            if (($router = $this->di->router) instanceof Zend_Controller_Router_Rewrite)
                $router->addDefaultRoutes();
            //
            if ($e instanceof Am_Exception_InternalError) {
                function_exists('http_response_code') && http_response_code(500);
            }

            $t = new Am_View;
            $t->assign('is_html', true); // must be already escaped here!
            if (isset($display_title))
                $t->assign('title', $display_title);
            $t->assign('error', $display_error);
            $t->assign('admin_email', $this->di->config->get('admin_email'));
            if (defined('AM_DEBUG') && AM_DEBUG) {
                $t->assign('trace', $e->getTraceAsString());
            }

            $t->display("error.phtml");

            // log error
            call_user_func_array(
                [
                    method_exists($e, 'log')? $e : $this->di->logger,
                    'log'
                ],
                [
                    'critical',
                    "Exception caught by aMember Core: " . get_class($e),
                    [
                        'exception' => $e,
                        'di' => $this->di
                    ]
                ]
            );


        } catch (Exception $e) {
            if ((defined('AM_DEBUG') && AM_DEBUG) || (AM_APPLICATION_ENV == 'testing')) {
                $display_error = "<pre>" . ($e) . ':' . $e->getMessage() . "</pre>" .
                    " thrown within the exception handler. Message: " . $e->getMessage() . " on line " . $e->getLine();
            } else {
                if ($e instanceof Am_Exception) {
                    $display_error = $e->getPublicError();
                }  else {
                    $display_error = ___('An internal error happened in the script, please contact webmaster for details');
                }
            }
            amDie($display_error);
        }
        (php_sapi_name() == 'cli') ? exit(1) : exit();
    }

    function __error($errno, $errstr, $errfile, $errline)
    {
        if (!(error_reporting() & $errno))
            return;
        $ef = (@AM_APPLICATION_ENV != 'debug') ?
            basename($errfile) : $errfile;
        switch ($errno) {
            case E_RECOVERABLE_ERROR:
                $msg = "<b>RECOVERABLE ERROR:</b> $errstr\nin line $errline of file $errfile";
                if (AM_APPLICATION_ENV == 'debug')
                    echo $msg;
                $this->di->logger->error($msg);
                return true;
            case E_ERROR:
            case E_PARSE:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                $this->di->logger->error("<b>ERROR:</b> $errstr\nin line $errline of file $errfile");
                ob_clean();
                amDie("ERROR [$errno] $errstr\nin line $errline of file $ef");
                exit(1);
            case E_USER_WARNING:
            case E_WARNING:
                if (!defined('AM_DEBUG') || !AM_DEBUG)
                    return;
                if (preg_match('#^Declaration of (Am_Protect_|Am_Paysystem_|Am_Protect|Am_Plugin_|Bootstrap_).+ should be compatible#', $errstr))
                    return;
                if (!defined('SILENT_AMEMBER_ERROR_HANDLER') && !amIsXmlHttpRequest())
                    print("<b>WARNING:</b> $errstr\nin line $errline of file $ef<br />");
                $this->di->logger->error("<b>WARNING:</b> $errstr\nin line $errline of file $errfile");
                break;

            case E_STRICT:
            case E_USER_NOTICE:
            case E_NOTICE:
                if (!defined('AM_DEBUG') || !AM_DEBUG)
                    return;
                if (amIsXmlHttpRequest())
                    return;
                if (preg_match('#^Declaration of (Am_Protect_|Am_Paysystem_|Am_Protect|Am_Plugin_|Bootstrap_).+ should be compatible#', $errstr))
                    return;
                print_rr("<b>NOTICE:</b> $errstr\nin line $errline of file $ef<br />");
                break;
        }
    }

    function getDefaultLocale($addRegion = false)
    {
        @list($found, ) = array_keys(Zend_Locale::getDefault());
        if (!$found)
            return 'en_US';
        if (!$addRegion)
            return $found;
        return (strlen($found) <= 4) ? ___('_default_locale') : $found;
    }

    function dbSync($reportNoChanges = true, $modules = null, $returnOutput = false)
    {
        $output = null;
        if (!$returnOutput)
        {
            $p = function() {
                echo implode('', func_get_args());
            };
        } else {
            $p = function () use (& $output) {
                $output .= implode('', func_get_args());
            };
        }

        $nl = empty($_SERVER['REMOTE_ADDR']) ? "\n" : "<br />\n";
        $db = new Am_DbSync();
        $db->parseTables($this->di->db);
        $xml = new Am_DbSync();

        $p("Parsing XML file: [application/default/db.xml]$nl");
        $xml->parseXml(file_get_contents(AM_APPLICATION_PATH . '/default/db.xml'));

        foreach ($this->di->modules->getPathForPluginsList("db.xml", $modules) as $module => $dbXmlFn)
        {
            $p("Parsing XML file: [application/$module/db.xml]$nl");
            $xml->parseXml(file_get_contents($dbXmlFn));
        }

        $this->di->hook->call(Am_Event::DB_SYNC, [
            'dbsync' => $xml,
        ]);

        $diff = $xml->diff($db);
        if ($sql = $diff->getSql($this->di->db->getPrefix())) {
            $p("Doing the following database structure changes:$nl");
            $p($diff->render());
            $p("$nl");
            if (!$returnOutput && ob_get_level()) ob_end_flush();
            $diff->apply($this->di->db);
            $p("DONE$nl");
            if (!$returnOutput && ob_get_level()) ob_end_flush();
        } elseif ($reportNoChanges) {
            $p("No database structure changes required$nl");
        }

        $output .= $this->etSync($modules, $returnOutput);

        return $output;
    }

    function etSync($modules = null, $returnOutput = false)
    {
        $output = null;
        if (!$returnOutput)
        {
            $p = function() {
                echo implode('', func_get_args());
            };
        } else {
            $p = function () use (& $output) {
                $output .= implode('', func_get_args());
            };
        }

        $e = new Am_Event(Am_Event::ET_SYNC);
        $etXml = [];

        foreach ($this->di->modules->getPathForPluginsList("email-templates.xml", $modules) as $module => $etXmlFn)
        {
            $etFiles["application/$module/email-templates.xml"] = file_get_contents($etXmlFn);
        }
        $etFiles['/default/email-templates.xml'] = file_get_contents(AM_APPLICATION_PATH . '/default/email-templates.xml');

        $e->setReturn($etFiles);
        $this->di->hook->call($e);
        $etFiles = $e->getReturn();

        $nl = empty($_SERVER['REMOTE_ADDR']) ? "\n" : "<br />\n";
        $t = $this->di->emailTemplateTable;

        foreach ($etFiles as $file => $xml) {
            $p("Parsing XML: [$file]$nl");
            $t->importXml($xml);
        }

        return $output;
    }

    function readConfig($fn)
    {
        $this->config = require_once $config;
        return $this;
    }

    public function __call($name, $arguments)
    {
        $movedFuncs = [
            'getSiteKey' => ['security', 'siteKey'],
            'getSiteHash' => ['security', 'siteHash'],
            'hash' => ['security', 'hash'],
            'obfuscate' => ['security', 'obfuscate'],
            'reveal' => ['security', 'reveal'],
            'generateRandomString' => ['security', 'randomString'],
        ];
        if (!empty($movedFuncs[$name]))
        {
            list($diObj, $func) = $movedFuncs[$name];
            return call_user_func_array([$this->di->{$diObj}, $func], $arguments);
        }
        throw new \Exception("Not-existing method called Am_App::$name. Died");
    }

    /**
     * This function is executed by composer.json during build and it is not used anywhere else in runtime
     */
    static function cleanupVendorDir()
    {
        $vendorDir = realpath(__DIR__  . '/../vendor');
        if (strlen($vendorDir) < strlen(__DIR__) )
        {
            fputs(STDERR, "Internal error - incorrectly defined vendorDir: $vendorDir\n");
            exit(2);
        }
        $vendorDir = realpath($vendorDir);

        @unlink($vendorDir . "/bin/generate-defuse-key");
        $it = new RecursiveDirectoryIterator($vendorDir);
        /** @var \SplFileInfo $file */
        foreach(new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST) as $file)
        {
            if (preg_match('#phing|composer|robo|phpunit#i', $file->getPathname())) continue;
            if (preg_match('#/src/#', $file->getPathname())) continue;
            if ($file->isFile())
            {
                if (!preg_match('#\.php$|\.crt$|license|composer.json|installed.json|packages?.json#i', $file->getFilename()) ||
                    preg_match('#/tests/|/test/|/fixtures/|/samples/#', $file->getPathname()))
                {
                    // echo "DELETE: " . $file->getFilename() . "\n";
                    unlink($file->getPathname());
                }
            }
            if ($file->getFilename() == '.') continue;
            if ($file->getFilename() == '..') continue;
            if ($file->isDir() && count(scandir($file->getPathname()))<=2)
            { // remove empty folders
                rmdir($file->getPathname());
            }
        }

    }
}

/**
 * class to run long operations in portions with respect to time and memory limits
 * callback function must set $context variable - it will be passed back on next
 * call, even after page reload.
 * when operation is finished, callback function must return boolean <b>true</b>
 * to indicate completion
 * @package Am_Utils
 */
class Am_BatchProcessor
{
    protected $callback;
    protected $tm_started, $tm_finished;
    protected $max_tm;
    protected $max_mem;
    /**
     * If process was explictly stopped from a function
     * @var bool
     */
    protected $stopped = false;

    /**
     * @param type $callback Callback function - must return true when processing finished
     * @param type $max_tm max execution time in seconds
     * @param type $max_mem memory limit in megabytes
     */
    public function __construct($callback, $max_tm = null, $max_mem = null)
    {
        $max_tm = $max_tm ?: AM_HEAVY_MAX_EXECUTION_TIME * 0.9;
        $max_mem = $max_mem ?: intval(AM_HEAVY_MEMORY_LIMIT) * 0.9;

        if (!is_callable($callback)) {
            throw new Am_Exception_InternalError("Not callable callback passed");
        }
        $this->callback = $callback;

        // get max time
        $this->max_tm = ini_get('max_execution_time');
        if ($this->max_tm <= 0)
            $this->max_tm = 60;
        $this->max_tm = min($this->max_tm, $max_tm);

        // get max memory
        $max_memory = strtoupper(ini_get('memory_limit'));
        if ($max_memory == -1)
            $max_memory = 64 * 1024 * 1024;
        elseif ($max_memory != '') {
            $multi = ['K' => 1024, 'M' => 1024 * 1024, 'G' => 1024 * 1024 * 1024];
            if (preg_match('/^(\d+)\s*(K|M|G)/', $max_memory, $regs))
                $max_memory = $regs[1] * $multi[$regs[2]];
            else
                $max_memory = intval($max_memory);
        }
        $this->max_mem = min($max_mem * 1024 * 1024, $max_memory * 0.9);
    }

    /**
     * @return true if process finished, false if process was breaked due to limits
     */
    function run(& $context)
    {
        $this->tm_started = time();
        $breaked = false;
        $params = [& $context, $this];
        while (!call_user_func_array($this->callback, $params)) {
            if ($this->isStopped() || !$this->checkLimits()) {
                $breaked = true;
                break;
            }
        }
        $this->tm_finished = time();
        return!$breaked;
    }

    function stop()
    {
        $this->stopped = true;
    }

    function isStopped()
    {
        return (bool) $this->stopped;
    }

    /**
     * @return bool false if limits are over
     */
    function checkLimits()
    {
        $tm_used = time() - $this->tm_started;
        if ($tm_used >= $this->max_tm)
            return false;
        if (memory_get_usage() > $this->max_mem)
            return false;
        return true;
    }

    function getRunningTime()
    {
        $finish = $this->tm_finished ? $this->tm_finished : time();
        return $finish - $this->tm_started;
    }
}

// html utils
class Am_Html
{
    static function escape($string)
    {
        return htmlentities($string, ENT_QUOTES, 'UTF-8', false);
    }

    static function attrs(array $attrs)
    {
        $s = "";
        foreach ($attrs as $k => $v)
        {
            if ($s) $s .= ' ';
            if ($v === null) {
                $s .= self::escape($k);
            } else {
                $s .= self::escape($k) . '="' . self::escape(is_array($v) ? implode(' ', $v) : $v) . '"';
            }
        }
        return $s;
    }

    /**
     * Render html for <option>..</option> tags of <select>
     * @param array of options key => value
     * @param mixed selected option key
     */
    static function renderOptions(array $options, $selected = '')
    {
        $out = "";
        foreach ($options as $k => $v) {
            if (is_array($v) && !isset($v['label'])) {
                //// render optgroup instead
                $out .=
                    "<optgroup label='" . Am_Html::escape($k) . "'>"
                    . self::renderOptions($v, $selected)
                    . "</optgroup>\n";
                continue;
            }
            if (is_array($selected)) {
                $sel = in_array($k, $selected) ? ' selected="selected"' : '';
            } else {
                $sel = (string) $k == (string) $selected ? ' selected="selected"' : null;
            }
            if (is_array($v)) {
                $label = $v['label'];
                unset($v['label']);
                $attrs = $v;
            } else {
                $label = $v;
                $attrs = [];
            }
            $out .= sprintf('<option value="%s"%s %s>%s</option>' . "\n",
                    self::escape($k),
                    $sel,
                    self::attrs($attrs),
                    self::escape($label));
        }
        return $out;
    }

    /**
     * Convert array of variables to string of input:hidden values
     * @param array variables
     * @return string <input type="hidden" name=".." value="..."/><input .....
     */
    static function renderArrayAsInputHiddens($vars, $parentK=null)
    {
        $ret = "";
        foreach ($vars as $k => $v)
            if (is_array($v))
                $ret .= self::renderArrayAsInputHiddens($v, $parentK ? $parentK . '[' . $k . ']' : $k);
            else
                $ret .= sprintf('<input type="hidden" name="%s" value="%s" />' . "\n",
                        Am_Html::escape($parentK ? ($parentK . "[" . $k . "]") : $k), Am_Html::escape($v));
        return $ret;
    }

    /**
     * Convert array of variables to array of input:hidden values
     * @param array variables
     * @return array key => value for including into form
     */
    static function getArrayOfInputHiddens($vars, $parentK=null)
    {
        $ret = [];
        foreach ($vars as $k => $v)
            if (is_array($v))
                $ret = array_merge(
                        $ret,
                        self::getArrayOfInputHiddens(
                            $v,
                            $parentK ? $parentK . '[' . $k . ']' : $k
                        )
                );
            else
                $ret[$parentK ? ($parentK . "[" . $k . "]") : $k] = $v;
        return $ret;
    }
}

class Am_Cookie
{
    /** @var bool ignore @see runPage calls */
    static protected $_unitTestEnabled = false;
    /** @var array for testing only
     * @internal
     */
    static private $_cookies = [];

    /** @internal */
    static function _setUnitTestEnabled($flag=true)
    {
        self::$_unitTestEnabled = (bool) $flag;
    }

    static private function _getCookieDomain($d)
    {
        if ($d === null)
            return null;

        $d = strtolower(trim(preg_replace('/(\:\d+)$/', '', $d)));

        if($d == 'localhost')
            return null;

        if(preg_match('/\.(dev|local)$/', $d))
            return null;

        if(filter_var($d, FILTER_VALIDATE_IP))
            return null;

        try {
            $d = '.' . Am_License::getMinDomain($d);
        } catch (Exception $e) {}

        return $d;
    }

    static function delete($name)
    {
        self::set($name, null, time() - 24 * 3600);
    }

    /**
     * @todo check domain parsing and make delCookie global
     */
    static function set($name, $value, $expires=0, $path = '/', $domain=null, $secure=false, $strictDomainName=false, $httponly=false)
    {
        if (self::$_unitTestEnabled)
            self::$_cookies[$name] = $value;
        else
            setcookie($name, $value, $expires, $path, ($strictDomainName ? $domain : self::_getCookieDomain($domain)), $secure, $httponly);
    }

    static function setSamesiteNone($name, $value, $expires=0, $path = '/', $domain=null, $secure=true, $strictDomainName=false, $httponly=false)
    {
        if (self::$_unitTestEnabled)
            self::$_cookies[$name] = $value;
        else {
            if(((!empty($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on'))
                || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && ($_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https'))))
            {
                if (version_compare(phpversion(), '7.3.0') >= 0)
                    setcookie($name, $value, [
                        'expires' => $expires,
                        'path' => $path,
                        'domain' => ($strictDomainName ? $domain : self::_getCookieDomain($domain)),
                        'secure' => true,
                        'httponly' => $httponly,
                        'samesite' => 'none'
                    ]);
                else
                    setcookie($name, $value, $expires, $path . '; samesite=none', ($strictDomainName ? $domain : self::_getCookieDomain($domain)), $secure, $httponly);
            }
            else
                setcookie($name, $value, $expires, $path, ($strictDomainName ? $domain : self::_getCookieDomain($domain)), false, $httponly);
        }
    }

    static function _get($name)
    {
        return @self::$_cookies[$name];
    }

    static function _clear()
    {
        self::$_cookies = [];
    }
}

/**
 * Get icon offsets
 */
class Am_View_Sprite
{
    protected static $_sprite_offsets = [
        'icon' => [
            'add' => 266,
            'admins' => 532,
            'affiliates-banners' => 798,
            'affiliates-commission-rules' => 1064,
            'affiliates-commission' => 1330,
            'affiliates-payout' => 1596,
            'affiliates' => 1862,
            'agreement' => 2128,
            'anonymize' => 2394,
            'api' => 2660,
            'awaiting-me' => 2926,
            'awaiting' => 3192,
            'backup' => 3458,
            'ban' => 3724,
            'build-demo' => 3990,
            'bundle-discount-adv' => 4256,
            'bundle-discount' => 4522,
            'buy-now' => 4788,
            'cancel-feedback' => 5054,
            'cart' => 5320,
            'ccrebills' => 5586,
            'change-pass' => 5852,
            'clear' => 6118,
            'close' => 6384,
            'closed' => 6650,
            'configuration' => 6916,
            'content-afflevels' => 7182,
            'content-category' => 7448,
            'content-directory' => 7714,
            'content-emails' => 7980,
            'content-files' => 8246,
            'content-folders' => 8512,
            'content-integrations' => 8778,
            'content-links' => 9044,
            'content-loginreminder' => 9310,
            'content-newsletter' => 9576,
            'content-pages' => 9842,
            'content-softsalefile' => 10108,
            'content-video' => 10374,
            'content-widget' => 10640,
            'content' => 10906,
            'copy' => 11172,
            'countries' => 11438,
            'dashboard' => 11704,
            'date' => 11970,
            'delete-requests' => 12236,
            'delete' => 12502,
            'documentation' => 12768,
            'download' => 13034,
            'downloads' => 13300,
            'earth' => 13566,
            'edit' => 13832,
            'email-snippet' => 14098,
            'email-template-layout' => 14364,
            'export' => 14630,
            'fields' => 14896,
            'gift-card' => 15162,
            'giftvouchers' => 15428,
            'help' => 15694,
            'helpdesk-category' => 15960,
            'helpdesk-faq' => 16226,
            'helpdesk-fields' => 16492,
            'helpdesk-ticket-my' => 16758,
            'helpdesk-ticket' => 17024,
            'helpdesk' => 17290,
            'info' => 17556,
            'invite-history-all' => 17822,
            'key' => 18088,
            'login' => 18354,
            'logs' => 18620,
            'magnify' => 18886,
            'menu' => 19152,
            'merge' => 19418,
            'new' => 19684,
            'newsletter-subscribe-all' => 19950,
            'newsletters' => 20216,
            'notification' => 20482,
            'oto' => 20748,
            'payment-link' => 21014,
            'personal-content' => 21280,
            'plus' => 21546,
            'preview' => 21812,
            'product-restore' => 22078,
            'products-categories' => 22344,
            'products-coupons' => 22610,
            'products-manage' => 22876,
            'products' => 23142,
            'rebuild' => 23408,
            'repair-tables' => 23674,
            'report-bugs' => 23940,
            'report-feature' => 24206,
            'reports-payments' => 24472,
            'reports-reports' => 24738,
            'reports-vat' => 25004,
            'reports' => 25270,
            'resend' => 25536,
            'resource-category' => 25802,
            'restore' => 26068,
            'retry' => 26334,
            'revert' => 26600,
            'run-report' => 26866,
            'saved-form' => 27132,
            'schedule-terms-change' => 27398,
            'self-service-products' => 27664,
            'self-service' => 27930,
            'setup' => 28196,
            'softsale' => 28462,
            'softsales-license' => 28728,
            'softsales-scheme' => 28994,
            'states' => 29260,
            'status_busy' => 29526,
            'support' => 29792,
            'trans-global' => 30058,
            'two-factor-authy' => 30324,
            'two-factor-duosecurity' => 30590,
            'user-groups' => 30856,
            'user-locked' => 31122,
            'user-not-approved' => 31388,
            'users-browse' => 31654,
            'users-email' => 31920,
            'users-import' => 32186,
            'users-insert' => 32452,
            'users' => 32718,
            'utilites' => 32984,
            'view' => 33250,
            'webhooks-configuration' => 33516,
            'webhooks-queue' => 33782,
            'webhooks' => 34048,
            'xtream-codes-line' => 34314,
            'two-factor-hotp' => 34580,
            'theme' => 34846,
            'oauth' => 35112,
            'add-ons' => 35378,
            'affiliates-setup' => 35644,
            'search' => 35910,
            'revisions' => 36176,
            'widget-configuration' => 36442,
            'widget-delete' => 36708,
            'affiliate-approve' => 36974,
            'affiliate-deny' => 37240,
            'helpdesk-snippets' => 37506,
        ],
        'flag' => [
            'ad' => 26,
            'ae' => 52,
            'af' => 78,
            'ag' => 104,
            'ai' => 130,
            'al' => 156,
            'am' => 182,
            'an' => 208,
            'ao' => 234,
            'ar' => 260,
            'as' => 286,
            'at' => 312,
            'au' => 338,
            'aw' => 364,
            'ax' => 390,
            'az' => 416,
            'ba' => 442,
            'bb' => 468,
            'bd' => 494,
            'be' => 520,
            'bf' => 546,
            'bg' => 572,
            'bh' => 598,
            'bi' => 624,
            'bj' => 650,
            'bm' => 676,
            'bn' => 702,
            'bo' => 728,
            'br' => 754,
            'bs' => 780,
            'bt' => 806,
            'bv' => 832,
            'bw' => 858,
            'by' => 884,
            'bz' => 910,
            'ca' => 936,
            'catalonia' => 962,
            'cc' => 988,
            'cd' => 1014,
            'cf' => 1040,
            'cg' => 1066,
            'ch' => 1092,
            'ci' => 1118,
            'ck' => 1144,
            'cl' => 1170,
            'cm' => 1196,
            'cn' => 1222,
            'co' => 1248,
            'cr' => 1274,
            'cs' => 1300,
            'cu' => 1326,
            'cv' => 1352,
            'cx' => 1378,
            'cy' => 1404,
            'cz' => 1430,
            'de' => 1456,
            'dj' => 1482,
            'dk' => 1508,
            'dm' => 1534,
            'do' => 1560,
            'dz' => 1586,
            'ec' => 1612,
            'ee' => 1638,
            'eg' => 1664,
            'eh' => 1690,
            'en' => 1716,
            'england' => 1742,
            'er' => 1768,
            'es' => 1794,
            'et' => 1820,
            'europeanunion' => 1846,
            'fam' => 1872,
            'fi' => 1898,
            'fj' => 1924,
            'fk' => 1950,
            'fm' => 1976,
            'fo' => 2002,
            'fr' => 2028,
            'ga' => 2054,
            'gb' => 2080,
            'gd' => 2106,
            'ge' => 2132,
            'gf' => 2158,
            'gh' => 2184,
            'gi' => 2210,
            'gl' => 2236,
            'gm' => 2262,
            'gn' => 2288,
            'gp' => 2314,
            'gq' => 2340,
            'gr' => 2366,
            'gs' => 2392,
            'gt' => 2418,
            'gu' => 2444,
            'gw' => 2470,
            'gy' => 2496,
            'hk' => 2522,
            'hm' => 2548,
            'hn' => 2574,
            'hr' => 2600,
            'ht' => 2626,
            'hu' => 2652,
            'id' => 2678,
            'ie' => 2704,
            'il' => 2730,
            'in' => 2756,
            'io' => 2782,
            'iq' => 2808,
            'ir' => 2834,
            'is' => 2860,
            'it' => 2886,
            'ja' => 2912,
            'jm' => 2938,
            'jo' => 2964,
            'jp' => 2990,
            'ke' => 3016,
            'kg' => 3042,
            'kh' => 3068,
            'ki' => 3094,
            'km' => 3120,
            'kn' => 3146,
            'kp' => 3172,
            'kr' => 3198,
            'kw' => 3224,
            'ky' => 3250,
            'kz' => 3276,
            'la' => 3302,
            'lb' => 3328,
            'lc' => 3354,
            'li' => 3380,
            'lk' => 3406,
            'lr' => 3432,
            'ls' => 3458,
            'lt' => 3484,
            'lu' => 3510,
            'lv' => 3536,
            'ly' => 3562,
            'ma' => 3588,
            'mc' => 3614,
            'md' => 3640,
            'me' => 3666,
            'mg' => 3692,
            'mh' => 3718,
            'mk' => 3744,
            'ml' => 3770,
            'mm' => 3796,
            'mn' => 3822,
            'mo' => 3848,
            'mp' => 3874,
            'mq' => 3900,
            'mr' => 3926,
            'ms' => 3952,
            'mt' => 3978,
            'mu' => 4004,
            'mv' => 4030,
            'mw' => 4056,
            'mx' => 4082,
            'my' => 4108,
            'mz' => 4134,
            'na' => 4160,
            'nc' => 4186,
            'ne' => 4212,
            'nf' => 4238,
            'ng' => 4264,
            'ni' => 4290,
            'nl' => 4316,
            'no' => 4342,
            'np' => 4368,
            'nr' => 4394,
            'nu' => 4420,
            'nz' => 4446,
            'om' => 4472,
            'pa' => 4498,
            'pe' => 4524,
            'pf' => 4550,
            'pg' => 4576,
            'ph' => 4602,
            'pk' => 4628,
            'pl' => 4654,
            'pm' => 4680,
            'pn' => 4706,
            'pr' => 4732,
            'ps' => 4758,
            'pt' => 4784,
            'pw' => 4810,
            'py' => 4836,
            'qa' => 4862,
            're' => 4888,
            'ro' => 4914,
            'rs' => 4940,
            'ru' => 4966,
            'rw' => 4992,
            'sa' => 5018,
            'sb' => 5044,
            'sc' => 5070,
            'scotland' => 5096,
            'sd' => 5122,
            'se' => 5148,
            'sg' => 5174,
            'sh' => 5200,
            'si' => 5226,
            'sj' => 5252,
            'sk' => 5278,
            'sl' => 5304,
            'sm' => 5330,
            'sn' => 5356,
            'so' => 5382,
            'sr' => 5408,
            'st' => 5434,
            'sv' => 5460,
            'sy' => 5486,
            'sz' => 5512,
            'tc' => 5538,
            'td' => 5564,
            'tf' => 5590,
            'tg' => 5616,
            'th' => 5642,
            'tj' => 5668,
            'tk' => 5694,
            'tl' => 5720,
            'tm' => 5746,
            'tn' => 5772,
            'to' => 5798,
            'tr' => 5824,
            'tt' => 5850,
            'tv' => 5876,
            'tw' => 5902,
            'tz' => 5928,
            'ua' => 5954,
            'ug' => 5980,
            'um' => 6006,
            'us' => 6032,
            'uy' => 6058,
            'uz' => 6084,
            'va' => 6110,
            'vc' => 6136,
            've' => 6162,
            'vg' => 6188,
            'vi' => 6214,
            'vn' => 6240,
            'vu' => 6266,
            'wales' => 6292,
            'wf' => 6318,
            'ws' => 6344,
            'ye' => 6370,
            'yt' => 6396,
            'za' => 6422,
            'zh' => 6448,
            'zm' => 6474,
            'zw' => 6500,
        ],
    ];

    function getOffset($id, $source = 'icon')
    {
        return isset(self::$_sprite_offsets[$source][$id]) ? self::$_sprite_offsets[$source][$id] : false;
    }
}