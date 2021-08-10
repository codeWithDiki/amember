<?php

class Aff_AdminDashboardController extends Am_Mvc_Controller_AdminDashboard
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
            new Am_AdminDashboardWidget('report-users', ___('Users Report'), [$this, 'renderWidgetReportUsers'], Am_AdminDashboardWidget::TARGET_ANY, null, Am_Auth_Admin::PERM_REPORT),
            new Am_AdminDashboardWidget('user-note', ___('Last User Notes'), [$this, 'renderWidgetUserNote'], Am_AdminDashboardWidget::TARGET_ANY, [$this, 'createWidgetUserNoteConfigForm'], 'grid_un'),
            new Am_AdminDashboardWidget('sales', ___('Sales Statistic'), [$this, 'renderWidgetSales'], Am_AdminDashboardWidget::TARGET_ANY, [$this, 'createWidgetSalesConfigForm'], Am_Auth_Admin::PERM_REPORT),
            new Am_AdminDashboardWidget('recurring-revenue', ___('Monthly Recurring Revenue'), [$this, 'renderWidgetRecurringRevenue'], Am_AdminDashboardWidget::TARGET_ANY, null, Am_Auth_Admin::PERM_REPORT),
            new Am_AdminDashboardWidget('revenue-goal', ___('Revenue Goal'), [$this, 'renderWidgetRevenueGoal'], Am_AdminDashboardWidget::TARGET_ANY, [$this, 'createWidgetRevenueGoalConfigForm'], Am_Auth_Admin::PERM_REPORT),
            new Am_AdminDashboardWidget('invoices', ___('Last Invoices List'), [$this, 'renderWidgetInvoices'], Am_AdminDashboardWidget::TARGET_ANY, [$this, 'createWidgetInvoicesConfigForm'], Am_Auth_Admin::PERM_REPORT),
            new Am_AdminDashboardWidget('aff-quick-start', ___('Quick Start'), [$this, 'renderWidgetAffQuickStart'], [Am_AdminDashboardWidget::TARGET_TOP])
        ];
    }

    function getPrefDefault()
    {
        return [
            'top' => ['aff-quick-start'],
            'bottom' => [],
            'main' => ['users', 'payments'],
            'aside' => ['sales', 'activity', 'report-users', 'user-note']
        ];
    }

    function getConfigPrefix()
    {
        return 'aff-';
    }

    function getControllerPath()
    {
        return 'aff/admin-dashboard';
    }

    function getMyWidgets()
    {
        $widgets = parent::getMyWidgets();
        return $widgets;

    }

    function renderWidgetAffQuickStart(Am_View $view, $config = null)
    {
        return $view->render('admin/aff/widget/quick-start.phtml');
    }

}