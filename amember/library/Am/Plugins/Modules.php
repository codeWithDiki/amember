<?php

class Am_Plugins_Modules extends Am_Plugins
{
    protected $moduleDir = [];
    protected $viewPath = [];

    public function beforeLoad($id, $file=null)
    {
        $di = Am_Di::getInstance();
        $moduleDir = dirname($file);
        $this->moduleDir[$id] = $moduleDir;
        // add module library dir to path
        $libDir = $moduleDir . '/library';
        if (file_exists($libDir)) {
            $di->includePath->append($libDir);
            $di->autoloader->add("", $libDir);
        }
        // add module controllers dir to front
        $controllersDir = $moduleDir . '/controllers';
        if (file_exists($controllersDir)) {
            $di->front->addControllerDirectory($controllersDir, $id);
        }
        // add view path
        $viewDir = $moduleDir . '/views';
        if (file_exists($viewDir)) {
            $this->viewPath[] = $viewDir;
        }
        // if module is running from phar://, include real directory too
        if (strpos($moduleDir, 'phar://')===0)
        {
            $viewDir = $di->root_dir . '/application/' . $id . '/views';
            if (file_exists($viewDir))
                $this->viewPath[] = $viewDir;
        }
    }

    /**
     * correct full filepath for all modules from enabled list
     * @example getPathForAllEnabled("db.xml") will return list ('aff' => '.../db.xml',..) and so on
     * @param $addPath string, for example "db.xml"
     * @return array
     */
    public function getPathForPluginsList($addPath, $listOfPlugins=null)
    {
        if ($listOfPlugins === null)
            $listOfPlugins = $this->getEnabled();
        $ret = [];
        foreach ($listOfPlugins as $id)
        {
            $path = $this->findPluginFile($id);
            if (file_exists($fn = dirname($path) . "/" . $addPath))
                $ret[$id] = $fn;
        }
        return $ret;
    }

    /**
     * return @array viewPath, ... for all enabled and loaded modules
     */
    public function getViewPath()
    {
        return $this->viewPath;
    }

    public function findSetupUrl($id)
    {
        if ($ret = parent::findSetupUrl($id))
            return $ret;
        $path = $this->findPluginFile($id);
        if (file_exists($fn = dirname($path) . "/library/SetupForms.php"))
            return $this->_di->url('admin-setup/'.$id, false);
    }
}