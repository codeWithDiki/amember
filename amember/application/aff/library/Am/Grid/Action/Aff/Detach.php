<?php

class Am_Grid_Action_Aff_Detach extends Am_Grid_Action_Group_Abstract
{
    protected $permission = Bootstrap_Aff::ADMIN_PERM_ID;

    public function __construct($id = null, $title = null)
    {
        $this->title = ___("Detach Affiliate");
        parent::__construct($id, $title);
    }

    public function handleRecord($id, $record)
    {
        $record->aff_id = null;
        $record->aff_added = null;
        $record->data()->set('aff-source', null);
        $record->save();
    }
}