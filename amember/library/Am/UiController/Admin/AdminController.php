<?php

class AdminController extends Am_Mvc_Controller_AdminDashboard
{
    protected $template = 'admin/index.phtml';

    function getDefaultWidgets()
    {
        return [
            new Am_AdminDashboardWidget('activity', ___('Recent Activity'), [$this, 'renderWidgetActivity'], Am_AdminDashboardWidget::TARGET_ANY, [$this, 'createWidgetActivityConfigForm'], 'grid_payment'),
            new Am_AdminDashboardWidget('users', ___('Last Users List'), [$this, 'renderWidgetUsers'], Am_AdminDashboardWidget::TARGET_ANY, [$this, 'createWidgetUsersConfigForm'], 'grid_u'),
            new Am_AdminDashboardWidget('user_logins', ___('Last User Logins List'), [$this, 'renderWidgetUserLogins'], Am_AdminDashboardWidget::TARGET_ANY, [$this, 'createWidgetUserLoginsConfigForm'], 'grid_u'),
            new Am_AdminDashboardWidget('payments', ___('Last Payments List'), [$this, 'renderWidgetPayments'], Am_AdminDashboardWidget::TARGET_ANY, [$this, 'createWidgetPaymentsConfigForm'], 'grid_payment'),
            new Am_AdminDashboardWidget('prefunds', ___('Last Refunds List'), [$this, 'renderWidgetRefunds'], Am_AdminDashboardWidget::TARGET_ANY, [$this, 'createWidgetRefundsConfigForm'], 'grid_payment'),
            new Am_AdminDashboardWidget('report-users', ___('Users Report'), [$this, 'renderWidgetReportUsers'], Am_AdminDashboardWidget::TARGET_ANY, [$this, 'createWidgetReportUsersConfigForm'], Am_Auth_Admin::PERM_REPORT),
            new Am_AdminDashboardWidget('user-note', ___('Last User Notes'), [$this, 'renderWidgetUserNote'], Am_AdminDashboardWidget::TARGET_ANY, [$this, 'createWidgetUserNoteConfigForm'], 'grid_un'),
            new Am_AdminDashboardWidget('sales', ___('Sales Statistic'), [$this, 'renderWidgetSales'], Am_AdminDashboardWidget::TARGET_ANY, [$this, 'createWidgetSalesConfigForm'], Am_Auth_Admin::PERM_REPORT),
            new Am_AdminDashboardWidget('recurring-revenue', ___('Monthly Recurring Revenue'), [$this, 'renderWidgetRecurringRevenue'], Am_AdminDashboardWidget::TARGET_ANY, null, Am_Auth_Admin::PERM_REPORT),
            new Am_AdminDashboardWidget('revenue-goal', ___('Revenue Goal'), [$this, 'renderWidgetRevenueGoal'], Am_AdminDashboardWidget::TARGET_ANY, [$this, 'createWidgetRevenueGoalConfigForm'], Am_Auth_Admin::PERM_REPORT),
            new Am_AdminDashboardWidget('invoices', ___('Last Invoices List'), [$this, 'renderWidgetInvoices'], Am_AdminDashboardWidget::TARGET_ANY, [$this, 'createWidgetInvoicesConfigForm'], Am_Auth_Admin::PERM_REPORT),
            new Am_AdminDashboardWidget('quick-start', ___('Quick Start'), [$this, 'renderWidgetQuickStart'], [Am_AdminDashboardWidget::TARGET_TOP]),
            new Am_AdminDashboardWidget('email', ___('Last Emails List'), [$this, 'renderWidgetEmail'], Am_AdminDashboardWidget::TARGET_ANY, [$this, 'createWidgetEmailConfigForm'], Am_Auth_Admin::PERM_LOGS_MAIL),
        ];
    }

    function getPrefDefault()
    {
        return [
            'top' => ['quick-start'],
            'bottom' => [],
            'main' => ['users', 'payments'],
            'aside' => ['sales', 'activity', 'report-users', 'user-note'],
        ];
    }

    function getConfigPrefix()
    {
        return '';
    }

    function getControllerPath()
    {
        return 'admin';
    }

    function getMyWidgets()
    {
        $widgets = parent::getMyWidgets();
        array_unshift($widgets['top'],
            new Am_AdminDashboardWidget('warnings', ___('Warnings'), [$this, 'renderWidgetWarnings'], ['top']));
        return $widgets;
    }

    public function preDispatch()
    {
        $db_version = $this->getDi()->store->get('db_version');
        if (empty($db_version)) {
            $this->getDi()->store->set('db_version', AM_VERSION);
        } elseif ($db_version != AM_VERSION) {
            $this->_response->redirectLocation($this->getDi()->url('admin-upgrade-db', false));
        }
        parent::preDispatch();
    }

    function disableMaintenanceAction()
    {
        Am_Config::saveValue('maintenance', '');
        return Am_Mvc_Response::redirectLocation($this->getDi()->url('admin', false));
    }
}