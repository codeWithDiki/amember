<?php
/**
 * @package Am_Mail
 * @internal
 */
class Am_Mail_MimePart extends Zend_Mime_Part
{
    protected $_streamPath = null; // get this info for serialization

    public function __construct($content)
    {
        //can not read same stream second time (send/serialize) but we need it in log too
        $this->_content = is_resource($content) ? stream_get_contents($content) : $content;
    }

    /**
     * @deprecated
     */
    function serialize() {}
    /***
     * @deprecated
     */
    function unserialize() {}

    public function __sleep()
    {
        return array_keys(get_object_vars($this));
    }
}