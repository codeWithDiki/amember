<?php

/*
 *
 *
 *     Author: Alex Scott
 *      Email: alex@cgi-central.net
 *        Web: http://www.cgi-central.net
 *    Details: Admin Payments
 *    FileName $RCSfile$
 *    Release: 6.3.6 ($Revision$)
 *
 * Please direct bug reports,suggestions or feedback to the cgi-central forums.
 * http://www.cgi-central.net/forum/
 *
 * aMember PRO is a commercial software. Any distribution is strictly prohibited.
 *
 */

abstract class Am_Grid_Filter_Payments_Abstract extends Am_Grid_Filter_Abstract
{
    protected $isInvoiceJoin = false;

    public function isFiltered()
    {
        foreach ((array) $this->vars['filter'] as $v) {
            if ($v) return true;
        }
    }

    public function setDateField($dateField)
    {
        $this->dateField = $dateField;
    }

    protected function applyFilter()
    {
        class_exists('Am_Form', true);
        $filter = (array) $this->vars['filter'];
        $q = $this->grid->getDataSource();

        $dateField = $this->vars['filter']['datf'];
        if (!array_key_exists($dateField, $this->getDateFieldOptions()))
            throw new Am_Exception_InternalError(sprintf('Unknown date field [%s] submitted in %s::%s',
                    $dateField, __CLASS__, __METHOD__));
        /* @var $q Am_Query */
        if ($filter['dat1']) {
            $q->addWhere("t.$dateField >= ?", Am_Form_Element_Date::convertReadableToSQL($filter['dat1']) . ($dateField == 'rebill_date' ? '' : ' 00:00:00'));
        }
        if ($filter['dat2']) {
            $q->addWhere("t.$dateField <= ?", Am_Form_Element_Date::convertReadableToSQL($filter['dat2']) . ($dateField == 'rebill_date' ? '' : ' 23:59:59'));
        }
        if (@$filter['text']) {
            switch (@$filter['type']) {
                case 'invoice':
                    if ($q->getTableName() == '?_invoice') {
                        $q->leftJoin('?_invoice_payment', 'p');
                        $q->leftJoin('?_invoice_refund', 'rf');
                        $q->addWhere('(t.invoice_id=? OR t.invoice_id IN (?a) OR t.public_id=? OR p.display_invoice_id=? OR rf.display_invoice_id=?)', $filter['text'], explode(',', $filter['text']), $filter['text'], $filter['text'], $filter['text']);
                    } else {
                        $q->addWhere('(t.invoice_id=? OR t.invoice_id IN (?a) OR t.invoice_public_id=? or t.display_invoice_id=?)', $filter['text'], explode(',', $filter['text']), $filter['text'], $filter['text']);
                    }
                    break;
                case 'login':
                    $q->addWhere('login=?', $filter['text']);
                    break;
                case 'name':
                    $q->addWhere("name_f LIKE ? OR name_l LIKE ?
                        OR CONCAT(name_f, ' ', name_l) LIKE ?
                        OR CONCAT(name_l, ' ', name_f) LIKE ?",
                        '%' . $filter['text'] . '%',
                        '%' . $filter['text'] . '%',
                        '%' . $filter['text'] . '%',
                        '%' . $filter['text'] . '%');
                    break;
                case 'receipt':
                    if ($q->getTableName() == '?_invoice') {
                        $q->leftJoin('?_invoice_payment', 'p');
                    }
                    $q->addWhere('receipt_id LIKE ?', '%' . $filter['text'] . '%');
                    break;
                case 'coupon':
                    if ($q->getTableName() != '?_invoice') {
                        $this->isInvoiceJoin = true;
                        $q->leftJoin('?_invoice', 'i', 't.invoice_id=i.invoice_id');
                    }
                    $q->addWhere('coupon_code=?', $filter['text']);
                    break;
                case 'payment_ids' :
                    if ($q->getTableName() == '?_invoice') {
                        $q->leftJoin('?_invoice_payment', 'p');
                    }
                    $q->addWhere('invoice_payment_id IN (?a)', explode(",", $filter['text']));
                    break;
                case 'items':
                    $q->addWhere(<<<CUT
                        EXISTS (SELECT invoice_item_id
                            FROM ?_invoice_item ii
                            WHERE ii.invoice_id=t.invoice_id
                                AND ii.item_title LIKE ?
                        )
CUT
                        , "%{$filter['text']}%");
                    break;
            }
        }
        if (@$filter['product_id']) {
            $pids = $this->grid->getDi()->productTable->extractProductIds($filter['product_id']);
            $pids[] = -1;
            $q->addWhere(<<<CUT
                EXISTS (SELECT invoice_item_id
                    FROM ?_invoice_item ii
                    WHERE ii.invoice_id=t.invoice_id
                        AND ii.item_type='product'
                        AND ii.item_id IN (?a)
                )
CUT
                , $pids);
        }
        if (@$filter['paysys_id']) {
            $q->addWhere('t.paysys_id IN (?a)', $filter['paysys_id']);
        }
    }

