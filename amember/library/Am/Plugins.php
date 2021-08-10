<?php

/**
 * Registry and bootstrapping of plugin objects
 * @package Am_Plugin
 */
class Am_Plugins
{
    const ERROR_WARN = 1;
    const ERROR_LOG = 2;
    const ERROR_EXCEPTION = 4;

    protected $type;
    protected $classNameTemplate = "%s"; // use %s for plugin name
    protected $configKeyTemplate = "%s.%s"; // default : type.pluginId
    protected $fileNameTemplates = [
        '%s.php',
        '%1$s/%1$s.php',
    ];
    protected $cache = [];
    protected $enabled = [];
    protected $title;
    protected $alias = [];
    protected $pharTemplate = null, $insidePharFilenameTemplates = [];
    protected $_available = [];
    protected $_di;
    /** @var array parsed annotations cache */
    protected $annotations = [];
    /** @var array DO NOT USE FOR ANY CODE, it is stricly fake code for findSetupUrl */
    protected $_setupForms = [];
    /** @var array Plugin loadn loading warnings */
    protected $warnings = [];

    function __construct(Am_Di $di, $type, $path, $classNameTemplate='%s', $configKeyTemplate='%s.%s', $fileNameTemplates= ['%s.php', '%1$s/%1$s.php',]
    )
    {
        $this->_di = $di;
        $this->type = $type;
        $this->paths = [$path];
        $this->classNameTemplate = $classNameTemplate;
        $this->configKeyTemplate = $configKeyTemplate;
        $this->fileNameTemplates = $fileNameTemplates;

        $en = (array) $di->config->get($this->getEnabledPluginsConfigKey(), []);
        $this->setEnabled($en);
    }

    protected function getEnabledPluginsConfigKey()
    {
        if ($this->getId() == 'modules')
            return 'modules';
        else
            return 'plugins.' . $this->getId();
    }

    protected function getDisabledPluginsConfigKey()
    {
        return $this->getEnabledPluginsConfigKey()  . '_disabled';
    }

    function getId()
    {
        return $this->type;
    }

    function setTitle($title)
    {
        $this->title = $title;
    }

    function getTitle()
    {
        return $this->title ? $this->title : ucfirst(fromCamelCase($this->getId()));
    }

    function getPaths()
    {
        return $this->paths;
    }

    function setPaths(array $paths)
    {
        $this->paths = (array) $paths;
    }

    function addPath($path)
    {
        $this->paths[] = (string) $path;
    }

    function setPharTemplate($pharTemplate, $insidePharFilenameTemplates)
    {
        $this->pharTemplate = $pharTemplate;
        $this->insidePharFilenameTemplates = $insidePharFilenameTemplates;
    }

    function setEnabled(array $list)
    {
        $this->enabled = array_unique($list);
        return $this;
    }

    function addEnabled($name)
    {
        $this->enabled[] = $name;
        $this->enabled = array_unique($this->enabled);
        return $this;
    }

    /**
     * @return array[string]
     */
    function getEnabled()
    {
        return (array) $this->enabled;
    }

    protected function _fileTemplateToRegexAndGlob($tpl, $path)
    {
        switch ($tpl)
        {
            case '%s.php':
                return ['|([a-zA-Z0-9_-]+?)\.php|', $path . '/*.php'];
            case '%1$s/%1$s.php':
                return ['|([a-zA-Z0-9_-]+?)\/\\1\.php|', $path . '/*/*.php'];
            default:
                $needle = (strpos($tpl, '%1$s') !== false) ? '%1$s' : '%s';
                // replace first occurence of $needle in $tpl to ([a-zA-Z0-9_-]+?)
                $regex = '|'.str_replace(preg_quote($needle), '([a-zA-Z0-9_-]+?)', preg_quote($tpl)).'|';
                // replace all following occurences to \\1
                $glob = $path.'/'.str_replace($needle, '*', $tpl);
        }
        return [$regex, $glob];
    }

