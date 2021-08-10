<?php

use phpseclib\Net\SFTP;

class Am_FileConnector_Sftp extends Am_FileConnector_Base
{
    use \Psr\Log\LoggerAwareTrait;

    /** @var Net_SFTP */
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
        ) ? $this->options['hostname']['port'] : 22;
    }

    public function connect()
    {
        if (!$this->logger)
            $this->logger = new \Psr\Log\NullLogger;
        $this->ftp = new phpseclib\Net\SFTP($h=$this->getHost(), $p=$this->getPort());
        $this->logger->debug("Connecting to $h:$p");
        if (!$this->ftp->login($this->options['user'], $this->options['pass'])) {
            $this->logger->debug("Login failed");
            return false;
        }
        $this->logger->debug("Login OK");
        return true;
    }

    public function cwd()
    {
        $ret = $this->ftp->pwd();
        $this->logger->debug("pwd returned {ret}", ['ret' => $ret]);
        $ret = rtrim($ret, "/\\");

        return $ret;
    }

    public function put($from, $to)
    {
        $this->logger->debug("put {from} => {to}", ['from' => $from, 'to' => $to]);
        return $this->ftp->put($to, file_get_contents($from)) &&
            $this->ftp->chmod($this->permFile, $to);
    }

    public function get($from, $to)
    {
        $this->logger->debug("get {from} => {$to}", ['from' => $from, 'to' => $to]);
        return $this->ftp->get($from, $to);
    }

    public function ls($dir)
    {
        $ret = $this->ftp->nlist($dir);
        $this->logger->debug("run nlist {dir}", ['dir' => $dir, 'ret' => $ret]);
        return $ret ? array_flip($ret) : [];
    }

    public function chdir($dir)
    {
        $this->logger->debug("chdir {dir}", ['dir' => $dir]);
        return $this->ftp->chdir($dir);
    }

    function rename($from, $to)
    {
        $this->logger->debug("rename {from} {to}", ['from'=>$from, 'to'=>$to]);
        return $this->ftp->rename($from, $to);
    }

    public function mkdir($dir)
    {
        $this->logger->debug("mkdir {dir}", ['dir' => $dir]);
        return $this->ftp->mkdir($dir)
            && $this->ftp->chmod($this->permDir, $dir);
    }

    public function rmdir($path)
    {
        $this->logger->debug("rmdir {dir}", ['dir' => $path]);
        return $this->ftp->rmdir($path);
    }

    public function unlink($path)
    {
        $this->logger->debug("unlink {path}", ['path' => $path]);
        return $this->ftp->delete($path, false);
    }

    public function getError()
    {
        $err = $this->ftp->getLastError();
        if (!$err) {
            $err = $this->ftp->getLastSFTPError();
        }
        if ($err) {
            $this->logger->debug("sftp error {msg}", ['msg' => $err]);
            return $err['msg'];
        }
    }

}
