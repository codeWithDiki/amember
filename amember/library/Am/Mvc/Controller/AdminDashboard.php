<?php

abstract class Am_Mvc_Controller_AdminDashboard extends Am_Mvc_Controller
{
    public function checkAdminPermissions(Admin $admin)
    {
        return true;
    }

    public function preDispatch()
    {
        parent::preDispatch();
        $this->view->controllerPath = $this->getControllerPath();
    }

    abstract function getDefaultWidgets();
    abstract function getControllerPath();

    function getSavedReportWidgets()
    {
        $res = [];
        foreach ($this->getDi()->savedReportTable->findByAdminId($this->getDi()->authAdmin->getUser()->pk()) as $report) {
            $res[] = new Am_AdminDashboardWidget(
                'saved-report-' . $report->pk(),
                $report->title,
                [$this, 'renderWidgetReport'],
                Am_AdminDashboardWidget::TARGET_ANY,
                [$this, 'createWidgetReportConfigForm'],
                Am_Auth_Admin::PERM_REPORT,
                ['savedReport' => $report]
            );
        }
        return $res;
    }

    function getAvailableWidgets()
    {
        $event = new Am_Event(Am_Event::LOAD_ADMIN_DASHBOARD_WIDGETS, ['controller' => $this,]);
        $this->getDi()->hook->call($event);

        $widgets = [];
        foreach (array_merge($this->getDefaultWidgets(),
                    $this->getSavedReportWidgets(),
                    $event->getReturn()) as $widget) {

            $widgets[$widget->getId()] = $widget;
        }
        return $widgets;
    }

    /**
     * Retrieve widget by id
     *
     * @param string $id
     * @return Am_AdminDashboardWidget
     */
    function getWidget($id)
    {
        $availableWidgets = $this->getAvailableWidgets();
        return isset($availableWidgets[$id])  ? $availableWidgets[$id] : null;
    }

    function customizeDashboardAction()
    {
        $widgets = $this->getAvailableWidgets();
        foreach ($widgets as $k => $widget) {
            if (!$widget->hasPermission($this->getDi()->authAdmin->getUser())) {
                unset($widgets[$k]);
            }
        }
        $this->view->enableReports();
        $this->view->widgets = $widgets;
        $this->view->config = $this->getWidgetConfig();
        $this->view->pref = $this->getPref();
        $this->view->display('admin/customize.phtml');
    }

    function getWidgetConfigFormAction()
    {
        $id = $this->getRequest()->getParam('id');
        $widget = $this->getWidget($id);
        if (!$widget) throw new Am_Exception_InputError(___('Unknown Widget with Id [%s]', $id));
        if (!$widget->hasConfigForm()) throw new Am_Exception_InputError(___('Widget with Id [%s] has not config form', $id));

        $form = $widget->getConfigForm();
        $config = $this->getWidgetConfig($widget->getId());
        if ($config) {
            $form->setDataSources([new HTML_QuickForm2_DataSource_Array($config)]);
        }

        echo $form;
    }

    abstract function getConfigPrefix();

    abstract function getPrefDefault();

    protected function getPref()
    {
        $pref = $this->getDi()->authAdmin->getUser()->getPref($this->getConfigPrefix() . Admin::PREF_DASHBOARD_WIDGETS);
        return is_null($pref) ? $this->getPrefDefault() : $pref;
    }

    protected function getWidgetConfig($widget_id = null)
    {
        $config = $this->getDi()->authAdmin->getUser()->getPref($this->getConfigPrefix() . Admin::PREF_DASHBOARD_WIDGETS_CONFIG);

        if (is_null($widget_id)) return $config;
        return isset($config[$widget_id]) ? $config[$widget_id] : null;
    }

    function saveDashboardAction()
    {
        if ($this->getRequest()->isPost()) {
            $this->getDi()->authAdmin->getUser()
                ->setPref($this->getConfigPrefix() . Admin::PREF_DASHBOARD_WIDGETS, $this->getRequest()->getParam('pref', []));
        }
    }

    function saveDashboardConfigAction()
    {
        if ($this->getRequest()->isPost()) {
            /* @var $widget Am_AdminDashboardWidget */
            $widget = $this->getWidget($this->getRequest()->getParam('id'));
            /* @var $form Am_Form */
            $form = $widget->getConfigForm();
            if (!$form)
                throw new Am_Exception_InputError(___('Can not save config for dashboard widget without config form [%s]',
                    $this->getRequest()->getParam('id')));

            $form->setDataSources([$this->getRequest()]);

            $config = $this->getDi()->authAdmin->getUser()->getPref($this->getConfigPrefix() . Admin::PREF_DASHBOARD_WIDGETS_CONFIG, []);
            if ($form->validate()) {
                $vars = $form->getValue();
                unset($vars['id']);
                $config[$widget->getId()] = $vars;

                $this->getDi()->authAdmin->getUser()->setPref($this->getConfigPrefix() . Admin::PREF_DASHBOARD_WIDGETS_CONFIG, $config);

                $html = $widget->render($this->view, $config[$widget->getId()]);
                $this->_response->ajaxResponse([
                    'status' => 'OK',
                    'html' => $html,
                    'id' => $widget->getId()
                ]);
            } else {
                $this->_response->ajaxResponse([
                    'status' => 'ERROR',
                    'html' => (string)$form,
                    'id' => $widget->getId()
                ]);
            }
        }
    }

