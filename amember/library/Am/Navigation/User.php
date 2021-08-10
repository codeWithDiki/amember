<?php

___('Add/Renew Subscription');
___('Profile');

/**
 * User menu at top of member controller
 * @package Am_Utils
 */
class Am_Navigation_User extends Am_Navigation_Container
{
    function addDefaultPages()
    {
        try {
            $user = Am_Di::getInstance()->user;
        } catch (Am_Exception_Db_NotFound $e) {
            $user = null;
        }

        list($config, $items) = self::getNavigation();
        $this->addItems($this, $user, $config, $items);

        Am_Di::getInstance()->hook->call(Am_Event::USER_MENU, [
            'menu' => $this,
            'user' => $user
        ]);

        /// workaround against using the current route for generating urls
        foreach (new RecursiveIteratorIterator($this, RecursiveIteratorIterator::SELF_FIRST) as $child)
            if ($child instanceof Am_Navigation_Page_Mvc && $child->getRoute()===null)
                $child->setRoute('default');
    }

    function addMenuPages($menu_id)
    {
        $user = Am_Di::getInstance()->auth->getUser();

        list($config, $items) = self::getNavigation($menu_id);
        $this->addItems($this, $user, $config, $items);

        /// workaround against using the current route for generating urls
        foreach (new RecursiveIteratorIterator($this, RecursiveIteratorIterator::SELF_FIRST) as $child)
            if ($child instanceof Am_Navigation_Page_Mvc && $child->getRoute()===null)
                $child->setRoute('default');
    }

    protected function addItems($nav, $user, $config, $items)
    {
        $order = 100;
        foreach ($config as $item) {
            if (!array_key_exists($item['id'], $items) || !is_callable($items[$item['id']])) continue;

            $page = call_user_func($items[$item['id']], $nav, $user, $order, (isset($item['config']) ? $item['config'] : []) ) ?: $this;
            if (isset($item['items']) && $item['items']) {
                $this->addItems($page, $user, $item['items'], $items);
            }
            $order += 100;
        }
    }

    static function getNavigation($menu_id = null)
    {
        $items = self::getUserNavigationItems();

        if (!empty($menu_id)) {
            //we do not need any default items for custom menus
            $config = json_decode(Am_Di::getInstance()->config->get("user_menu{$menu_id}", '[]'), true);
            return [$config, $items];
        }

        $items_ids = array_keys($items);
        $seen_before = array_merge((Am_Di::getInstance()->config->get('user_menu_seen') ?: []),
            [
                'custom-link', 'link', 'page', 'folder',
                'signup-form', 'profile-form', 'container',
                'resource-categories', 'payment-history',
                'login-logout',
            ]);

        $new_items = array_map(function($el) {return ['id' => $el];}, array_diff($items_ids, $seen_before));

        if (!is_null(Am_Di::getInstance()->config->get('user_menu'))) {
            $config = json_decode(Am_Di::getInstance()->config->get('user_menu'), true);
        } else {
            $config = self::getDefaultNavigation();
        }

        $config += $new_items;

        return [$config, $items];
    }

    static function getDefaultNavigation()
    {
        $items = [];

        $items[] = ['id' => 'dashboard', 'name' => 'Dashboard'];
        $f = Am_Di::getInstance()->savedFormTable->getDefault(SavedForm::D_MEMBER);
        $items[] = ['id' => 'signup-form', 'name' => $f->title, 'config' => ['id' => $f->pk()]];
        $f = Am_Di::getInstance()->savedFormTable->getDefault(SavedForm::D_PROFILE);
        $items[] = ['id' => 'profile-form', 'name' => $f->title, 'config' => ['id' => $f->pk()]];
        $items[] = ['id' => 'resource-categories', 'name' => 'Resource Categories Menu'];

        return $items;
    }

