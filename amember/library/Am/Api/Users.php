<?php

class Am_Api_Users extends Am_ApiController_Table
{
    use Am_Mvc_Controller_User_Create;

    protected $_nested = [
        'invoices' => true,
        'access' => true,
        'user-consent' => true,
    ];

    protected function prepareRecordForDisplay(Am_Record $rec, $request)
    {
        $rec->pass = null;
        return parent::prepareRecordForDisplay($rec, $request);
    }

    public function createTable()
    {
        return $this->getDi()->userTable;
    }

    function createRecord($vars)
    {
        return $this->createUser($vars);
    }

    public function setForInsert(Am_Record $record, array $vars)
    {
        if (isset($vars['pass']))
        {
            $record->setPass($vars['pass']);
            unset($vars['pass']);
        }
        parent::setForInsert($record, $vars);
    }

    public function setForUpdate(Am_Record $record, array $vars)
    {
        if (isset($vars['pass']))
        {
            $record->setPass($vars['pass']);
            unset($vars['pass']);
        }
        parent::setForUpdate($record, $vars);
    }
}