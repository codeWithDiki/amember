<?php

/*
 * TODO use version_compare() to filter out already installed modules and core updates
 * TODO make manifest.xml for upgrade - to delete files, chmod and file hashes()
 */

@define('AM_CHECK_UPGRADES_URL', 'https://www.amember.com/amember/check-upgrades');

class AdminUpgradeController extends Am_Mvc_Controller_Upgrade_Base
{
    use Am_Mvc_Controller_Upgrade_FileConnectorTrait;
    use Am_Mvc_Controller_Upgrade_AmemberTokenTrait;

    protected $allowedDomains = [
        'www.amember.com',
        'www.cgi-central.net',
    ];
    protected $upgrades = [];

    protected function getSteps()
    {
        return [
            ['stepSetCookie', ___('Create Session Key')],
            ['stepLoadUpgradesList', ___('Get Available Upgrades List')],
            ['stepConfirmUpgrades', ___('Choose Upgrades to Install')],
            ['stepGetRemoteAccess', ___('Retreive Access Parameters if necessary')],
            ['stepDownload', ___('Download Upgrades')],
            ['stepUnpack', ___('Unpack Upgrades')],
            ['stepSetMaint', ___('Enter Maintenance Mode')],
            ['stepCopy', ___('Copy Upgrades')],
            ['stepAutoEnable', ___('Enable plugins if necessary')],
            ['stepUpgradeDb', ___('Upgrade Database')],
            ['stepUnsetMaint', ___('Quit Maintenance Mode')],
        ];
    }

    protected function getSessionNamespace()
    {
        return 'amember-upgrade';
    }

    protected function getStrings()
    {
        return [
            'finished_title' => ___('Upgrade Finished'),
            'finished_text' => ___('Upgrade Finished'),
            'title' => ___('Upgrade'),
        ];
    }

    public function checkAction()
    {
        //$this->getDi()->store->delete('upgrades-list');
        // read/write to am_store and handle dismission of upgrades and new plugins notifications
        // check if saved record exists
        $load = $this->getDi()->store->getBlob('upgrades-list');
        if (!empty($load))
        {
            $upgrades = unserialize($load);
        } else {
            $upgrades = ['_loaded' => null, '_dismissed' => null];
        }
        if ($upgrades['_loaded'] < (time() - 3600))
        {
            $upgrades['items'] = $this->loadUpgradesList(false);
            $upgrades['_loaded'] = time();
            $this->getDi()->store->setBlob('upgrades-list', serialize($upgrades));
        }
        $ret = [];
        foreach ($upgrades['items'] as $upgrade)
        {
            if (version_compare($upgrade->version, AM_VERSION) <= 0) continue;

            if (!empty($upgrades['_dismissed']
            [$upgrade->type]
            [$upgrade->id]
            [$upgrade->version]
            [$this->getDi()->authAdmin->getUserId()]
            ))
                continue;
            if (empty($upgrade->notice)) {
                $upgrade->notice = !empty($upgrade->is_new) ? 'New Module Available: ' : 'Upgrade Available';
                $upgrade->notice .= sprintf(': %s [%s] ', $upgrade->title, $upgrade->version);
            }
            $upgrade->dismiss_url = $this->getDi()->url('admin-upgrade/dismiss',
                [
                    'type' => $upgrade->type,
                    'id'   => $upgrade->id,
                    'version' => $upgrade->version,
                ], false);
            $ret[] = $upgrade;
        }
        //$this->setCookie('am_upgrade_checked', 1, '+1 hour');
        return $this->ajaxResponse($ret);
    }

    public function dismissAction()
    {
        $load = $this->getDi()->store->getBlob('upgrades-list');
        if (!empty($load))
        {
            $upgrades = unserialize($load);
            $upgrades['_dismissed']
            [$this->_request->get('type')]
            [$this->_request->get('id')]
            [$this->_request->get('version')]
            [$this->getDi()->authAdmin->getUserId()] = time();
            $this->getDi()->store->setBlob('upgrades-list', serialize($upgrades));
        }
    }