    static function getUserNavigationItems()
    {
        return Am_Di::getInstance()->hook->filter([
            'dashboard' => [__CLASS__, 'buildDashboard'],
            'payment-history' => [__CLASS__, 'buildPaymentHistory'],
            'link' => [__CLASS__, 'buildLink'],
            'page' => [__CLASS__, 'buildPage'],
            'folder' => [__CLASS__, 'buildFolder'],
            'signup-form' => [__CLASS__, 'buildSignupForm'],
            'profile-form' => [__CLASS__, 'buildProfileForm'],
            'resource-categories' => [__CLASS__, 'buildResourceCategories'],
            'custom-link' => [__CLASS__, 'buildCustomLink'],
            'container' => [__CLASS__, 'buildContainer'],
            'directory' => ['Bootstrap_Directory', 'buildMenu'],
            'login-logout' => [__CLASS__, 'buildLoginLogout'],
        ], Am_Event::USER_MENU_ITEMS);
    }

    static function buildLoginLogout(Am_Navigation_Container $nav, /* ?User */ $user, $order, $config)
    {
        return $user ?
            $nav->addPage([
                'id' => 'logout',
                'controller' => 'login',
                'action' => 'logout',
                'route' => 'user-logout',
                'label' => ___('Logout'),
                'title' => ___('Logout'),
                'order' => $order
            ], true) :
            $nav->addPage([
                'id' => 'login',
                'controller' => 'login',
                'label' => ___('Login'),
                'title' => ___('Login'),
                'order' => $order
            ], true);

    }

    static function buildDashboard(Am_Navigation_Container $nav, /* ?User */ $user, $order, $config)
    {
        if (!$user) return;

        return $nav->addPage([
            'id' => 'member',
            'controller' => 'member',
            'label' => ___('Dashboard'),
            'title' => ___('Dashboard'),
            'order' => $order
        ], true);
    }

    static function buildPaymentHistory(Am_Navigation_Container $nav, /* ?User */ $user, $order, $config)
    {
        if (!$user) return;

        return $nav->addPage([
            'id' => 'payment-history',
            'controller' => 'member',
            'action' => 'payment-history',
            'label' => ___('Payment History'),
            'order' => $order
        ], true);
    }

    static function buildCustomLink(Am_Navigation_Container $nav, /* ?User */ $user, $order, $config)
    {
        if (isset($config['uri']) && $config['uri']) {
            return $nav->addPage([
                'id' => 'custom-link-' . substr(crc32($config['uri']), 0, 8),
                'uri' => $config['uri'],
                'label' => ___($config['label']),
                'order' => $order,
                'target' => isset($config['target']) ? $config['target'] : null,
            ], true);
        }
    }

    static function buildLink(Am_Navigation_Container $nav, /* ?User */ $user, $order, $config)
    {
        if (isset($config['id']) && $link = Am_Di::getInstance()->linkTable->load($config['id'], false)) {
            if ($link->hasAccess($user)) {
                return $nav->addPage([
                    'id' => 'link-' . $link->pk(),
                    'uri' => $link->getUrl(),
                    'label' => ___($link->title),
                    'order' => $order
                ], true);
            }
        }
    }

    static function buildPage(Am_Navigation_Container $nav, /* ?User */ $user, $order, $config)
    {
        if (isset($config['id']) && $page = Am_Di::getInstance()->pageTable->load($config['id'], false)) {
            if ($page->hasAccess($user)) {
                return $nav->addPage([
                    'id' => 'page-' . $page->pk(),
                    'uri' => $page->getUrl(),
                    'label' => ___($page->title),
                    'order' => $order
                ], true);
            }
        }
    }

    static function buildFolder(Am_Navigation_Container $nav, /* ?User */ $user, $order, $config)
    {
        if (isset($config['id']) && $folder = Am_Di::getInstance()->folderTable->load($config['id'], false)) {
            if ($folder->hasAccess($user)) {
                return $nav->addPage([
                    'id' => 'folder-' . $folder->pk(),
                    'uri' => $folder->getUrl(),
                    'label' => ___($folder->title),
                    'order' => $order
                ], true);
            }
        }
    }

