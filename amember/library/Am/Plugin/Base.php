<?php


/**
 * Base class for plugin or module entity
 * @package Am_Plugin
 */
class Am_Plugin_Base implements \Psr\Log\LoggerAwareInterface
{
    // build.xml script will run 'grep $_pluginStatus plugin.php' to find out status
    const STATUS_PRODUCTION = 1; // product - all ok
    const STATUS_BETA = 2; // beta - display warning on configuration page
    const STATUS_DEV = 4; // development - do not include into distrubutive
    // by default plugins are included into main build
    const COMM_FREE = 1; // separate plugin - do not include into dist
    const COMM_COMMERCIAL = 2; // commercial plugins, build separately

    /** to strip when calculating id from classname */
    protected $_idPrefix = 'Am_Plugin_';
    /** to automatically add after _initSetupForm */
    protected $_configPrefix = null;
    protected $id;
    protected $config = [];
    protected $version = null;
    private $_di;

    /** @var \Psr\Log\LoggerInterface */
    protected $logger;
    /**
     * Usually hooks are disabled when @see isConfigured
     * returns false. However hooks from this list will
     * anyway be enabled
     * @var array of hook names
     */
    protected $hooksToAlwaysEnable = ['setupForms', 'adminWarnings', 'setupEmailTemplateTypes'];

    function __construct(Am_Di $di, array $config)
    {
        $this->_di = $di;
        $this->config = $config;
        // logger can be replaced later by call of setLogger()
        $this->logger = $di->hasService('logger') ? $di->logger : new \Psr\Log\NullLogger();
        $this->setupHooks();
        $this->init();
    }

    function init()
    {

    }

    /**
     * get dependency injector
     * @return Am_Di
     */
    function getDi()
    {
        return $this->_di;
    }

    function setupHooks()
    {
        $manager = $this->getDi()->hook;
        foreach ($this->getHooks() as $hook => $callback)
            $manager->add($hook, $callback);
    }

    /**
     * Returns false if plugin is not configured and most hooks must be disabled
     * @return bool
     */
    public function isConfigured()
    {
        return true;
    }

    public function onAdminWarnings(Am_Event $event)
    {
        if (!$this->isConfigured()) {
            $setupUrl = $this->getDi()->url('admin-setup/' . $this->getId());
            $event->addReturn(___("Plugin [%s] is not configured yet. Please %scomplete configuration%s", $this->getId(), '<a href="' . $setupUrl . '">', '</a>'));
        }
    }

    /**
     * @return array hookName (without Am_Event) => callback
     */
    public function getHooks()
    {
        $ret = [];
        $isConfigured = $this->isConfigured();
        foreach (get_class_methods(get_class($this)) as $method)
            if (strpos($method, 'on') === 0) {
                $hook = lcfirst(substr($method, 2));
                if ($isConfigured || in_array($hook, $this->hooksToAlwaysEnable))
                    $ret[$hook] = [$this, $method];
            }
        return $ret;
    }

    function destroy()
    {
        $this->getDi()->hook->unregisterHooks($this);
    }

    function getTitle()
    {
        $_ = $this->getId(false);
        $_ = ucfirst(preg_replace('/(.*?[a-z]{1})([A-Z]{1}.*?)/', '$1 $2', preg_replace('#__(\d+)$#', ':$1', $_)));
        return $_;
    }

    function getId($oldStyle=true)
    {
        if (null == $this->id)
        {
            $_ = explode('\\', get_class($this));
            $class = array_pop($_);
            if (stripos($class, $this->_idPrefix)===0)
                $class = substr($class, strlen($this->_idPrefix));
            $this->id = $class;
        }
        return $oldStyle ? fromCamelCase($this->id, '-') : $this->id;
    }

    function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    public function getConfig($key=null, $default=null)
    {
        if ($key === null)
            return $this->config;
        $c = & $this->config;
        foreach (explode('.', $key) as $s) {
            $c = & $c[$s];
            if (is_null($c) || (is_string($c) && $c == ''))
                return $default;
        }
        return $c;
    }

    /**
     * mostly for unit testing
     * @param array $config
     * @access private
     */
    public function _setConfig(array $config)
    {
        $this->config = $config;
    }

    /** Function will be executed after plugin deactivation */
    public function deactivate()
    {

    }

    /** Function will be executed after plugin activation */
    static function activate($id, $pluginType)
    {

    }

    public function getVersion()
    {
        return $this->version === null ? AM_VERSION : $this->version;
    }

    /**
     * @return string|null directory of plugin if plugin has its own directory
     */
    public function getDir()
    {
        $c = new ReflectionClass(get_class($this));
        $fn = $c->getFileName();
        if (preg_match($pat = '|([\w_-]+)' . preg_quote(DIRECTORY_SEPARATOR) . '\1\.php|', $fn)) {
            return dirname($fn);
        }
    }

    /**
     * Can be used to display custom plugin information above of
     * documentation from aMember website
     * or for custom plugins that do not have public documentation
     *
     * @return string return formatted readme for the plugin
     */
    public function getReadme()
    {
        return null;
    }

    public function onSetupForms(Am_Event_SetupForms $event)
    {
        $m = new ReflectionMethod($this, '_initSetupForm');
        if ($m->getDeclaringClass()->getName() == __CLASS__)
            return;
        $form = $this->_beforeInitSetupForm();
        if (!$form)
            return;
        $form->finishInit( function() use ($form) {
            $this->_initSetupForm($form);
            $this->_afterInitSetupForm($form);
        });
        $event->addForm($form);
    }

    /** @return Am_Form_Setup */
    protected function _beforeInitSetupForm()
    {
        $form = new Am_Form_Setup($this->getId());
        $form->setTitle($this->getTitle());
        return $form;
    }

    protected function _afterInitSetupForm(Am_Form_Setup $form)
    {
        if ($this->_configPrefix)
            $form->addFieldsPrefix($this->_configPrefix . $this->getId() . '.');

        $h = new Am_View_Helper_Help;
        $form->addEpilog($h($this));

        if (defined($const = get_class($this) . "::PLUGIN_STATUS") && (constant($const) == self::STATUS_BETA || constant($const) == self::STATUS_DEV)) {
            $beta = (constant($const) == self::STATUS_DEV) ? 'ALPHA' : 'BETA';
            $form->addProlog("<div class='warning_box'>This plugin is currently in $beta testing stage, some features may be unstable. " .
                "Please test it carefully before use.</div>");
        }
    }

    protected function _initSetupForm(Am_Form_Setup $form)
    {

    }

    function logDebug($message)
    {
        //debug method does not write logs
        $this->logger->info(get_class($this).' : '.$message, ['class' => get_class($this)]);
    }

    public function setLogger(\Psr\Log\LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}
