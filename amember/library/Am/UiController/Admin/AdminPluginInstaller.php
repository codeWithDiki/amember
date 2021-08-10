<?php

use GuzzleHttp\Client;

/**
 * @author Alexey Presnyakov <alex@cgi-central.net
 * @license Commercial
 */


class AdminPluginInstallerController extends Am_Mvc_Controller_Upgrade_Base
{
    use Am_Mvc_Controller_Upgrade_AmemberTokenTrait;
    use Am_Mvc_Controller_Upgrade_FileConnectorTrait;

    protected function getSteps()
    {
        check_demo();
        return [
            0 => ['stepVars', ___('Create Session Key')],
            1 => ['stepSetCookie', ___('Create Session Key')],
            2 => ['stepConfirm', ___('Confirmation')],
            3 => ['stepGetRemoteAccess', ___('Retreive Access Parameters if necessary')],
            4 => ['stepDownload', ___('Download')],
            5 => ['stepUnpack', ___('Unpack')],
            6 => ['stepUpload', ___('Upload')],
            7 => ['stepActivate', ___('Activate')],
            8 => ['stepUnsetVariable', 'Finish'],
        ];
    }

    /** do not reset encrypted ftp info for this case, as we may want to install several plugins */
    protected function resetSession()
    {
        $info = $this->loadRemoteAccessInfo();
        $this->getSession()->unsetAll();
        $this->storeRemoteAccess($info);
    }

    public function stepVars()
    {
        $productId = $this->getRequest()->getInt('product_id');
        $productName = $this->getRequest()->getFiltered('product_name');
        if (!$productId)
            throw new Am_Exception_InputError("Empty product_id passed");
        if (!$productName)
            throw new Am_Exception_InputError("Empty product_name passed");

        $this->getSession()->product_id = $productId;
        $this->getSession()->product_name = $productName;

        return true;
    }

    public function stepConfirm()
    {
        if ($this->getRequest()->isPost())
        {
            return true;
        }

        $strings = $this->getStrings();

        $productName = $this->getSession()->product_name;

        $url = $this->getDi()->url('admin-plugin-installer');
        $this->view->content = <<<CUT
    
    <form method="post" action="$url">
        <div>You are going to download and install plugin: <b>{$productName}</b>.</div>
        <br />
        <input type="submit" name='continue' value="Click to continue" >
    </form>
CUT;
        $this->view->title   = $strings['title'];
        $this->view->display('admin/layout.phtml');
        $this->noDisplay = true;
        return false;
    }

    public function stepDownload()
    {
        if (!$this->loadAmemberToken(true))
            $this->_redirect('admin-plugins/callback');

        $branch = (defined('AM_BETA') && AM_BETA) ? 'beta' : 'release';
        $req = $this->amemberGetAuthenticatedRequest('GET', $url = 'https://www.amember.com/amember/api2/cgi-plugin-download?product_id='.
            $this->getSession()->product_id
            . '&version=' . AM_VERSION
            . '&branch=' . $branch);

        $client = new GuzzleHttp\Client();
        $resp = $client->send($req, ['http_errors' => false]);
        if ($resp->getStatusCode() != 200)
            throw new Am_Exception_InputError("Could not get download URL from aMember website, please try in 2 minutes #1");

        $data = json_decode((string)$resp->getBody(), true);
        if (empty($data['data']['url']))
            throw new Am_Exception_InputError("Could not get download URL from aMember website, please try in 2 minutes #2");

        $url = $data['data']['url'];

        $productId = (int)$this->getSession()->product_id;

        $client = new GuzzleHttp\Client();
        $resp = $client->get($url, ['http_errors' => false]);
        if ($resp->getStatusCode() != 200)
            throw new Am_Exception_InputError("Could not download from aMember website, please try in 2 minutes #1");

        $fn = $this->getDi()->data_dir . '/plugin-download-' . $productId . '-' . $this->getDi()->security->randomString(6) .'.zip';
        if (file_exists($fn))
            if (!unlink($fn))
                throw new Am_Exception_InternalError("Cannot delete file [$fn]");

        if (!file_put_contents($fn, (string)$resp->getBody()))
            throw new Am_Exception_InternalError("Cannot write plugin file [$fn]");

        $this->getSession()->plugin_fn = $fn;

        return true;
    }

    public function stepUnpack()
    {
        $fn = $this->getSession()->plugin_fn;

        $zip = new \splitbrain\PHPArchive\Zip();
        try {
            $zip->open($fn);
        } catch (\Exception $e) {
            throw new Am_Exception_InputError("Could not open downloaded file $fn: " . $e->getMessage() );
        }

        $dir = dirname($fn) . '/' . basename($fn, '.zip');
        $this->getSession()->plugin_dir = $dir;
        try {
            $excludeRegex = '#readme-(module|plugin-misc|plugin-phar|plugin-protect)\.txt$#';
            $zip->extract($dir, $stripFolders = 1, $excludeRegex);
        } catch (\Exception $e) {
            throw new Am_Exception_InputError("Could not open downloaded file $fn to $dir: " . $e->getMessage());
        }

        unlink($fn);

        $this->getSession()->plugin_type = $this->detectPluginType($dir);

        return true;
    }