    public function stepConfirmUpgrades()
    {
        $form = new Am_Form_Admin('confirm-upgrade');
        $upgrades = $form->addGroup('upgrades', ['class' => 'am-no-label']);
        $options = [];
        $static = '';
        $upgrades->addStatic()->setContent('<h2>'.___('Available Upgrades').'</h2>');
        foreach ($this->getUpgrades() as $k => $upgrade)
        {
            if (!empty($upgrade->new))
            {
                $upgrades->addStatic()->setContent('<br /><h2>'.___('New Modules Available').'</h2>');
            }
            $text = sprintf('%s%s, '.___('version').' %s - %s' . '<br />',
                '<b>'.$upgrade->title.'</b>',
                $upgrade->type =='core' ? '' : sprintf(' [%s - %s]', $upgrade->type, $upgrade->id),
                '<i>'.$upgrade->version.'</i>', '<i>'.amDate($upgrade->date).'</i>');
            $check = $upgrades->addCheckbox($k, empty($upgrade->checked) ? null : ['checked' => 'checked'])->setContent($text);
            if (!empty($upgrade->disabled))
            {
                $check->toggleFrozen(true);
                $check->setValue(0);
            }

            $static .= "<div class='changelog' style='margin-top:.5em' data-for='$k'><pre style='white-space: pre-wrap; max-height:500px;overflow-y:scroll'>".
                $upgrade->text.
                "</pre></div>\n";
            $upgrades->addStatic()->setContent($static);
        }

        $form->addCheckbox('_confirm', ['class' => 'am-no-label'])
            ->setContent(___('I understand that upgrade may overwrite customized PHP files and templates, I have already made a backup of aMember Pro folder and database'))
            ->addRule('required');

        $form->addScript()->setScript(<<<CUT
    jQuery(function(){
        jQuery('#confirm-upgrade').submit(function(ev){
            var ok = jQuery('#amember-login-ok').val() == '1';
            if (!ok)
            {
                alert("To continue, please login to your aMember.Com account\\n"+
                      "using button on this page. Active 'Support&Upgrades'\\n" +
                      "subscription is also required.");
                ev.stopPropagation();
                return false;
            }
        });
    });        
CUT
        );

        $form->addSubmit('', ['value' => ___('Install Updates')]);
        if ($form->isSubmitted() && $form->validate() && $upgrades->getValue())
        {
            $confirmed = array_keys(array_filter($upgrades->getValue()));
            if (!$confirmed)
            {
                $this->view->title = ___('No upgrades to install');
                $this->view->content = '<a href="'.$this->getDi()->url('admin').'">'.___('Back').'</a>';
                return false;
            }
            $upgrades = $this->getUpgrades();
            foreach ($upgrades as $k => $v)
                if (!in_array($k, $confirmed))
                    unset($upgrades[$k]);
            $this->setUpgrades($upgrades);
            return true;
        } else {
            $this->view->content = $this->renderUserView() . (string)$form;
            $this->view->title   = ___('Choose Upgrades to Install');
            $this->view->display('admin/layout.phtml');
            $this->noDisplay = true;
            return false;
        }
    }

