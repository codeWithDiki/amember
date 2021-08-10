<?php

class Am_Grid_Action_Group_MassTicketReply extends Am_Grid_Action_Group_Abstract
{
    protected $needConfirmation = true;
    protected $form;
    protected $strategy;
    protected $_vars;


    public function __construct()
    {
        parent::__construct('mass-ticket-reply', ___('Mass Reply'));
        $this->setTarget('_top');
        $this->strategy = $this->getDi()->helpdeskStrategy;
    }

    public function handleRecord($id, $ticket)
    {
        if (!$this->strategy->canEditTicket($ticket)) {
            throw new Am_Exception_AccessDenied(___('Access Denied'));
        }

        $message = $this->getDi()->helpdeskMessageRecord;
        $message->content = $this->_vars['content'];
        $message->ticket_id = $ticket->ticket_id;
        $message->type = 'message';
        $message->setAttachments($this->_vars['attachments']);
        $message = $this->strategy->fillUpMessageIdentity($message);
        $message->save();

        $this->strategy->onAfterInsertMessage($message, $ticket);

        $ticket->status = $this->strategy->getTicketStatusAfterReply($message);
        $ticket->updated = $this->getDi()->sqlDateTime;
        $ticket->save();
        if (isset($this->_vars['_close']) && $this->_vars['_close']) {
            $ticket->status = HelpdeskTicket::STATUS_CLOSED;
            $ticket->save();
        }
    }

    public function getForm()
    {
        if (!$this->form) {
            $form = $this->strategy->createForm();

            $form->addTextarea('content', [
                'rows' => 7,
                'class' => 'am-no-label am-el-wide',
                'placeholder' => ___('Write your reply...')
            ])
                ->addRule('required');

            $this->strategy->addUpload($form);

            $form->addAdvCheckbox('_close', null, ['content' => ___('Close This Ticket After Response')]);

            $form->addSaveButton(___('Reply'));
            $this->form = $form;
        }
        return $this->form;
    }

    public function renderConfirmationForm($btn = null, $addHtml = null)
    {
        $form = $this->getForm();
        $vars = $this->grid->getCompleteRequest()->toArray();
        $vars[$this->grid->getId() . '_confirm'] = 'yes';
        foreach ($vars as $k => $v) {
            if ($form->getElementsByName($k)) {
                unset($vars[$k]);
            }
        }
        foreach(Am_Html::getArrayOfInputHiddens($vars) as $k => $v) {
            $form->addHidden($k)->setValue($v);
        }

        $url_yes = $this->grid->makeUrl(null);
        $form->setAction($url_yes);

        echo $this->renderTitle();
        echo (string)$this->form;
    }

    public function run()
    {
        if (!$this->getForm()->validate()) {
            echo $this->renderConfirmationForm();
        } else {
            $this->_vars = $this->getForm()->getValue();
            return parent::run();
        }
    }

    function getDi()
    {
        return Am_Di::getInstance();
    }
}