    public function renderInputs()
    {
        $prefix = $this->grid->getId();

        $filter = (array) $this->vars['filter'];
        $filter['datf'] = Am_Html::escape(@$filter['datf']);
        $filter['dat1'] = Am_Html::escape(@$filter['dat1']);
        $filter['dat2'] = Am_Html::escape(@$filter['dat2']);
        $filter['text'] = Am_Html::escape(@$filter['text']);

        $pOptions = [];
        $pOptions = $pOptions + Am_Di::getInstance()->productTable->getProductOptions();
        $pOptions = Am_Html::renderOptions($pOptions, @$filter['product_id']);

        $paysysOptions = [];
        $paysysOptions = $paysysOptions +
            Am_Di::getInstance()->paysystemList->getOptions();
        $paysysOptions = Am_Html::renderOptions(
                $paysysOptions,
                @$filter['paysys_id']
        );

        $options = Am_Html::renderOptions([
                'invoice' => ___('Invoice Number'),
                'receipt' => ___('Payment Receipt'),
                'login' => ___('Username'),
                'name' => ___('Name'),
                'coupon' => ___('Coupon Code'),
                'items' => ___('Item Title'),
                'payment_ids' => ___('Payment Ids'),
            ], @$filter['type']);

        $dOptions = $this->getDateFieldOptions();
        if (count($dOptions) === 1) {
            $dSelect = sprintf('%s: <input type="hidden" name="%s_filter[datf]" value="%s" />',
                    current($dOptions), $prefix, key($dOptions));
        } else {
            $dSelect = sprintf('<select name="%s_filter[datf]">%s</select>', $prefix,
                    Am_Html::renderOptions($dOptions, @$filter['datf']));
        }

        $start = ___('Start Date');
        $end = ___('End Date');
        $offer_product = '-' . ___('Filter by Product') . '-';
        $offer_paysys = '-' . ___('Filter by Paysystem') . '-';
        return <<<CUT
<div style='display:table-cell; padding-right:0.4em; padding-bottom:0.4em; width:160px; box-sizing:border-box;'>
<select name="{$prefix}_filter[product_id][]" style="width:160px" class="magicselect am-combobox" multiple="multiple" data-offer='$offer_product'>
$pOptions
</select>
</div>
<div style='display:table-cell; padding-right:0.4em; padding-bottom:0.4em; width:160px; box-sizing:border-box;'>
<select name="{$prefix}_filter[paysys_id][]" style="width:160px" class="magicselect" multiple="multiple" data-offer='$offer_paysys'>
$paysysOptions
</select>
   </div>
<div style='display:table-cell; padding-bottom:0.4em;'>
$dSelect
<input type="text" placeholder="$start" name="{$prefix}_filter[dat1]" autocomplete="off" class="datepicker" style="width:100px" value="{$filter['dat1']}" />
<input type="text" placeholder="$end" name="{$prefix}_filter[dat2]" autocomplete="off" class="datepicker" style="width:100px" value="{$filter['dat2']}" />
</div>
<input type="text" name="{$prefix}_filter[text]" value="{$filter['text']}" style="width:380px; margin-bottom:.4em" />
<select name="{$prefix}_filter[type]" style="margin-bottom:.4em">
$options
</select>
CUT;
    }

    public function getDateFieldOptions()
    {
        return ['dattm' => ___('Payment Date')];
    }
}

class Am_Grid_Filter_Payments extends Am_Grid_Filter_Payments_Abstract
{
    public function renderInputs()
    {
        $prefix = $this->grid->getId();
        $filter = (array) $this->vars['filter'];
        $tSelect = sprintf('<select name="%s_filter[ptype]">%s</select>',
                    $prefix,
                    Am_Html::renderOptions([
                        '' => '-' . ___('Filter by Type') . '-',
                        'sale' => ___('Sales'),
                        'rebill' => ___('Rebills')
                    ], @$filter['ptype']));

        return parent::renderInputs() . '<br />' .
            "{$tSelect} " .
            $this->renderDontShowRefunded();
    }

    public function renderDontShowRefunded()
    {
        return sprintf('<label>
                <input type="hidden" name="%s_filter[dont_show_refunded]" value="0" />
                <input type="checkbox" name="%s_filter[dont_show_refunded]" value="1" %s /> %s</label>',
            $this->grid->getId(), $this->grid->getId(),
            (!empty($this->vars['filter']['dont_show_refunded']) ? 'checked' : ''),
            Am_Html::escape(___('hide refunded payments'))
        );
    }

    protected function applyFilter()
    {
        parent::applyFilter();
        $filter = (array) $this->vars['filter'];
        $q = $this->grid->getDataSource();
        if (!empty($filter['dont_show_refunded'])) {
            $q->addWhere('t.refund_dattm IS NULL');
        }
        if (!empty($filter['ptype'])) {
            if (!$this->isInvoiceJoin) {
                $q->leftJoin('?_invoice', 'i', 't.invoice_id=i.invoice_id');
                $this->isInvoiceJoin = true;
            }
            switch ($filter['ptype']) {
                case 'sale':
                    $q->addWhere('DATE(t.dattm)=DATE(i.tm_started)');
                    break;
                case 'rebill':
                    $q->addWhere('DATE(t.dattm)<>DATE(i.tm_started)');
                    break;
            }
        }
    }
}