    function renderUserView()
    {
        $userSubscriptionText = function($date, $expired) {
            if (! $date)
                return "<span style='color:gray;'>unknown</span>";
            elseif ($expired)
                return "<span style='color:#ba2727;'>expired</span>";
            else
                return "<span style='color:green;'>active</span>, expires " . amDate($date);
        };

        $amemberSiteResources = $this->fetchAmemberResourcesWithErrorHandling($tokenValid);
        $loggedIn =  !empty($amemberSiteResources['am_core_expire']) && $tokenValid;
        if ($loggedIn)
        {
            $logoutLink = $this->getOauthLogoutLink($this->getDi(), $this->getDi()->url('admin-upgrade', false), true);
            $refreshLink = $this->getDi()->url('admin-upgrade?refresh=1', true);
            $name_f = Am_Html::escape($amemberSiteResources['name_f']);
            $login = Am_Html::escape($amemberSiteResources['login']);
            $expired = $amemberSiteResources['am_core_expire'] < $this->getDi()->sqlDate;
            $userSubscriptionText = $userSubscriptionText($amemberSiteResources['am_core_expire'], $expired);

            $okToContinue = ($loggedIn && !$expired) ? 1 : 0;

            $out = <<<CUT
    <div class="am-token-auth am-token-auth_upgrade">
        <div class="am-amember-user-info">
                <input type="hidden" id="amember-login-ok" value="$okToContinue" />
                <i class="far fa-user" style="padding-right: .5em"></i> 
                <span class="am-amember-user-info-identity">
                <span>{$name_f}</span>
                <span> ({$login})</span></span>,
                Updates & Support Subscription: <span>$userSubscriptionText</span>
                &mdash;
                <a href="$refreshLink">refresh</a>
                <a class="am-amember-user-info-logout" href="$logoutLink">logout</a>
        </div>
    </div>
CUT;
            if ($expired ) $out .= <<<CUT
    <div class="am-plugins-expired-subscription">
        Unfortunately, your subscription to "Updates & Support" is already expired. Please visit
        <a href="https://www.amember.com/amember/signup">aMember Website</a> to
        update your subscription. Plugin purchase is not available without
        active updates subscription.
    </div>
CUT;

        } else {
            $loginLink = $this->getOauthLoginLink($this->getDi(), $this->getDi()->url('admin-upgrade', false), true);
            $out = <<<CUT
    <a class="am-amember-user-info-login" style="float:none; margin: 0 0 1em 0" href="$loginLink">Login to Your aMember PRO Account</a>
CUT;
        }
        return $out;
    }

    public function stepLoadUpgradesList()
    {
        if (($ret = $this->loadUpgradesList(true)) === false)
            return false;
        $this->setUpgrades($ret);
        if (!$ret)
            throw new Am_Exception_InputError(
                ___("No Updates Available") .
                ". <a href='".$this->getDi()->url("admin-upgrade", ['reset'=>1,'beta'=>1])."'>" .
                ___("Check for beta version")
                . '</a>');
        return true;
    }

    public function setUpgrades($ret)
    {
        $this->getDi()->store->setBlob('do-upgardes-list', serialize($ret));
    }

    public function getUpgrades()
    {
        return unserialize($this->getDi()->store->getBlob('do-upgardes-list'));
    }