    static function buildSignupForm(Am_Navigation_Container $nav, /* ?User */ $user, $order, $config)
    {
        if (isset($config['id']) && $f = Am_Di::getInstance()->savedFormTable->load($config['id'], false)) {
            $params = $f->isDefault(SavedForm::D_MEMBER) ? [] : ['c' => $f->code];
            return $nav->addPage([
                'id' => 'add-renew-' . ($f->code ? $f->code : 'default'),
                'label' => ___($f->title),
                'controller' => 'signup',
                'action' => 'index',
                'route' => 'signup',
                'order' => $order,
                'params' => $params
            ], true);
        }
    }

    static function buildProfileForm(Am_Navigation_Container $nav, /* ?User */ $user, $order, $config)
    {
        if (isset($config['id']) && $f = Am_Di::getInstance()->savedFormTable->load($config['id'], false)) {
            $params = $f->isDefault(SavedForm::D_PROFILE) ? [] : ['c' => $f->code];
            return $nav->addPage([
                'id' => 'profile-' . ($f->code ? $f->code : 'default'),
                'label' => ___($f->title),
                'controller' => 'profile',
                'route' => 'profile',
                'order' => $order,
                'params' => $params
            ], true);
        }
    }

    static function buildResourceCategories(Am_Navigation_Container $nav, /* ?User */ $user, $order, $config)
    {
        if (!$user) return;

        $tree = Am_Di::getInstance()->resourceCategoryTable->getAllowedTree($user);
        $pages = [];
        foreach ($tree as $node) {
            $pages[] = self::getContentCategoryPage($node, $order);
        }

        if (count($pages))
            $nav->addPages($pages);
    }

    static protected function getContentCategoryPage($node, $order)
    {
        $subpages = [];
        foreach ($node->getChildNodes() as $n) {
            $subpages[] = self::getContentCategoryPage($n, 0);
        }

        $page = [
            'id' => 'content-category-' . $node->pk(),
            'route' => 'content-c',
            'controller' => 'content',
            'action' => 'c',
            'label' => ___($node->title),
            'order' => 0,
            'params' => [
                'id' => $node->pk(),
                'title' => $node->title
            ]
        ];

        $container = [
            'id' => 'content-category-' . $node->pk(),
            'uri' => 'javascript:;',
            'label' => ___($node->title),
            'order' => $order + $node->sort_order
        ];


        if ($subpages) {
            $container['pages'] = array_merge($node->self_cnt ? [$page] : [], $subpages);
        } else {
            $page['order'] = $order;
            $container = $page;
        }

        return $container;
    }

    static function buildPages(Am_Navigation_Container $nav, /* ?User */ $user, $order, $config)
    {
        if (!$user) return;

        $sort = 0;
        $pages = Am_Di::getInstance()->resourceAccessTable
            ->getAllowedResources($user, ResourceAccess::PAGE);
        foreach ($pages as $p) {
            if ($p->onmenu) {
                $nav->addPage([
                    'id' => 'page-' . $p->pk(),
                    'uri' => $p->getUrl(),
                    'label' => ___($p->title),
                    'order' => $order + $sort++
                ]);
            }
        }
    }

    static function buildLinks(Am_Navigation_Container $nav, /* ?User */ $user, $order, $config)
    {
        if (!$user) return;

        $links = Am_Di::getInstance()->resourceAccessTable
                    ->getAllowedResources($user, ResourceAccess::LINK);
        foreach ($links as $l) {
            if ($l->onmenu) {
                $nav->addPage([
                    'id' => 'link-' . $l->pk(),
                    'uri' => $l->getUrl(),
                    'label' => ___($l->title),
                    'order' => $order + $sort++
                ]);
            }
        }
    }

    static function buildContainer(Am_Navigation_Container $nav, /* ?User */ $user, $order, $config)
    {
        if (isset($config['label'])) {
            return $nav->addPage([
                'id' => 'container-' . substr(md5($config['label']),0,6),
                'uri' => 'javascript:;',
                'label' => ___($config['label']),
                'order' => $order,
            ], true);
        }
    }
}