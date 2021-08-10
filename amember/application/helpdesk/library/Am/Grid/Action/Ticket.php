<?php

class Am_Grid_Action_Ticket extends Am_Grid_Action_Abstract
{
    protected $type = self::NORECORD; // this action does not operate on existing records
    protected $strategy = null;

    public function __construct($id = null, $title = null)
    {
        $this->title = ___('Submit New Ticket');
        parent::__construct($id, $title);
    }

    public function run()
    {
        /** @var HTML_QuickForm2 $form */
        $form = $this->grid->getForm();
        /** @var HTML_QuickForm2_Element_InputSubmit $_ */
        list($_) = $form->getElementsByName('save');
        $_->setAttribute('value', ___('Submit Ticket'));

        if ($form->isSubmitted() && $form->validate()) {
            $values = $form->getValue();
            $values = $this->valuesFromForm($values);

            if (defined('AM_ADMIN')
                && AM_ADMIN
                && isset($values['from'])
                && $values['from'] == 'user') {

                $user = Am_Di::getInstance()->userTable->findFirstByLogin($values['loginOrEmail']);
                if (!$user)
                    $user = Am_Di::getInstance()->userTable->findFirstByEmail($values['loginOrEmail']);
                if (!$user)
                    throw new Am_Exception_InputError("User not found with username or email equal to {$values['loginOrEmail']}");
                $this->switchStrategy(new Am_Helpdesk_Strategy_User(Am_Di::getInstance(), $user->pk()));
            }

            $ticket = Am_Di::getInstance()->helpdeskTicketRecord;
            if (isset($values['category_id']) && isset($values['category'][$values['category_id']])) {
                $ticket->setForInsert($values['category'][$values['category_id']]);
            } elseif (isset($values['additional'])) {
                $ticket->setForInsert($values['additional']);
            }
            $ticket->subject = $values['subject'];
            $ticket->created = Am_Di::getInstance()->sqlDateTime;
            $ticket->updated = Am_Di::getInstance()->sqlDateTime;
            $ticket->category_id = isset($values['category_id']) ? $values['category_id'] : null;
            if (($category = $ticket->getCategory()) && ($category->owner_id || $category->watcher_ids)) {
                $ticket->owner_id = $category->owner_id;
                $ticket->watcher_ids = $category->watcher_ids;
            }
            $ticket = $this->getStrategy()->fillUpTicketIdentity($ticket, $this->grid->getCompleteRequest());
            // mask will be generated on insertion
            $ticket->insert();
            $content = $values['content'];
            if (defined('AM_ADMIN')
                && AM_ADMIN) {

                $user = $ticket->getUser();
                $tpl = new Am_SimpleTemplate;
                $tpl->assign('user', $user);
                $content = $tpl->render($content);
            }

            $message = Am_Di::getInstance()->helpdeskMessageRecord;
            $message->content = $content;
            $message->ticket_id = $ticket->pk();
            $message->dattm = Am_Di::getInstance()->sqlDateTime;
            $message = $this->getStrategy()->fillUpMessageIdentity($message);
            $message->setAttachments(@$values['attachments']);
            $message->insert();

            $this->getStrategy()->onAfterInsertTicket($ticket, $message);
            $this->getStrategy()->onAfterInsertMessage($message, $ticket);

            $this->restoreStrategy();

            echo $this->renderTicketSubmitted($ticket);
            return;
        } elseif (!$form->isSubmitted()) {
            $ds = [];
            if ($this->grid->getCompleteRequest()->isPost()) {
                $ds[] = $this->grid->getCompleteRequest();
            }
            $ds[] = new HTML_QuickForm2_DataSource_Array($this->valuesToForm());
            $form->setDataSources($ds);
        }

        echo $this->renderTitle();
        echo $form;
    }

    /**
     * @see Am_Grid_Editable::valuesToForm()
     */
    protected function valuesToForm()
    {
        $record = $this->grid->getDataSource()->createRecord();
        $ret = method_exists($record, 'toArray') ? $record->toArray() : (array)$record;
        $this->grid->_transformFormValues(Am_Grid_Editable::GET, $ret);
        $args = [& $ret, $record];
        $this->grid->runCallback(Am_Grid_Editable::CB_VALUES_TO_FORM, $args);
        return $ret;
    }

    /**
     * @see Am_Grid_Editable::valuesFromForm()
     */
    protected function valuesFromForm($values)
    {
        $record = $this->grid->getDataSource()->createRecord();

        foreach ($this->grid->getVariablesList() as $k)
        {
            unset($values[$this->getId() . '_' . $k]);
        }
        unset($values['save']);
        unset($values['_save_']);
        $this->grid->_transformFormValues(Am_Grid_Editable::SET, $values);
        $args = [& $values, $record];
        $this->grid->runCallback(Am_Grid_Editable::CB_VALUES_FROM_FORM, $args);
        return $values;
    }

    /** @return Am_Helpdesk_Strategy_Abstract */
    protected function getStrategy()
    {
        return is_null($this->strategy) ?
            Am_Di::getInstance()->helpdeskStrategy :
            $this->strategy;
    }

    public function switchStrategy(Am_Helpdesk_Strategy_Abstract $strategy)
    {
        $this->strategy = $strategy;
    }

    public function restoreStrategy()
    {
        if (!is_null($this->strategy)) {
            $this->strategy = null;
        }
    }

    protected function renderTicketSubmitted($ticket)
    {
        $out = sprintf('<h1>%s</h1>', ___('Ticket has been submitted'));
        $out .= sprintf('<p>%s</p><p>%s <a class="link" href="%s" target="_top"><strong>#%s</strong></a></p>',
            ___('Thanks for contacting us! Your message has been received! We will respond to you as soon as possible.'),
            ___('Reference number is:'),
            $this->getStrategy()->ticketUrl($ticket),
            $ticket->ticket_mask
        );
        return $out;
    }
}