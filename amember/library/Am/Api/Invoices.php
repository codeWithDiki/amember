<?php

class Am_Api_Invoices extends Am_ApiController_Table
{
    protected $_nested = [
        'invoice-items' => true,
        'invoice-payments' => true,
        'invoice-refunds' => true,
        'access' => true,
    ];

    protected $_defaultNested = [
        'invoice-items',
        'invoice-payments',
        'invoice-refunds',
        'access',
    ];

    public function setInsertNested(Am_Record $record, array $vars)
    {
        if (empty($this->_nestedInput['invoice-items']))
            throw new Am_Exception_InputError("At least one invoice-items must be passed to create invoice");
    }

    public function insertNested(Am_Record $record, array $vars, $request, $response, $args)
    {
        parent::insertNested($record, $vars, $request, $response, $args);
        $this->record->calculate()->update();
    }
}
