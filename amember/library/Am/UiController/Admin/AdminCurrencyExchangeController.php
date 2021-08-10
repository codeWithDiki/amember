<?php

class Am_Grid_Filter_CurrencyExchange extends Am_Grid_Filter_Abstract
{
    protected $cOptions;

    function __construct($cOptions)
    {
        $this->cOptions = $cOptions;
    }

    protected function applyFilter()
    {
        $filter = $this->getParam('filter');
        if (!empty($filter['currency']))
        {
            $base = Am_Currency::getDefault();
            $this->grid->getDataSource()->addWhere("IF(base='{$base}', currency, base)=?", $filter['currency']);
        }
    }

    public function renderInputs()
    {
        $gridId = $this->grid->getId();
        $options = array_merge([''=>''], array_combine($this->cOptions, $this->cOptions));
        array_remove_value($options, Am_Currency::getDefault());
        $filter = $this->getParam('filter');
        return sprintf("<select name='{$gridId}_filter[currency]'>\n%s\n</select>\n",
            Am_Html::renderOptions($options, @$filter['currency']));
    }
}

class AdminCurrencyExchangeController extends Am_Mvc_Controller_Grid
{
    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->isSuper();
    }
    public function createGrid()
    {
        $base = Am_Currency::getDefault();

        $ds = new Am_Query($this->getDi()->currencyExchangeTable);
        $ds->addField("IF(base='{$base}', currency, base)", 'r_currency')
            ->addField("ROUND(IF(base='{$base}', rate, 1/rate), 6)", 'r_rate')
            ->addWhere("IF(base='{$base}', currency, base)<>?", $base)
            ->setOrder('date', true);

        $grid = new Am_Grid_Editable('_curr', ___('Currency Exchange Rates'), $ds, $this->_request, $this->view);

        if ($curr = $this->getDi()->db->selectCol("SELECT DISTINCT currency FROM ?_currency_exchange;")) {
            $grid->setFilter(new Am_Grid_Filter_CurrencyExchange($curr));
        }
        $grid->addField('r_currency', ___('Currency'));
        $grid->addField(new Am_Grid_Field_Date('date', ___('Date')))->setFormatDate();
        $grid->addField('r_rate', ___('Exchange Rate'), true, 'right');
        $grid->setForm([$this, 'createForm']);
        return $grid;
    }
    public function createForm()
    {
        $form = new Am_Form_Admin;
        $options = Am_Currency::getSupportedCurrencies();
        array_remove_value($options, Am_Currency::getDefault());

        $form->addSelect('currency', ['class' => 'am-combobox'])
            ->setLabel(___('Currency'))
            ->loadOptions($options)
            ->addRule('required');

        $form->addDate('date')->setLabel(___('Date'))
            ->addRule('required')
            ->addRule('callback2', "--wrong date--", [$this, 'checkDate']);

        $form->addText('rate', ['length' => 8])
            ->setLabel(___("Exchange Rate\nenter cost of 1 (one) %s", Am_Currency::getDefault()))
            ->addRule('required');

        return $form;
    }

    public function checkDate($date)
    {
        if ($date < $this->getDi()->sqlDate) return ___('You can not set up exchange rate for past.');
    }
}
