<?php
/**
 * Do not send any e-mails
 * @package Am_Mail
 */
class Am_Mail_Transport_Null extends Am_Mail_Transport_Base
{
    protected function _sendMail()
    {
        // do nothing
    }

    function sendFromSaved($from, $recipients, $body, array $headers, $subject, $eol)
    {
        // do nothing
    }
}