    function stepDownload(Am_BatchProcessor $batch, & $start)
    {
        if (!$this->loadAmemberToken(true))
            $this->redirectHtml(
                $this->getOauthLoginLink($this->getDi(), $this->getDi()->surl('admin-upgrade')),
                ___('Please login into your amember.com account to continue')
            );

        $upgrades = $this->getUpgrades();
        foreach ($upgrades as $k => & $upgrade)
        {
            if (!empty($upgrade->upload_id))
                continue;
            if (!$batch->checkLimits())
            {
                $start = $k;
                return false;
            }
            $url = $upgrade->{'api-url'};
            $parsed = parse_url($url);
            if (!in_array($parsed['scheme'], ['http', 'https']))
                throw new Am_Exception_Security("Strange upgrade URL scheme: ".Am_Html::escape($parsed['scheme']));
            if (!in_array($parsed['host'], $this->allowedDomains))
                throw new Am_Exception_Security("Strange upgrade URL host: ".Am_Html::escape($parsed['host']));

            try {
                $client = new GuzzleHttp\Client();
                $req = $this->amemberGetAuthenticatedRequest('POST', $url);
                $response = $client->send($req, ['form_params' => $this->getUpgradeData()]);
                $body = json_decode((string)$response->getBody(), true);
                if (empty($body['status']) || ($body['status']!='OK') || empty($body['data']['url']))
                    throw new Am_Exception_InputError("Upgrade download problem: " . $body['message']);

                $response = $client->get($body['data']['url']);
            } catch (Exception $e) {
                $this->view->title = ___('Upgrade Download Problem');
                $this->view->content = ___('Could not download file [%s]. Error %s. Please %stry again%s later.',
                    Am_Html::escape($url),
                    get_class($e) . ': ' . $e->getMessage(),
                    '<a href="admin-upgrade?_='.time().'">', '</a>');
                $this->view->display('admin/layout.phtml');
                return false;
            };
            ini_set('display_errors', true);
            $ext = ($response->getHeader('Content-type') == 'application/zip') ? '.zip' : '.tgz' ;
            $fn = $this->getDi()->data_dir .  '/.upgrade.' . $this->getDi()->security->randomString(8) . $ext;
            $fp = fopen($fn, 'w');
            if (!$fp) throw new Am_Exception_InputError("Could not open file [$fn] for write");
            $responseStream = \GuzzleHttp\Psr7\StreamWrapper::getResource($response->getBody());
            if (!stream_copy_to_stream($responseStream, $fp) || !fclose($fp) || !filesize($fn))
            {
                unlink($fn);
                $this->view->title = ___('Upgrade Download Problem');
                $this->view->content = ___('Could not download file [%s]. Error %s. Please %stry again%s later.',
                    Am_Html::escape($url),
                    'storing download problem',
                    '<a href="admin-upgrade?tm='.time().'">', '</a>');
                $this->view->display('admin/layout.phtml');
                return false;
            }
            $upload = $this->getDi()->uploadRecord;
            $upload->name = basename($fn);
            $upload->path = basename($fn);
            $upload->prefix = 'upgrade';
            $upload->uploaded = time();
            $upload->desc = $upgrade->title .' '.$upgrade->version;
            $upload->insert();
            $upgrade->upload_id = $upload->pk();
            $this->setUpgrades($upgrades);
            $this->outText("Downloaded [$url] - " . $upload->getSizeReadable() . '<br />');
        }
        return true; // force page load
    }

    /**
     * Create PHPArchive/Zip or Tar depending on file header
     * ZIP - 4b50 0403
     * TGZ - 8b1f 0008
     * @param $compressedFn
     * @return splitbrain\PHPArchive\Archive zip or tar
     */
    function createPhpArchive($compressedFn)
    {
        if (preg_match('#\.zip$#', $compressedFn))
            return new \splitbrain\PHPArchive\Zip();
        else
            return new \splitbrain\PHPArchive\Tar();
    }

    function stepUnpack(Am_BatchProcessor $batch)
    {
        $upgrades = $this->getUpgrades();
        foreach ($upgrades as $k => & $upgrade)
        {
            $upgrade->dir = null;
            if (!empty($upgrade->dir)) continue; // already unpacked?
            $record = $this->getDi()->uploadTable->load($upgrade->upload_id);
            $fn = $record->getFullPath();
            $upgrade->dir = $this->getDi()->data_dir . DIRECTORY_SEPARATOR . $record->getFilename() . '-unpack';
            if (!mkdir($upgrade->dir))
            {
                throw new Am_Exception_InputError("Could not create folder to unpack downloaded archive: [{$upgrade->dir}]");
                unset($upgrade->dir);
            }
            try {
                $tar = $this->createPhpArchive($fn);
                $tar->open($fn);
                $tar->extract($upgrade->dir);
            } catch (Exception $e) {
                $this->getDi()->logger->error("Could not unpack archive", ["exception" => $e]);
                unset($upgrade->dir);
                @rmdir($upgrade->dir);
            }
            // normally we delete uploaded archive
            $record->delete();
            unset($upgrade->upload_id);
            $this->setUpgrades($upgrades);
        }
        return true;
    }

