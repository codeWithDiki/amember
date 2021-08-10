<?php

/**
 * @package Am_Utils
 */
class Am_Cache_Backend_Array implements Zend_Cache_Backend_ExtendedInterface
{
    protected $_cache = [];

    public function setDirectives($directives) {
        $this->_directives = $directives;
    }

    public function load($id, $doNotTestCacheValidity = false) {
        if (isset($this->_cache[$id]))
            return $this->_cache[$id];
        return false;
    }

    public function test($id) {
        return isset($this->_cache[$id]);
    }

    public function save($data, $label, $tags = [], $specificLifetime = false) {
        $this->_cache[$label] = $data;
        return true;
    }

    public function remove($id) {
        unset($this->_cache[$id]);
        return true;
    }

    public function clean($mode = Zend_Cache::CLEANING_MODE_ALL, $tags = []) {
        switch ($mode) {
            case Zend_Cache::CLEANING_MODE_ALL:
                // delete all data and all tags
                $this->_cache = [];
                break;
        }
    }

    public function getCapabilities()
    {
        return [];
    }

    public function getFillingPercentage()
    {
        return 0;
    }

    public function getIds()
    {
        return array_keys($this->_cache);
    }

    public function getIdsMatchingAnyTags($tags = [])
    {
        return [];
    }

    public function getIdsMatchingTags($tags = [])
    {
        return [];
    }

    public function getIdsNotMatchingTags($tags = [])
    {
        return [];
    }

    public function getMetadatas($id)
    {
        return [];
    }

    public function getTags()
    {
        return [];
    }

    public function touch($id, $extraLifetime)
    {
    }
    public function __sleep()
    {
        return [];
    }
}