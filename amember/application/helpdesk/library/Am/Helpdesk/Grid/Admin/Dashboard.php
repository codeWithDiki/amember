<?php

class Am_Helpdesk_Grid_Admin_Dashboard extends Am_Helpdesk_Grid_Admin
{
    function init()
    {
        parent::init();
        $this->addCallback(Am_Grid_ReadOnly::CB_RENDER_STATIC, function(& $out, $grid) {
            $url = json_encode($this->getDi()->url('helpdesk/admin/p/view/checklock'));
            $out .= <<<CUT
<script type="text/javascript">
    function amHelpdeskCheckLock()
    {
        jQuery.get({$url}, function(data) {
           jQuery('.am-helpdesk-grid-worker').remove();
           data.forEach(function(el) {
              jQuery('[data-ticket_mask=' + el.ticket_mask + ']').append(`<div class="am-helpdesk-grid-worker" title="currently working on this ticket">\${el.lock_admin}</div>`);
           });
        });
    }
    amHelpdeskCheckLock();
    setInterval(amHelpdeskCheckLock, 1000 * 30);
</script>
CUT;

        });
    }

    protected function createDs()
    {
        $q = parent::createDS();
        $q->addWhere('t.status<>?', HelpdeskTicket::STATUS_CLOSED);
        return $q;
    }

    public function initActions()
    {
        parent::initActions();
        $this->actionAdd(new Am_Grid_Action_Ticket());
        $this->actionAdd(new Am_Grid_Action_Delete());
        if ($cnt = $this->getDi()->helpdeskTicketTable->countByStatus(HelpdeskTicket::STATUS_CLOSED)) {
            $this->actionAdd(new Am_Grid_Action_Url('archive', ___('Closed Tickets') . " ($cnt)",
                $this->getDi()->url('helpdesk/admin/archive', false)))
                ->setType(Am_Grid_Action_Abstract::NORECORD)
                ->setTarget('_top')
                ->setCssClass('link');
        }
    }
}