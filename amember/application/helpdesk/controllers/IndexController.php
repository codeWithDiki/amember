<?php

class Am_Helpdesk_Grid_User extends Am_Helpdesk_Grid
{
    public function init()
    {
        $this->addCallback(Am_Grid_ReadOnly::CB_TR_ATTRIBS, function(&$attrs, $r){
            if ($r->has_new) {
                $attrs['class'] = isset($attrs['class']) ? $attrs['class'] . ' emphase' : 'emphase';
            }
        });
        $uid = $this->getDi()->auth->getUserId();
        //hide filter if user has few tickets
        if ($this->getDi()->helpdeskTicketTable->countByUserId($uid) < 10) {
            $this->addCallback(Am_Grid_ReadOnly::CB_RENDER_CONTENT, function(&$out, $g){
               $out = preg_replace('/<!-- start am-filter-wrap -->.*?<!-- end am-filter-wrap -->/is', '', $out);
            });
        }
        parent::init();
    }

    public function initGridFields()
    {
        $this->addField('subject', ___('Subject'), true, '', [$this, 'renderSubject']);
        $this->addField('updated', ___('Updated'), true, '', [$this, 'renderTime']);
        $this->addField('ticket_mask', '#');
        $this->addField('status', ___('Status'), true, '', [$this, 'renderStatus'], '1%');

        $this->addCallback(Am_Grid_ReadOnly::CB_TR_ATTRIBS, [$this, 'cbGetTrAttribs']);
    }

    protected function createFilter()
    {
        return new Am_Grid_Filter_Helpdesk;
    }

    public function getStatusIconId($id, $record)
    {
        return $id == 'awaiting' && $record->status == HelpdeskTicket::STATUS_AWAITING_USER_RESPONSE ?
            $id . '-me' : $id;
    }

    protected function isNotImportant($record)
    {
        return $record->status == HelpdeskTicket::STATUS_CLOSED;
    }

    public function initActions()
    {
        parent::initActions();
        $this->actionAdd(new Am_Grid_Action_Ticket());
    }

    public function createDs()
    {
        $query = parent::createDS();
        $query->addWhere('t.user_id=?',
            Am_Di::getInstance()->auth->getUserId()
        );
        return $query;
    }
}

class Helpdesk_IndexController extends Am_Mvc_Controller_Pages
{
    protected $layout = 'member/layout.phtml';

    function preDispatch()
    {
        $this->getDi()->auth->requireLogin($this->getDi()->surl('helpdesk', false));
        $this->view->body_class = 'am-page-helpdesk';
        if ($page = $this->getDi()->navigationUser->findOneBy('id', 'helpdesk')) {
            $page->setActive(true);
        }
        parent::preDispatch();
    }

    public function initPages()
    {
        $this->addPage('Am_Helpdesk_Grid_User', 'index', ___('Tickets'))
            ->addPage([$this, 'createController'], 'view', ___('Conversation'));
    }

    public function renderTabs()
    {
        $intro = $this->getDi()->config->get('helpdesk.intro');
        return $intro ? sprintf('<div class="am-info">%s</div>', $intro) : '';
    }

    public function createController($id, $title, $grid)
    {
        return new Am_Helpdesk_Controller($grid->getRequest(), $grid->getResponse(), $this->_invokeArgs);
    }
}