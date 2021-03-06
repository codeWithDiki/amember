<?php

/**
 * @package Am_Utils
 */
class Am_Cache_Backend_Null extends Zend_Cache_Backend implements Zend_Cache_Backend_Interface
{
    protected $_cache = [];

    public function clean($mode = Zend_Cache::CLEANING_MODE_ALL, $tags = []){
        return true;
    }
    public function load($id, $doNotTestCacheValidity = false)
    {
        return false;
    }
    public function remove($id)
    {
        return true;
    }
    public function save($data, $id, $tags = [], $specificLifetime = false)
    {
        return true;
    }
    public function setDirectives($directives)
    {

    }
    public function test($id)
    {
        return false;
    }
}