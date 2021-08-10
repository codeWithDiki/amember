<?php

/**
 * @package Am_Mail
 * @todo put into a separate file for lazy-loading
 */
class Am_Mail_Transport_Sendmail extends Zend_Mail_Transport_Sendmail implements Am_Mail_Transport_Iface
{
    function sendFromSaved($from, $recipients, $body, array $headers, $subject, $EOL)
    {
        if ($EOL !== $this->EOL)
        {
            $my_eol = $EOL;
            $t_eol = $this->EOL;
            $body = str_replace($my_eol, $t_eol, $body);
            $headers = array_map(
                function ($_) use ($my_eol, $t_eol) {
                    return str_replace($my_eol, $t_eol, $_);
                },
                $headers
            );
        }

        $this->_mail = new Am_Mail_Saved;
        $this->_mail->from = $from;
        $this->_mail->subject = $subject;
        $this->recipients = $recipients;
        $this->body = $body;
        $this->_prepareHeaders($headers);
        $this->_sendMail();
    }
}