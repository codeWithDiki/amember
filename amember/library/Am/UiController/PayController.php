<?php

class PayController extends Am_Mvc_Controller
{
    public function indexAction()
    {
        /* @var $invoice Invoice */
        $invoice = $this->getDi()->invoiceTable->findBySecureId($this->getParam('secure_id'), 'payment-link');
        if (!$invoice || ($invoice->status != Invoice::PENDING))
            throw new Am_Exception_InternalError(
                sprintf('Unknow invoice [%s] or invoice is already processed',
                    filterId($this->getParam('secure_id'))));
        if (!$invoice->due_date && (sqlDate($invoice->tm_added) < sqlDate("-" . Invoice::DEFAULT_DUE_PERIOD . " days")))
            throw new Am_Exception_InputError(___('Invoice is expired'));
        elseif ($invoice->due_date && ($invoice->due_date < sqlDate('now')))
            throw new Am_Exception_InputError(___('Invoice is expired'));

        $form = new Am_Form();
        if (!$invoice->paysys_id)
        {
            $psOptions = [];
            foreach ($this->getDi()->paysystemList->getAllPublic() as $ps)
            {
                $psOptions[$ps->getId()] = $this->renderPaysys($ps);
            }

            //universal format for payload in all %_GET_PAYSYSTEMS events
            $_ = array_keys($psOptions);
            $_ = $this->getDi()->hook->filter($_, Am_Event::PAY_PAGE_GET_PAYSYSTEMS, ['invoice' => $invoice]);
            $psOptions = array_filter($psOptions, function($id) use ($_) {return in_array($id, $_);}, ARRAY_FILTER_USE_KEY);

            if (count($psOptions) == 1) {
                reset($psOptions);
                $form->addHtml()
                    ->setLabel(___('Payment System'))
                    ->setHtml(current($psOptions));
                $form->addHidden('paysys_id')->setValue(key($psOptions));
            } else {
                $paysys = $form->addAdvRadio('paysys_id')
                    ->setLabel(___('Payment System'))
                    ->loadOptions($psOptions);
                $paysys->addRule('required', ___('Please choose a payment system'));
            }
        }

        $form = $this->getDi()->hook->filter($form, Am_Event::PAY_PAGE_FORM, ['invoice' => $invoice]);

        $form->addSaveButton(___('Pay'));

        $this->view->invoice = $invoice;
        $this->view->form = $form;

        if ($form->isSubmitted() && $form->validate())
        {
            $vars = $form->getValue();

            if (!$invoice->paysys_id)
            {
                $invoice->setPaysystem($vars['paysys_id']);
                $invoice->save();
            }

            $invoice = $this->getDi()->hook->filter($invoice, Am_Event::PAY_PAGE_INVOICE, ['vars' => $vars]);

            $payProcess = new Am_Paysystem_PayProcessMediator($this, $invoice);
            $payProcess->process();

            throw new Am_Exception_InternalError(
                sprintf('Error occurred while trying process invoice [%s]',
                    filterId($invoice->public_id)));
        }

        $this->view->layoutNoMenu = true;
        $this->view->display('pay.phtml');
    }

    protected function renderPaysys(Am_Paysystem_Description $p)
    {
        return sprintf('<span class="am-paysystem-title">%s</span> <span class="am-paysystem-desc">%s</span>',
            $p->getTitle(), $p->getDescription());
    }
}