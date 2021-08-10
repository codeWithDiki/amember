<?php
/**
 * This is a proxy e-mail transport, it does the following:
 *   - initializes real transport when necessary using Am_Di::getInstance()->config->get() values
 *   - saves e-mail messages to log when enabled
 *   - puts not regular messages to queue instead of sending when enabled
 * @package Am_Mail
 */
class Am_Mail_Queue extends Zend_Mail_Transport_Abstract
{
    const QUEUE_DISABLED = -1;
    const QUEUE_OK = 1;
    const QUEUE_ONLY_INSTANT = 2;
    const QUEUE_FULL = 3;

    /** @var Zend_Mail_Transport_Abstract */
    protected $transport;

    protected $queueEnabled = false;
    /** @var int seconds */
    protected $queuePeriod;
    /** @var int limit of emails in $queuePeriod minutes */
    protected $queueLimit;
    /** @var int limit of periodical e-mails per hour
     * (automatically set to 80% @see $queueLimit)
     * to keep window for urgent emails like password
     * requests  */
    protected $queuePeriodicLimit;
    /** @var int days to store sent messages
     * even if that is null, aMember can anyway
     * store messages for queuing, it will then
     * be deleted automatically after 14 days if not delivered */
    protected $logDays;
    /**
     * How many messages can we send in this period? This is set
     * by @see getQueueStatus
     * @var int
     */
    protected $leftMessagesToSend = null;

    /**
     * @deprecated
     * DO NOT USE IN NEW CODE, KEPT FOR COMPAT
     * @return self
     */
    static public function getInstance()
    {
        return Am_Di::getInstance()->mailQueue;
    }

    public function __construct(Am_Mail_Transport_Iface $transport)
    {
        $this->setTransport($transport);
    }

    function setTransport(Zend_Mail_Transport_Abstract $transport)
    {
        $this->transport = $transport;
    }

    /**
     *
     * @return Zend_Mail_Transport_Abstract
     */
    function getTransport()
    {
        return $this->transport;
    }

    /**
     * @param $period minutes
     * @param $limit allow messages per $period
     */
    function enableQueue($period, $limit)
    {
        $this->queueEnabled = true;
        $this->queuePeriod = (int)$period;
        if ($this->queuePeriod <= 600)
        {
            throw new Am_Exception_InternalError("email_queue_period set to invalid value [{$this->queuePeriod}]");
        }
        $this->queueLimit = (int)$limit;
        $this->queuePeriodicLimit = (int)$this->queueLimit * 80 / 100;
        return $this;
    }

    public function logEnabled()
    {
        return $this->logDays > 0;
    }

    public function setLogDays($days)
    {
        $this->logDays = max(0, intval($days));
    }

    /**
     * Send message or put it queue if necessary
     */
    public function send(Zend_Mail $mail)
    {
        if (!$mail instanceof Am_Mail) {
            trigger_error(__METHOD__.' should get Am_Mail, not Zend_Mail', E_USER_NOTICE);
            $isInstant = true;
        } else {
            $isInstant = $mail->isInstant();
        }
        $status = $this->getQueueStatus();
        $sent = null;
        $exception = null;
        if ($status != self::QUEUE_DISABLED && $mail->isBackground()) {
            $this->addToQueue($mail, $sent);
            return;
        }

        if (in_array($status, [self::QUEUE_DISABLED, self::QUEUE_OK])
            || (($status == self::QUEUE_ONLY_INSTANT) && $isInstant)) {
            try {
                $this->transport->send($mail);
                $sent = Am_Di::getInstance()->time;
            } catch (Zend_Mail_Exception $e) {
                $exception = $e;
            }
        }
        if ($status != self::QUEUE_DISABLED || $this->logEnabled()) {
            $this->addToQueue($mail, $sent);
        }
        if ($exception) {
            throw $exception;
        } // re-raise
    }

    /**
     * Put message to queue instead of sending it
     */
    protected function _sendMail()
    {
        if (defined('AM_FB_ENABLED')) {
            fb(['header' => $this->header, 'body' => $this->body], 'E-Mail');
        }
    }

    /**
     * Just save headers as it passed to
     * @param mixed $headers
     */
    protected function _prepareHeaders($headers)
    {
        $this->headers = $headers;
    }

