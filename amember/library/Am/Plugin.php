<?php

/**
 * Base class for plugin
 * @package Am_Plugin
 */
class Am_Plugin extends Am_Plugin_Base
{
    /**
     * Function will be called when user access amember/payment/pluginid/xxx url directly
     * This can be used for IPN actions, or for displaying confirmation page
     * @see getPluginUrl()
     * @param Am_Mvc_Request $request
     * @param Am_Mvc_Response $response
     * @param array $invokeArgs
     * @throws Am_Exception_NotImplemented
     */
    function directAction(Am_Mvc_Request $request, Am_Mvc_Response $response, array $invokeArgs)
    {
        throw new Am_Exception_NotImplemented("'direct' action is not implemented in " . get_class($this));
    }

    static function activate($id, $pluginType)
    {
        if ($xml = static::getDbXml()) {
            self::syncDb($xml, Am_Di::getInstance()->db);
        }
        if ($xml = static::getEtXml()) {
            self::syncEt($xml, Am_Di::getInstance()->emailTemplateTable);
        }
    }

    function onDbSync(Am_Event $e)
    {
        if ($xml = static::getDbXml()) {
            $e->getDbsync()->parseXml($xml);
        }
    }

    function onEtSync(Am_Event $e)
    {
        if ($xml = static::getEtXml()) {
            $e->addReturn($xml, 'Plugin::' . $this->getId());
        }
    }

    static final function syncDb($xml, $db)
    {
        $origDb = new Am_DbSync();
        $origDb->parseTables($db);

        $desiredDb = new Am_DbSync();
        $desiredDb->parseXml($xml);

        $diff = $desiredDb->diff($origDb);
        if ($sql = $diff->getSql($db->getPrefix())) {
            $diff->apply($db);
        }
    }

    static final function syncEt($xml, $t)
    {
        $t->importXml($xml);
    }

    static function getDbXml()
    {
        return null;
    }

    static function getEtXml()
    {
        return null;
    }
}
