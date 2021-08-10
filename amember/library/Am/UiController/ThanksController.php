<?php

class ThanksController extends Am_Mvc_Controller
{
    /** @var Invoice */
    protected $invoice;

    protected $importantVars = []; // variables that are required to get this page re-displayed

    public function preDispatch()
    {
        parent::preDispatch();
        $this->getDi()->plugins_payment->loadEnabled()->getAllEnabled();
    }

    public function getIdFromRequest()
    {
        $id = filterId(urldecode($this->_request->get('id')));
        if (empty($id))
            $id = filterId(@urldecode(@$_GET['id']));
        return $id;
    }

    public function getInvoiceByIdOrThrowException($id)
    {
        $invoice = $this->getDi()->invoiceTable->findBySecureId($id, 'THANKS');
        if (!$invoice)
            throw new Am_Exception_InputError(___("Invoice #%s not found", $id));
        $tm = max($invoice->tm_started, $invoice->tm_added, $invoice->getLastPaymentTime());
        if (($this->getDi()->time - strtotime($tm)) > 48*3600)
            throw new Am_Exception_InputError(___("Link expired"));
        return $invoice;
    }

    public function setInvoice(Invoice $invoice)
    {
        $this->invoice = $invoice;
        return $this;
    }

    public function getInvoice()
    {
        return $this->invoice;
    }

    public function getImportantVars()
    {
        return $this->importantVars;
    }

    function indexAction()
    {
        // logic/load
        if (!$this->invoice) // we could call setInvoice before then we do not need it
        {
            $id = $this->getIdFromRequest();
            if ($id)
            {
                $invoice = $this->getInvoiceByIdOrThrowException($id);
                $this->setInvoice($invoice);
                $this->importantVars['id'] = $id;
            }
        }
        if (($login = $this->getParam('uid')) &&
            $this->getDi()->security->hash($login, 8) == $this->getParam('h'))
        {
            $this->view->user = $this->getDi()->userTable->findFirstByLogin($login);
            $this->importantVars['uid'] = $this->getParam('uid');
            $this->importantVars['h'] = $this->getParam('h');
        }

        // display invoice
        if ($this->invoice)
        {
            $this->view->invoice = $this->invoice;

            foreach ($this->invoice->getPaymentRecords() as $p) {
                $this->view->payment = $p;
            }

            $cd_sec = 10;
            if (!$this->invoice->tm_started)
            {
                $this->view->show_waiting = true;
                $sectext = "{m}:{s} " . Am_Html::escape(___("seconds"));
                $this->view->refreshTime = "<span class='am-countdown' id='am-countdown' ".
                    "data-reload='true' data-start='{$cd_sec}' data-format='{$sectext}'></span>";
            }
        }

        // Clean signup_member_login and signup_member_id to avoid duplicate signups with the same email address
        $this->getSession()->signup_member_id = null;
        $this->getSession()->signup_member_login = null;

        $this->getDi()->hook->call(Am_Event::THANKS_PAGE, [
            'controller' => $this,
            'invoice'    => $this->invoice,
        ]);

        $this->view->receiptAfterPayment = true;
        $this->view->layoutNoMenu = true;
        $this->view->display('thanks.phtml');
    }

}