class Am_Grid_Filter_Invoices extends Am_Grid_Filter_Payments_Abstract
{
    public function renderInputs()
    {
        return parent::renderInputs() . '<br />' . $this->renderAdditioanlInputs();
    }

    protected function renderAdditioanlInputs()
    {
        $filter = (array) $this->vars['filter'];
        $prefix = $this->grid->getId();

        $statusOptions = [];
        foreach (Invoice::$statusText as $k => $v) {
            $statusOptions[$k] = ___($v);
        }

        $statusOptions = Am_Html::renderOptions($statusOptions, @$filter['status']);
        $offer_status = '-' . ___('Filter by Status') . '-';

        $status = <<<CUT
<select name="{$prefix}_filter[status][]" class="magicselect" multiple="multiple" data-offer='$offer_status'>
$statusOptions
</select>
CUT;
        $pending = sprintf('<label>
                <input type="hidden" name="%s_filter[dont_show_pending]" value="0" />
                <input type="checkbox" name="%s_filter[dont_show_pending]" value="1" %s /> %s</label>',
            $this->grid->getId(), $this->grid->getId(),
            (@$this->vars['filter']['dont_show_pending'] == 1 ? 'checked' : ''),
            Am_Html::escape(___('hide pending invoices'))
        );
        $active = sprintf('<label>
                <input type="hidden" name="%s_filter[show_only_active]" value="0" />
                <input type="checkbox" name="%s_filter[show_only_active]" value="1" %s /> %s</label>',
            $this->grid->getId(), $this->grid->getId(),
            (@$this->vars['filter']['show_only_active'] == 1 ? 'checked' : ''),
            Am_Html::escape(___('hide invoices without active access'))
        );
        $cancelUpgraded = sprintf('<label>
                <input type="hidden" name="%s_filter[dont_show_upgraded]" value="0" />
                <input type="checkbox" name="%s_filter[dont_show_upgraded]" value="1" %s /> %s</label>',
            $this->grid->getId(), $this->grid->getId(),
            (@$this->vars['filter']['dont_show_upgraded'] == 1 ? 'checked' : ''),
            Am_Html::escape(___('hide canceled by upgrade invoices'))
        );

        return <<<CUT
<div style='display:table-cell; padding-right:0.4em; padding-bottom:0.4em; width:160px; box-sizing:border-box;'>
    $status
</div>
<div style='display:table-cell; padding-bottom:0.4em; vertical-align:top'>
    $pending<br />$active<br />$cancelUpgraded
</div>
CUT;
    }

    protected function applyFilter()
    {
        parent::applyFilter();
        $filter = (array) $this->vars['filter'];
        $q = $this->grid->getDataSource();
        if (@$filter['dont_show_upgraded'])
            $q->addWhere('t.invoice_id NOT IN (SELECT `id` FROM ?_data WHERE `table` = "invoice" AND `key` = ?)', Invoice::UPGRADE_INVOICE_ID);
        if (@$filter['dont_show_pending'])
            $q->addWhere('t.status<>?', Invoice::PENDING);
        if(@$filter['show_only_active']) {
            $curdate = $this->grid->getDi()->sqlDate;
            $q->addWhere(<<<CUT
EXISTS (SELECT access_id FROM ?_access WHERE invoice_id = t.invoice_id AND begin_date<='{$curdate}' AND expire_date>='{$curdate}')
CUT
            );
        }
        if (isset($filter['status']) && $filter['status']) {
            $q->addWhere('t.status IN (?a)', $filter['status']);
        }
    }

    public function getDateFieldOptions()
    {
        return [
            'tm_added' => ___('Added'),
            'tm_started' => ___('Started'),
            'tm_cancelled' => ___('Cancelled'),
            'rebill_date' => ___('Rebill Date'),
        ];
    }
}

class Am_Grid_Filter_Refunds extends Am_Grid_Filter_Payments_Abstract
{
    public function getDateFieldOptions()
    {
        return ['dattm' => ___('Refund Date')];
    }
}