    function renderWidgetQuickStart(Am_View $view, $config = null)
    {
        return $view->render('admin/widget/quick-start.phtml');
    }

    function createWidgetUserNoteConfigForm()
    {
        $form = new Am_Form_Admin();
        $form->addInteger('num')
            ->setLabel(___('Number of Notes to display'))
            ->setValue(5);

        return $form;
    }

    function renderWidgetUserNote(Am_View $view, $config = null)
    {
        $view->num = is_null($config) ? 5 : $config['num'];
        return $view->render('admin/widget/user-note.phtml');
    }

    function createWidgetEmailConfigForm()
    {
        $form = new Am_Form_Admin();
        $form->addInteger('num')
            ->setLabel(___('Number of Emails to display'))
            ->setValue(5);

        return $form;
    }

    function renderWidgetEmail(Am_View $view, $config = null)
    {
        $view->num = is_null($config) ? 5 : $config['num'];
        return $view->render('admin/widget/email.phtml');
    }

    public function renderWidgetReport(Am_View $view, $config=null, $invokeArgs = [])
    {
        class_exists('Am_Report_Standard', true);
        $view->enableReports();

        /* @var $savedReport SavedReport */
        $savedReport = $invokeArgs['savedReport'];

        $request = new Am_Mvc_Request(unserialize($savedReport->request));

        $r = Am_Report_Abstract::createById($savedReport->report_id);
        if (!$r) return;
        $r->applyConfigForm($request);

        $result = $r->getReport();
        $result->setTitle($savedReport->title);

        $type = is_null($config) ? 'graph-line' : $config['type'];
        $wrap = '<h2>%s</h2><div class="admin-index-report-wrapper"><div class="admin-index-report report-%s">%s</div></div>';
        switch ($type) {
            case 'graph-line' :
                $output = new Am_Report_Graph_Line($result);
                $output->setSize('100%', 250);
                break;
            case 'graph-bar' :
                $output = new Am_Report_Graph_Bar($result);
                $output->setSize('100%', 250);
                break;
            case 'table' :
                $output = new Am_Report_Table($result);
                $wrap = '<h2>%s</h2><div class="report-%s">%s</div>';
                break;
            default :
                throw new Am_Exception_InputError(___('Unknown report display type [%s]', $type));
        }

        return sprintf($wrap, $savedReport->title, $savedReport->report_id, $output->render());
    }

    public function createWidgetReportConfigForm()
    {
        $form = new Am_Form_Admin();
        $form->addSelect('type')
            ->setLabel(___('Display Type'))
            ->setValue('graph-line')
            ->loadOptions([
                'graph-line' => ___('Graph Line'),
                'graph-bar' => ___('Graph Bar'),
                'table' => ___('Table')
            ]);

        return $form;
    }

    public function renderWidgetUsers(Am_View $view, $config=null)
    {
        $view->num = is_null($config) ? 5 : $config['num'];
        return $view->render('admin/widget/users.phtml');
    }

    public function createWidgetUsersConfigForm()
    {
        $form = new Am_Form_Admin();
        $form->addInteger('num')
            ->setLabel(___('Number of Users to display'))
            ->setValue(5);

        return $form;
    }

    public function renderWidgetUserLogins(Am_View $view, $config=null)
    {
        $view->num = is_null($config) ? 5 : $config['num'];
        return $view->render('admin/widget/user-logins.phtml');
    }

    public function createWidgetUserLoginsConfigForm()
    {
        $form = new Am_Form_Admin();
        $form->addInteger('num')
            ->setLabel(___('Number of Users to display'))
            ->setValue(5);

        return $form;
    }

    public function renderWidgetPayments(Am_View $view, $config=null)
    {
        $view->num = is_null($config) ? 5 : $config['num'];
        return $view->render('admin/widget/payments.phtml');
    }

