<?php

/**
 * Adds request specific information to logger record based on current request
 *
 * Class Am_Logger_MessageExtra
 */
class Am_Logger_MessageExtra
{
    function __invoke(array $record)
    {
        $record['extra']['user_id'] = null;
        
        if (Am_Di::getInstance()->session->isStarted()) {
            try { // necessary to avoid errors if init is not finished
                $record['extra']['user_id'] = Am_Di::getInstance()->auth->getUserId();
            } catch (Exception $e) {
        
            }
        }
        if (!empty($_SERVER['REMOTE_ADDR']))
            $record['extra']['remote_addr'] = $_SERVER['REMOTE_ADDR'];
        if (!empty($_SERVER['REQUEST_URI']))
            $record['extra']['url'] = $_SERVER['REQUEST_URI'];
        if (!empty($_SERVER['HTTP_REFERER']))
            $record['extra']['referrer'] = $_SERVER['HTTP_REFERER'];

        return $record;
    }
}