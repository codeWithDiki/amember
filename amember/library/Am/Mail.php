<?php
/**
 * E-Mail sending class for aMember
 * @package Am_Mail
 */
class Am_Mail extends Zend_Mail {
    const REGULAR = 10;
    const ADMIN_REQUESTED = 20;
    const USER_REQUESTED = 30;
    const BACKGROUND = 0;
    protected $periodic = self::REGULAR;
    protected $emailTemplateName = null;

    const PRIORITY_HIGH = 9;
    const PRIORITY_MEDIUM = 5;
    const PRIORITY_LOW = 0;
    protected $priority = null;

    protected $addUnsubscribeLink = false;

    public function __construct($charset = 'utf-8')
    {
        parent::__construct($charset);
        $this->setHeaderEncoding(Zend_Mime::ENCODING_BASE64);
    }

    public function setPeriodic($periodic){ $this->periodic = $periodic ; return $this; }
    public function getPeriodic($periodic){ return $this->periodic; }
    /** Should the e-mail be sent immediately, or it can be put to queue ? */
    public function isInstant(){ return $this->periodic == self::USER_REQUESTED; }
    public function isBackground(){ return $this->periodic == self::BACKGROUND; }
    /** @return int calculate from periodic+priority, bigger will stay higher in the queue and will be set faster */
    public function getPriority(){ return (int)$this->priority + (int)$this->periodic;}
    /** Set mail order in the queue, by default it will be set based on "periodic" */
    public function setPriority($priority){ $this->priority = $priority; return $this; }
    public function setEmailTemplateName($emailTemplateName) { $this->emailTemplateName = $emailTemplateName; return $this; }
    public function getEmailTemplateName() { return $this->emailTemplateName; }
    /**
     * Add unsubscibe link of given type (see class constants)
     * This must be called before adding e-mail body
     * @param int $type
     */
    public function addUnsubscribeLink(){
        if ($this->_bodyText || $this->_bodyHtml)
            throw new Am_Exception_InternalError("Body is already added, could not do " . __METHOD__);
        $this->addUnsubscribeLink = 1;
    }
    public function getAddUnsubscribeLink()
    {
        if (Am_Di::getInstance()->config->get('disable_unsubscribe_link'))
            return false; //disabled at all
        return $this->addUnsubscribeLink;
    }
    public function clearUnsubscribeLink(){
        if ($this->_bodyText || $this->_bodyHtml)
            throw new Am_Exception_InternalError("Body is already added, could not do " . __METHOD__);
        $this->addUnsubscribeLink = false;
    }

    public function setBodyHtml($html, $charset = null, $encoding = Zend_Mime::ENCODING_QUOTEDPRINTABLE) {
        $html = Am_Di::getInstance()->unsubscribeLink->addToHtml($this, $html);
        parent::setBodyHtml($html, $charset, $encoding);
    }
    public function setBodyText($txt, $charset = null, $encoding = Zend_Mime::ENCODING_QUOTEDPRINTABLE) {
        $txt = Am_Di::getInstance()->unsubscribeLink->addToText($this, $txt);
        parent::setBodyText($txt, $charset, $encoding);
    }

    function __sleep()
    {
        return array_keys(get_object_vars($this));
    }

    public function createAttachment($body,
                                     $mimeType    = Zend_Mime::TYPE_OCTETSTREAM,
                                     $disposition = Zend_Mime::DISPOSITION_ATTACHMENT,
                                     $encoding    = Zend_Mime::ENCODING_BASE64,
                                     $filename    = null)
    {
        $mp = new Am_Mail_MimePart($body); // it was only the change
        $mp->encoding = $encoding;
        $mp->type = $mimeType;
        $mp->disposition = $disposition;
        $mp->filename = $filename;
        $this->addAttachment($mp);
        return $mp;
    }
    /**
     * Set message To: admin
     * @return Am_Mail
     */
    public function toAdmin(){
        $this->clearRecipients();
        $this->addTo(Am_Di::getInstance()->config->get('admin_email'), Am_Di::getInstance()->config->get('site_title') . ' Admin');
        if (Am_Di::getInstance()->config->get('copy_admin_email'))
            foreach (preg_split("/[,;]/",Am_Di::getInstance()->config->get('copy_admin_email')) as $email)
                if ($email) $this->addBcc($email);
        return $this;
    }

    /**
     * @return Am_Mail_Transport_Iface
     */
    public static function getDefaultTransport()
    {
        return Am_Di::getInstance()->mailTransport;
    }

    /**
     * Do not use in new code!!!! Kept for compat with old plugins
     * @deprecated
     * @param Zend_Mail_Transport_Abstract $transport
     */
    public static function setDefaultTransport(Zend_Mail_Transport_Abstract $transport)
    {
        Am_Di::getInstance()->setService('mailTransport', $transport);
    }


    public function send($transport = null)
    {
        if ($transport === null) {
            $transport = Am_Di::getInstance()->mailTransport;
        }

        if ($this->_date === null) {
            $this->setDate();
        }

        if(null === $this->_from && null !== self::getDefaultFrom()) {
            $this->setFromToDefaultFrom();
        }

        if(null === $this->_replyTo && null !== self::getDefaultReplyTo()) {
            $this->setReplyToFromDefault();
        }

        $transport->send($this);

        return $this;
    }
}
