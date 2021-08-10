<?php

class Am_Helpdesk_Strategy_Admin extends Am_Helpdesk_Strategy_Abstract
{
    protected $_identity = null;

    public function __construct(Am_Di $di, $admin_id = null)
    {
        parent::__construct($di);
        $this->_identity = $admin_id ? $admin_id : $this->getDi()->authAdmin->getUserId();
    }

    public function isMessageAvailable($message)
    {
        return!($message->type == 'comment' && !$message->admin_id);
    }

    public function isMessageForReply($message)
    {
        if ($message->type == 'comment') {
            return false;
        } else {
            return!$message->admin_id;
        }
    }

    public function fillUpMessageIdentity($message)
    {
        $message->admin_id = $this->getIdentity();
        return $message;
    }

    public function fillUpTicketIdentity($ticket, $request)
    {
        //loginOrEmail was already validated in form
        //and we must find user with such login or email
        //in any case
        $user = $this->getDi()->userTable->findFirstByLogin($request->get('loginOrEmail'));
        if (!$user) {
            $user = $this->getDi()->userTable->findFirstByEmail($request->get('loginOrEmail'));
        }

        $ticket->user_id = $user->user_id;
        return $ticket;
    }

    public function getTicketStatusAfterReply($message)
    {
        if ($message->type == 'comment') {
            return $message->getTicket()->status;
        } else {
            return 'awaiting_user_response';
        }
    }

    public function onAfterInsertMessage($message, $ticket)
    {
        if ($message->type == 'message') {
            $ticket->updateQuick('has_new', 1);
        }

        if ($message->type == 'message'
            && $this->getDi()->config->get('helpdesk.notify_new_message', 1)) {
            $user = $this->getUser($message->getTicket()->user_id);
            if ($user->unsubscribed)
                return;

            $et = Am_Mail_Template::load('helpdesk.notify_new_message', $user->lang);
            if ($et) {
                $et->setTicket($message->getTicket());
                $et->setUser($user);
                $et->setMessage($message);
                $et->setUrl($this->getDi()->surl('helpdesk/ticket/'.$message->getTicket()->ticket_mask, false));
                $et->send($user);
            }
        }
    }

    public function onViewTicket($ticket)
    {
        //nop
    }

    /**
     * @return Am_Form
     */
    public function createNewTicketForm()
    {
        $form = parent::createNewTicketForm();

        $element = HTML_QuickForm2_Factory::createElement('text', 'loginOrEmail');
        $element->setId('loginOrEmail')
            ->setLabel(___('Username/Email'))
            ->addRule('callback', ___('Can not find user with such username or email'), [$this, 'checkUser']);

        //prepend element to form
        $formElements = $form->getElements();
        $form->insertBefore($element, $formElements[0]);

        $from = HTML_QuickForm2_Factory::createElement('select', 'from');
        $from->setLabel(___('Create ticket as'));
        $from->loadOptions([
            'admin' => ___('Admin'),
            'user' => ___('Customer')
        ]);

        $form->insertBefore($from, $element);

        $form->addScript('script')->setScript(<<<CUT
jQuery("input#loginOrEmail").autocomplete({
        minLength: 2,
        source: amUrl("/admin-users/autocomplete")
});
CUT
        );

        $id = $form->getId();
        $snippets = ___('Snippets');

        $form->addScript('snippets')->setScript(<<<CUT
jQuery('#$id').find('textarea[name=content]').after('<br /><br /><a href="javascript:;" id="snippets-link" class="local">$snippets</a>')
jQuery('#snippets-link').bind('click', function(){
    var \$this = jQuery(this);
    var div = jQuery('<div></div>');
    div.load(amUrl('/helpdesk/admin/p/view/displaysnippets'), {}, function(){
        div.dialog({
            autoOpen: true,
            modal : true,
            title : "",
            width : 700,
            position : {my: "center", at: "center", of: window}
        });
        div.find('.am-grid-wrap').bind('load', function() {
            jQuery(this).find('a.am-helpdesk-insert-snippet').unbind().click(function(){
                var \$target = \$this.closest('div.am-form').find('textarea[name=content]');
                \$target.insertAtCaret(jQuery(this).data('snippet-content'))
                div.dialog('close');
            })
        })
    })
})
CUT
        );

        return $form;
    }