    /**
     * Important side effect used by child classes
     *    sets $this->>_available[$id] = $FoundFilename;
     * @return array of strings - [ id => title, id2 => title ]
     */
    function getAvailable()
    {
        $found = [];

        // at first return plugins from folders or from -core.phar - as it is more often used
        foreach ($this->paths as $path) {
            foreach ($this->fileNameTemplates as $tpl)
            {
                list($regex, $glob) = $this->_fileTemplateToRegexAndGlob($tpl, $path);
                foreach (am_glob($glob) as $fullpath) {
                    $s = substr($fullpath, strlen($path) + 1);
                    if (preg_match($regex, $s, $regs)) {
                        $id = $regs[1];
                        if ($id == 'default') {
                            continue;
                        }
                        $found[$id] = $this->getTitleForAvailablePlugin($id, $fullpath);
                        $this->_available[$id] = $fullpath;
                    }
                }
            }
        }
        // then find out for plugins in phar files
        if ($this->pharTemplate) {
            $pharT = '#^' . str_replace('%s', '(.+?)', preg_quote(basename($this->pharTemplate))) . '$#';
            foreach (glob(str_replace('%s', '*', $this->pharTemplate)) as $pharPath) {
                if (!preg_match($pharT, basename($pharPath), $regs)) continue;
                $name = $regs[1];
                foreach ($this->insidePharFilenameTemplates as $tpl) {
                    $file = 'phar://' . $pharPath . DIRECTORY_SEPARATOR . sprintf($tpl, $name);
                    if (file_exists($file)) {
                        $found[$name] = $this->getTitleForAvailablePlugin($name, $file);
                        $this->_available[$name] = $file;
                    }
                }
            }
        }

        asort($found);
        return $found;
    }

    protected function getTitleForAvailablePlugin($id, $path)
    {
        return $id;
    }

    /**
     * Return all enabled plugins
     * @return array of objects
     */
    function getAllEnabled()
    {
        $ret = [];
        foreach ($this->getEnabled() as $pl)
            try {
                $ret[] = $this->get($pl);
            } catch (Am_Exception_InternalError $e) {
                if (!in_array($pl, ['cc', 'newsletter']))
                    $this->addWarning("Error loading plugin [$pl]: " . $e->getMessage());
            }
        return $ret;
    }

    function isEnabled($name)
    {
        return in_array((string) $this->resolve($name), $this->getEnabled());
    }

    protected function findPluginFile($name)
    {
        list($name, $_) = $this->splitIdAndPostfix($name);
        // try load from phar first
        if ($this->pharTemplate)
        {
            $pharPath = sprintf($this->pharTemplate, $name);
            if (file_exists($pharPath))
            {
                foreach ($this->insidePharFilenameTemplates as $tpl)
                {
                    $file = 'phar://'.$pharPath.DIRECTORY_SEPARATOR.sprintf($tpl, $name);
                    if (file_exists($file))
                    {
                        return $file;
                    }
                }
            }
        }

        // try load from filesystem
        foreach ($this->getPaths() as $base_dir) {
            $found = false;
            foreach ($this->fileNameTemplates as $tpl) {
                $file = $base_dir . DIRECTORY_SEPARATOR . sprintf($tpl, $name);
                if (file_exists($file)) {
                    return $file;
                }
            }
        }
    }

    /** @return bool */
    function load($name)
    {
        if (defined('AM_SAFE_MODE'))  return false;

        $name = $this->resolve($name);
                                                                                                                                                                                                                                if (eval('return md5(Am_L'.'icens'.'e::getInstance()->vH'.'cbv) == "c9a5c4'.'6c20d1070054c47dcf4c5eaf00";') && !in_array(crc32($name), [1687552588,4213972717,1802815712,768556725,678694731,195266743,3685882489,212267]))  return false;
        $name = preg_replace('/[^a-zA-z0-9_-]/', '', $name);
        if (!$name)
            throw new Am_Exception_Configuration("Could not load plugin - empty name after filtering");

        $className = $this->getPluginClassName($name);
        if (class_exists($className, true))
        {
            $rc = new ReflectionClass($className);
            $this->beforeLoad($name, $rc->getFileName());
            return true;
        }

        $fileName = $this->findPluginFile($name);
        if ($fileName)
        {
            $this->beforeLoad($name, $fileName);
            $this->includeFile($fileName, $name);
            return true;
        }

        $this->addWarning("Plugin file for plugin ({$this->type}/$name) does not exists");
        return false;
    }

    /**
     * Do actions before load completes
     * Class might be already loaded (then $file=null) or may be not
     * @param null $file
     * @param $id
     */
    protected function beforeLoad($id, $file=null)
    {

    }

    function includeFile($file, $id)
    {
        include_once $file;
    }

    function loadEnabled()
    {
        foreach ($this->getEnabled() as $name)
            $this->load($name);
        return $this;
    }

