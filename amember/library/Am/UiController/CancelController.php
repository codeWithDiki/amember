<?php

/*
 *  User's cancel payment page. Displayed after failed payment.
 *
 *     Author: Alex Scott
 *      Email: alex@cgi-central.net
 *        Web: http://www.cgi-central.net
 *    Details: User's failed payment page
 *    FileName $RCSfile$
 *    Release: 6.3.6 ($Revision$)
 *
 * Please direct bug reports,suggestions or feedbacks to the cgi-central support
 * http://www.cgi-central.net/support/
 *
 *
 * aMember PRO is a commercial software. Any distribution is strictly prohibited.
 *
 *
 */

class CancelController extends Am_Mvc_Controller
{
    /** @var Invoice */
    protected $invoice;

    public function preDispatch()
    {
        parent::preDispatch();
        $this->view->invoice = null;
        $this->view->id = null;
        $this->view->paysystems = [];
        $this->invoice = $this->getDi()->invoiceTable->findBySecureId(!empty($_REQUEST['id']) ? $_REQUEST['id'] : null, "CANCEL");
        if ($this->invoice) {
            if ($this->invoice->isPaid())
                throw new Am_Exception_InputError("Invoice #{$this->invoice->public_id} is already paid");

            $this->getDi()->plugins_payment->loadEnabled();

            //universal format for payload in all %_GET_PAYSYSTEMS events
            $paysystems = $this->getDi()->paysystemList->getAllPublic();

            try {
                $pl = $this->getDi()->plugins_payment->loadGet($this->invoice->paysys_id);
                if($pl->supportsCancelPage() && ($cancel_list = $pl->getConfig('cancel_paysys_list')))
                {
                    $paysystems = array_filter($paysystems, function($p) use ($cancel_list) { return in_array($p->getId(), $cancel_list);});
                }
            } catch (Exception $ex) {
                //continue
            }

            $_ = array_map(function($p) {return $p->getId();}, $paysystems);
            $_ = $this->getDi()->hook->filter($_, Am_Event::CANCEL_PAGE_GET_PAYSYSTEMS, ['invoice' => $this->invoice]);
            $paysystems = array_filter($paysystems, function($p) use ($_) { return in_array($p->getId(), $_);});

            $this->view->paysystems = $paysystems;
            $this->view->invoice = $this->invoice;
            $this->view->id = $this->getFiltered('id');
        } else {
            throw new Am_Exception_InputError("No invoice found");
        }
    }

    function repeatAction()
    {
        if (!$this->invoice)
            throw new Am_Exception_InputError('No invoice found, cannot repeat');
        if ($this->invoice->isPaid())
            throw new Am_Exception_InputError("Invoice {$this->invoice->public_id} is already paid");
        $found = false;
        foreach ($this->view->paysystems as $ps)
            if ($ps->getId() == $this->getFiltered('paysys_id')) {
                $found = true;
                break;
            }
        if (!$found)
            return $this->indexAction();

        $this->invoice->setPaysystem($this->getFiltered('paysys_id'), false);
        $this->invoice->save();

        if ($err = $this->invoice->validate())
            throw new Am_Exception_InputError($err[0]);

        $payProcess = new Am_Paysystem_PayProcessMediator($this, $this->invoice);
        $result = $payProcess->process();
        if ($result->isFailure()) {
            $this->view->error = $result->getErrorMessages();
            return $this->indexAction();
        }
    }

    function indexAction()
    {
        $form = new Am_Form();
        $form->setAction($this->getDi()->url('cancel/repeat', false));
        $psOptions = [];
        foreach ($this->view->paysystems as $ps) {
            $psOptions[$ps->getId()] = $this->renderPaysys($ps);
        }

        $paysys = $form->addAdvRadio('paysys_id')
                ->setLabel(___('Payment System'))
                ->loadOptions($psOptions);

        $paysys->addRule('required', ___('Please choose a payment system'));

        if (count($psOptions) == 1) {
            $paysys->setValue(key($psOptions));
            $paysys->toggleFrozen(true);
        }

        $form->addHidden('id')
            ->setValue($this->getFiltered('id'));

        $form->addSaveButton(___('Make Payment'));
        $this->view->form = $form;
        $this->view->display('cancel.phtml');
    }

    protected function renderPaysys(Am_Paysystem_Description $p)
    {
        return sprintf('<span class="am-paysystem-title">%s</span> <span class="am-paysystem-desc">%s</span>',
            $p->getTitle(), $p->getDescription());
    }
}