    public function renderWidgetActivity(Am_View $view, $config = null)
    {
        $interval = is_null($config) ? Am_Interval::PERIOD_THIS_WEEK_FROM_SUN : $config['interval'];
        $limit = min(50, (is_null($config) || !$config['limit']) ? 10 : $config['limit']);
        $events = is_null($config) ? [] : $config['events'];
        list($start, $stop) = $this->getDi()->interval->getStartStop($interval);

        $timeline = [];
        $di = $this->getDi();
        $events_def = [
            'payment' => [
                'date_field' => 'dattm',
                'get_list' => function($limit, $start) use ($di) {
                    return  $di->invoicePaymentTable->selectLast($limit, $start);
                }
            ],
            'refund' => [
                'date_field' => 'dattm',
                'get_list' => function($limit, $start) use ($di) {
                    return  $di->invoiceRefundTable->selectLast($limit, $start);
                }
            ],
            'signin' => [
                'date_field' => 'last_login',
                'get_list' => function($limit, $start) use ($di) {
                    return $di->userTable->findBy([['last_login', '>', $start]], 0, $limit, 'last_login DESC');
                }
            ],
            'signup' => [
                'date_field' => 'added',
                'get_list' => function($limit, $start) use ($di) {
                    return $di->userTable->selectLast($limit, $start);
                }
            ],
            'download' => [
                'date_field' => 'dattm',
                'get_list' => function($limit, $start) use ($di) {
                    return $di->fileDownloadTable->selectLast($limit, $start);
                }
            ],
            'user_note' => [
                'date_field' => 'dattm',
                'get_list' => function($limit, $start) use ($di) {
                    return $di->userNoteTable->selectLast($limit, $start);
                }
            ],
        ];
        if (!$events) {
            $events = array_keys($events_def);
        }
        $data = [];
        foreach ($events as $event_id) {
            if (isset($events_def[$event_id])) {
                $data[$event_id] = $events_def[$event_id]['get_list']($limit, $start);
            }
        }
        foreach ($data as $id => $rows) {
            foreach ($rows as $row) {
               $row->event_type = $id;
               $row->event_date_field = $events_def[$id]['date_field'];
               $timeline[sqlTime($row->{$events_def[$id]['date_field']}) . '-' . $row->pk()] = $row;
            }
        }

        krsort($timeline, SORT_STRING);
        $timeline = array_slice($timeline, 0, $limit);
        $view->assign('timeline', $timeline);
        return $view->render('admin/widget/activity.phtml');
    }

    public function renderWidgetRefunds(Am_View $view, $config=null)
    {
        $view->num = is_null($config) ? 5 : $config['num'];
        return $view->render('admin/widget/refunds.phtml');
    }

    public function renderWidgetInvoices(Am_View $view, $config=null)
    {
        $view->num = is_null($config) ? 5 : $config['num'];
        $view->statuses = $config['statuses'];
        return $view->render('admin/widget/invoices.phtml');
    }

    public function createWidgetPaymentsConfigForm()
    {
        $form = new Am_Form_Admin();
        $form->addInteger('num')
            ->setLabel(___('Number of Payments to display'))
            ->setValue(5);

        return $form;
    }

    public function createWidgetRefundsConfigForm()
    {
        $form = new Am_Form_Admin();
        $form->addInteger('num')
            ->setLabel(___('Number of Refunds to display'))
            ->setValue(5);

        return $form;
    }

    public function createWidgetInvoicesConfigForm()
    {
        $form = new Am_Form_Admin();
        $form->addInteger('num')
            ->setLabel(___('Number of Invoices to display'))
            ->setValue(5);
        $form->addMagicselect('statuses')
            ->setLabel(___('Show invoices with selected statuses') . "\n" . ___('leave it empty in case if you want to show all invoices'))
            ->loadOptions(Invoice::$statusText);
        return $form;
    }

    public function renderWidgetReportUsers(Am_View $view, $config = null)
    {
        $display = empty($config['display']) ? array_keys($this->getWidgetReportUsersDisplayTypes()) : $config['display'];
        $view->enableReports();
        $view->report = $this->getReportUsers($display);
        return $view->render('admin/widget/report-users.phtml');
    }

    public function renderWidgetWarnings(Am_View $view)
    {
        $this->view->warnings = AM_APPLICATION_ENV == 'demo' ? [] : $this->getWarnings();
        $this->view->notice = $this->getNotice();
        return $view->render('admin/widget/warnings.phtml');
    }