class AdminPaymentsController extends Am_Mvc_Controller_Pages
{
    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->hasPermission('grid_payment') || $admin->hasPermission('grid_invoice');
    }

    public function initPages()
    {
        $admin = $this->getDi()->authAdmin->getUser();

        if ($admin->hasPermission('grid_payment'))
            $this->addPage([$this, 'createPaymentsPage'], 'index', ___('Payment'));

        if ($admin->hasPermission('grid_payment'))
            $this->addPage([$this, 'createRefundsPage'], 'refunds', ___('Refund'));

        if ($admin->hasPermission('grid_invoice')) {
            $this->addPage([$this, 'createInvoicesPage'], 'invoices', ___('Invoice'));
            if ($this->getDi()->config->get('manually_approve_invoice'))
                $this->addPage([$this, 'createInvoicesPage'], 'not-approved', ___('Not Approved'));
        }
    }

    private function _addTaxAddressToQuery(Am_Query $query): Am_Query
    {
        $query->leftJoin('?_address', 'a', 'a.address_id=t.tax_address_id')
            ->addField('a.name_f', 'tax_name_f')
            ->addField('a.name_l', 'tax_name_l')
            ->addField('a.street', 'tax_street')
            ->addField('a.street2', 'tax_street2')
            ->addField('a.city', 'tax_city')
            ->addField('a.state', 'tax_state')
            ->addField('a.zip', 'tax_zip')
            ->addField('a.country', 'tax_country');
        return $query;
    }

    private function _addTaxAddressToExport(Am_Grid_Action_Export  $action) : Am_Grid_Action_Export
    {
        $action
        ->addField(new Am_Grid_Field('tax_name_f', ___('Tax First Name')))
        ->addField(new Am_Grid_Field('tax_name_l', ___('Tax Last Name')))
        ->addField(new Am_Grid_Field('tax_street', ___('Tax Street')))
        ->addField(new Am_Grid_Field('tax_street2', ___('Tax Street2')))
        ->addField(new Am_Grid_Field('tax_city', ___('Tax City')))
        ->addField(new Am_Grid_Field('tax_state', ___('Tax State')))
        ->addField(new Am_Grid_Field('tax_zip', ___('Tax Zip')))
        ->addField(new Am_Grid_Field('tax_country', ___('Tax Country')));
        return $action;
    }

    function createPaymentsPage()
    {
        $totalFields = [];

        $query = new Am_Query($this->getDi()->invoicePaymentTable);
        $query->leftJoin('?_user', 'm', 'm.user_id=t.user_id')
            ->addField("(SELECT GROUP_CONCAT(IF(qty > 1, CONCAT(item_title, ' (', qty, ' pcs)' ), item_title)  SEPARATOR ', ') FROM ?_invoice_item WHERE invoice_id=t.invoice_id)", 'items')
            ->addField('m.login', 'login')
            ->addField('m.email', 'email')
            ->addField('m.street', 'street')
            ->addField('m.street2', 'street2')
            ->addField('m.city', 'city')
            ->addField('m.state', 'state')
            ->addField('m.country', 'country')
            ->addField('m.phone', 'phone')
            ->addField('m.zip', 'zip')
            ->addField("CONCAT(m.name_f,' ',m.name_l)", 'name')
            ->addField('m.name_f')
            ->addField('m.name_l')
            ->addField('m.remote_addr', 'm_remote_addr')
            ->addField('m.last_ip', 'last_ip')
            ->addField('DATE(dattm)', 'date')
            ->addField('t.invoice_public_id', 'public_id')
            ->addField('t.amount - IFNULL(t.refund_amount, 0)', 'balance');

        $this->_addTaxAddressToQuery($query);

        //Additional Fields
        foreach ($this->getDi()->userTable->customFields()->getAll() as $field) {
            if (isset($field->from_config) && $field->from_config) {
                if ($field->sql) {
                    $query->addField('m.'.$field->name, $field->name);
                }
            }
        }
        $query->setOrder("invoice_payment_id", true);

        $grid = new Am_Grid_Editable('_payment', ___('Payments'), $query, $this->_request, $this->view);
        $grid->actionsClear();
        $grid->addField(new Am_Grid_Field_Date('dattm', ___('Date/Time')));

        $grid->addField('invoice_id', ___('Invoice'))
            ->setGetFunction([$this, '_getInvoiceNum'])
            ->addDecorator(
                new Am_Grid_Field_Decorator_Link(
                    'admin-user-payments/index/user_id/{user_id}#invoice-{invoice_id}', '_top'));

        if ($this->getDi()->plugins_tax->isEnabled('vat2015') &&
            $this->getDi()->plugins_tax->loadGet('vat2015')->getConfig('sequential')) {

            $grid->addField('display_invoice_id', ___('Invoice Number'));
            if ($this->getDi()->authAdmin->getUser()->isSuper()) {
                $grid->actionAdd(new Am_Grid_Action_LiveEdit('display_invoice_id'));
            }
        }

        $grid->addField('receipt_id', ___('Receipt'));
        $grid->addField('paysys_id', ___('Payment System'));
        array_push($totalFields, $grid->addField('amount', ___('Amount'), true, 'right')
            ->setGetFunction([$this, 'getAmount']));

        if ($this->getDi()->plugins_tax->getEnabled()) {
            array_push($totalFields, $grid->addField('tax', ___('Tax'), true, 'right')
                ->setGetFunction([$this, 'getTax']));
        }
        $grid->addField(new Am_Grid_Field_Expandable('refund_amount', ___('Refunded')))
            ->setPlaceholder(function($amt, $r){
                return sprintf('<div class="red" style="text-align:right">&minus;%s</div>', Am_Currency::render($amt, $r->currency));
            })
            ->setAjax($this->getDi()->url('admin-payments/get-refunds?id={invoice_payment_id}',null,false))
            ->setIsNeedExpandFunction(function($val, $obj, $field, $fieldObj){
                return !is_null($obj->$field);
            });
        $grid->addField('items', ___('Items'));
        $_ = $grid->addField('login', ___('Username'));
        if ($this->getDi()->authAdmin->getUser()->hasPermission('grid_u', 'edit')) {
            $_->addDecorator(new Am_Grid_Field_Decorator_Link(
                'admin-users?_u_a=edit&_u_b={THIS_URL}&_u_id={user_id}', '_top'));
        }
        $grid->addField('name', ___('Name'));
        $grid->setFilter(new Am_Grid_Filter_Payments);

        $action = new Am_Grid_Action_Export();
        $action->addField(new Am_Grid_Field('dattm', ___('Date/Time')))
            ->addField(new Am_Grid_Field('date', ___('Date')))
            ->addField(new Am_Grid_Field('receipt_id', ___('Receipt')))
            ->addField(new Am_Grid_Field('paysys_id', ___('Payment System')))
            ->addField(new Am_Grid_Field('amount', ___('Amount')))
            ->addField(new Am_Grid_Field('tax', ___('Tax')))
            ->addField(new Am_Grid_Field('currency', ___('Currency')))
            ->addField(new Am_Grid_Field('refund_dattm', ___('Refunded Date/Time')))
            ->addField(new Am_Grid_Field('refund_amount', ___('Refunded Amount')))
            ->addField(new Am_Grid_Field('items', ___('Items')))
            ->addField(new Am_Grid_Field('invoice_id', ___('Invoice (Internal Id)')))
            ->addField(new Am_Grid_Field('public_id', ___('Invoice (Public Id)')))
            ->addField(new Am_Grid_Field('display_invoice_id', ___('Invoice (Sequential Receipt Number)')));

        $this->_addTaxAddressToExport($action);

        $this->addUserFields($action);
        $action->setGetDataSourceFunc([$this, 'getExportDsAll']);
        $grid->actionAdd($action);
        if ($this->getDi()->config->get('send_pdf_invoice')) {
            $grid->actionAdd(new Am_Grid_Action_ExportPdf);
        }
        $action = $grid->actionAdd(new Am_Grid_Action_Total());
        foreach ($totalFields as $f) {
            $action->addField($f, 'ROUND(t.%s / t.base_currency_multi, 2)');
        }
        $f = new Am_Grid_Field('balance', ___('Balance (Paid-Refund)'));
        $f->setGetFunction([$this, 'getAmount']);
        array_push($totalFields, $f);
        $action->addField($f, 'ROUND((t.amount - IFNULL(t.refund_amount, 0)) / t.base_currency_multi, 2)');

        $grid->setEventId('gridPayment');
        return $grid;
    }

    function getRefundsAction()
    {
        $this->getDi()->authAdmin->getUser()->checkPermission('grid_payment');

        $out = '';
        foreach ($this->getDi()->invoiceRefundTable->findByInvoicePaymentId($this->getParam('id')) as $r) {
            switch ($r->refund_type) {
                case InvoiceRefund::VOID :
                    $type = ___('void');
                    break;
                case InvoiceRefund::CHARGEBACK :
                    $type = ___('chargeback');
                    break;
                default :
                    $type = ___('refund');
            }
            $out .= sprintf('<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                amDatetime($r->dattm),
                Am_Html::escape($r->receipt_id),
                Am_Currency::render($r->amount, $r->currency),
                Am_Html::escape($type));
        }
        echo sprintf('<div><table class="grid">%s</table></div>', $out);
    }

    function getMultiSelect($obj, $controller, $field=null)
    {
        return implode(',', (array)@unserialize($obj->{$field}));
    }

    function getStateTitle($obj, $controller, $field=null)
    {
        return $this->getDi()->stateTable->getTitleByCode($obj->country, $obj->state);
    }

    function getCountryTitle($obj, $controller, $field=null)
    {
        return $this->getDi()->countryTable->getTitleByCode($obj->country);
    }

    function createRefundsPage()
    {
        $query = new Am_Query($this->getDi()->invoiceRefundTable);
        $query->leftJoin('?_user', 'm', 'm.user_id=t.user_id')
            ->addField("(SELECT GROUP_CONCAT(IF(qty > 1, CONCAT(item_title, ' (', qty, ' pcs)' ), item_title)  SEPARATOR ', ') FROM ?_invoice_item WHERE invoice_id=t.invoice_id)", 'items')
            ->addField('m.login', 'login')
            ->addField('m.email', 'email')
            ->addField('m.street', 'street')
            ->addField('m.street2', 'street2')
            ->addField('m.city', 'city')
            ->addField('m.state', 'state')
            ->addField('m.country', 'country')
            ->addField('m.phone', 'phone')
            ->addField('m.zip', 'zip')
            ->addField("CONCAT(m.name_f,' ',m.name_l)", 'name')
            ->addField('m.name_f')
            ->addField('m.name_l')
            ->addField('m.remote_addr', 'm_remote_addr')
            ->addField('m.last_ip', 'last_ip')
            ->addField('DATE(dattm)', 'date')
            ->addField('t.invoice_public_id', 'public_id');

        if($this->getDi()->plugins_tax->getEnabled())
        {
            $this->_addTaxAddressToQuery($query);
        }
        //Additional Fields
        foreach ($this->getDi()->userTable->customFields()->getAll() as $field) {
            if (isset($field->from_config) && $field->from_config) {
                if ($field->sql) {
                    $query->addField('m.'.$field->name, $field->name);
                }
            }
        }
        $query->setOrder("invoice_payment_id", true);

        $grid = new Am_Grid_Editable('_refund', ___('Refunds'), $query, $this->_request, $this->view);
        $grid->setPermissionId('grid_payment');
        $grid->actionsClear();
        $grid->addField(new Am_Grid_Field_Date('dattm', ___('Date/Time')));

        $grid->addField('invoice_id', ___('Invoice'))
            ->setGetFunction([$this, '_getInvoiceNum'])
            ->addDecorator(
                new Am_Grid_Field_Decorator_Link(
                    'admin-user-payments/index/user_id/{user_id}#invoice-{invoice_id}', '_top'));
        $grid->addField('receipt_id', ___('Receipt'));
        $grid->addField('paysys_id', ___('Payment System'));
        $fieldAmount = $grid->addField('amount', ___('Amount'), true, 'right')
            ->setGetFunction([$this, 'getAmount']);

        if ($this->getDi()->plugins_tax->getEnabled()) {
            $grid->addField('tax', ___('Tax'), true, 'right')
                ->setGetFunction(array($this, 'getTax'));
        }

        $grid->addField('items', ___('Items'));
        $_ = $grid->addField('login', ___('Username'));
        if ($this->getDi()->authAdmin->getUser()->hasPermission('grid_u', 'edit')) {
            $_ ->addDecorator(new Am_Grid_Field_Decorator_Link(
                'admin-users?_u_a=edit&_u_b={THIS_URL}&_u_id={user_id}', '_top'));
        }
        $grid->addField('name', ___('Name'));
        $grid->setFilter(new Am_Grid_Filter_Refunds);

        $action = new Am_Grid_Action_Export();
        $action->addField(new Am_Grid_Field('dattm', ___('Date/Time')))
            ->addField(new Am_Grid_Field('date', ___('Date')))
            ->addField(new Am_Grid_Field('receipt_id', ___('Receipt')))
            ->addField(new Am_Grid_Field('paysys_id', ___('Payment System')))
            ->addField(new Am_Grid_Field('amount', ___('Amount')))
            ->addField(new Am_Grid_Field('tax', ___('Tax')))
            ->addField(new Am_Grid_Field('currency', ___('Currency')))
            ->addField(new Am_Grid_Field('items', ___('Items')))
            ->addField(new Am_Grid_Field('invoice_id', ___('Invoice (Internal Id)')))
            ->addField(new Am_Grid_Field('public_id', ___('Invoice (Public Id)')))
            ->addField(new Am_Grid_Field('display_invoice_id', ___('Refund (Sequential Receipt Number)')));

        if($this->getDi()->plugins_tax->getEnabled())
        {
            $this->_addTaxAddressToExport($action);
        }


        $this->addUserFields($action);
        $action->setGetDataSourceFunc([$this, 'getExportDsAll']);
        $grid->actionAdd($action);
        if ($this->getDi()->config->get('send_pdf_invoice')) {
            $grid->actionAdd(new Am_Grid_Action_ExportPdf);
        }

        $action = $grid->actionAdd(new Am_Grid_Action_Total());
        $action->addField($fieldAmount, 'ROUND(%s / t.base_currency_multi, 2)');
        $grid->setEventId('gridRefund');
        return $grid;
    }

    function getAmount(Am_Record $p)
    {
        return Am_Currency::render($p->amount, $p->currency);
    }

    function getTax(Am_Record $p) : string
    {
        return Am_Currency::render($p->tax, $p->currency);
    }

    function _getInvoiceNum(Am_Record $invoice)
    {
        return $invoice->invoice_id . '/' . $invoice->public_id;
    }

    function createInvoicesPage($page)
    {
        $query = new Am_Query($this->getDi()->invoiceTable);
        if ($page == 'not-approved') {
            $query->addWhere('is_confirmed<1');
        }
        $query->leftJoin('?_user', 'm', 'm.user_id=t.user_id')
            ->addField("(SELECT GROUP_CONCAT(IF(qty > 1, CONCAT(item_title, ' (', qty, ' pcs)' ), item_title)  SEPARATOR ', ') FROM ?_invoice_item WHERE invoice_id=t.invoice_id)", 'items')
            ->addField('m.login', 'login')
            ->addField('m.email', 'email')
            ->addField('m.street', 'street')
            ->addField('m.street2', 'street2')
            ->addField('m.city', 'city')
            ->addField('m.state', 'state')
            ->addField('m.country', 'country')
            ->addField('m.phone', 'phone')
            ->addField('m.zip', 'zip')
            ->addField("CONCAT(m.name_f,' ',m.name_l)", 'name')
            ->addField('m.name_f')
            ->addField('m.name_l')
            ->addField('m.remote_addr', 'm_remote_addr')
            ->addField('m.last_ip', 'last_ip')
            ->addField('DATE(tm_started)', 'started_date')
            ->addField('DATE(tm_added)', 'added_date');

        //Additional Fields
        foreach ($this->getDi()->userTable->customFields()->getAll() as $field) {
            if (isset($field->from_config) && $field->from_config) {
                if ($field->sql) {
                    $query->addField('m.'.$field->name, $field->name);
                }
            }
        }
        $query->setOrder("invoice_id", true);

        $grid = new Am_Grid_Editable('_invoice', ___('Invoices'), $query, $this->_request, $this->view);
        $grid->setEventId('gridInvoice');
        $grid->setRecordTitle([$this, 'getInvoiceRecordTitle']);
        $grid->actionsClear();
        $grid->actionAdd(new Am_Grid_Action_Delete())->setTarget('_top');
        $grid->addField(new Am_Grid_Field_Date('tm_added', ___('Added')));

        $grid->addField('invoice_id', ___('Invoice'))->setGetFunction([$this, '_getInvoiceNum'])->addDecorator(
            new Am_Grid_Field_Decorator_Link(
                'admin-user-payments/index/user_id/{user_id}#invoice-{invoice_id}', '_top')
        );
        $grid->addField('status', ___('Status'))->setRenderFunction([$this, 'renderInvoiceStatus']);
        $grid->addField('paysys_id', ___('Payment System'));
        $grid->addField('_total', ___('Billing Terms'), false)->setGetFunction([$this, 'getInvoiceTotal']);
        $grid->addField(new Am_Grid_Field_Date('rebill_date', ___('Rebill Date')))->setFormatDate();
        $grid->addField('items', ___('Items'));
        $_ = $grid->addField('login', ___('Username'));
        if ($this->getDi()->authAdmin->getUser()->hasPermission('grid_u', 'edit')) {
            $_->addDecorator(new Am_Grid_Field_Decorator_Link(
                'admin-users?_u_a=edit&_u_b={THIS_URL}&_u_id={user_id}', '_top'));
        }
        $grid->addField('name', ___('Name'));
        $filter = new Am_Grid_Filter_Invoices();
        $grid->setFilter($filter);

        $termsField = new Am_Grid_Field('_total', ___('Billing Terms'));
        $termsField->setGetFunction([$this, 'getInvoiceTotal']);

        $action = new Am_Grid_Action_Export();
        $action->addField(new Am_Grid_Field('tm_added', ___('Added (Date/Time)')))
            ->addField(new Am_Grid_Field('added_date', ___('Added (Date)')))
            ->addField(new Am_Grid_Field('tm_started', ___('Started (Date/Time)')))
            ->addField(new Am_Grid_Field('started_date', ___('Started (Date)')))
            ->addField(new Am_Grid_Field('rebill_date', ___('Rebill Date')))
            ->addField(new Am_Grid_Field('invoice_id', ___('Invoice (Internal Id)')))
            ->addField(new Am_Grid_Field('public_id', ___('Invoice (Public Id)')))
            ->addField(new Am_Grid_Field('status', ___('Status')))
            ->addField($termsField)
            ->addField(new Am_Grid_Field('paysys_id', ___('Payment System')))
            ->addField(new Am_Grid_Field('first_total', ___('First Total')))
            ->addField(new Am_Grid_Field('first_tax', ___('First Tax')))
            ->addField(new Am_Grid_Field('currency', ___('Currency')))
            ->addField(new Am_Grid_Field('item_title', ___('Product Title')))
            ->addField(new Am_Grid_Field('coupon_code', ___('Coupon')))
            ->addField(new Am_Grid_Field('comment', ___('Comment')));

        $this->addUserFields($action);

        $action->setGetDataSourceFunc([$this, 'getExportDs']);
        $grid->actionAdd($action);
        if ($this->getDi()->config->get('manually_approve_invoice')) {
            $grid->actionAdd(new Am_Grid_Action_Group_Callback('approve', ___("Approve"), [$this, 'approveInvoice']));
        }
        $action = $grid->actionAdd(new Am_Grid_Action_Total());
        foreach (['t.first_total' => ___('First'), 't.second_total' => ___('Second')] as $f => $title) {
            $action->addField(new Am_Grid_Field($f, $title), 'ROUND(%s / t.base_currency_multi, 2)');
        }

        return $grid;
    }

    public function addUserFields(Am_Grid_Action_Export $action)
    {
        $stateTitleField = new Am_Grid_Field('state_title', ___('State Title'));
        $stateTitleField->setGetFunction([$this, 'getStateTitle']);

        $countryTitleField = new Am_Grid_Field('country_title', ___('Country Title'));
        $countryTitleField->setGetFunction([$this, 'getCountryTitle']);

        $action->addField(new Am_Grid_Field('user_id', ___('User ID')))
            ->addField(new Am_Grid_Field('email', ___('Email')))
            ->addField(new Am_Grid_Field('login', ___('Username')))
            ->addField(new Am_Grid_Field('name', ___('Name')))
            ->addField(new Am_Grid_Field('name_f', ___('First Name')))
            ->addField(new Am_Grid_Field('name_l', ___('Last Name')))
            ->addField(new Am_Grid_Field('street', ___('Street')))
            ->addField(new Am_Grid_Field('street2', ___('Street2')))
            ->addField(new Am_Grid_Field('city', ___('City')))
            ->addField(new Am_Grid_Field('state', ___('State')))
            ->addField($stateTitleField)
            ->addField(new Am_Grid_Field('country', ___('Country')))
            ->addField($countryTitleField)
            ->addField(new Am_Grid_Field('phone', ___('Phone')))
            ->addField(new Am_Grid_Field('zip', ___('Zip Code')))
            ->addField(new Am_Grid_Field('m_remote_addr', ___('Registration IP')))
            ->addField(new Am_Grid_Field('last_ip', ___('Recent Login IP')));

        //Additional Fields
        foreach ($this->getDi()->userTable->customFields()->getAll() as $field) {
            if (isset($field->from_config) && $field->from_config) {
                if ($field->sql) {
                    if(in_array($field->type, ['multi_select','checkbox'])){
                        $f = new Am_Grid_Field($field->name, $field->title . ' (Value)');
                        $f->setGetFunction([$this,'getMultiSelect']);
                        $action->addField($f);

                        $op = $field->options;
                        $fn = $field->name;
                        $f = new Am_Grid_Field($field->name . '_label', $field->title . ' (Label)');
                        $f->setGetFunction(function($obj, $controller, $field=null) use ($op, $fn){
                            return implode(',', array_map(function($el) use ($op) {
                                return isset($op[$el]) ? $op[$el] : $el;
                            }, (array)@unserialize($obj->{$fn})));
                        });
                        $action->addField($f);

                    } else {
                        $action->addField(new Am_Grid_Field($field->name, $field->title));
                    }
                } else {
                    if(in_array($field->type, ['multi_select','checkbox'])){
                        //we use trailing __blob to distinguish multi select fields from data table
                        $mfield = new Am_Grid_Field($field->name . '__blob', $field->title . ' (Value)');
                        $mfield->setGetFunction([$this,'getMultiSelect']);
                        $action->addField($mfield);

                        $op = $field->options;
                        $fn = $field->name . '__blob';
                        $f = new Am_Grid_Field($field->name . '_label__blob', $field->title . ' (Label)');
                        $f->setGetFunction(function($obj, $controller, $field=null) use ($op, $fn){
                            return implode(',', array_map(function($el) use ($op) {
                                return isset($op[$el]) ? $op[$el] : $el;
                            }, (array)@unserialize($obj->{$fn})));
                        });
                        $action->addField($f);
                    } else {
                        //we use trailing __ to distinguish fields from data table
                        $action->addField(new Am_Grid_Field($field->name . '__', $field->title));
                    }
                }
            }
        }
    }

    public function getInvoiceRecordTitle(Invoice $invoice = null)
    {
        return $invoice ? sprintf('%s (%s/%s, %s, %s: %s)',
                ___('Invoice'), $invoice->pk(), $invoice->public_id,
                $invoice->getUser()->getName(),
                ___('Billing Terms'), new Am_TermsText($invoice)) :
            ___('Invoice');
    }

    public function getExportDs(Am_Query $ds, $fields)
    {
        foreach ($fields as $field) {
            if ($field->getFieldName() == 'item_title') {
                $ds->leftJoin('?_invoice_item', 'iititle', 'iititle.invoice_id=t.invoice_id')
                    ->addField('iititle.item_title', 'item_title');
                break;
            }
        }
        return $this->getExportDsAll($ds, $fields);
    }

    public function getExportDsAll(Am_Query $ds, $fields) {
        $i = 0;
        //join only selected fields
        foreach ($fields as $field) {
            $fn = $field->getFieldName();
            if (substr($fn, -6) == '__blob') { //multi select field from data table
                $i++;
                $field_name = substr($fn, 0, strlen($fn)-6);
                $ds = $ds->leftJoin("?_data", "d$i", "m.user_id = d$i.id AND d$i.table='user' AND d$i.key='$field_name'")
                    ->addField("d$i.blob", $fn);
            }
            if (substr($fn, -2) == '__') { //field from data table
                $i++;
                $field_name = substr($fn, 0, strlen($fn)-2);
                $ds = $ds->leftJoin("?_data", "d$i", "m.user_id = d$i.id AND d$i.table='user' AND d$i.key='$field_name'")
                    ->addField("d$i.value", $fn);
            }
        }
        return $ds;
    }

    public function getInvoiceTotal(Invoice $invoice)
    {
        return $invoice->getTerms();
    }

    public function renderInvoiceStatus(Invoice $invoice)
    {
        return '<td>' . $invoice->getStatusTextColor() . '</td>';
    }

    public function approveInvoice($id, Invoice $invoice)
    {
        $invoice->approve();
    }

    function invoiceCardAction()
    {
        $this->view->savedFormOptions = $this->getDi()->savedFormTable->getOptions();
        $this->view->invoice = $this->getDi()->invoiceTable->load($this->getParam('id'));
        $this->view->display('admin/invoice-card.phtml');
    }
}