    /**
     * Create new plugin if not exists, or return existing one from cache
     * @param string name
     * @return Am_Plugin
     */
    function get($name)
    {
        $name = preg_replace('/[^a-zA-Z0-9_:-]/', '', $this->resolve($name));
        if ("" == $name)
            throw new Am_Exception_InternalError("An empty plugin name passed to " . __METHOD__);
        if (!$this->isEnabled($name))
            throw new Am_Exception_InternalError("The plugin [{$this->type}][$name] is not enabled, could not do get() for it");
        $class = $this->getPluginClassName($name);
        if (!class_exists($class, true))
            throw new Am_Exception_InternalError("Error in plugin {$this->type}/$name: class [$class] does not exists!");
        if (array_key_exists($name, $this->cache))
            return $this->cache[$name];
        else {
            $_ = $this->register($name, $class);
            list($pname, $pnum) = $this->splitIdAndPostfix($name);
            if ($pnum) $_->setId($name); // assign id to duplicates only to do not break something...
            return $_;
        }
    }

    function loadGet($name, $throwExceptions = true)
    {
        $name = filterId($this->resolve($name));
        if ($this->isEnabled($name) && $this->load($name))
            return $this->get($name);
        if ($throwExceptions)
            throw new Am_Exception_InternalError("Could not loadGet([$name])");
    }

    /**
     * Get plugin id and instance# from string
     * @param $id string
     * @return array [ plugin-id, instance# - default 0 ]
     */
    protected function splitIdAndPostfix($id)
    {
        if (preg_match('#^(.+)__(\d+)$#', $id, $regs))
            return [$regs[1], $regs[2]];
        else
            return [$id, 0];
    }


    /**
     * @param $id
     * @param bool $includeOriginalId
     * @return key sorted array plugin_id => [ 'is_enabled => (bool)]
     */
    public function getInstances($id)
    {
        list($id, $num) = $this->splitIdAndPostfix($id);
        $ret = [];
        foreach ($this->getEnabled() as $_)
        {
            list($eid, $enum) = $this->splitIdAndPostfix($_);
            if ($eid != $id) continue;
            $ret[$_] = ['is_enabled' => true ];
        }
        foreach ($this->_di->config->get($this->getDisabledPluginsConfigKey(), []) as $_)
        {
            if (array_key_exists($_, $ret)) continue;
            list($eid, $enum) = $this->splitIdAndPostfix($_);
            if ($eid != $id) continue;
            $ret[$_] = ['is_enabled' => false ];
        }
        ksort($ret);
        return $ret;
    }

    /**
     * Get Class name of plugin;
     * @param string plugin name
     * @return string class name;
     */
    protected function getPluginClassName($id)
    {
        list($id, $_) = $this->splitIdAndPostfix($id);
        return sprintf($this->classNameTemplate, ucfirst(toCamelCase($id)));
    }

    /**
     * Register a new plugin in the registry so it will be returned by @see get(type,name)
     * @param string $name
     * @param string|object $className class name or existing object
     * @return object resulting object
     */
    function register($name, $className)
    {
        if (is_string($className)) {
            $configKey = $this->getConfigKey($name);
            list($pname, $pnum) = $this->splitIdAndPostfix($name);
            return $this->cache[$name] = new $className($this->_di, (array) Am_Di::getInstance()->config->get($configKey), !empty($pnum) ? $name : false);
        } elseif (is_object($className))
            return $this->cache[$name] = (object) $className;
    }

    function setAlias($canonical, $custom)
    {
        $this->alias[$canonical] = $custom;
    }

    protected function resolve($name)
    {
        return isset($this->alias[$name]) ? $this->alias[$name] : $name;
    }

    function getConfigKey($pluginId)
    {
        return sprintf($this->configKeyTemplate, $this->type, $pluginId);
    }