    /**
     * Save e-mail to mail_queue table
     * @param Am_Mail $mail
     * @param int $sent timestamp
     * @return int inserted record id
     */
    public function addToQueue(Am_Mail $mail, $sent = null)
    {
        $vals = [
            'from' => $mail->getFrom(),
            'recipients' => implode(',', $mail->getRecipients()),
            'count_recipients' => count($mail->getRecipients()),
            'subject' => mb_decode_mimeheader($mail->getSubject()),
            'priority' => $mail->getPriority(),
            'email_template_name' => $mail->getEmailTemplateName(),
            'serialized' => serialize($mail),
            'added' => Am_Di::getInstance()->time,
            'sent' => $sent ? $sent : null,
        ];
        Am_Di::getInstance()->db->query("INSERT INTO ?_mail_queue SET ?a", $vals);

        return Am_Di::getInstance()->db->selectCell("SELECT LAST_INSERT_ID()");
    }

    /**
     * Send message to transport from queue
     * @param array $row as retrieved from database
     */
    public function _sendSavedMessage(array & $row)
    {
        try {
            if (empty($row['serialized'])) { // deprecated!! to send messages remaining in queue. remove later
                $this->transport
                    ->sendFromSaved($row['from'], $row['recipients'], $row['body'], unserialize($row['headers']), $row['subject'], $this->EOL);
            } else { // new format!
                $mail = unserialize($row['serialized']);
                $this->transport->send($mail);
            }
            $row['sent'] = Am_Di::getInstance()->time;
            Am_Di::getInstance()->db->query(
                "UPDATE ?_mail_queue SET sent=?d WHERE queue_id=?d",
                $row['sent'],
                $row['queue_id']
            );
        } catch (Zend_Mail_Exception $e) {
            Am_Di::getInstance()->logger->error("Mail system exception while sending saved message", ["exception" => $e]);
            $row['failures']++;
            if ($row['failures'] >= 3) {
                //// deleting message on 3-rd failure
                Am_Di::getInstance()->db->query(
                    "DELETE FROM ?_mail_queue
                    WHERE queue_id=?d",
                    $row['queue_id']
                );
            } else {
                // save failure
                Am_Di::getInstance()->db->query(
                    "UPDATE ?_mail_queue
                    SET failures=failures+1, last_error=?
                    WHERE queue_id=?d",
                    $e->getMessage(),
                    $row['queue_id']
                );
            }
        }
    }

    /**
     * Check if there are messages in queue, and sending is allowed,
     * then send
     */
    public function sendFromQueue()
    {
        if (!in_array($this->getQueueStatus(), [self::QUEUE_OK, self::QUEUE_DISABLED])) {
            return;
        }
        //
        do {
            $sent = 0;
            $order = (AM_APPLICATION_ENV == 'testing') ? 'queue_id' : 'rand()';
            $q = Am_Di::getInstance()->db->queryResultOnly(
                "SELECT * FROM ?_mail_queue
                WHERE sent IS NULL AND added > ?
                ORDER BY priority DESC, added, $order limit 10", strtotime('-2 weeks')
            );
            while ($row = Am_Di::getInstance()->db->fetchRow($q)) {
                if (!in_array($this->getQueueStatus(), [self::QUEUE_OK, self::QUEUE_DISABLED])) {
                    return;
                }
                $this->_sendSavedMessage($row);
                $sent++;
            }
        } while ($sent);
    }

    public function getQueueStatus()
    {
        if (!$this->queueEnabled) {
            return self::QUEUE_DISABLED;
        }
        $sentLastPeriod = Am_Di::getInstance()->db->selectCell(
            "SELECT SUM(count_recipients)
            FROM ?_mail_queue WHERE sent >= ?d",
            Am_Di::getInstance()->time - $this->queuePeriod
        );
        $this->leftMessagesToSend = max(0, $this->queuePeriodicLimit - $sentLastPeriod);
        if ($sentLastPeriod < $this->queuePeriodicLimit) {
            return self::QUEUE_OK;
        } elseif ($sentLastPeriod < $this->queueLimit) {
            return self::QUEUE_ONLY_INSTANT;
        } else {
            return self::QUEUE_FULL;
        }
    }

    /**
     * Remove old e-mail messages from the queue
     */
    function cleanUp()
    {
        $days = (int)Am_Di::getInstance()->config->get('email_log_days', 0);
        Am_Di::getInstance()->db->query(
            "DELETE FROM ?_mail_queue
                WHERE added <= ?d AND sent IS NOT NULL",
            Am_Di::getInstance()->time - 3600 * 24 * $days
        );
    }
}