<?php

/** empty */
class Am_Import_Log
{
    const TYPE_SKIP = 1;
    const TYPE_ERROR = 2;
    const TYPE_SUCCESS = 3;
    const TYPE_PROCCESSED = 4;

    const MAX_ERRORS_LOG = 15;
    const MAX_SKIP_LOG = 15;

    /** @var Am_Session_Ns */
    protected $session;
    protected static $instance = null;

    protected function __construct()
    {
        $this->session = Am_Di::getInstance()->session->ns('amember_import_log');
    }

    public static function getInstance()
    {
        if (!self::$instance)
        {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function touchStat($type)
    {
        if (!isset($this->session->stat) ||
            !is_array($this->session->stat))
        {

            $this->session->stat = [
                self::TYPE_SKIP => 0,
                self::TYPE_ERROR => 0,
                self::TYPE_SUCCESS => 0,
                self::TYPE_PROCCESSED => 0,
            ];
        }
        $this->session->stat[$type]++;
    }

    public function getStat($type = null)
    {
        if (is_null($type))
        {
            return $this->session->stat;
        }

        if (isset($this->session->stat[$type]))
        {
            return $this->session->stat[$type];
        } else
        {
            return 0;
        }
    }

    public function logError($message, $lineParsed)
    {
        if (!isset($this->session->errors))
        {
            $this->session->errors = [];
        }
        if (count($this->session->errors) >= self::MAX_ERRORS_LOG)
        {
            return;
        }

        $error = [];
        $error['msg'] = $message;
        $error['lineParsed'] = $lineParsed;
        $this->session->errors[] = $error;
    }

    public function logSkip($lineParsed)
    {
        if (!isset($this->session->skip))
        {
            $this->session->skip = [];
        }
        if (count($this->session->skip) >= self::MAX_SKIP_LOG)
        {
            return;
        }
        $this->session->skip[] = $lineParsed;
    }

    public function clearLog()
    {
        $this->session->errors = [];
        $this->session->skip = [];
        $this->session->stat = null;
    }

    public function getErrors()
    {
        if (!isset($this->session->errors))
        {
            $this->session->errors = [];
        }

        return $this->session->errors;
    }

    public function getSkip()
    {
        if (!isset($this->session->skip))
        {
            $this->session->skip = [];
        }

        return $this->session->skip;
    }
}