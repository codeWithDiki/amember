<?php

class Am_Nonce
{
    const LEN = 15;

    const REASON_INVALID = 'nonce_invalid';
    const REASON_EXPIRED = 'nonce_expired';
    const REASON_HASH = 'nonce_wrong_hash';

    protected $lifetime = 3600;
    /** @var Am_Security */
    protected $di;

    function __construct(Am_Di $di)
    {
        $this->di = $di;
    }

    function lifetime($seconds = null)
    {
        if ($seconds !== null)
        {
            $this->lifetime = (int)$seconds;
            return $this;
        }
        return $this->lifetime;
    }

    /**
     * Add nonce field to URL
     * @param $url
     * @param $fieldName
     * @return string
     */
    function url($url, $key, $nonceField = '_nonce')
    {
        $add = urlencode($nonceField) . '=' . $this->create($key);
        if (strpos($url, '?')===false)
            return $url . '?' . $add;
        else
            return $url . '&' . $add;
    }

    /**
     * Return hidden input HTML for nonce
     */
    function hidden($key, $nonceField = '_nonce')
    {
        $nonceField = Am_Html::escape($nonceField);
        $v = $this->create($key);
        return "<input type='hidden' name='$nonceField' value='$v'>";
    }

    /**
     * Generate nonce value for $key and current timestamp
     * @param string $key
     * @return string
     */
    function create($key)
    {
        $tm = floor($this->di->time / 4) * 4; // do not show exact time
        return $tm . '_' . $this->di->security->hash($tm.'_'.$key, self::LEN);
    }

    /**
     * Validate request value and throw security exception if nonce is invalid
     * @param $key
     * @param $nonceField
     */
    function check($key, $nonceField = '_nonce')
    {
        $v = $this->di->request->get($nonceField);
        if ($v && $this->verify($v, $key, $reason))
            return;
        if ($reason == self::REASON_EXPIRED)
            throw new Am_Exception_Security(___("CSRF protection error - form must be submitted within %d minutes after displaying, please repeat", $this->lifetime));
        else
            throw new Am_Exception_Security(___('CSRF protection error - nonce field is invalid or empty'));
    }

    function verify($nonceReceived, $key, & $reason = null)
    {
        $x = explode('_', $nonceReceived, 2);
        if ((count($x)!=2) || !$x[1])
        {
            $reason = self::REASON_INVALID;
            return false;
        }
        list($tm, $hash) = $x;
        $timeNow = $this->di->time;
        if ( ($tm+$this->lifetime) < $timeNow)
        {
            $reason = self::REASON_EXPIRED;
            return false;
        }
        // reject tricky requests with time in future
        if ($tm > $timeNow)
        {
            $reason = self::REASON_EXPIRED;
            return false;
        }

        if (!hash_equals($this->di->security->hash($tm.'_'.$key, self::LEN), $hash))
        {
            $reason = self::REASON_HASH;
            return false;
        }

        return true;
    }
}