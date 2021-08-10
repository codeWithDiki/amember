<?php

class Am_Plugins_Payment extends Am_Plugins
{
    public function getAvailable()
    {
        $_ = parent::getAvailable();
        unset($_['free']); // hide free plugin from list - it is always enabled
        return $_;
    }

    public function getEnabled()
    {
        return array_unique(parent::getEnabled() + ['free']);
    }

    /**
     * @return bool
     */
    public function haveCcPluginsInTheList(array $plugins)
    {
        $this->getAvailable();
        foreach ($plugins as $_)
        {
            if (strpos($this->_available[$_], '/cc/')!== false) return true;
            if (strpos($this->_available[$_], '\\cc\\')!== false) return true;
        }
        return false;
    }

    public function activate($id, $errorHandling = self::ERROR_EXCEPTION, $commitToConfigAndDbSync = false)
    {
        if ($this->haveCcPluginsInTheList(array_merge($this->getEnabled(), [$id])))
        {
            $_ = $this->_di->modules->activate('cc', $errorHandling, $commitToConfigAndDbSync);
            if ($_ !== true)
                return $_;
        }
        return parent::activate($id, $errorHandling, $commitToConfigAndDbSync);
    }

    public function deactivate(
        $id,
        $errorHandling = self::ERROR_EXCEPTION,
        $commitToConfig = false,
        $cleanConfig = false
    ) {
        $ret = parent::deactivate($id, $errorHandling, $commitToConfig, $cleanConfig);
        if ($ret === true)
        {
            $list = $this->getEnabled();
            array_remove_value($list, $id);
            if (!$this->haveCcPluginsInTheList($list))
                $this->_di->modules->deactivate('cc', $errorHandling, $commitToConfig);
        }
        return $ret;
    }

}