<?php

class AdminMenuController extends Am_Mvc_Controller
{
    function checkAdminPermissions(Admin $admin)
    {
        return $admin->hasPermission(Am_Auth_Admin::PERM_SETUP);
    }

    function indexAction()
    {
        $menu_id = strval($this->getParam('menu_id')) ?: null;

        list($items, $item_desc) = Am_Navigation_User::getNavigation($menu_id);

        $special_items = [
            'dashboard' => [
                'title' => ___('Dashboard'),
                'desc' => ___('Dashboard Icon')
            ],
            'payment-history' => [
                'title' => ___('Payment History'),
                'desc' => ___("Page with list of user's payments")
            ],
            'resource-categories' => [
                'title' => ___('Resource Categories Menu'),
                'desc' => ___("Add Menu Items for each Resource Category that user has access to")
            ],
            'login-logout' => [
                'title' => ___('Login/Logout Link'),
                'desc' => ___("Add Login/Logout link based on user authentication status")
            ],
        ];

        foreach ($this->getDi()->hook->filter([], Am_Event::USER_MENU_ITEMS) as $id => $cb) {
            $special_items[$id] = [
                'title' => ucwords(str_replace('-', ' ', $id)) . ' Menu',
                'desc' => ''
            ];
        }

        if ($this->getRequest()->isPost()) {
            Am_Config::saveValue("user_menu{$menu_id}", $this->getParam('user_menu'));

            if (empty($menu_id)) {
                $seen_before = $this->getDi()->config->get('user_menu_seen') ?: [];
                $item_ids = array_keys($item_desc);
                Am_Config::saveValue('user_menu_seen', array_merge($seen_before, $item_ids));
            }
            Am_Mvc_Response::redirectLocation($this->url('admin-menu', ['menu_id' => $menu_id], false));
        }

        $v = $this->getDi()->view;
        $v->special_items = $special_items;
        $v->items = array_values($items);
        $v->user = $this->getDi()->userTable->findFirstBy();
        $v->menu_id = $menu_id;
        $v->display('admin/menu.phtml');
    }

    function previewAction()
    {
        $menu_id = strval($this->getParam('menu_id')) ?: null;

        $this->getDi()->config->set('user_menu_seen', array_keys(Am_Navigation_User::getUserNavigationItems()));
        $this->getDi()->config->set("user_menu", $this->getParam('user_menu'));
        //we do not want to start real authenticated session
        $this->getDi()->auth->_setUser($this->getDi()->userTable->load((int)$this->getParam('user_id')));
        $this->getDi()->view->display('member/_menu.phtml');
    }

    function resetAction()
    {
        $menu_id = strval($this->getParam('menu_id')) ?: null;

        if (empty($menu_id)) {
            Am_Config::saveValue('user_menu', null);
            Am_Config::saveValue('user_menu_seen', null);
        } else {
            Am_Config::saveValue("user_menu{$menu_id}", null);
        }
        Am_Mvc_Response::redirectLocation($this->url('admin-menu', ['menu_id' => $menu_id], false));
    }
}