    protected function detectPluginType($dir)
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir),
            RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $file)
        {
            if (preg_match('#^amember-(protect|payment|storage|misc|module)-(.+).phar$#', $file->getFilename(), $regs))
                return ($regs[1] == 'module')?'modules':$regs[1];
            if ($file->isDir())
            {
                if (preg_match('#^(protect|payment|storage|misc)$#', $file->getFilename(), $regs))
                    return $regs[1];
                if ($file->getFilename() == 'cc')
                    return 'payment';
            }
            if ($file->getFilename() == 'Bootstrap.php')
                return 'modules';
        }
    }

    public function stepUpload()
    {
        $dir = $this->getSession()->plugin_dir . '/';

        $connector = $this->getFileConnector();
        if (!$connector->connect())
        {
            $this->outText('Connection error: ' . Am_Html::escape($connector->getMessage()));
            return false;
        }
        if (!$connector->chdir($connector->getRoot()))
        {
            $this->outText('Could not chroot to root folder: [' . Am_Html::escape($connector->getRoot()) . ']');
            return false;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir),
            RecursiveIteratorIterator::SELF_FIRST);
        $logger = $this->getDi()->logger;
        foreach ($iterator as $file)
        {
            if ($file->getFileName() == '.' || $file->getFileName() == '..') continue;
            if (!strpos($file->getPathName(), $strip = $dir))
            {
                new Am_Exception_InputError(sprintf('Could not strip local root prefix: [%s] from fn [%s]',
                    $strip, $file->getPathName()));
            }
            // path relative to amember root
            $path = substr($file->getPathName(), strlen($strip));
            if ($file->isDir())
            {
                if (!$connector->mkdir($path) && !$connector->ls($path))
                {
                    $this->outText('Could not create folder [' . Am_Html::escape($path) . ']<br />' . $connector->getError());
                    return false;
                }
                $this->outText('created folder ' . Am_Html::escape($path) . "<br />\n");
                $logger->info('created folder {path}', ['path' => $path]);
            } else {
                if (!$connector->put($file->getPathName(), $path))
                {
                    $this->outText('Could not copy file ['
                        . Am_Html::escape($file->getPathName())
                        . '] to remote [' . Am_Html::escape($path)
                        . '] ' . $connector->getError());
                    return false;
                }
                $this->outText('copy file ' . Am_Html::escape($path) . "<br />\n");
                $logger->info('copy {fn} => {path}', ['fn' => $file->getPathName(), 'path' => $path]);
            }
        }
        // remove localdirectory and files
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir),
            RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file)
        {
            if ($file->getFileName() == '.' || $file->getFileName() == '..') continue;
            if ($file->isDir())
                rmdir($file->getPathName());
            else
                unlink($file->getPathName());
        }
        rmdir($dir);

        return true;
    }

    public function stepActivate()
    {
        $this->getSession()->csrf = $csrf = $this->getDi()->security->randomString(12);
        $type = $this->getSession()->plugin_type;
        if (!$type) return true;
    
        /**
         * @var ArrayObject $plugins
         */
        $plugins = $this->getDi()->plugins;
        if (!$plugins->offsetExists($type))
        {
            return true;
        }
        $pluginId = $this->getSession()->product_name;
        try
        {
            $plugins[$type]->activate($pluginId, Am_Plugins::ERROR_EXCEPTION | Am_Plugins::ERROR_LOG, true);
        } catch (Exception $e) {
            throw new Am_Exception_InputError("Could not activate plugin [$pluginId]");
        }
        return true;
    }

    public function stepUnsetVariable()
    {
        $url_setup = $this->getDi()->url('admin-setup?tryOpenPage='.$this->getSession()->product_name);
        $url_plugins = $this->getDi()->url('admin-plugins');
        $strings = $this->getStrings();

        $this->view->content = <<<CUT
    <div style="max-width: 800px; font-size: 130%; line-height: 120%;">
    <p>The plugin has been successfully installed.</p>
    
    <a href="{$url_setup}">Continue to Setup</a> 
    or 
    <a href="{$url_plugins}">Back to Plugins list</a>
     
CUT;


        $this->view->title   = $strings['finished_title'];
        $this->view->display('admin/layout.phtml');
        $this->noDisplay = true;

        $this->resetSession();
    }

    protected function getSessionNamespace()
    {
        return 'amember-plugin-installer';
    }

    protected function getStrings()
    {
        return [
            'finished_title' => ('Plugin Installed'),
            'finished_text' => ('Plugin Installed'),
            'title' => ('Install Plugin'),
        ];
    }
}