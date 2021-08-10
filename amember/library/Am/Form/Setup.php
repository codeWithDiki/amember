<?php

/**
 * Abstract admin setup/configuration form
 * @package Am_Form
 * @subpackage Setup
 */
class Am_Form_Setup extends Am_Form_Admin
{
    protected $pageId;
    protected $title;
    protected $comment;
    protected $defaults = [];
    protected $fieldsPrefix = null;
    protected $saveConfigCb = [];
    protected $order = 4000; // globals=100, plugins=200, email=300, advanced=400
    protected $_finishCallback;

    public function __construct($id)
    {
        parent::__construct('setup_form_'.$id, null);
        $this->setPageId($id);
        $h = new Am_View_Helper_Help;
        $this->addEpilog($h($this));
    }

    public function getOrder()
    {
        return $this->order;
    }

    public function setOrder($order)
    {
        $this->order = (int)$order;
    }

    public function getUrl()
    {
        return $this->getDi()->url('admin-setup/' . $this->getPageId(), false);
    }

    function renderTitle()
    {
        return Am_Html::escape($this->getTitle());
    }

    function renderComment()
    {
        return Am_Html::escape($this->getComment());
    }

    public function setPageId($id)
    {
        $this->pageId = $id;
        return $this;
    }

    public function getPageId()
    {
        return $this->pageId;
    }

    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    public function getTitle()
    {
        return $this->title ? $this->title : $this->pageId;
    }

    public function setComment($comment)
    {
        $this->comment = $comment;
        return $this;
    }

    public function getComment()
    {
        return $this->comment;
    }

    public function init()
    {
    }

    /**
     * When called with callback arg, it configures a function that must executed before form can be used for edit
     * When called without params, it runs function (once) to finish configuration. It is safe to call multiple times
     */
    public function finishInit(callable $callback = null) {
        if ($callback === null)
        {
            if ($this->_finishCallback)
            {
                call_user_func($this->_finishCallback, $this);
                $this->_finishCallback = null; // run once
            }
        } else {
            $this->_finishCallback = $callback;
        }
        return $this;
    }
    /**
     * Actual elements will be added here - to do lazy loading
     */
    public function initElements()
    {
    }

    public function prepare()
    {
        $this->finishInit();
        $this->initElements();
        $this->addHidden('_p')
            ->setValue($this->getPageId())
            ->toggleFrozen(true);
        $this->addSaveButton();
    }

    public function getDefaults()
    {
        $ret = $this->defaults;
        $ret['_p'] = $this->getPageId();
        foreach ($this->getRecursiveIterator() as $el)
        {
            if (isset($el->default))
                $ret[ $el->getName() ] = $el->default;
        }
        return $ret;
    }

    public function setDefault($k, $v)
    {
        $this->defaults[$k] = $v;
    }

    public function addSaveCallbacks($beforeSaveConfig, $afterSaveConfig)
    {
        $this->saveConfigCb[] = [
            'beforeSaveConfig' => $beforeSaveConfig,
            'afterSaveConfig' => $afterSaveConfig
        ];
    }

    public function saveConfig()
    {
        $c = new Am_Config;
        $c->read();
        $before = clone $c;
        foreach (array_merge($this->getDefaults(), $this->getValue()) as $k => $v)
        {
            if (preg_match('/(^|\.)(save|_save_|_p)$/', $k)) continue;
            if (preg_match('/(^|\.)_csrf$/', $k)) continue;
            if ($v === null) continue;
            $c->set($k, $v);
        }
        $this->beforeSaveConfig($before, $c);
        $c->save();
        $this->afterSaveConfig($before, $c);
        return true;
    }

    public function beforeSaveConfig(Am_Config $before, Am_Config $after)
    {
        foreach ($this->saveConfigCb as $cb) {
            if (is_callable($cb['beforeSaveConfig'])) {
                call_user_func_array($cb['beforeSaveConfig'], func_get_args());
            }
        }
    }

    public function afterSaveConfig(Am_Config $before, Am_Config $after)
    {
        foreach ($this->saveConfigCb as $cb) {
            if (is_callable($cb['afterSaveConfig'])) {
                call_user_func_array($cb['afterSaveConfig'], func_get_args());
            }
        }
    }

    public function replaceDotInNames()
    {
        foreach ($this->getRecursiveIterator() as $el)
        {
            $name = $el->getName();
            if (strpos($name, '.')===false) continue;
            $el->setName(self::name2underscore($name));
        }
    }

    /**
     * Prepend field names with prefix. Useful for plugins config forms
     * @param string $prefix
     */
    public function addFieldsPrefix($prefix, $container = null)
    {
        $this->fieldsPrefix = $prefix;
        if ($container === null) $container = $this;
        foreach ($container->getIterator() as $el)
        {
            if ($el->getName()=='_save_') continue;
            if ($el instanceof HTML_QuickForm2_Container && !$el->getName())
                $this->addFieldsPrefix($prefix, $el); // rename child elements of container
            else {
                $n = $el->getName();
                if (!empty($this->defaults[$n]))
                {
                    $this->defaults[$prefix . $n] = $this->defaults[$n];
                    unset($this->defaults[$n]);
                }
                $el->setName($prefix . $n);
            }
        }
    }

    /**
     * @return Am_Di
     */
    public function getDi()
    {
        return Am_Di::getInstance();
    }

    /** replace . to ___ in field name */
    static function name2dots($name)
    {
        return str_replace('___', '.', $name);
    }

    /** replace ___ to . in field name */
    static function name2underscore($name)
    {
        return str_replace('.', '___', $name);
    }
}