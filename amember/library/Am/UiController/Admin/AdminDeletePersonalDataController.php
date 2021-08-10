<?php

class AdminDeletePersonalDataController extends Am_Mvc_Controller_Grid
{
    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->hasPermission('grid_u');
    }

    public function createGrid()
    {
        $query = new Am_Query($this->getDi()->userDeleteRequestTable);

        $query->leftJoin('?_user', 'u', 't.user_id=u.user_id');
        $query->leftJoin('?_admin', 'a', 't.admin_id = a.admin_id');

        $query->addField('u.login', 'login');
        $query->addField('a.login', 'admin');

        $query->setOrderRaw('completed, added');

        $grid = new Am_Grid_Editable('_user_delete_request', ___('Personal Data Delete Requests'), $query, $this->_request, $this->view);

        $grid->addField(new Am_Grid_Field_Date('added', ___('Added')));

        $grid->addField('user_id', ___('User'))
            ->setRenderFunction(function($rec)
            {
                return sprintf(
                    "<td><a href='%s' target='_top'>%s</a></td>", $this->getDi()->url('admin-users', ['_u_a' => 'edit', '_u_id' => $rec->user_id]), $rec->login
                );
            });

        $grid->addField('remote_addr', ___('IP address'));

        $grid->addField(new Am_Grid_Field_Expandable('errors', ___('Processing Errors')))->setGetFunction(function($record)
        {
            return !empty($record->errors)? "<pre>" . $record->errors . "</pre>" : "&nbsp;";
        })->setSafeHtml(true);

        $grid->addField(new Am_Grid_Field_Date('processed', ___('Time Processed')));
        $grid->addField('admin', ___('Processed by admin'));

        $grid->addCallback(Am_Grid_Editable::CB_TR_ATTRIBS, function(& $ret, $record)
        {
            if ($record->completed)
            {
                $ret['class'] = isset($ret['class']) ? $ret['class'] . ' disabled' : 'disabled';
            }
        });

        $grid->actionsClear();
        $grid->actionAdd(new Am_Grid_Action_Process());
        $grid->addCallback(Am_Grid_Editable::CB_RENDER_STATIC, function(&$out){
            $out = <<<CUT
<pre>
When you click to "process" Delete Request, amember will try to 
    cancel all user's active recurring invoices, 
    unsubscribe user from all newsletter lists, 
    and remove user from all linked third-party scripts. 
On success user's personal data will be anonymized. 
If aMember was unable to cancel invoices/subscirptions automatically, 
you will need to review errors and do everything that is necessary to cancel/unsubscribe manually, 
and then run anonymize process again. 
</pre>
CUT;
        });

        return $grid;
    }

}

class Am_Grid_Action_Process extends Am_Grid_Action_Anonymize
{
    function isAvailable($record)
    {
        return !$record->completed;
    }
}