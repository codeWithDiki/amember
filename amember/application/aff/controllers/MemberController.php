<?php

/*
 *
 *     Author: Alex Scott
 *      Email: alex@cgi-central.net
 *        Web: http://www.cgi-central.net
 *    Details: Affiliate pages
 *    FileName $RCSfile$
 *    Release: 6.3.6 ($Revision$)
 *
 * Please direct bug reports,suggestions or feedback to the cgi-central forums.
 * http://www.cgi-central.net/forum/
 *
 * aMember PRO is a commercial software. Any distribution is strictly prohibited.
 *
 */

class Aff_MemberController extends Am_Mvc_Controller
{
    /** @var User */
    protected $user;

    public function preDispatch()
    {
        $this->getDi()->auth->requireLogin($this->getDi()->url('aff/member', null, false));
        $this->user = $this->getDi()->user;
        if (!$this->user->is_affiliate) {
            //throw new Am_Exception_InputError("Sorry, this page is opened for affiliates only");
            $this->_redirect('member');
        }
    }

    function indexAction()
    {
        $this->_redirect('aff/aff');
    }

    function statsAction()
    {
        class_exists('Am_Report_Standard', true);
        include_once AM_APPLICATION_PATH . '/aff/library/Reports.php';

        if ($this->getDi()->config->get('aff.affiliate_can_view_details') && $detailDate = $this->getFiltered('detailDate')) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $detailDate))
                throw new Am_Exception_InputError("Wrong date passed");
            $c = 0;
            foreach ($this->getDi()->affCommissionTable->fetchByDate($detailDate, $this->user->user_id) as $comm) {
                $c++;
                $invoice = $comm->getInvoice();
                $u = $invoice ? $invoice->getUser() : null;
                $product = $comm->getProduct();

                $product_title = $product ? ___($product->title) : $comm->comment;

                if ($u) {
                    $vn = $this->getModule()->getConfig('affiliate_can_view_name');
                    $ve = $this->getModule()->getConfig('affiliate_can_view_email');
                    $ep = $vn ? ' &lt;%s&gt;' : '%s';

                    $user_title = trim(sprintf('%s%s',
                        ($vn ? Am_Html::escape($u->name_f . '&nbsp;' . $u->name_l) : ''),
                        ($ve ? sprintf($ep, $this->escape($u->email)) : '')));
                } else {
                    $user_title = null;
                }
                $s = sprintf('%s%s &ndash; <strong>%s</strong>%s',
                    ($user_title ? "{$user_title} " : ''),
                    ($user_title ? "({$product_title})" : $product_title),
                    Am_Currency::render($comm->amount),
                    ($comm->tier ? ___(' (%d-tier)', $comm->tier+1) : ''));
                if ($comm->record_type == AffCommission::VOID)
                    $s = "<div style='color: red'>$s (void)</div>";
                echo $s . "<br />\n";
            }
            if (!$c)
                echo ___('No commissions on this date');

            return;
        }

        $rs = new Am_Report_AffStats();
        $rs->setAffId($this->user->user_id);
        $rc = new Am_Report_AffClicks();
        $rc->setAffId($this->user->user_id);
        $rn = new Am_Report_AffSales();
        $rn->setAffId($this->user->user_id);

        $this->view->monthyear = $this->getInt('monthyear', '');

        if (!$this->getInt('monthyear')) {
            $firstDate[] = $this->getDi()->db->selectCell("SELECT MIN(date) FROM ?_aff_commission WHERE aff_id=?d", $this->user->user_id);
            $firstDate[] = current(explode(' ', $this->getDi()->db->selectCell("SELECT MIN(`time`) FROM ?_aff_click WHERE aff_id=?d", $this->user->user_id)));
            $rs->setInterval(min($firstDate), 'now')->setQuantity(new Am_Report_Quant_Month());
        } else {
            $ym = $this->getInt('monthyear');
            if (!$ym || strlen($ym) != 6)
                $ym = date('Ym');
            $start = mktime(0, 0, 0, substr($ym, 4, 2), 1, substr($ym, 0, 4));
            $rs->setInterval(date('Y-m-d 00:00:00', $start), date('Y-m-t 23:59:59', $start))->setQuantity(new Am_Report_Quant_Day());
            $this->view->period = [date('Y-m-d 00:00:00', $start), date('Y-m-t 23:59:59', $start)];
        }
        $rc->setInterval($rs->getStart(), $rs->getStop())->setQuantity(clone $rs->getQuantity());
        $rn->setInterval($rs->getStart(), $rs->getStop())->setQuantity(clone $rs->getQuantity());

        $result = $rs->getReport();
        $rc->getReport($result);
        $rn->getReport($result);

        $output = new Am_Report_Graph_Line($result);
        $output->setSize('100%', 300);
        $this->view->report = $output->render();

        /* extract data from report to show it in view */
        $rows = [];
        $totals = [];
        $lines = $result->getLines();
        foreach ($result->getPointsWithValues() as $r) {
            /* @var $r Am_Report_Point */
            if ($result->getQuantity()->getId() == 'month') {
                $hasValue = false;
                foreach ($lines as $line) {
                    if ($r->getValue($line->getKey())) {
                        $hasValue = true;
                        break;
                    }
                }
                $href = $hasValue ? $this->getDi()->url('__SELF__', ["monthyear"=>$r->getKey()]) : '';
            } elseif ($this->getModule()->getConfig('affiliate_can_view_details')) {
                $href = "javascript: showAffDetails('{$r->getKey()}')";
            } else {
                $href = "";
            }
            $rows[$r->getKey()]['date'] = $r->getLabel();
            $rows[$r->getKey()]['date_href'] = $href;
            foreach ($lines as $i=>$line){
                list($start, $stop) = $result->getQuantity()->getStartStop($r->getKey());

                $href = $r->getValue($line->getKey()) > 0 ?
                    sprintf("javascript:affDetail('%s', '%s', '%s')", $start, $stop, $r->getLabel()) :
                    null;

                $rows[$r->getKey()][$line->getKey() . '_href'] = $href;
                $rows[$r->getKey()][$line->getKey()] = $r->getValue($line->getKey());

                if(empty($totals[$line->getKey()]))
                    $totals[$line->getKey()] = 0;
                
                $totals[$line->getKey()] = $totals[$line->getKey()]+doubleval($r->getValue($line->getKey()));
            }
        }
        if ($this->getParam('csv')) {
            $filename = sprintf("aff-stat-%s-%s.csv",
                $this->user->login, $this->getInt('monthyear') ?: 'full');
            return $this->sendReportFile($rows, $filename);
        }

        $this->view->canViewDetails = $this->getModule()->getConfig('affiliate_can_view_details');
        $this->view->canViewEmail = $this->getModule()->getConfig('affiliate_can_view_email');
        $this->view->canViewName = $this->getModule()->getConfig('affiliate_can_view_name');
        $this->view->totals = $totals;
        $this->view->rows = $rows;
        $this->view->result = $result;
        $this->view->display('aff/stats.phtml');
    }

    public function exportDetailsAction()
    {
        if (!$this->getModule()->getConfig('affiliate_can_view_details')) {
            throw new Am_Exception_AccessDenied;
        }

        $q = new Am_Query($this->getDi()->affCommissionTable);
        $q->addWhere('t.aff_id=?', $this->user->pk())
            ->leftJoin('?_invoice_item', 'ii', 't.invoice_item_id = ii.invoice_item_id')
            ->leftJoin('?_invoice', 'i', 'ii.invoice_id = i.invoice_id')
            ->leftJoin('?_user', 'u', 'i.user_id = u.user_id')
            ->leftJoin('?_aff_payout_detail', 'pd', 'pd.payout_detail_id=t.payout_detail_id')
            ->leftJoin('?_aff_payout', 'p', 'p.payout_id=pd.payout_id')
            ->addField("CONCAT(u.name_f, ' ', u.name_l)", 'u_name')
            ->addField("u.email", 'u_email')
            ->addField("ii.item_title")
            ->addField("p.date", 'payout_date');

        if ($ym = $this->getInt('monthyear', '')) {
            $start = mktime(0, 0, 0, substr($ym, 4, 2), 1, substr($ym, 0, 4));
            $begin = date('Y-m-d 00:00:00', $start);
            $end = date('Y-m-t 23:59:59', $start);

            $q->addWhere('t.date BETWEEN ? AND ?', $begin, $end);
        }

        $filename = sprintf("aff-details-%s-%s.csv",
                $this->user->login, $this->getInt('monthyear') ?: 'full');
        $this->sendDetailsReportFile($q->selectAllRecords(), $filename);
    }

    public function sendReportFile($rows, $filename)
    {
        $delimiter = ',';
        $data = [];
        $data[] = [
                ___('Date'), ___('Transactions'), ___('Commission'), ___('Clicks (All)'), ___('Clicks (Unique)')
        ];
        foreach ($rows as $r) {
            $data[] = [
                $r['date'], $r['sales'], $r['commission'], $r['clicks_all'], $r['clicks']
            ];
        }

        foreach ($data as & $r) {
            $out = "";
            foreach ($r as $s) {
                $out .= ( $out ? $delimiter : "") . amEscapeCsv($s, $delimiter);
            }
            $r = $out;
        }
        $this->_helper->sendFile->sendData(implode("\r\n", $data), 'text/csv', $filename);
    }

    public function sendDetailsReportFile($rows, $filename)
    {
        $delimiter = ',';
        $data = [];
        $head = [];
        $head[] = ___('Date');
        if ($this->getModule()->getConfig('affiliate_can_view_name')) {
            $head[] = ___('User');
        }
        if ($this->getModule()->getConfig('affiliate_can_view_email')) {
            $head[] = ___('Email');
        }
        $head[] = ___('Product');
        $head[] = ___('Type');
        $head[] = ___('Amount');
        $head[] = ___('Tier');
        $head[] = ___('Payout Date');
        $data[] = $head;

        foreach ($rows as $r) {
            $_ = [];
            $_[] = $r->date;
            if ($this->getModule()->getConfig('affiliate_can_view_name')) {
                $_[] = $r->u_name;
            }
            if ($this->getModule()->getConfig('affiliate_can_view_email')) {
                $_[] = $r->u_email;
            }
            $_[] = $r->item_title ?: $r->comment;
            $_[] = $r->record_type;
            $_[] = $r->amount;
            $_[] = $r->tier;
            $_[] = $r->payout_date;
            $data[] = $_;
        }

        foreach ($data as & $r) {
            $out = "";
            foreach ($r as $s) {
                $out .= ( $out ? $delimiter : "") . amEscapeCsv($s, $delimiter);
            }
            $r = $out;
        }
        $this->_helper->sendFile->sendData(implode("\r\n", $data), 'text/csv', $filename);
    }

    public function payoutInfoAction()
    {
        $form = new Am_Form;
        $form->addCsrf();
        $form->setAction($this->getUrl());
        $this->getModule()->addPayoutInputs($form);
        $form->addSubmit('_save', ['value' => ___('Save')]);
        $form->addDataSource(new Am_Mvc_Request($d = $this->user->toArray()));
        if ($form->isSubmitted() && $form->validate()) {
            foreach ($form->getValue() as $k => $v) {
                if ($k[0] == '_')
                    continue;
                if ($k == 'aff_payout_type')
                    $this->user->set($k, $v);
                else
                    $this->user->data()->set($k, $v);
            }
            $this->user->update();
        }

        $this->view->form = $form;
        $this->view->display('aff/payout-info.phtml');
    }

    public function payoutAction()
    {
        $query = new Am_Query($this->getDi()->affPayoutDetailTable);
        $query->leftJoin('?_aff_payout', 'p', 'p.payout_id=t.payout_id');
        $query->addField('p.*')
            ->addWhere('aff_id=?',  $this->user->pk());

        $this->view->payouts = $query->selectAllRecords();
        $this->view->display('aff/payout.phtml');
    }

    public function clicksDetailAction()
    {
        $date_from = $this->getFiltered('from');
        $date_to = $this->getFiltered('to');
        $this->view->clicks = $this->getDi()->affClickTable->fetchByDateInterval($date_from, $date_to, $this->getDi()->auth->getUserId());
        $this->view->display('/aff/clicks-detail.phtml');

    }

    public function getKeywordsReport($uid, $from=null, $to=null, $kw=null)
    {
        $from_t = $from ? "{$from} 00:00:00" : null;
        $to_t = $to ? "{$to} 23:59:59" : null;

        $db = $this->getDi()->db;
        $db->query('DROP TEMPORARY TABLE IF EXISTS ?_aff_keywords_tmp');
        $db->query("CREATE TEMPORARY TABLE ?_aff_keywords_tmp (
            keyword_id int not null DEFAULT 0,
            keyword varchar(64) not null DEFAULT '',
            clicks_count int not null DEFAULT 0,
            clicks_unique_count int not null DEFAULT 0,
            leads_count int not null DEFAULT 0,
            sales_count int not null DEFAULT 0,
            sales_amount decimal(12,2) not null DEFAULT 0,
            PRIMARY KEY (`keyword_id`)
            )");
        //clicks
        $db->query(<<<CUT
            INSERT INTO ?_aff_keywords_tmp (keyword_id, clicks_count)
                SELECT keyword_id, COUNT(*)
                    FROM ?_aff_click t
                    LEFT JOIN ?_aff_keyword k USING (keyword_id)
                    WHERE keyword_id IS NOT NULL
                        AND t.aff_id = ?
                        AND k.aff_id = ?
                        {AND time >= ?}
                        {AND time <= ?}
                        {AND `value` = ?}
                    GROUP BY keyword_id
CUT
            , $uid, $uid, $from_t ?: DBSIMPLE_SKIP, $to_t ?: DBSIMPLE_SKIP, $kw ?: DBSIMPLE_SKIP);

        //clicks unique
        $db->query(<<<CUT
            INSERT INTO ?_aff_keywords_tmp (keyword_id, clicks_unique_count)
                SELECT keyword_id, COUNT(DISTINCT remote_addr)
                    FROM ?_aff_click t
                    LEFT JOIN ?_aff_keyword k USING (keyword_id)
                    WHERE keyword_id IS NOT NULL
                        AND t.aff_id = ?
                        AND k.aff_id = ?
                        {AND time >= ?}
                        {AND time <= ?}
                        {AND `value` = ?}
                    GROUP BY keyword_id
            ON DUPLICATE KEY UPDATE clicks_unique_count = VALUES(clicks_unique_count)
CUT
            , $uid, $uid, $from_t ?: DBSIMPLE_SKIP, $to_t ?: DBSIMPLE_SKIP, $kw ?: DBSIMPLE_SKIP);

        //leads
        $db->query(<<<CUT
            INSERT INTO ?_aff_keywords_tmp (keyword_id, leads_count)
                SELECT keyword_id, cnt FROM
                    (SELECT keyword_id, COUNT(*) as cnt
                        FROM ?_aff_lead t
                        LEFT JOIN ?_aff_keyword k USING (keyword_id)
                        WHERE keyword_id IS NOT NULL
                            AND t.aff_id = ?
                            AND k.aff_id = ?
                            {AND time >= ?}
                            {AND time <= ?}
                            {AND `value` = ?}
                        GROUP BY keyword_id) s
            ON DUPLICATE KEY UPDATE leads_count = s.cnt
CUT
            , $uid, $uid, $from_t ?: DBSIMPLE_SKIP, $to_t ?: DBSIMPLE_SKIP, $kw ?: DBSIMPLE_SKIP);

        //sales_count
        $db->query(<<<CUT
            INSERT INTO ?_aff_keywords_tmp (keyword_id, sales_count)
                SELECT keyword_id, cnt FROM
                    (SELECT keyword_id, SUM(if(record_type='commission', 1, 0)) as cnt
                        FROM ?_aff_commission t
                        LEFT JOIN ?_aff_keyword k USING (keyword_id)
                        WHERE keyword_id IS NOT NULL
                            AND t.aff_id = ?
                            AND k.aff_id = ?
                            {AND date >= ?}
                            {AND date <= ?}
                            {AND `value` = ?}
                        GROUP BY keyword_id) s
            ON DUPLICATE KEY UPDATE sales_count = s.cnt
CUT
            , $uid,$uid, $from ?: DBSIMPLE_SKIP, $to ?: DBSIMPLE_SKIP, $kw ?: DBSIMPLE_SKIP);

        //sales_amount
        $db->query(<<<CUT
            INSERT INTO ?_aff_keywords_tmp (keyword_id, sales_amount)
                SELECT keyword_id, cnt FROM
                    (SELECT keyword_id, SUM(IF(record_type='commission', amount, -amount)) AS cnt
                        FROM ?_aff_commission t
                        LEFT JOIN ?_aff_keyword k USING (keyword_id)
                        WHERE keyword_id IS NOT NULL
                            AND t.aff_id = ?
                            AND k.aff_id = ?
                            {AND date >= ?}
                            {AND date <= ?}
                            {AND `value` = ?}
                        GROUP BY keyword_id) s
            ON DUPLICATE KEY UPDATE sales_amount = s.cnt
CUT
            , $uid, $uid, $from ?: DBSIMPLE_SKIP, $to ?: DBSIMPLE_SKIP, $kw ?: DBSIMPLE_SKIP);

        //keyword
        $db->query(<<<CUT
            UPDATE ?_aff_keywords_tmp t
                SET keyword = (SELECT value FROM ?_aff_keyword s WHERE s.keyword_id = t.keyword_id)
CUT
        );
        $res = [];
        $q = $db->queryResultOnly("SELECT * FROM ?_aff_keywords_tmp");
        while ($row = $db->fetchRow($q))
        {
            foreach (['clicks_count', 'leads_count', 'sales_count', 'sales_amount'] as $_) {
                $row[$_] = (float)$row[$_];
            }
            $res[] = json_decode(json_encode($row), false);
        }
        return $res;
    }

    public function keywordsAction()
    {
        $values = $this->getDi()->cacheFunction->call([$this, 'getKeywordsReport'], [$this->getDi()->auth->getUserId()], [], 120);

        if ($values) {
            $ds = new Am_Grid_DataSource_Array($values);

            $grid = new Am_Grid_ReadOnly('_aff_keywords', 'Keywords', $ds, $this->getRequest(), $this->getView());
            $grid->addField('keyword', ___('Keyword'));
            $grid->addField('clicks_count', ___('Clicks'));
            $grid->addField('clicks_unique_count', ___('Unique Clicks'));
            $grid->addField('leads_count', ___('Leads'));
            $grid->addField('sales_count', ___('Sales'));
            $grid->addField('sales_amount', ___('Commissions'))->setRenderFunction(
                function($record){
                    return "<td>". Am_Currency::render($record->sales_amount)."</td>";
                }
            );
            $grid->runWithLayout('aff/keywords.phtml');
        } else {
            $this->view->content = sprintf(<<<CUT
<div class="am-block-nodata">%s</div>
CUT
                , ___('There is not any keywords stats for your account yet'));
            $this->view->display('aff/keywords.phtml');
        }
    }
}