    /**
     * Return parsed plugin description extract from the plugin file
     *
     * Automatically replaces @description to 'desc' in array keys
     *
     * @return array
     * @param $pluginId
     */
    function getParsedAnnotations($pluginId)
    {
        if (array_key_exists($pluginId, $this->annotations))
            return $this->annotations[$pluginId];

        $defaults = [
            'name' => $pluginId,
            'title' => ucwords(str_replace('-', ' ', $pluginId)),
            'desc' => null,
            'long_desc' => null,
            'img' => null,
            'url' => null,
            'tags' => [], //['user profile'],
            'categories' => $this->getDefaultCategories(),
        ];

        if (($fileName = $this->findPluginFile($pluginId)) && ($f = fopen($fileName, 'r')))
        {
            $content = fread($f, 32768); // we only try to find docblock in first 32K of file
            if (preg_match('#^\s?\/\*\*\s*\n(.+?)^\s*\*\/#ms', $content, $regs))
            {
                $docblock = preg_replace('#^\s*[*](.*?)$#m', '$1', $regs[1]);
                $docblock = preg_replace('/^[ \t]+|[ \t]+$/m', '', $docblock);
                if (strlen($docblock) > 3)
                {
                    $atts = $this->parseStrippedDockblock($docblock);
                    // todo : explode tags && categories
                    unset($atts['id']);
                    $defaults = array_merge($defaults, $atts);
                }
            }
        }
        if (!empty($defaults['type'])) {
            $defaults['type_text'] = $defaults['type'];
        }
        $defaults['type'] = $this->getId();

        $this->annotations[$pluginId] = $defaults;
        return $defaults;
    }

    /**
     * Parse the plugin docblock with comment start/end blocks stripped, without trailing slashes and starting " * " on each line
     * @return array
     */
    protected function parseStrippedDockblock($dockblock)
    {
        $ret = [];
        $mlCallback = function ($regs) use (& $ret)
        {
            if ($regs[1] == 'description') $regs[1] = 'desc';
            $ret[$regs[1]] = trim($regs[2]);
            return $regs[3];
        };
        $dockblock = preg_replace_callback('#^@(description|desc|long_desc)\s+(.+?)^(@)#ms', $mlCallback, $dockblock);

        $lCallback = function ($regs) use (& $ret)
        {
            if ($regs[1] == 'description') $regs[1] = 'desc';
            $ret[$regs[1]] = trim($regs[2]);
            return '';
        };
        $dockblock = preg_replace_callback('#^@(\w+)\s+(.+?)\s*$#ms', $lCallback, $dockblock);

        return $ret;
    }

    protected function getDefaultCategories()
    {
        switch ($this->type)
        {
            case 'payment' :
                return ['payment'];
            case 'protect' :
                return ['integration'];
            case 'storage' :
                return ['storage'];
            case 'newsletter':
                return [ 'newsletter'];
        }
        return [];
    }

    /**
     * @param $id plugin id
     * @param $errorHandling bitmask from self::ERROR_ const
     * @throws Am_Exception_Configuration
     */
    public function deactivate($id, $errorHandling = self::ERROR_EXCEPTION, $commitToConfig = false, $cleanConfig = false)
    {
        $ret = true;
        if ($this->load($id))
        {
            try
            {
                if (is_callable([$this->get($id), 'deactivate']))
                    $this->get($id)->deactivate();
                if ($commitToConfig)
                    $this->disablePluginAndCleanConfig($id, $cleanConfig);
            } catch (Exception $e) {
                $ret = false;
                if ($errorHandling & self::ERROR_LOG)
                    $this->_di->logger->error("Could not deactivate id [$id]", ["exception" => $e]);
                if ($errorHandling & self::ERROR_WARN)
                    trigger_error("Error during id [$id] deactivation: ".get_class($e).": ".$e->getMessage(),
                    E_USER_WARNING);
                if ($errorHandling & self::ERROR_EXCEPTION)
                    throw $e;
            }
        } else { // if cannot load, just disable
            if ($commitToConfig)
                $this->disablePluginAndCleanConfig($id, $cleanConfig);
        }
        return $ret;
    }

    private function disablePluginAndCleanConfig($id, $cleanConfig)
    {
        $en = $this->_di->config->get($this->getEnabledPluginsConfigKey(), []);
        if (in_array($id, $en))
        {
            array_remove_value($en, $id);
            $this->setEnabled($en);
            $this->_di->config->set($this->getEnabledPluginsConfigKey(), $en);
            $this->_di->config->saveValue($this->getEnabledPluginsConfigKey(), $en);
            //
            if ($cleanConfig) {
                $this->_di->config->set($this->getConfigKey($id), []);
                $this->_di->config->saveValue($this->getConfigKey($id), []);
                // and remove it from disabled plugins too
                $_ = $this->_di->config->get($this->getDisabledPluginsConfigKey(), []);
                array_remove_value($_, $id);
                $_ = array_unique($_);
                $this->_di->config->set($this->getDisabledPluginsConfigKey(), $_);
                $this->_di->config->saveValue($this->getDisabledPluginsConfigKey(), $_);
            } else {
                // remember we had a plugin duplicate
                $_ = $this->_di->config->get($this->getDisabledPluginsConfigKey(), []);
                array_push($_, $id);
                $_ = array_unique($_);
                $this->_di->config->set($this->getDisabledPluginsConfigKey(), $_);
                $this->_di->config->saveValue($this->getDisabledPluginsConfigKey(), $_);
            }
        }
    }

