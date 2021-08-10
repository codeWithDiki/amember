<?php

class Am_FileConnector_Local extends Am_FileConnector_Base
{
    public function connect()
    {
        return true;
    }

    public function cwd()
    {
        return getcwd();
    }

    public function get($from, $to)
    {
        return copy($from, $to);
    }

    public function getError()
    {
    }

    /** @todo implement normally ! */
    public function ls($dir)
    {
        $d = opendir($dir);
        if (!$d) {
            return false;
        }
        $ret = [];
        while ($f = readdir($d)) {
            $ret[$f] = stat($dir.DIRECTORY_SEPARATOR.$f);
        }
        closedir($d);

        return $ret;
    }

    public function mkdir($dir)
    {
        return @mkdir($dir) && (chmod($dir, $this->permDir) || true);
    }

    public function put($from, $to)
    {
        return copy($from, $to) && (chmod($to, $this->permFile) || true);
    }

    public function chdir($dir)
    {
        return chdir($dir);
    }
    function rename($from, $to)
    {
        return rename($from, $to);
    }
    function unlink($path)
    {
        return unlink($path);
    }
    function rmdir($dir)
    {
        return rmdir($dir);
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
    }
}