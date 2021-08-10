<?php

class Am_Helpdesk_Grid_Admin extends Am_Helpdesk_Grid
{
    public function initGridFields()
    {
        /* @var $ds Am_Query */
        $ds = $this->getDataSource();
        $ds->leftJoin('?_admin', 'a', 't.owner_id=a.admin_id')
            ->addField("CONCAT(a.login, ' (',a.name_f, ' ', a.name_l, ')')", 'owner');

        if ($this->getDi()->plugins_misc->isEnabled('avatar') &&
            $this->getDi()->modules->get('helpdesk')->getConfig('show_avatar')) {

            $this->addField('avatar', '', false, '', [$this, 'renderAvatar'], '1%');
        } elseif ($this->getDi()->modules->get('helpdesk')->getConfig('show_gravatar')) {
            $this->addField('gravatar', '', false, '', [$this, 'renderGravatar'], '1%');
        }

        $this->addField('subject', ___('Subject'), true, '', [$this, 'renderSubject']);
        $this->addField(new Am_Grid_Field_Date('created', ___('Created')))->setFormatDate();
        $this->addField('updated', ___('Updated'), true, '', [$this, 'renderTime']);
        $this->addField('m_login', ___('User'), true, '', [$this, 'renderUser']);
        if ($this->getDi()->db->selectCell("SELECT COUNT(*) FROM ?_helpdesk_ticket WHERE owner_id IS NOT NULL")) {
            $this->addField('owner_id', ___('Owner'), true, '', [$this, 'renderOwner']);
        }
        $this->addField('ticket_mask', ___('Ticket#'));
        $this->addField('status', ___('Status'), true, '', [$this, 'renderStatus'], '1%');

        $this->addCallback(Am_Grid_ReadOnly::CB_TR_ATTRIBS, [$this, 'cbGetTrAttribs']);
    }

    public function initActions()
    {
        parent::initActions();
        $this->actionAdd(new Am_Grid_Action_Delete);
        $this->actionAdd(new Am_Grid_Action_Group_CloseTicket);
        $this->actionAdd(new Am_Grid_Action_Group_MassTicketReply);
        $this->actionAdd(new Am_Grid_Action_Group_Delete);
    }

    public function getStatusIconId($id, $record)
    {
        return ($id == 'awaiting' && $record->status == HelpdeskTicket::STATUS_AWAITING_ADMIN_RESPONSE ?
            $id . '-me' : $id);
    }

    public function renderOwner($record)
    {
        return $record->owner_id ?
            sprintf('<td>%s</td>', Am_Html::escape(str_replace(' ( )', '', $record->owner))) :
            '<td></td>';
    }

    public function renderGravatar($record)
    {
        return sprintf('<td><div style="margin:0 auto; width:30px; height:30px; border-radius:50%%; overflow: hidden; box-shadow: 0 2px 4px #d0cfce;"><img src="%s" with="30" height="30" /></div></td>',
            '//www.gravatar.com/avatar/' . md5(strtolower(trim($record->m_email))) . '?s=30&d=mm');
    }

    public function renderAvatar($record)
    {
        return sprintf('<td><div style="margin:0 auto; width:30px; height:30px; border-radius:50%%; overflow: hidden; box-shadow: 0 2px 4px #d0cfce;"><img src="%s" with="30" height="30" /></div></td>',
            $this->getDi()->url('misc/avatar/' . $record->m_avatar));
    }

    protected function isNotImportant($record)
    {
        return $record->status == HelpdeskTicket::STATUS_AWAITING_USER_RESPONSE;
    }
}