    function stepSetMaint()
    {
        $this->getSession()->maintenance_stored = $this->getDi()->config->get('maintenance');
        Am_Config::saveValue('maintenance', 'Briefly unavailable for scheduled maintenance. Check back in a minute.');
        return true;
        // make the string available for translation
        ___('Briefly unavailable for scheduled maintenance. Check back in a minute.');
    }

    function stepUnsetMaint()
    {
        Am_Config::saveValue('maintenance', @$this->getSession()->maintenance_stored);
        $this->getDi()->store->delete('upgrades-list');
        $this->getDi()->store->delete('do-upgardes-list');
        return true;
    }

    function stepCopy(Am_BatchProcessor $batch)
    {
        $connector = $this->getFileConnector();
        set_time_limit(600);
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
        $upgrades = $this->getUpgrades();
        foreach ($upgrades as $k => & $upgrade)
        {
            if (empty($upgrade->dir)) continue;

            $upgradePhp = $upgrade->dir . '/amember/_upgrade.php';
            if (file_exists($upgradePhp))
                require_once $upgradePhp;
            if (function_exists('_amemberBeforeUpgrade'))
                _amemberBeforeUpgrade($this, $connector, $upgrade);

            $dir = $upgrade->dir . DIRECTORY_SEPARATOR . 'amember' . DIRECTORY_SEPARATOR;
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir),
                RecursiveIteratorIterator::SELF_FIRST);
            foreach ($iterator as $file)
            {
                if ($file->getFileName() == '.' || $file->getFileName() == '..') continue;
                if ($file->getFileName() == '_upgrade.php') continue; // do not copy that run-once file
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
            rmdir($upgrade->dir);
            unset($upgrade->dir);
            $this->setUpgrades($upgrades);

            if (function_exists('_amemberAfterUpgrade'))
                _amemberAfterUpgrade($this, $connector, $upgrade);

            if (!$batch->checkLimits())
            {
//                $batch->stop();
//                return false;
            }
        }
        return true;
    }

    /**
     * disabled from 6.1.0
     */
    function stepAutoEnable()
    {
        return true;
    }

    function stepUpgradeDb()
    {
        ob_start();
        $this->getDi()->app->dbSync(true);
        $this->outText(ob_get_clean());
        return true;
    }

    function getUpgradeData()
    {
        $data = [];
        $data['beta'] = intval((@$_REQUEST['beta'] > 0) || (defined('AM_BETA') && AM_BETA));
        $data['am-version'] = AM_VERSION;
        $data['plugins'] = [];
        foreach ($this->getDi()->plugins as $type => $pm)
            foreach ($pm->getEnabled() as $v)
                $data['plugins'][$pm->getId()][$v] = true;
        $data['extensions'] = implode(',', get_loaded_extensions());
        $data['lang'] = implode(',', $this->getDi()->getLangEnabled(false));
        $data['php-version'] = PHP_VERSION;
        $data['mysql-version'] = $this->getDi()->db->selectCell("SELECT VERSION()");
        $data['root-url'] = $this->getDi()->url('');
        $data['root-surl'] = $this->getDi()->surl('');
        $data['license'] = $this->getConfig('license');
        return $data;
    }

    function loadUpgradesList($requireAuth = false)
    {
        try
        {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', AM_CHECK_UPGRADES_URL, [
                'form_params' => $this->getUpgradeData(),
                'timeout' => 15,
            ]);
        } catch (Exception $e) {
            $this->view->title = ___('Update Error');
            $this->view->content = ___('Could not fetch upgrades list from remote server. %sTry again%',
                '<a href="admin-upgrade">', '</a>');
            $this->view->display('admin/layout.phtml');
            return false;
        }

        $xml = new SimpleXMLElement($response->getBody());
        $ret = [];
        foreach ($xml->item as $u)
        {
            $el = new stdclass;
            foreach ($u->attributes() as $k => $v)
                $el->$k = (string)$v;
            $el->text = (string)$u;
            $el->text = strip_tags($el->text, '<li><ul><b><i><p><hr><br>');
            $ret[] = $el;
        }
        return $ret;
    }

}