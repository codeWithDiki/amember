<?php

class Am_FileConnector_Ftp extends Am_FileConnector_Base
{
    /** @var ftp */
    protected $ftp;

    public function getHost()
    {
        return @$this->options['hostname']['host'];
    }

    public function getPort()
    {
        return (
            array_key_exists('port', $this->options['hostname'])
            && (intval($this->options['hostname']['port']) > 0)
        ) ? intval($this->options['hostname']['port']) : 21;
    }

    public function connect()
    {
        $this->ftp = Am_ftp_base::create();
        if ($this->logger)
            $this->ftp->setLogger($this->logger);
        $this->ftp->SetServer($this->getHost(), $this->getPort());
        $this->ftp->Passive(true); // questionable if it must be default or configurable
        if (!$this->ftp->connect($this->getHost(), $this->getPort())) {
            $this->ftp->PushError('connect', "Could not connect to host");

            return false;
        }
        if (!$this->ftp->login($this->options['user'], $this->options['pass'])) {
            $this->ftp->PushError('auth', "Authentication failed");

            return false;
        }
        return true;
    }

    public function cwd()
    {
        $ret = $this->ftp->pwd();
        $ret = rtrim($ret, "/\\");

        return $ret;
    }

    public function put($from, $to)
    {
        return $this->ftp->put($from, $to) && ($this->ftp->chmod($to, $this->permFile) || true);
    }

    public function get($from, $to)
    {
        return $this->ftp->get($from, $to);
    }

    public function ls($dir)
    {
        return $this->ftp->dirlist($dir);
    }

    public function chdir($dir)
    {
        return $this->ftp->chdir($dir);
    }

    public function mkdir($dir)
    {
        return $this->ftp->mkdir($dir) && ($this->ftp->chmod($dir, $this->permDir) || true);
    }

    public function rmdir($path)
    {
        return $this->ftp->rmdir($path);
    }

    public function unlink($path)
    {
        return $this->ftp->delete($path);
    }

    function rename($from, $to)
    {
        $this->logger->debug("rename {from} {to}", ['from'=>$from, 'to'=>$to]);
        return $this->ftp->rename($from, $to);
    }

    public function getError()
    {
        $err = $this->ftp->PopError();
        if ($err) {
            return $err['msg'];
        }
    }

    /**
     * Sets a logger instance on the object.
     *
     * @param \Psr\Log\LoggerInterface $logger
     *
     * @return null
     */
    public function setLogger(\Psr\Log\LoggerInterface $logger)
    {
        $this->logger = $logger;
        if ($this->ftp)
            $this->ftp->setLogger($logger);
    }
}
