<?php

/**
 * Add unsubscribe link to message content and validate it
 * Class Am_Mail_UnsubscribeLink
 */
class Am_Mail_UnsubscribeLink
{
    const UNSUBSCRIBE_HTML = '
<br />
<font color="gray">To unsubscribe from our periodic e-mail messages, please click the following <a href="%link%">link</a></font>
<br />
';
    const UNSUBSCRIBE_TXT = '

-------------------------------------------------------------------
To unsubscribe from our periodic e-mail messages, please click the
following link:
  %link%
-------------------------------------------------------------------

';
    /** @deprecated  */
    const LINK_USER = 1;
    /**
     * @var Am_Di
     */
    protected $di;

    public function __construct(Am_Di $di)
    {
        $this->di = $di;
    }

    function addToHtml(Am_Mail $mail, $content)
    {
        $this->_addUnsubscribeLink($mail, $content, true);
        return $content;
    }

    function addToText(Am_Mail $mail, $content)
    {
        $this->_addUnsubscribeLink($mail, $content, false);
        return $content;
    }

    protected function addListUnsubscribe($mail, $link)
    {
        foreach ($mail->getHeaders() as $header => $value)
        {
            if ($header == 'List-Unsubscribe') return;
        }
        $mail->addHeader('List-Unsubscribe', sprintf('<%s>', $link));
    }
    /**
     * @param string $content - will be modified
     * @param bool $isHtml
     * @return null
     */
    protected function _addUnsubscribeLink(Am_Mail $mail, & $content, $isHtml){
        if (!$mail->getAddUnsubscribeLink()) return ;
        $e = $mail->getRecipients();
        if (!$e) {
            trigger_error("E-Mail address is empty in " . __METHOD__.", did you call addUnsubscribeLink before adding receipients?", E_USER_WARNING);
            return; // no email address
        }
        $e = array_shift($e);
        if (!$e) {
            trigger_error("E-Mail address is empty in " . __METHOD__.", did you call addUnsubscribeLink before adding receipients?", E_USER_WARNING);
            return; // no email address
        }
        $link = $this->get($e, $mail->getAddUnsubscribeLink());
        $this->addListUnsubscribe($mail, $link);
        if (strpos($content, $link)!==false) return; //already added
        if ($isHtml) {
            $out = $this->di->config->get('unsubscribe_html', self::UNSUBSCRIBE_HTML);
        } else {
            $out = $this->di->config->get('unsubscribe_txt', self::UNSUBSCRIBE_TXT);
        }
        $t = new Am_SimpleTemplate();
        $t->assign('link', $link);
        $out = "\r\n" .  $t->render($out);
        if (!$isHtml) {
            $content .= "\r\n" . $out;
        } else {
            $content = str_ireplace('</body>', $out . '</body>', $content, $replaced);
            if (!$replaced)
                $content .= $out;
        }
    }

    function get($email)
    {
        $di = $this->di;
        $sign = $di->security->hash($di->security->siteKey() . $email . 'MAIL-USER', 10);
        $link = $di->url('unsubscribe', ['e'=>$di->security->base64url_encode($email), 's' => $sign],false,true);
        return $link;
    }
    function validate(&$email, $sign)
    {
        $di = $this->di;
        if(strpos('@', $email)===false){
            $email = $di->security->base64url_decode($email);
        }
        return $sign === $di->security->hash($di->security->siteKey() . $email . 'MAIL-USER', 10);
    }
}