    public function addUpload($form)
    {
        $t = Am_Html::escape(___('Add Attachments'));
        $form->addHtml()->setHtml(<<<CUT
<a href="javascript:;" class="local am-helpdesk-attachment-expand" onclick="jQuery(this).closest('.am-row').hide().next().show();">$t</a>
CUT
        );
        $form->addUpload('attachments', ['multiple' => 1], ['prefix' => Bootstrap_Helpdesk::ADMIN_ATTACHMENT_UPLOAD_PREFIX, 'secure' => true])
            ->setLabel(___('Attachments'))
            ->setJsOptions(<<<CUT
{
   onFileAdd: function(info) {
        if (info.mime.startsWith('image/')) {
            var \$target = jQuery(this).closest('.am-helpdesk-reply-panel').find('textarea[name=content]');
            \$target.insertAtCaret('!' + info.name + '!');
            \$target.change();
        }
   }
}
CUT
            );
    }

    public function getAdminName($message)
    {
        $admin = $this->getAdmin($message->admin_id);
        return $admin ? $admin->login : ___('Administrator has been removed, id [%d]', $message->admin_id);
    }

    public function getTemplatePath()
    {
        return 'admin/helpdesk';
    }

    public function getIdentity()
    {
        return $this->_identity;
    }

    public function canViewTicket($ticket)
    {
        return true;
    }

    public function canViewMessage($message)
    {
        return true;
    }

    public function canEditTicket($ticket)
    {
        return true;
    }

    public function canCloseTicket($ticket)
    {
        return true;
    }

    public function canEditMessage($message)
    {
        $module = $this->getDi()->modules->get('helpdesk');
        return ($message->type == 'comment' && (boolean) $message->admin_id) ||
            ($module->getConfig('can_edit') &&
                $message->admin_id &&
                $this->getIdentity() == $message->admin_id &&
                $message->dattm > sqlTime("-{$module->getConfig('can_edit_timeout', 5)} minute")
            );
    }

    public function canUseSnippets()
    {
        return true;
    }

    public function canUseFaq()
    {
        return true;
    }

    public function canUseMarkdown()
    {
        return true;
    }

    public function canEditOwner($ticket)
    {
        return true;
    }

    public function canEditWatcher($ticket)
    {
        return true;
    }

    public function canViewOwner($ticket)
    {
        return true;
    }

    public function canEditCategory($ticket)
    {
        return true;
    }

    public function checkUser($loginOrEmail)
    {
        $user = $this->getDi()->userTable->findFirstByLogin($loginOrEmail);
        if (!$user) {
            $user = $this->getDi()->userTable->findFirstByEmail($loginOrEmail);
        }
        return (boolean) $user;
    }

    public function createForm()
    {
        $form = new Am_Form_Admin();
        $form->setAttribute('class', 'am-helpdesk-form');
        return $form;
    }

    public function ticketUrl($ticket)
    {
        return $this->assembleUrl([
                'ticket' => $ticket->ticket_mask
        ], 'helpdesk-ticket-admin', false);
    }

    public function newUrl()
    {
        return $this->assembleUrl([], 'helpdesk-new-admin', false);
    }

    protected function getControllerName()
    {
        return 'admin';
    }

    public function getTicketNavigation(HelpdeskTicket $ticket)
    {
        $container = parent::getTicketNavigation($ticket);

        $container->addPage(new Am_Navigation_Page_Uri([
            'label' => ___('Comment'),
            'order' => 100,
            'li-class' => 'am-helpdesk-ticket-action-reply',
            'class' => 'local',
            'uri' => $this->assembleUrl([
                'page_id' => 'view',
                'action' => 'reply',
                'ticket' => $ticket->ticket_mask,
                'type' => 'comment',
            ], 'default', false)
        ]));

        return $container;
    }
}