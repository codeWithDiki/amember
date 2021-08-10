<?php

/**
 * BuyNow button functionality implementation
 *
 */
class BuyController extends Am_Mvc_Controller
{
    protected $user_id = false;
    function indexAction()
    {
        if (!$h = $this->_request->getFiltered('h'))
            throw new Am_Exception_InputError("[h] parameter is required");

        if (!$btn = $this->getDi()->buttonTable->findFirstByHash($h))
            throw new Am_Exception_InputError("BuyNow button [$h] not found");

        if (!($user_id = $this->getDi()->auth->getUserId()))
        {
            if ($this->getSession()->signup_member_id && $this->getSession()->signup_member_login)
            {
                $user = $this->getDi()->userTable->load((int)$this->getSession()->signup_member_id, false);
                if ($user && ((($this->getDi()->time - strtotime($user->added)) < 24*3600) && ($user->status == User::STATUS_PENDING)))
                {
                    if ($this->getSession()->signup_member_login == $user->login) {
                        $user_id = $this->getSession()->signup_member_id;
                    }
                }
            }
        }
        if($user_id)
        {
            $invoice = $btn->createInvoice();
            $invoice->user_id = $this->user_id = $user_id;
            if ($btn->use_coupons && $this->getParam('coupon')) {
                $invoice->setCouponCode(trim($this->getParam('coupon')));
                if ($error = $invoice->validateCoupon()) {
                    $invoice->setCouponCode('');
                }
            }
            $invoice->calculate();

            $ps = $this->getPaysystems($btn, $invoice);

            if (($btn->use_coupons && !$this->getParam('coupon'))
                || count($ps) !== 1
                || $invoice->getItem(0)->tryLoadProduct()->getOptions()) {

                $form = new Am_Form();
                $form->addCsrf();

                $op = $invoice->getItem(0)->tryLoadProduct()->getOptions(true);

                if ($op) {
                    $this->insertProductOptions(
                        $form,
                        $invoice->getItem(0)->tryLoadProduct(),
                        $op,
                        $invoice->getItem(0)->tryLoadProduct()->getBillingPlan()
                    );
                }

                if (count($ps) !== 1) {
                    $form->addAdvRadio('paysys_id')
                        ->setLabel(___('Payment System'))
                        ->loadOptions(array_map(function($_) {return $_->getTitle();}, $ps))
                        ->addRule('required', ___('Please choose a payment system'));
                }

                if ($btn->use_coupons) {
                    $coupon = $form->addText('coupon')
                        ->setLabel(___('Coupon code'));
                    $coupon->addRule('callback2', null, [$this, 'validateCoupon'])
                        ->addRule('remote', null, [
                            'url' => $this->url('ajax', ['do'=>'check_coupon'], false),
                        ]);
                    if ($c = $this->getParam('coupon')) {
                        $coupon->setValue($c);
                    }
                }

                $form->addSaveButton(___('Pay'));

                if ($form->isSubmitted() && $form->validate()) {
                    $vars = $form->getValue();

                    if ($op) {
                        $options = $vars['productOption'];
                        foreach ($options as $k => $v)
                        {
                            $options[$k] = [
                                'value' => $v,
                                'optionLabel' => $op[$k]->title,
                                'valueLabel' => $op[$k]->getOptionLabel($v)
                            ];
                        }

                        $item = $invoice->getItem(0);
                        $invoice->deleteItem($item);
                        $invoice->add($item->tryLoadProduct(), $item->qty, $options);
                    }

                    if (!empty($vars['paysys_id'])) {
                        $invoice->paysys_id = $vars['paysys_id'];
                    }
                    if (!empty($vars['coupon'])) {
                        $invoice->setCouponCode($vars['coupon']);
                        $invoice->validateCoupon();
                    }
                } else {
                    $this->view->invoice = $invoice;
                    $this->view->form = $form;
                    $this->view->layoutNoMenu = true;
                    $this->view->display('pay.phtml');
                    return;
                }
            }

            if (empty($invoice->paysys_id)) {
                reset($ps);
                $invoice->paysys_id = key($ps);
            }

            $invoice->calculate();
            if ($invoice->isZero()) {
                $invoice->paysys_id = 'free';
            }

            if ($errors = $invoice->validate()) {
                throw new Am_Exception_InputError(current($errors));
            }

            $invoice->save();

            $payProcess = new Am_Paysystem_PayProcessMediator($this, $invoice);
            $result = $payProcess->process();
            if ($result->isFailure()) {
                throw new Am_Exception_InputError($result->getLastError());
            }
        } else {
            if ($btn->saved_form_id) {
                $sf = $this->getDi()->savedFormTable->load($btn->saved_form_id);
            } else {
                $sf = $this->getDi()->savedFormTable->getDefault(SavedForm::D_SIGNUP);
            }

            $redirectUrl = $this->getDi()->surl(['buy/%s', $h], false);
            $this->getDi()->store->set("am-order-data-$h", json_encode([
                'hide-bricks' => array_merge(['product', 'paysystem', 'donation'] , ($btn->use_coupons ? [] : ['coupon'])),
                'pass-data' => $btn->use_coupons ? ['coupon'] : [],
                'redirect' => $redirectUrl,
                'button' => $btn->hash
            ], true), '+3 hours');

            $url = $this->getDi()->surl("signup/{$sf->code}", ['order-data' => $h], false);
            $this->_redirect($url);
        }
    }

