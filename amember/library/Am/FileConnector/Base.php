<?php

abstract class Am_FileConnector_Base implements \Psr\Log\LoggerAwareInterface
{
    protected $options = [];
    protected $root;
    protected $permFile = 0644;
    protected $permDir = 0755;
    /** @var \Psr\Log\LoggerAwareInterface */
    protected $logger;

    public function __construct(array $options)
    {
        $this->options = $options;
        $this->root  = empty($options['root']) ? null : rtrim($options['root'], '/');
        $this->logger = new \Psr\Log\NullLogger();
    }

    /**
     * Return root directory from options without right slash
     */
    public function getRoot()
    {
        return $this->root;
    }
    /**
     * @return @bool false on failure, true on ok
     */
    abstract function connect();

    abstract function cwd();

    abstract function put($from, $to);

    abstract function get($from, $to);

    abstract function ls($dir);

    abstract function chdir($dir);

    abstract function mkdir($dir);

    abstract function rename($from, $to);

    abstract function unlink($path);

    abstract function rmdir($path);

    /**
     * @return string last error message
     */
    abstract function getError();
}

