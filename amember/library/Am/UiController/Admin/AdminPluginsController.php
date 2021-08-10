<?php

class AdminPluginsController extends Am_Mvc_Controller
{
    use Am_Mvc_Controller_Upgrade_AmemberTokenTrait;


    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->isSuper();
    }

    public function preDispatch()
    {
        parent::preDispatch();
        //enable module without save to allow load plugins
        $modules = $this->getDi()->modules;
        if (!$modules->isEnabled('newsletter'))
        {
            $ret = $modules->addEnabled('newsletter');
            $modules->loadGet('newsletter')->setEnabledByAdminPlugins(true);
        }
        if (!$modules->isEnabled('newsletter'))
        {
            $ret = $modules->addEnabled('cc');
            $modules->loadGet('cc')->setEnabledByAdminPlugins(true);
        }
    }

    protected function getInstalledPluginsList(& $listToDisable = [])
    {
        $ignorePlugins = ['cc', 'newsletter', 'disk', 'upload'];

        $ret = [];
        foreach ($this->getDi()->plugins as $pm)
        {
            $enabled = $pm->getEnabled();
            foreach ($enabled as $_)
                if (!preg_match('#__\d+$#', $_)) $enabledNotFound[$_] = true;
            $enabledNotFound['free'] = false; // ignore

            foreach ($pm->getAvailable() as $pluginId => $desc)
            {
                $enabledNotFound[$pluginId] = false;

                if (in_array($pluginId, $ignorePlugins)) continue;

                $annotation = $pm->getParsedAnnotations($pluginId);
                // add necessary fields
                $defaults = [
                    'id' => $pluginId,
                    'title' => ucwords(str_replace('-', ' ', $pluginId)),
                    'long_images' => [],
                    'desc' => "",
                    'long_desc' => "",
                    'price' => null,
                    'img' => null,
                    'url' => null,
                    'tags' => [],
                    'categories' => [],
                    'instances' => null,
                ];
                foreach ($defaults as $k=>$v)
                    if (empty($annotation[$k]))
                        $annotation[$k] = $v;
                $annotation['setup_url'] = null;
                if ($annotation['is_enabled'] = in_array($pluginId, $enabled))
                    $annotation['setup_url'] = $pm->findSetupUrl($pluginId);
                if (!empty($annotation['logo_url']))
                    $annotation['img'] = "https://cdn.amember.com/public/images/paysystems/{$annotation['logo_url']}";
                $annotation['is_installed'] = true;
                $ret[ $annotation['name'] ] = $annotation;

                $countEnabled = 0;
                $countInstances = 0;
                foreach ($pm->getInstances($pluginId) as $id => $rec)
                {
                    $num = 0;
                    if (preg_match('#__(\d+)$#', $id, $regs))
                        $num = $regs[1];
                    if ($num != 0) $countInstances++;
                    $ret[ $annotation['name'] ]['instances'][] = [
                        'title' => $annotation['title'] . ':' . $num,
                        'name' => $id,
                        'is_enabled' => $rec['is_enabled'],
                        'setup_url' => $pm->findSetupUrl($id),
                    ];
                    $countEnabled += (int)$rec['is_enabled'];
                }
                if ($countEnabled)
                    $ret[ $annotation['name'] ]['is_enabled'] = true;
                if (!$countInstances )
                    $ret[ $annotation['name'] ]['instances'] = null; // only core plugin enabled
            }
            foreach (array_filter($enabledNotFound) as $id => $_)
            {
                try{
                    $pm->get($id);
                    // We can get plugin, probably it was loaded different way. So do not show warning.
                    unset($enabledNotFound[$id]);
                    continue;
                }catch(Exception $e){
                
                }
                $listToDisable[] = $pm->getId() . ":" . $id;
            }
        }
        return $ret;
    }

    public function indexAction()
    {
        if (empty($this->getSession()->csrf))
            $this->getSession()->csrf = $this->getDi()->security->randomString(8);

        $amemberSiteResources = $this->fetchAmemberResourcesWithErrorHandling($tokenValid);

        $this->view->assign('amemberSiteResources', json_encode($amemberSiteResources));
        $this->view->assign('loggedIn', (bool)((bool)$amemberSiteResources && $tokenValid));

        if (AM_APPLICATION_ENV != 'demo')
        {
            $this->view->assign('loginLink',
                $this->getOauthLoginLink($this->getDi(), $this->getDi()->url('admin-plugins', false), false));
            $this->view->assign('logoutLink',
                $this->getOauthLogoutLink($this->getDi(), $this->getDi()->url('admin-plugins', false), false));
        } else {
            $this->view->assign('loginLink', 'javascript:alert("Sorry, this function is disabled in demo")');
            $this->view->assign('logoutLink', 'javascript:alert("Sorry, this function is disabled in demo")');
        }

        $listDoDisable = [];
        $this->view->assign('installedPlugins', $this->getInstalledPluginsList($listDoDisable));
        $this->view->assign('listToDisable', $listDoDisable);

        $this->view->assign('csrf', $this->getSession()->csrf);

        $this->view->display("admin/plugins.phtml");
    }

    public function activateAction()
    {
        if (!$this->validateAjaxCsrf()) return;

        if (defined('AM_SAFE_MODE') && AM_SAFE_MODE)
            return $this->_response->ajaxResponse(['status' => 'safe_mode', 'message' => 'Plugin activation is disabled in aMember "Safe Mode"']);

        $type = $this->_request->getFiltered('type');
        $name = $this->_request->getFiltered('name');

        $plugins = $this->getDi()->plugins;
        if (!$plugins->offsetExists($type))
        {
            return $this->_response->ajaxResponse(['status' => 'unknown_type', 'message' => 'Unknown type']);
        }

        $pm = $plugins[$type];
        if ($pm->activate($name, Am_Plugins::ERROR_LOG, true))
        {
            $name = preg_replace('#__\d+$#', '', $name);
            $list = $this->getInstalledPluginsList();
            return $this->_response->ajaxResponse(['status' => 'OK', 'product' => $list[$name]]);
        } else {
            return $this->_response->ajaxResponse(['status' => 'activation_error', 'message' => 'Activation error, check for details in logs']);
        }
    }

    public function deactivateAction()
    {
        if (!$this->validateAjaxCsrf()) return;

        $type = $this->_request->getFiltered('type');
        $name = $this->_request->getFiltered('name');
        $clean = $this->_request->getInt('clean');
        /**
         * @var ArrayObject $plugins;
         */
        $plugins = $this->getDi()->plugins;
        if (!$plugins->offsetExists($type))
        {
            return $this->_response->ajaxResponse(['status' => 'unknown_type', 'message' => 'Unknown type']);
        }

        if ($plugins[$type]->deactivate($name, Am_Plugins::ERROR_LOG, true, $clean))
        {
            $name = preg_replace('#__\d+$#', '', $name);
            $list = $this->getInstalledPluginsList();
            return $this->_response->ajaxResponse(['status' => 'OK', 'product' => $list[$name]]);
        } else {
            return $this->_response->ajaxResponse(['status' => 'deactivation_error', 'message' => 'Deactivation error, check for details in logs']);
        }
    }

    public function duplicateAction()
    {
        if (!$this->validateAjaxCsrf()) return;

        $type = $this->_request->getFiltered('type');
        $name = $this->_request->getFiltered('name');
        $plugins = $this->getDi()->plugins;
        if (!$plugins->offsetExists($type))
        {
            return $this->_response->ajaxResponse(['status' => 'unknown_type', 'message' => 'Unknown type']);
        }
        $en = $plugins[$type]->getEnabled();
        if ($plugins[$type]->duplicate($name, Am_Plugins::ERROR_LOG, true))
        {
            $name = preg_replace('#__\d+$#', '', $name);
            $list = $this->getInstalledPluginsList();
            return $this->_response->ajaxResponse(['status' => 'OK', 'product' => $list[$name]]);
        } else {
            return $this->_response->ajaxResponse(['status' => 'duplicate_error', 'message' => 'Could not enable plugin copy, check for details in logs']);
        }
    }

    protected function doAmemberRequest($req, $url)
    {
        check_demo();
        try
        {
            $this->loadAmemberToken(true);
            $request = $this->amemberGetAuthenticatedRequest('POST',
                $url);
        } catch (Exception $e) {
            $this->_response->ajaxResponse(['status'=>'OK', 'message' => 'Could not load request data']);
            return false;
        }
        $client = new GuzzleHttp\Client();
        $resp = $client->send($request, [
            'http_errors' => false,
            'body' => http_build_query($req),
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
        ]);
        if ($resp->getStatusCode() != 200)
        {
            $data = json_decode($resp->getBody(), true);
            $this->_response->ajaxResponse([
                'ok' => false,
                'message' => 'Request to aMember website failed: ' . @$data['message']
            ]);
            return false;
        }

        return json_decode($resp->getBody(), true);
    }

    public function checkoutAction()
    {
        check_demo();
        if (!$this->validateAjaxCsrf()) return;

        $req = [];
        $ids = $this->getRequest()->getParam('product_id');
        $req['product_id'] = array_filter(array_map('intval', $ids));
        if (!$req['product_id'])
            return $this->_response->ajaxResponse(['status' => 'OK', 'message' => 'No products selected for purchase']);
        $req['coupon'] = trim($this->getRequest()->getFiltered('coupon'));
        $req['return_url'] = $this->getDi()->url('admin-plugins', false);

        $ret = $this->doAmemberRequest($req, 'https://www.amember.com/amember/api2/cgi-plugin-order');
        if ($ret === false)
            return; // response already sent

        $data = [
            'status' => 'OK',
            'redirect_url' =>  $ret['data']['redirect_url'],
        ];
        $this->_response->ajaxResponse($data);
    }

    public function addFreeAction()
    {
        check_demo();
        if (!$this->validateAjaxCsrf()) return;

        $req = [];
        $ids = $this->getRequest()->getParam('product_id');
        $req['product_id'] = array_filter(array_map('intval', $ids));
        if (!$req['product_id'])
            return $this->_response->ajaxResponse(['status' => 'OK', 'message' => 'No products selected for purchase']);

        $ret = $this->doAmemberRequest($req, 'https://www.amember.com/amember/api2/cgi-plugin-free');
        if ($ret === false)
            return; // response already sent

        $data = $ret['data'];
        if ($ret['status'] == 'OK')
        {
            $this->storeAmemberSiteResources($ret['data']);
        }
        $data['status'] = 'OK';
        if (empty($data['am_subscriptions']))
            $data['am_subscriptions'] = ['_' => '_'];

        $this->_response->ajaxResponse($data);
    }

    public function getPluginsList()
    {
        $_ = [];
        foreach ($this->getDi()->plugins as $k => $pm)
            $_ = array_merge($_, $pm->getEnabled());
        return $_;
   }

   protected function validateAjaxCsrf()
   {
        if ($this->getParam('csrf') != $this->getSession()->csrf)
        {
           $this->_response->ajaxResponse(['status' => 'csrf_failed', 'message' => 'Incorrect csrf code']);
           return false;
        }
        return true;
   }

    public function callbackAction()
    {
        check_demo();
        $this->runOauthCallback($this->getDi());
    }

    public function logoutAction()
    {
        check_demo();
        $this->runOauthLogout($this->getDi());
    }
}