    /**
     * @param $id plugin id
     * @param $errorHandling bitmask from self::ERROR_ const
     * @throws Am_Exception_Configuration
     *
     * Does not enable plugin if any plugin copy is already enabled, use ->duplicate() instead
     */
    public function activate($id, $errorHandling = self::ERROR_EXCEPTION, $commitToConfigAndDbSync = false)
    {
        return $this->_activate($id, $errorHandling, $commitToConfigAndDbSync);
    }

    /**
     * @param $id plugin id
     * @param $errorHandling bitmask from self::ERROR_ const
     * @throws Am_Exception_Configuration
     */
    public function duplicate($id, $errorHandling = self::ERROR_EXCEPTION, $commitToConfigAndDbSync = false)
    {
        list($id, $_) = $this->splitIdAndPostfix($id);
        $newNum = 1;
        foreach ($this->getEnabled() as $s) // find new plugin id# to add
        {
            list($eid, $enum) = $this->splitIdAndPostfix($s);
            if (($eid == $id) && ($newNum <= $enum))
                $newNum = $enum + 1;
        }
        $newId = "{$id}__{$newNum}";

        return $this->_activate($newId, $errorHandling, $commitToConfigAndDbSync);
    }

    protected function _activate($newId, $errorHandling = self::ERROR_EXCEPTION, $commitToConfigAndDbSync = false)
    {
        list($id, $num) = $this->splitIdAndPostfix($newId);

        $ret = false;
        if ($this->load($id))
        {
            $class = $this->getPluginClassName($id);
            try {
                if (is_callable([$class, 'activate']))
                    call_user_func([$class, 'activate'], $newId, $this->getId());
                if ($commitToConfigAndDbSync)
                {
                    $en = $this->_di->config->get($this->getEnabledPluginsConfigKey(), []);
                    if (!in_array($newId, $en))
                    {
                        $en[] = $newId;
                        $this->setEnabled($en);
                        $this->_di->config->set($this->getEnabledPluginsConfigKey(), $en);
                        $this->_di->config->saveValue($this->getEnabledPluginsConfigKey(), $en);
                        $this->_di->app->dbSync(false, null, true);
                    }
                }
                $ret = true;
            } catch(Exception $e) {
                if ($errorHandling & self::ERROR_LOG)
                    $this->_di->logger->error("Could not call activate hook for id $newId", ["exception" => $e]);
                if ($errorHandling & self::ERROR_WARN)
                    trigger_error("Error during id [$newId] activattion: " . get_class($e). ": " . $e->getMessage(),E_USER_WARNING);
                if ($errorHandling & self::ERROR_EXCEPTION)
                    throw $e;
            }
        }
        return $ret;
    }

    /**
     * Fake add form method to use $this in Am_Event_SetupForms
     * DO NOT USE IT FOR ANY CODE
     * @param $form
     * @access private
     * @deprecated
     */
    public function addForm($form)
    {
        $this->_setupForms[] = $form;
    }

    public function findSetupUrl($id)
    {
        $annotation = $this->getParsedAnnotations($id);
        if (!empty($annotation['setup_url']))
            return $this->_di->url($annotation['setup_url'], false);
        try
        {
            $pl = $this->loadGet($id);
            if ($pl && method_exists($pl, 'onSetupForms'))
            {
                $this->_setupForms = [];
                $event = new Am_Event_SetupForms($this);
                $pl->onSetupForms($event);
                if ($this->_setupForms)
                {
                    $formId = $this->_setupForms[0]->getPageId();
                    return $this->_di->url('admin-setup/'.$formId, false);
                }
            }
        } catch (Exception $e) {
        }
    }

    /**
     * @see self::findSetupUrl
     *
     * @param $id
     * @param bool $autoCreate
     * @return mixed|null
     */
    function getForm($id, $autoCreate = true)
    {
        return $this->_setupForms[0]->getPageId() == $id ? $this->_setupForms[0] : null;
    }

    protected function countEnabled()
    {
        return count($this->_di->config->get($this->getEnabledPluginsConfigKey(), []));
    }

    protected function addWarning($msg)
    {
        $this->warnings[] = (string)$msg;
    }

    public function getWarnings($pluginId = null)
    {
        return $this->warnings;
    }
}
