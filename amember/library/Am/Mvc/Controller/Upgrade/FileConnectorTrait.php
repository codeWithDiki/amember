<?php


/**
 * To be added to upgrade controler to provide
 *
 * Trait Am_Mvc_Controller_Upgrade_FileConnectorTrait
 */
trait Am_Mvc_Controller_Upgrade_FileConnectorTrait
{
    protected function getAdminKey() { return 'am_admin_key'; }

    function encrypt($data)
    {
        if (empty($_COOKIE[$this->getAdminKey()]))
            return null;
        $c = new Am_Crypt_Aes128($_COOKIE[$this->getAdminKey()]);
        return $c->encrypt($data);
    }

    function decrypt($ciphertext)
    {
        if (empty($_COOKIE[$this->getAdminKey()]))
            return null;
        $c = new Am_Crypt_Aes128($_COOKIE[$this->getAdminKey()]);
        return $c->decrypt($ciphertext);
    }

    function storeRemoteAccess(array $info)
    {
        $this->getSession()->admin_remote_access = $this->encrypt(serialize($info));
        return true;
    }

    function loadRemoteAccessInfo()
    {
        if (empty($this->getSession()->admin_remote_access)) return [];
        return unserialize($this->decrypt($this->getSession()->admin_remote_access));
    }

    /**
     * @param array $info
     * @return Am_FileConnector_Base
     */
    function getFileConnector($info = [])
    {
        if (!$info)
            $info = $this->loadRemoteAccessInfo();
        $connector = $this->loadRemoteAccessInfo();
        if (empty($info['method']))
            return null;
        $class = 'Am_FileConnector_' . ucfirst(toCamelCase($info['method']));
        return new $class($info);
    }

    function tryConnect(array & $info)
    {
        $connector = $this->getFileConnector($info);
        $logger = new \Psr\Log\NullLogger();

        if (!$connector->connect())
        {
            return "Connection failed: " . $connector->getError();
        }
        // create temp file locally
        $fn = tempnam($this->getDi()->data_dir, 'test-ftp-');
        $f = fopen($fn, 'w'); fclose($f);
        $cwd = $connector->cwd();
        $ls = $connector->ls(".");
        $root = $this->guessChrootedAmemberPath($cwd, array_keys($ls), $this->getDi()->root_dir);
        $root_path = null;

        $tryPath = array_filter([ @$info['path'], $root, $this->getDi()->root_dir]);
        foreach($tryPath as $path){
            $ls = $connector->ls(rtrim($path, '/') . '/data');
            $logger->debug("try path", ['ls'=>$ls]);
            if ($ls && array_key_exists(basename($fn), $ls)){
                $root_path = $path;
                break;
            }
        }
        @unlink($fn);
        if (is_null($root_path))
        {
            return "Connection succesful, but upgrade script was unable to locate test file on remote server";
        }
        $info['root'] = $root_path;
    }

    function guessChrootedAmemberPath($cwd, array $lsCwd, $amRoot)
    {
        // split amRoot to dirnames
        $dirnames_r = array_filter(preg_split('|[\\/]|', $cwd));
        $dirnames_l = array_filter(preg_split('|[\\/]|', $amRoot));
        // find first occurence of dirnames in lsCwd
        $start = false;
        $foundInLs = [];
        foreach ($dirnames_l as $lstart => $d)
        {
            if ($start = array_search($d, $dirnames_r))
                break;
            if (in_array($d, $lsCwd))
                $foundInLs[] = $lstart;
        }
        if ($start === false)
        {
            if ($foundInLs)
            {
                $start = null;
                $lstart = min($foundInLs) - 1;
            }
        }
        return '/' . implode('/', array_merge(
                array_slice($dirnames_r, 0, $start),
                array_slice($dirnames_l, $lstart)
            ));
    }

    function needsRemoteAccess()
    {
        if ( !function_exists('getmyuid') && !function_exists('fileowner')) return false;
        $fn = $this->getDi()->data_dir . '/temp-write-test-' . time();
        $f = @fopen($fn, 'w');
        if (!$f )
            throw new Am_Exception_InternalError("Could not create test file - check if data dir is writeable");
        if ( getmyuid() == @fileowner($fn) ) return false;
        @fclose($f);
        @unlink($fn);
        return true;
    }

    function askRemoteAccess()
    {
        $form = new Am_Form_Admin('remote-access');
        $info = $this->loadRemoteAccessInfo();
        if ($info && !empty($info['_tested']))
            return true;
        if ($info)
            $form->addDataSource(new Am_Mvc_Request($info));
        $method = $form->addSelect('method', null, ['options' => ['ftp' => 'FTP', 'sftp' => 'SFTP']])
            ->setLabel(___('Access Method'));
        $gr = $form->addGroup('hostname')->setLabel(___('Hostname'));
        $gr->addText('host')->addRule('required')->addRule('regex', 'Incorrect hostname value', '/^[\w\._-]+$/');
        $gr->addHTML('port-label')->setHTML('&nbsp;:<b>Port</b>&nbsp;');
        $gr->addText('port', ['size'=>3]);
        $gr->addHTML('port-notice')->setHTML('&nbsp;leave empty if default');
        $form->addText('user')->setLabel(___('Username'))->addRule('required');
        $form->addPassword('pass')->setLabel(___('Password'));
        /** @var HTML_QuickForm2_Element_Input $p */
        $p = $form->addText("path", ['id'=>'path', 'size'=>60, 'placeholder' => 'keep empty for auto-detection'])->setLabel(___("Full Path to aMember Pro files on server"));
        $p->addFilter(function($s) {
            $s = preg_replace('/[^a-zA-Z0-9\-\._\/]/','', $s);
            return str_replace('..', '', $s);
        });
        $form->addSubmit('', ['value' => ___('Continue')]);

        $error = null;
        $vars = $form->getValue();
        if ($form->isSubmitted() && $form->validate() && !($error = $this->tryConnect($vars)))
        {
            $vars['_tested'] = true;
            $this->storeRemoteAccess($vars);
            return true;
        } else {
            //$this->view->title = ___("File Access Credentials Required");
            $this->view->title = ___('Upgrade');
            $this->view->content = "";
            $this->outStepHeader();
            if ($error) $method->setError($error);
            $this->view->content .= (string)$form;
            $this->view->display('admin/layout.phtml');
            $this->noDisplay = true;
        }
    }


    public function stepSetCookie()
    {
        if (!$this->_request->getCookie($this->getAdminKey()))
        {
            unset($this->getSession()->admin_remote_access);
            $_COOKIE[$this->getAdminKey()] = $this->getDi()->security->randomString(56);
            Am_Cookie::set($this->getAdminKey(), $_COOKIE[$this->getAdminKey()], $this->getDi()->time + 3600);
        }
        return true;
    }

    public function stepGetRemoteAccess()
    {
        if (!$this->needsRemoteAccess())
        {
            $this->storeRemoteAccess(['method' => 'local', 'root' => $this->getDi()->root_dir, '_tested' => true]);
            return true;
        }
        return $this->askRemoteAccess();
    }

}