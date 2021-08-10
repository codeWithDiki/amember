<?php

class Am_Plugins_Newsletter extends Am_Plugins
{
    public function activate($id, $errorHandling = self::ERROR_EXCEPTION, $commitToConfigAndDbSync = false)
    {
        $ret = parent::activate($id, $errorHandling, $commitToConfigAndDbSync);
        if ($ret)
            $ret = $ret && $this->_di->modules->activate('newsletter', $errorHandling, $commitToConfigAndDbSync);
        return $ret;
    }

    public function deactivate(
        $id,
        $errorHandling = self::ERROR_EXCEPTION,
        $commitToConfig = false,
        $cleanConfig = false
    ) {
        $ret = parent::deactivate($id, $errorHandling, $commitToConfig, $cleanConfig);
        if ($ret && !$this->countEnabled())
            $ret = $ret && $this->_di->modules->deactivate('newsletter', $errorHandling, $commitToConfig, $cleanConfig);
        return $ret;
    }
}