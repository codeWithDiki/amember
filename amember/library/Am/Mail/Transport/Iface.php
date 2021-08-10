<?php

interface Am_Mail_Transport_Iface
{
    /**
     * Send a mail using this transport
     *
     * @param  Zend_Mail $mail
     * @access public
     * @return void
     * @throws Zend_Mail_Transport_Exception
     */
    function send(Zend_Mail $mail);

    /**
     * @deprecated
     * Kept ONLY FOR compatibility with messages stored in mail queue
     * Shall not be used for new code
     * @param $from
     * @param $recipients
     * @param $body
     * @param array $headers
     * @param $subject
     * @param $EOL from Am_Mail_Queue where message was serialized
     */
    function sendFromSaved($from, $recipients, $body, array $headers, $subject, $EOL);

}