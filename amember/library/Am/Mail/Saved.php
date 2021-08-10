<?php
/**
 * @author Alexey Presnyakov <alex@cgi-central.net
 * @license Commercial
 */

/**
 * Just to satisfy Zend_Mail_Transport_Abstract needs
 * @internal
 * @package Am_Mail
 */
class Am_Mail_Saved
{
    public $from;
    public $subject;
    public $recipients;

    function getFrom()
    {
        return $this->from;
    }

    function getSubject()
    {
        if (strpos($this->subject, '=?') === 0) {
            return $this->subject;
        } else {
            return mb_encode_mimeheader($this->subject, 'UTF-8');
        }
    }

    function getRecipients()
    {
        return $this->recipients;
    }

    function getReturnPath()
    {
        return $this->from;
    }
}