    protected function getReportUsers($display)
    {
        class_exists('Am_Report_Standard', true);
        $res = $this->getDi()->db->select("SELECT status as ARRAY_KEY, COUNT(*) as `count`
            FROM ?_user
            GROUP BY status");
        for ($i = 0; $i <= 2; $i++)
            $res[$i]['count'] = (int) @$res[$i]['count'];
        $active_paid = $this->getDi()->db->selectCell("
            SELECT COUNT(DISTINCT p.user_id) AS active
            FROM ?_invoice_payment p
                INNER JOIN ?_user u USING (user_id)
            WHERE u.status = 1");
        $active_free = $res[1]['count'] - $active_paid;
        $result = new Am_Report_Result;
        $result->setTitle(___('Users Breakdown'));

        $p1 = new Am_Report_Point(1, ___('Pending'));
        $p1->addValue(0, (int) $res[0]['count']);

        $p2 = new Am_Report_Point(2, ___('Active'));
        $p2->addValue(0, (int)$active_paid);

        $p3 = new Am_Report_Point(3, ___('Active (free)'));
        $p3->addValue(0, (int)$active_free);

        $p4 = new Am_Report_Point(4, ___('Expired'));
        $p4->addValue(0, (int)$res[2]['count']);

        $points = [
            1 => $p1,
            2 => $p2,
            3 => $p3,
            4 => $p4,
        ];

        foreach ($display as $_) {
            $result->addPoint($points[$_]);
        }

        $result->addLine(new Am_Report_Line(0, ___('# of users')));

        $output = new Am_Report_Graph_Bar($result);
        $output->setSize('100%', 250);
        return $output;
    }

    public function renderWidgetSales(Am_View $view, $config = null)
    {
        $intervals = is_null($config) ? [Am_Interval::PERIOD_TODAY, Am_Interval::PERIOD_THIS_WEEK_FROM_SUN] : (array)$config['interval'];
        $display_left = !isset($config['display_left']) ?  $this->getWidgetSalesDefaultInfoTypes('left') : $config['display_left'];
        $display_right = !isset($config['display_right']) ?  $this->getWidgetSalesDefaultInfoTypes('right') : $config['display_right'];
        $out = '';
        foreach ($intervals as $interval) {
            list($start, $stop) = $this->getDi()->interval->getStartStop($interval);

            $view->display_left = $display_left;
            $view->display_right = $display_right;
            $view->start = $start;
            $view->stop = $stop;
            $view->interval = $interval;
            $view->reportTitle = $this->getDi()->interval->getTitle($interval);
            $view->controller = $this;
            $out .= $view->render('admin/widget/sales.phtml');
        }
        return $out;
    }

    //MRR is all of your recurring revenue normalized in to a monthly amount
    public function renderWidgetRecurringRevenue(Am_View $view, $config = null)
    {
        $view->amount = Am_Currency::render(
            $this->getDi()->db->selectCell(<<<CUT
                SELECT SUM(30 * second_total / i.base_currency_multi /
                    (CAST(second_period AS SIGNED) *
                        CASE
                            WHEN SUBSTR(second_period, -1) = 'd' THEN 1
                            WHEN SUBSTR(second_period, -1) = 'm' THEN 30
                            WHEN SUBSTR(second_period, -1) = 'y' THEN 365
                        END
                    )) AS amt
                    FROM ?_invoice i
                        WHERE status=?
                            AND rebill_date >= ?
                            AND EXISTS (SELECT * FROM ?_invoice_payment ip WHERE ip.invoice_id=i.invoice_id)
CUT
                , Invoice::RECURRING_ACTIVE, $this->getDi()->sqlDate));
        return $view->render('admin/widget/recurring-revenue.phtml');
    }

    public function renderWidgetRevenueGoal(Am_View $view, $config = null)
    {
        $interval = is_null($config) ? Am_Interval::PERIOD_THIS_MONTH : $config['interval'];
        list($start, $stop) = $this->getDi()->interval->getStartStop($interval);
        $s = new DateTime($start);
        $e = new DateTime($stop);
        $n = new DateTime(sqlTime('now'));
        $diff = $e->diff($s);
        $days_total = $diff->days + 1;
        $diff_current = $n->diff($s);
        $days_current = $diff_current->days + 1;

        $amount = $this->getDi()->db->selectCell("SELECT SUM(amount/base_currency_multi) AS amt " .
                " FROM ?_invoice_payment WHERE dattm BETWEEN ? AND ?", $start, $stop);
        $refund_amount = $this->getDi()->db->selectCell("SELECT SUM(amount/base_currency_multi) AS amt " .
                " FROM ?_invoice_refund WHERE dattm BETWEEN ? AND ?", $start, $stop);

        $view->amount = max(0, $amount - $refund_amount);
        $view->goal = (isset($config['goal']) && $config['goal']>0) ? $config['goal'] : 10000;

        $view->ontarget = ($view->goal/$days_total) <= ($view->amount/$days_current);

        $width = (100 * $view->amount) / $view->goal;
        $view->width = min(100, round($width));

        $goal_target = $days_current * ($view->goal/$days_total);
        $width_target = (100 * $goal_target) / $view->goal;
        $view->width_target = min(100, round($width_target));
        $view->goal_target = $goal_target;

        $view->diff = $view->amount - $goal_target;
        $view->forecast = $days_total * ($view->amount/$days_current);
        $view->reportTitle = $this->getDi()->interval->getTitle($interval);

        $compare = (is_null($config) || !isset($config['compare'])) ? 'period' : $config['compare'];
        if ($compare) {
            switch ($compare) {
                case 'period':
                    $diff = "- {$this->getDi()->interval->getDuration($interval)}";
                    $cmp_title = ___('change over previous period');
                    break;
                case 'year':
                    $diff = "- 1 year";
                    $cmp_title = ___('change over same period in previous year');
                    break;
            }
            $c_start = date('Y-m-d 00:00:00', strtotime("$start $diff"));
            $c_stop = date('Y-m-d 23:59:59', strtotime("$stop $diff"));

            $c_amount = $this->getDi()->db->selectCell("SELECT SUM(amount/base_currency_multi) AS amt " .
                " FROM ?_invoice_payment WHERE dattm BETWEEN ? AND ?", $c_start, $c_stop);
            $c_refund_amount = $this->getDi()->db->selectCell("SELECT SUM(amount/base_currency_multi) AS amt " .
                " FROM ?_invoice_refund WHERE dattm BETWEEN ? AND ?", $c_start, $c_stop);

            $c_total = $c_amount - $c_refund_amount;

            if ($c_total > 0) {
                $c = ($view->forecast * 100 / $c_total) - 100;
                $view->cdiff = $c;
                $view->ctitle = $cmp_title;
            }
        }

        return $view->render('admin/widget/revenue-goal.phtml');
    }

    public function createWidgetRevenueGoalConfigForm()
    {
        $form = new Am_Form_Admin();
        $form->addSelect('interval', null, [
            'options' =>
            [
                Am_Interval::PERIOD_TODAY => ___('Today'),
                Am_Interval::PERIOD_THIS_WEEK_FROM_SUN => ___('This Week (Sun-Sat)'),
                Am_Interval::PERIOD_THIS_WEEK_FROM_MON => ___('This Week (Mon-Sun)'),
                Am_Interval::PERIOD_THIS_MONTH => ___('This Month'),
                Am_Interval::PERIOD_THIS_QUARTER => ___('This Quarter'),
                Am_Interval::PERIOD_THIS_YEAR => ___('This Year')
            ]
        ])->setLabel(___('Period'))->setValue(Am_Interval::PERIOD_THIS_MONTH);
        $g = $form->addGroup();
        $g->setSeparator(' ');
        $g->setLabel(___('Revenue Goal'));
        $g->addText('goal', ['size' => 8, 'placeholder' => '10000']);
        $g->addHtml()
            ->setHtml(Am_Currency::getDefault());
        $form->addSelect('compare', null, [
            'options' =>
            [
               '' => ___('None'),
               'period' => ___('Previous Period'),
               'year' => ___('Same Period in Previous Year')
            ]
        ])->setLabel(___('Compare To'))
            ->setValue('period');

        return $form;
    }

    public function createWidgetActivityConfigForm()
    {
        $form = new Am_Form_Admin();
        $form->addMagicSelect('events')
            ->setLabel(___("Events\n" .
                "to inclulde to widget, leave it " .
                "empty to include all events"))
            ->loadOptions([
                'payment' => ___('Payment'),
                'refund' => ___('Refund'),
                'signin' => ___('Sign In'),
                'signup' => ___('Sign Up'),
                'download' => ___('File Download'),
                'user_note' => ___('User Notes')
            ]);

        $form->addSelect('interval', null, [
            'options' =>
            [
                Am_Interval::PERIOD_TODAY => ___('Today'),
                Am_Interval::PERIOD_LAST_7_DAYS => ___('Last 7 Days'),
                Am_Interval::PERIOD_LAST_14_DAYS => ___('Last 14 Days'),
                Am_Interval::PERIOD_THIS_WEEK_FROM_SUN => ___('This Week (Sun-Sat)'),
                Am_Interval::PERIOD_THIS_WEEK_FROM_MON => ___('This Week (Mon-Sun)'),
                Am_Interval::PERIOD_THIS_MONTH => ___('This Month'),
                Am_Interval::PERIOD_THIS_QUARTER => ___('This Quarter'),
                Am_Interval::PERIOD_THIS_YEAR => ___('This Year')
            ]
        ])->setLabel(___('Period'))->setValue(Am_Interval::PERIOD_THIS_WEEK_FROM_SUN);
        $form->addText('limit', ['size' => 3, 'placeholder' => '10'])
            ->setLabel(___('Maximum number of events'))
            ->addRule('lte', ___('Should be less then %d', 50), 50);
        return $form;
    }

    public function createWidgetReportUsersConfigForm()
    {
        $form = new Am_Form_Admin();

        $form->addSortableMagicSelect('display', null, ['options' => $this->getWidgetReportUsersDisplayTypes()])
            ->setLabel(___('Display'))
            ->setValue(array_keys($this->getWidgetReportUsersDisplayTypes()));

        return $form;
    }

    function getWidgetReportUsersDisplayTypes()
    {
        return [
            1 => ___('Pending'),
            2 => ___('Active'),
            3 => ___('Active (free)'),
            4 => ___('Expired'),
        ];
    }

    public function createWidgetSalesConfigForm()
    {
        $form = new Am_Form_Admin();
        $form->addSortableMagicSelect('interval', null, ['options' => $this->getDi()->interval->getOptions()])
            ->setLabel(___('Period'))
            ->setValue([Am_Interval::PERIOD_TODAY]);

        $form->addSortableMagicSelect('display_left', null, ['options' => $this->getWidgetSalesInfoTypes()])
            ->setLabel(___('Display (Left Column)'))
            ->setValue($this->getWidgetSalesDefaultInfoTypes('left'));

        $form->addSortableMagicSelect('display_right', null, ['options' => $this->getWidgetSalesInfoTypes()])
            ->setLabel(___('Display (Right Column)'))
            ->setValue($this->getWidgetSalesDefaultInfoTypes('right'));

        return $form;
    }

    function getWidgetSalesDefaultInfoTypes($side)
    {
        return $side == 'left' ?
            ['payments', 'refunds', 'aff-comm'] :
            ['signups', 'cancels', 'rebills-next'];
    }

    function getWidgetSalesInfoTypes()
    {
        return [
            'payments' => ___('Payments'),
            'refunds' => ___('Refuns'),
            'sales' => ___('Sales'),
            'rebills' => ___('Rebills'),
            'aff-comm' => ___('Affiliate Commisisons'),
            'signups' => ___('Registrations'),
            'cancels' => ___('Cancellations'),
            'cancels-final' => ___('Cancellations (without upgrade)'),
            'rebills-next' => ___('Rebills (Next Period)'),
            'rebills-next-30' => ___('Rebills (Next 30 Days)'),
            'rebills-next-month' => ___('Rebills (Next Month)'),
            'rebills-remaining-month' => ___('Rebills (Remaining in Current Month)')
        ];
    }

    function getPaymentsStats($start, $stop)
    {
        $row = $this->getDi()->db->selectRow("
            SELECT
                COUNT(*) AS cnt,
                ROUND(SUM(amount / base_currency_multi), 2) AS total
            FROM ?_invoice_payment
            WHERE dattm BETWEEN ? AND ?",
            sqlTime($start), sqlTime($stop));
        return [(int) $row['cnt'], moneyRound($row['total'])];
    }

    function getRefundsStats($start, $stop)
    {
        $row = $this->getDi()->db->selectRow("
            SELECT
                COUNT(*) AS cnt,
                ROUND(SUM(amount / base_currency_multi), 2) AS total
            FROM ?_invoice_refund
            WHERE dattm BETWEEN ? AND ?",
            sqlTime($start), sqlTime($stop));
        return [(int) $row['cnt'], moneyRound($row['total'])];
    }

    function getSalesStats($start, $stop)
    {
        $row = $this->getDi()->db->selectRow("
            SELECT
                COUNT(*) AS cnt,
                ROUND(SUM(ip.amount / ip.base_currency_multi), 2) AS total
            FROM ?_invoice_payment ip
            LEFT JOIN ?_invoice i
            USING (invoice_id)
            WHERE dattm BETWEEN ? AND ? AND DATE(i.tm_started) = DATE(ip.dattm)",
            sqlTime($start), sqlTime($stop));
        return [(int) $row['cnt'], moneyRound($row['total'])];
    }

    function getRebillsStats($start, $stop)
    {
        $row = $this->getDi()->db->selectRow("
            SELECT
                COUNT(*) AS cnt,
                ROUND(SUM(ip.amount / ip.base_currency_multi), 2) AS total
            FROM ?_invoice_payment ip
            LEFT JOIN ?_invoice i
            USING (invoice_id)
            WHERE dattm BETWEEN ? AND ? AND DATE(i.tm_started) <> DATE(ip.dattm)",
            sqlTime($start), sqlTime($stop));
        return [(int) $row['cnt'], moneyRound($row['total'])];
    }

    function getCancelsStats($start, $stop, $without_upgrade = false)
    {
        return $this->getDi()->db->selectCell("
            SELECT COUNT(*)
            FROM ?_invoice
            WHERE tm_cancelled BETWEEN ? AND ? 
            { AND invoice_id NOT IN (SELECT `id` FROM ?_data WHERE `table` = 'invoice' AND `key` = ?) }",
            sqlTime($start), sqlTime($stop), $without_upgrade ? Invoice::UPGRADE_INVOICE_ID : DBSIMPLE_SKIP);
    }

    function getPlannedRebills($start, $stop)
    {
        $row = $this->getDi()->db->selectRow("
            SELECT
                COUNT(*) AS cnt,
                ROUND(SUM(second_total / base_currency_multi), 2) AS total
            FROM ?_invoice
            WHERE rebill_date BETWEEN ? AND ?
            AND tm_cancelled IS NULL",
            sqlDate($start), sqlDate($stop));
        return [(int) $row['cnt'], moneyRound($row['total'])];
    }

    function getSignupsCount($start, $stop)
    {
        return $this->getDi()->db->selectCell("
            SELECT
                COUNT(*) AS cnt
            FROM ?_user
            WHERE added BETWEEN ? AND ?",
            sqlTime($start), sqlTime($stop));
    }

    function getErrorLogCount()
    {
        $time = $this->getDi()->time;
        $tm = date('Y-m-d H:i:s', $time - 24 * 3600);
        return $this->getDi()->db->selectCell(
            "SELECT COUNT(*)
            FROM ?_error_log
            WHERE dattm BETWEEN ? AND ?",
            $tm, $this->getDi()->sqlDateTime);
    }

    function getAccessLogCount()
    {
        $tm = date('Y-m-d H:i:s', $this->getDi()->time - 24 * 3600);
        return $this->getDi()->db->selectCell(
            "SELECT COUNT(log_id)
            FROM ?_access_log
            WHERE dattm BETWEEN ? AND ?",
            $tm,
            $this->getDi()->sqlDateTime);
    }

    function getWarnings()
    {
        $warn = [];

        $ext = ['pdo', 'pdo_mysql', 'openssl', 'mbstring', 'iconv', 'xml', 'xmlwriter', 'xmlreader', 'ctype'];
        foreach ($ext as $e) {
            if (!extension_loaded($e))
                $warn[] = "aMember require <b>$e</b> extension to be installed in PHP. Please contact your hosting support and ask to enable this extension for PHP on your server (<a href='http://www.php.net/manual/en/$e.installation.php' target='_blank' rel='noreferrer' class='link'>installation instructions</a>).";
        }
        if (version_compare(phpversion(), '7.2') < 0) {
            $warn[] = "PHP version 7.2 or greater is required to run aMember. Your PHP Version is: " . phpversion() .
                " Please upgrade or ask your hosting to upgrade.";
        }

        $setupUrl = $this->getDi()->url('admin-setup');

        if (!$this->getDi()->config->get('maintenance')) {
            $t = Am_Cron::getLastRun();
            $diff = time() - $t;
            $tt = $t ? (___('at ') . amDatetime($t)) : ___('NEVER (oops! no records that it has been running at all!)');
            if (($diff > 24 * 3600) && (AM_APPLICATION_ENV != 'demo'))
                $warn[] = ___('Cron job has been running last time %s, it is more than 24 hours ago.
Most possible external cron job has been set incorrectly. It may cause very serious problems with the script.
You can find info how to set up cron job for your installation <a class="link" href="http://www.amember.com/docs/Cron" target="_blank">here</a>.', $tt);
        }

        foreach ($this->getDi()->plugins as $pm)
        {
            $pm->loadEnabled();
            foreach ($pm->getWarnings() as $msg)
            {
                $warn[] = $msg . sprintf(' <a href="%s">%s</a>',
                        $this->getDi()->url('admin-plugins'),
                        ___('fix'));
            }
        }

        if ($this->getDi()->store->get('AM_UPGRADE_REQUIRES_RENAME_V6')==3)
        {
            $_ = $this->getDi()->url('admin-upgrade-v6');
            $warn[] = sprintf("Please follow %s after upgrading to %s",
                "<a class=link href='$_'>post-upgrade wizard</a>", "aMember Pro v.6");
        }

        if (!$this->getDi()->productTable->count()) {
            $productsUrl = $this->getDi()->url('admin-products');
            $warn[] = ___('You have not added any products, your signup forms will not work until you <a class="link" href="' . $productsUrl . '">add at least one product</a>');
        }

        if ($this->getDi()->config->get('email_queue_enabled') && !$this->getDi()->config->get('use_cron')) {
            $url = $this->getDi()->url('admin-setup/advanced');
            $warn[] = ___('%sEnable%s and %sconfigure%s external cron if you are using E-Mail Throttle Queue',
                '<a class="link" href="'.$url. '">', '</a>', '<a class="link" href="http://www.amember.com/docs/Cron">', '</a>');
        }

        if ($this->getDi()->db->selectCell("SELECT COUNT(*) FROM ?_email_template WHERE name in (?a)",
            ['pending_to_user', 'pending_to_admin', 'expire', 'autoresponder']) &&
            !$this->getDi()->config->get('use_cron') &&
            (AM_APPLICATION_ENV != 'demo')) {
            $url = $this->getDi()->url('admin-setup/advanced');
            $warn[] = ___('%sEnable%s and %sconfigure%s external cron if you are using Periodic E-Mails (Autoresponder/Expiration/Pending Notifications)',
                '<a class="link" href="'. $url. '">', '</a>', '<a class="link" href="http://www.amember.com/docs/Cron">', '</a>');
        }

        // load all plugins
        try {
            foreach ($this->getDi()->plugins as $m)
                $m->loadEnabled();
        } catch (Exception $e) {
            //nop
        }

        if($this->getDi()->config->get('enable-account-delete') && $this->getDi()->db->selectCell("select count(*) from ?_user_delete_request where completed=0"))
        {
            $warn[] = ___('You have pending %sPersonal Data Delete%s requests.', "<a href='".$this->getDi()->url('admin-delete-personal-data')."'>", "</a>");
        }
        $event = $this->getDi()->hook->call(Am_Event::ADMIN_WARNINGS);
        return array_merge($warn, $event->getReturn());
    }

    function getNotice()
    {
        $warn = [];

        // Check for not approved users.
        if($this->getDi()->config->get('manually_approve')) {
            $na_users = $this->getDi()->db->selectCell('select count(*) from ?_user where is_approved<1');
            if($na_users) {
                $url = $this->getDi()->url('admin-users', ['_u_search[field-is_approved][val]'=>0]);
                $warn[] = sprintf(
                    ___('Number of users who require approval: %d. %sClick here%s to review these users.'),
                    $na_users,
                    '<a class="link" href="'.$url.'">',
                    '</a>'
                    );
            }
        }

        // Check for not approved invoices.
        if($this->getDi()->config->get('manually_approve_invoice')) {
            $na_invoices = $this->getDi()->db->selectCell('select count(*) from ?_invoice where is_confirmed<1');
            if($na_invoices) {
                $url = $this->getDi()->url('default/admin-payments/p/not-approved/index');
                $warn[] = sprintf(
                    ___('Number of invoices which require approval: %d. %sClick here%s to review these invoices.'),
                    $na_invoices,
                    '<a class="link" href="'.$url.'">',
                    '</a>'
                    );
            }
        }

        // load all plugins
        try {
            foreach ($this->getDi()->plugins as $m)
                $m->loadEnabled();
        } catch (Exception $e) {
            //nop
        }

        $event = $this->getDi()->hook->call(Am_Event::ADMIN_NOTICE);
        return array_merge($warn, $event->getReturn());
    }

    function hasPermissions($perm, $priv = null)
    {
        return $this->getDi()->authAdmin->getUser()->hasPermission($perm);
    }

    function disableQuickstartAction()
    {
        $pref = $this->getPref();
        foreach ($pref['top'] as $k => $w) {
            if ($w == 'quick-start') unset($pref['top'][$k]);
        }
        $this->getDi()->authAdmin->getUser()->setPref($this->getConfigPrefix() . Admin::PREF_DASHBOARD_WIDGETS, $pref);
        return $this->indexAction();
    }

    function getWidgetsByTarget()
    {
        $widgets = [
            'top' => [],
            'bottom' => [],
            'main' => [],
            'aside' => []
        ];

        $pref = $this->getPref();
        $availableWidgets = $this->getAvailableWidgets();

        foreach ($pref as $target => $enabledWidgets) {
            foreach ($enabledWidgets as $id) {
                if (isset($availableWidgets[$id]) &&
                    $availableWidgets[$id]->hasPermission($this->getDi()->authAdmin->getUser())) {

                    $widgets[$target][] = $availableWidgets[$id];
                }
            }
        }
        return $widgets;
    }

    function indexAction()
    {
        $this->view->enableReports();
        $this->view->widgets = $this->getMyWidgets();
        $this->view->widgetsConfig = $this->getWidgetConfig();
        $this->view->display($this->template);
    }

    function getMyWidgets()
    {
        $widgets = $this->getWidgetsByTarget();
        return $widgets;
    }

    function widgetAction()
    {
        $this->getDi()->session->writeClose();
        $id = $this->getFiltered('id');
        $widgets = $this->getMyWidgets();

        list(,$target,$index) = explode('-', $id);

        if(!strlen($target) || !strlen($index) || !isset($widgets[$target][$index])) {
            return;
        }

        $widget = $widgets[$target][$index];
        $config = $this->getWidgetConfig();

        $w = $widget->render($this->view, isset($config[$widget->getId()]) ? $config[$widget->getId()] : null);
        $this->_response->ajaxResponse([
            'widget' => $w,
            'hash' => md5($w)
        ]);
    }
}