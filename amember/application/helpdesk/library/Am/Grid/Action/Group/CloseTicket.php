<?php

class Am_Grid_Action_Group_CloseTicket extends Am_Grid_Action_Group_Abstract
{
    function getTitle()
    {
        return ___('Close Ticket(s)');
    }

    public function handleRecord($id, $record)
    {
        $ticket = $this->grid->getDi()->helpdeskTicketTable->load($id);
        $ticket->status = HelpdeskTicket::STATUS_CLOSED;
        $ticket->save();
    }
}
