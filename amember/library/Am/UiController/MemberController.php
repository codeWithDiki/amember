<?php

/*
 *   Members page. Used to renew subscription.
 *
 *
 *     Author: Alex Scott
 *      Email: alex@cgi-central.net
 *        Web: http://www.cgi-central.net
 *    Details: Member display page
 *    FileName $RCSfile$
 *    Release: 6.3.6 ($Revision$)
 *
 * Please direct bug reports,suggestions or feedback to the cgi-central forums.
 * http://www.cgi-central.net/forum/
 *
 * aMember PRO is a commercial software. Any distribution is strictly prohibited.
 *
 */

class MemberController extends Am_Mvc_Controller
{
    /** @var User */
    protected $user;
    protected $user_id;

    function preDispatch()
    {
        $this->getDi()->auth->requireLogin($this->getDi()->url('member', false));
        $this->user = $this->getDi()->user;
        $this->view->assign('user', $this->user);
        $this->user_id = $this->user->pk();
    }

    function paymentHistoryAction()
    {
        $this->view->display('member/payment-history.phtml');
    }

    function setError($error)
    {
        $this->view->assign('error', $error);
        return false;
    }

    function addRenewAction()
    {
        $this->_redirect('signup');
    }

    function indexAction()
    {
        if (($pid = $this->getDi()->config->get('dashboard_page')) &&
            ($page = $this->getDi()->pageTable->load($pid, false))) {

            echo $page->render($this->view, $this->getDi()->user);
        } else {
            $this->view->display('member/main.phtml');
        }
    }

    function getInvoiceAction()
    {
        if (!$id = $this->getDi()->security->reveal($this->getParam('id')))
            throw new Am_Exception_InputError("Wrong invoice# passed");

        $record = $this->getParam('t', 'payment') == 'payment' ?
            $this->getDi()->invoicePaymentTable->load($id, false) :
            $this->getDi()->invoiceRefundTable->load($id, false);

        if (!$record)
            throw new Am_Exception(___("Invoice not found"));
        if ($record->user_id != $this->user->user_id)
            throw new Am_Exception_Security("Foreign invoice requested : [$id] for {$this->user->user_id}");

        $this->getDi()->plugins_payment->loadEnabled()->getAllEnabled();
        $pdfInvoice = Am_Pdf_Invoice::create($record);
        $pdfInvoice->setDi($this->getDi());

        $this->_helper->sendFile->sendData($pdfInvoice->render(), 'application/pdf', $pdfInvoice->getFileName());
    }

    function upgradeAction()
    {
        if (!$id = $this->getFiltered('invoice_id'))
            throw new Am_Exception_InputError("Wrong invoice# passed");
        /* @var $invoice Invoice */
        if (!$invoice = $this->getDi()->invoiceTable->findFirstByPublicId($id))
            throw new Am_Exception_InputError(___("Invoice not found"));
        if ($invoice->user_id != $this->user->user_id)
            throw new Am_Exception_Security("Foreign invoice requested : [$id] for {$this->user->user_id}");
        // right now we only can handle first item
        $item = null;
        foreach ($invoice->getItems() as $it) {
            if ($it->pk() == $this->getDi()->security->reveal($this->getParam ('invoice_item_id')))
                $item = $it;
        }

        $upgrade = $this->getDi()->productUpgradeTable->load($this->_request->getInt('upgrade'));
        if (!$invoice->canUpgrade($item, $upgrade))
            throw new Am_Exception_Security("Cannot process upgrade");

        $newInvoice = $invoice->doUpgrade($item, $upgrade, $this->getParam('coupon'));
        $newInvoice->toggleValidateProductRequirements(false);//we already checked it @see Invoice::canUpgrade
        if (($newInvoice->getStatus() == Invoice::PENDING) && !$newInvoice->data()->get('upgrade-pending')) {
            if ($err = $newInvoice->validate())
                throw new Am_Exception_InputError($err[0]);
            $newInvoice->save();
            $payProcess = new Am_Paysystem_PayProcessMediator($this, $newInvoice);
            try {
                $result = $payProcess->process();
            } catch (Am_Exception_Redirect $e) {
                throw $e;
            }
            if ($result->isFailure())
                throw new Am_Exception_InputError(current($result->getErrorMessages()));
        }
        if ($newInvoice->isCompleted()) {
            $this->_redirect('member/payment-history?' . http_build_query([
                '_msg' => ___('Product upgrade finished successfully') . '.'
                ]));
        } else {
            $this->_redirect('member/payment-history?' . http_build_query([
                '_msg' => ___('Processing your product upgrade') . '...'
                ]));
        }
    }

    function restoreRecurringAction()
    {
        if (!$this->getDi()->config->get('allow_restore'))
            throw new Am_Exception_InputError;

        if (!$id = $this->getFiltered('invoice_id'))
            throw new Am_Exception_InputError("Wrong invoice# passed");
        /* @var $invoice Invoice */
        if (!$invoice = $this->getDi()->invoiceTable->findFirstByPublicId($id))
            throw new Am_Exception_InputError(___("Invoice not found"));
        if ($invoice->user_id != $this->user->user_id)
            throw new Am_Exception_Security("Foreign invoice requested : [$id] for {$this->user->user_id}");

        $newInvoice = $invoice->doRestoreRecurring();
        $newInvoice->setPaysystem($invoice->paysys_id);

        if ($err = $newInvoice->validate())
            throw new Am_Exception_InputError($err[0]);

        $newInvoice->data()->set(Invoice::ORIG_ID, $invoice->pk());
        $newInvoice->insert();

        $payProcess = new Am_Paysystem_PayProcessMediator($this, $newInvoice);
        $result = $payProcess->process();
    }
    
    function manualRebillAction()
    {
        if (!$id = $this->getFiltered('invoice_id'))
            throw new Am_Exception_InputError("Wrong invoice# passed");
        
        $invoice = $this->getDi()->invoiceTable->findBySecureId($id, 'MANUAL-REBILL');
        
        if (empty($invoice) || ($invoice->user_id != $this->getDi()->auth->getUserId()))
            throw new Am_Exception_Security("Attempt to unauthorized access. Unable to load invoice or invoice was created by another user");
    
        $payProcess = new Am_Paysystem_PayProcessMediator($this, $invoice);
        $result = $payProcess->process();
    
    }
    
}