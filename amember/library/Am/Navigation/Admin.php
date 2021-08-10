<?php

/**
 * @package Am_Utils
 */
class Am_Navigation_Admin extends Am_Navigation_Container
{
    function addDefaultPages()
    {
        $this->addPage([
            'id' => 'dashboard',
            'controller' => 'admin',
            'label' => ___('Dashboard'),
            'class' => 'bold',
            'order' => 1,
        ]);

        $this->addPage(Am_Navigation_Page::factory([
            'id' => 'users',
            'uri' => '#',
            'label' => ___('Users'),
            'order' => 10,
            'pages' =>
            array_merge(
            [
                [
                    'id' => 'users-browse',
                    'controller' => 'admin-users',
                    'label' => ___('Browse Users'),
                    'resource' => 'grid_u',
                    'privilege' => 'browse',
                    'class' => 'bold',
                    'order' => 10
                ],
                [
                    'id' => 'users-insert',
                    'uri' => Am_Di::getInstance()->url('admin-users', ['_u_a'=>'insert']),
                    'label' => ___('Add User'),
                    'resource' => 'grid_u',
                    'privilege' => 'insert',
                    'order' => 20
                ],
            ],
            !Am_Di::getInstance()->config->get('manually_approve') ? [] : [
                [
                    'id' => 'user-not-approved',
                    'controller' => 'admin-users',
                    'action'     => 'not-approved',
                    'label' => ___('Not Approved Users'),
                    'resource' => 'grid_u',
                    'privilege' => 'browse',
                    'order' => 40
                ]
            ],
            !Am_Di::getInstance()->config->get('enable-account-delete') ? [] : [
                [
                    'id' => 'delete-requests',
                    'controller' => 'admin-delete-personal-data',
                    'action'     => 'index',
                    'label' => ___('Delete Requests'),
                    'resource' => 'grid_u',
                    'privilege' => 'delete',
                    'order' => 50
                ]
            ],
            [
                [
                    'id' => 'users-email',
                    'controller' => 'admin-email',
                    'label' => ___('E-Mail Users'),
                    'resource' => Am_Auth_Admin::PERM_EMAIL,
                    'order' => 60
                ],
                [
                    'id' => 'users-import',
                    'controller' => 'admin-import',
                    'label' => ___('Import Users'),
                    'resource' => Am_Auth_Admin::PERM_IMPORT,
                    'order' => 70
                ]
            ])
        ]));

        $this->addPage([
            'id' => 'reports',
            'uri' => '#',
            'label' => ___('Reports'),
            'order' => 20,
            'pages' => [
                [
                    'id' => 'reports-reports',
                    'controller' => 'admin-reports',
                    'label' => ___('Reports'),
                    'resource' => Am_Auth_Admin::PERM_REPORT,
                    'order' => 10,
                ],
                [
                    'id' => 'reports-payments',
                    'type' => 'Am_Navigation_Page_Mvc',
                    'controller' => 'admin-payments',
                    'label' => ___('Payments'),
                    'order' => 20,
                    'resource' => [
                        'grid_payment',
                        'grid_invoice'
                    ]
                ],
            ]
        ]);

        $this->addPage([
            'id' => 'products',
            'uri' => '#',
            'label' => ___('Products'),
            'order' => 30,
            'pages' => array_filter([
                [
                    'id' => 'products-manage',
                    'controller' => 'admin-products',
                    'label' => ___('Manage Products'),
                    'resource' => 'grid_product',
                    'class' => 'bold',
                    'order' => 10,
                ],
                [
                    'id' => 'products-coupons',
                    'controller' => 'admin-coupons',
                    'label' => ___('Coupons'),
                    'resource' => 'grid_coupon',
                    'order' => 20,
                ],
            ])
        ]);

/**
 *  Temporary disable this menu if user is on upgrade controller in order to avoid error:
 *  Fatal error: Class Folder contains 1 abstract method and must therefore be declared abstract or implement the remaining methods (ResourceAbstract::getAccessType)
 *
 *   @todo Remove this in the future;
 *
 */
        $content_pages = [];

        if(Am_Di::getInstance()->request->getControllerName() != 'admin-upgrade') {
            $order = 10;
            foreach (Am_Di::getInstance()->resourceAccessTable->getAccessTables() as $t)
            {
                $k = $t->getPageId();
                $content_pages[] = [
                    'id' => 'content-'.$k,
                    'module'    => 'default',
                    'controller' => 'admin-content',
                    'action' => 'index',
                    'label' => $t->getAccessTitle(),
                    'resource' => 'grid_' . $t->getPageId(),
                    'params' => [
                        'page_id' => $k,
                    ],
                    'route' => 'inside-pages',
                    'order' => $order,
                ];
                $order += 10;
            }
        }

        if (!Am_Di::getInstance()->config->get('disable_resource_category'))
        {
            $content_pages[] = [
                'id' => 'content-category',
                'module'    => 'default',
                'controller' => 'admin-resource-categories',
                'action' => 'index',
                'resource' => 'grid_content',
                'label' => ___('Content Categories'),
                'order' => 1000,
            ];
        }

        $this->addPage([
            'id' => 'content',
            'controller' => 'admin-content',
            'label' => ___('Protect Content'),
            'class' => 'bold',
            'order' => 40,
            'pages' => $content_pages,
        ]);

        $this->addPage([
            'id' => 'configuration',
            'uri' => '#',
            'label' => ___('Configuration'),
            'order' => 50,
            'pages' => array_filter([
                [
                    'id' => 'setup',
                    'controller' => 'admin-setup',
                    'label' => ___('Setup/Configuration'),
                    'resource' => Am_Auth_Admin::PERM_SETUP,
                    'class' => 'bold',
                    'order' => 10,
                ],
                [
                    'id' => 'add-ons',
                    'controller' => 'admin-plugins',
                    'label' => ___('Add-ons'),
                    'resource' => Am_Auth_Admin::PERM_SETUP,
                    'class' => 'bold',
                    'order' => 15,
                ],
                [
                    'id' => 'saved-form',
                    'controller' => 'admin-saved-form',
                    'label' => ___('Forms Editor'),
                    'resource' => @constant('Am_Auth_Admin::PERM_FORM'),
                    'class' => 'bold',
                    'order' => 20,
                ],
                (method_exists(Am_Di::getInstance()->theme, 'hasSetupForm') && Am_Di::getInstance()->theme->hasSetupForm()) ? [
                    'id' => 'theme',
                    'uri' => Am_Di::getInstance()->url("admin-setup/themes-" . Am_Di::getInstance()->theme->getId(), false),
                    'label' => ___('Appearance'),
                    'resource' => Am_Auth_Admin::PERM_SETUP,
                    'order' => 30,
                ] : null,
                [
                    'id' => 'agreement',
                    'controller' => 'admin-agreement',
                    'label' => ___('Agreement Documents'),
                    'resource' => 'grid_agreement',
                    'order' => 40,
                ],
                [
                    'id' => 'buy-now',
                    'controller' => 'admin-buy-now',
                    'label' => ___('BuyNow Buttons'),
                    'resource' => @constant('Am_Auth_Admin::PERM_SETUP'),
                    'order' => 50,
                ],
                [
                    'id' => 'fields',
                    'controller' => 'admin-fields',
                    'label' => ___('Add User Fields'),
                    'resource' =>  @constant('Am_Auth_Admin::PERM_ADD_USER_FIELD'),
                    'order' => 60,
                ],
                [
                    'id' => 'menu',
                    'controller' => 'admin-menu',
                    'label' => ___('User Menu'),
                    'resource' => Am_Auth_Admin::PERM_SETUP,
                    'order' => 70,
                ],
                [
                    'id' => 'email-template-layout',
                    'controller' => 'admin-email-template-layout',
                    'label' => ___('Email Layouts'),
                    'resource' =>  Am_Auth_Admin::PERM_SETUP,
                    'order' => 80,
                ],
                [
                    'id' => 'ban',
                    'controller'=> 'admin-ban',
                    'label' => ___('Blocking IP/E-Mail'),
                    'resource' => @constant('Am_Auth_Admin::PERM_BAN'),
                    'order' => 90,
                ],
                [
                    'id' => 'countries',
                    'controller' => 'admin-countries',
                    'label' => ___('Countries/States'),
                    'resource' => @constant('Am_Auth_Admin::PERM_COUNTRY_STATE'),
                    'order' => 100,
                ],
                [
                    'id' => 'admins',
                    'controller' => 'admin-admins',
                    'label' => ___('Admin Accounts'),
                    'resource' => Am_Auth_Admin::PERM_SUPER_USER,
                    'order' => 110,
                ],
                [
                    'id' => 'change-pass',
                    'controller' => 'admin-change-pass',
                    'label' => ___('Change Password'),
                    'order' => 120,
                ],
            ]),
        ]);

        $this->addPage([
            'id' => 'utilites',
            'uri' => '#',
            'label' => ___('Utilities'),
            'order' => 1000,
            'pages' => array_filter([
                Am_Di::getInstance()->modules->isEnabled('cc') ? null : [
                    'id' => 'backup',
                    'controller' => 'admin-backup',
                    'label' => ___('Backup'),
                    'resource' => Am_Auth_Admin::PERM_BACKUP_RESTORE,
                    'order' => 10,
                ],
                Am_Di::getInstance()->modules->isEnabled('cc') ? null : [
                    'id' => 'restore',
                    'controller' => 'admin-restore',
                    'label' => ___('Restore'),
                    'resource' => Am_Auth_Admin::PERM_BACKUP_RESTORE,
                    'order' => 20,
                ],
                [
                    'id' => 'rebuild',
                    'controller' => 'admin-rebuild',
                    'label' => ___('Rebuild Db'),
                    'resource' => @constant('Am_Auth_Admin::PERM_REBUILD_DB'),
                    'order' => 30,
                ],
                [
                    'id' => 'repair-tables',
                    'controller' => 'admin-repair-tables',
                    'label' => ___('Repair Db'),
                    'resource' => Am_Auth_Admin::PERM_SUPER_USER,
                    'order' => 40,
                ],
                [
                    'id' => 'logs',
                    'type' => 'Am_Navigation_Page_Mvc',
                    'controller' => 'admin-logs',
                    'label' => ___('Logs'),
                    'resource' => [
                        @constant('Am_Auth_Admin::PERM_LOGS'),
                        @constant('Am_Auth_Admin::PERM_LOGS_ACCESS'), // to avoid problems on upgrade!
                        @constant('Am_Auth_Admin::PERM_LOGS_INVOICE'),
                        @constant('Am_Auth_Admin::PERM_LOGS_MAIL'),
                        @constant('Am_Auth_Admin::PERM_LOGS_ADMIN'),
                    ],
                    'order' => 50,
                ],
                [
                    'id' => 'info',
                    'controller' => 'admin-info',
                    'label' => ___('System Info'),
                    'resource' => @constant('Am_Auth_Admin::PERM_SYSTEM_INFO'),
                    'order' => 60,
                ],
                [
                    'id' => 'trans-global',
                    'controller' => 'admin-trans-global',
                    'label' => ___('Edit Messages'),
                    'resource' => @constant('Am_Auth_Admin::PERM_TRANSLATION'),
                    'order' => 70,
                ],
//                (count(Am_Di::getInstance()->getLangEnabled(false)) > 1) ? array(
//                    'controller' => 'admin-trans-local',
//                    'label' => ___('Local Translations'),
//                    'resource' => Am_Auth_Admin::PERM_SUPER_USER,
//                ) : null,
                [
                    'id' => 'clear',
                    'controller' => 'admin-clear',
                    'label' => ___('Delete Old Records'),
                    'resource' => @constant('Am_Auth_Admin::PERM_CLEAR'),
                    'order' => 80,
                ],
                [
                    'id' => 'build-demo',
                    'controller' => 'admin-build-demo',
                    'label' => ___('Build Demo'),
                    'resource' => @constant('Am_Auth_Admin::PERM_BUILD_DEMO'),
                    'order' => 90,
                ],
            ]),
        ]);
        $this->addPage([
            'id' => 'help',
            'uri' => '#',
            'label' => ___('Help & Support'),
            'order' => 1001,
            'pages' => array_filter([
                [
                    'id' => 'documentation',
                    'uri' => 'http://www.amember.com/docs/',
                    'target' => '_blank',
                    'label'      => ___('Documentation'),
                    'resource' => @constant('Am_Auth_Admin::PERM_HELP'),
                    'order' => 10,
                ],
                [
                    'id' => 'support',
                    'uri' => 'https://www.amember.com/support/',
                    'target' => '_blank',
                    'label'      => ___('Support'),
                    'resource' => @constant('Am_Auth_Admin::PERM_HELP'),
                    'order' => 20,
                ],
                [
                    'id' => 'report-bugs',
                    'uri' => 'http://bt.amember.com/',
                    'target' => '_blank',
                    'label'      => ___('Report Bugs'),
                    'resource' => @constant('Am_Auth_Admin::PERM_HELP'),
                    'order' => 30,
                ],
                [
                    'id' => 'report-feature',
                    'uri' => 'http://bt.amember.com/',
                    'target' => '_blank',
                    'label' => ___('Suggest Feature'),
                    'resource' => @constant('Am_Auth_Admin::PERM_HELP'),
                    'order' => 40,
                ],
             ])
        ]);

        Am_Di::getInstance()->hook->call(Am_Event::ADMIN_MENU, ['menu' => $this]);

        /// workaround against using the current route for generating urls
        foreach (new RecursiveIteratorIterator($this, RecursiveIteratorIterator::SELF_FIRST) as $child)
            if ($child instanceof Am_Navigation_Page_Mvc && $child->getRoute()===null)
                $child->setRoute('default');
    }
}