    function validateCoupon($value)
    {
        if ($value == "")
            return null;
        $coupon = $this->getDi()->couponTable->findFirstByCode($value);
        $msg = $coupon ? $coupon->validate($this->user_id) : ___('No coupons found with such coupon code');
        return $msg === null ? null : $msg;
    }

    protected function getPaysystems($btn, $invoice)
    {
        $paysys = [];
        if ($paysystems = $btn->unserializeList($btn->paysys_id))
        {
            if (!in_array('free', $paysystems))
                $paysystems[] = 'free';
            foreach ($paysystems as $paysystem_id)
            {
                try {
                    $ps = $this->getDi()->paysystemList->get($paysystem_id);
                } catch (Exception $e) {
                    $this->getDi()->logger->error("Error while loading payment system $paysystem_id for {btn}", ["exception" => $e, 'btn' => $btn]);
                    continue;
                }
                if ($ps)
                {//it is enabled now
                    $plugin = $this->getDi()->plugins_payment->get($ps->paysys_id);
                    if (!($err = $plugin->isNotAcceptableForInvoice($invoice)))
                    {
                        $paysys[$ps->getId()] = $ps;
                    }
                }
            }
        }

        if (!$paysys)
        {
            foreach ($this->getDi()->paysystemList->getAllPublic() as $ps)
            {
                $plugin = $this->getDi()->plugins_payment->get($ps->paysys_id);
                if (!($err = $plugin->isNotAcceptableForInvoice($invoice)))
                {
                    $paysys[$ps->getId()] = $ps;
                }
            }
        }
        if ($this->getDi()->config->get('product_paysystem')) {
            $ps_ids = array_keys($paysys);
            foreach($invoice->getItems() as $item) {
                if (($product = $item->tryLoadProduct()) &&
                    ($product_ps_id = $product->getBillingPlan()->paysys_id) ) {

                    $ps_ids = array_intersect($ps_ids, explode(',', $product_ps_id));
                }
            }

            foreach ($paysys as $k => $v) {
                if (!in_array($k, $ps_ids)) unset($paysys[$k]);
            }
        }
        return $paysys;
    }

    protected function insertProductOptions(HTML_QuickForm2_Container $form, $pid, array $productOptions,
            BillingPlan $plan)
    {
        foreach ($productOptions as $option)
        {
            $elName = "productOption[{$option->name}]";
            $isEmpty = empty($_POST['productOption'][$option->name]);
            /* @var $option ProductOption */
            $el = null;
            switch ($option->type)
            {
                case 'text':
                    $el = $form->addElement('text', $elName);
                    if ($isEmpty)
                        $el->setValue($option->getDefault());
                    break;
                case 'radio':
                    $el = $form->addElement('advradio', $elName);
                    $el->loadOptions($option->getSelectOptionsWithPrice($plan));
                    if ($isEmpty)
                        $el->setValue($option->getDefault());
                    break;
                case 'select':
                    $el = $form->addElement('select', $elName);
                    $el->loadOptions($option->getSelectOptionsWithPrice($plan));
                    if ($isEmpty)
                        $el->setValue($option->getDefault());
                    break;
                case 'multi_select':
                    $el = $form->addElement('magicselect', $elName);
                    $el->loadOptions($option->getSelectOptionsWithPrice($plan));
                    if ($isEmpty)
                        $el->setValue($option->getDefaults());
                    break;
                case 'textarea':
                    $el = $form->addElement('textarea', $elName, 'class=am-el-wide rows=5');
                    if ($isEmpty)
                        $el->setValue($option->getDefault());
                    break;
                case 'checkbox':
                    $opts = $option->getSelectOptionsWithPrice($plan);
                    if ($opts)
                    {
                        $el = $form->addGroup($elName);
                        $el->setSeparator("<br />");
                        foreach ($opts as $k => $v) {
                            $chkbox = $el->addAdvCheckbox(null, ['value' => $k])->setContent(___($v));
                            if ($isEmpty && in_array($k, (array)$option->getDefaults()))
                                $chkbox->setAttribute('checked', 'checked');
                        }
                        $el->addHidden(null, ['value' => '']);
                        $el->addFilter('array_filter');
                        if (count($opts) == 1 && $option->is_required) {
                            $chkbox->addRule('required', ___('This field is required'), null, HTML_QuickForm2_Rule::ONBLUR_CLIENT);
                        }
                    } else {
                        $el = $form->addElement('advcheckbox', $elName);
                    }
                    break;
                case 'date':
                    $el = $form->addElement('date', $elName);
                    break;
                }
            if ($el && $option->is_required)
            {
                // onblur client set to only validate option fields with javascript
                // else there is a problem with hidden fields as quickform2 does not skip validation for hidden
                $el->addRule('required', ___('This field is required'), null, HTML_QuickForm2_Rule::ONBLUR_CLIENT);
            }
            $el->setLabel(___($option->title));
        }
    }
}