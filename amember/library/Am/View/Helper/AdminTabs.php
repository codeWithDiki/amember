<?php

/**
 * View helper to display admin tabs
 * @package Am_View
 */
class Am_View_Helper_AdminTabs extends Zend_View_Helper_Abstract
{
    function adminTabs(Am_Navigation_Container $menu)
    {
        $m = new Am_View_Helper_Menu();
        $m->setView($this->view);
        $admin = $this->view->di->authAdmin->getUser();
        $toDelete = [];
        foreach (new RecursiveIteratorIterator($menu, RecursiveIteratorIterator::CHILD_FIRST) as $page) {
            /* @var $page Am_Navigation_Page */
            if ($resources = $page->getResource()) {
                $hasPermission = false;
                foreach ((array)$resources as $resource) {
                    if ($admin->hasPermission($resource, $page->getPrivilege()))
                        $hasPermission = true;
                }
                if (!$hasPermission) {
                    $toDelete[] = $page->getId();
                }
            }
        }
        foreach ($toDelete as $id) {
            $p = $menu->findOneById($id);
            $p->getParent()->removePage($p);
        }

        $out = '<div class="am-tabs-wrapper">'
            . $m->renderMenu($menu,
                    [
                        'ulClass' => 'am-tabs',
                        'activeClass' => 'active',
                        'normalClass' => 'normal',
                        'disabledClass' => 'disabled',
                        'maxDepth' => 1,
                    ]
                )
            . '</div>';
        return str_replace(['((', '))'], ['<span class="menu-item-alert">', '<span>'], $out);
    }
}