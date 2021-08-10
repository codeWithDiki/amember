<?php

abstract class Am_Paysystem_CreditCard_Token extends Am_Paysystem_CreditCard implements Am_Paysystem_TokenPayment
{
    use Am_Paysystem_TokenPayment_ValidateToken;
    
    protected $_pciDssNotRequired = true;
    
    function getToken(Invoice $invoice)
    {
        return $this->getDi()->CcRecordTable->getRecordByUser($invoice->getUser(), $invoice->paysys_id)->getToken();
    }
    
    function saveToken(Invoice $invoice, $token)
    {
        $this->getDi()->CcRecordTable->getRecordByUser($invoice->getUser(),
            $invoice->paysys_id)->updateToken($token)->save();
    }
    
    function updateToken(Invoice $invoice, $values)
    {
        $this->getDi()->CcRecordTable->getRecordByUser($invoice->getUser(),
            $invoice->paysys_id)->updateToken($values)->save();
        
        return $this->getToken($invoice);
    }
    
    /** @return bool if plugin needs to store CC info */
    public function storesCcInfo()
    {
        return false;
    }
    
    public function storeCreditCard(CcRecord $cc, Am_Paysystem_Result $result)
    {
        try {
            $this->validateToken(
                $this->findInvoiceForTokenUpdate($cc->user_id), $cc->getToken());
        } catch (Exception $e) {
            $result->setFailed([$e->getMessage()]);
            return;
        }
        $result->setSuccess();
    }
    
    public function loadCreditCard(Invoice $invoice)
    {
        return $this->getDi()->CcRecordTable->getRecordByUser($invoice->getUser(), $invoice->paysys_id);
    }
    
    function supportsTokenPayment()
    {
        return true;
    }
    
    function createForm($actionName, $invoice = null)
    {
        if (!empty($invoice)) {
            $this->_setInvoice($invoice);
        }
        return new Am_Form_CreditCard_Token($this,
            $actionName == 'cc' ? Am_Form_CreditCard_Token::PAYFORM : $actionName);
    }
    
    
    /**
     * Will be used to get payment information from user and exchange it to payment token
     * token should be stored in form @see getJsTokenFieldName @see getJsTokenFromRequest
     *
     * @param HTML_QuickForm2_Container $form
     * @return mixed
     */
    abstract function insertPaymentFormBrick(HTML_QuickForm2_Container $form);
    
    /** Will be used in update CC info form or on signup form.
     * Should insert JS code that will exchange CC info to token
     * and pass this token in form @see getJsTokenName() field.
     * @see getJsTokenFromRequest()
     * If payment system doesn't have different API for updates,
     * the same code should be used.
     * @param HTML_QuickForm2_Container $form
     * @return mixed
     */
    function insertUpdateFormBrick(HTML_QuickForm2_Container $form)
    {
        return $this->insertPaymentFormBrick($form);
    }
    
    
    function getJsTokenFieldName()
    {
        return $this->getId() . 'Token';
    }
    
    /**
     * Get payment token from request.
     * @param array|Am_Mvc_Request $request
     * @return mixed|null
     */
    function getJsTokenFromRequest($request)
    {
        if ($request instanceof Am_Mvc_Request) {
            $token = $request->getParam($this->getJsTokenFieldName());
        }
        else {
            if (is_array($request)) {
                $token = @$request[$this->getJsTokenFieldName()];
            }
            else {
                $token = null;
            }
        }
        if (!empty($token)) {
            $obj = @json_decode($token, true);
        }
        return !empty($obj) ? $obj : $token;
    }
    
    
    /**
     * Many paysystems gives you short time token that you need to change to lifetime,
     * or attach this token to customer payment record.
     * Method should do necessary actions and in response return long time token that could be used
     * to process recurring payments.
     * @param $shortTimeToken
     * @return array|mixed
     * @throws Am_Exception_Paysystem_TransactionInvalid
     *
     */
    function convertPaymentTokenToLifetime($shortTimeToken, Invoice $invoice)
    {
        return $shortTimeToken;
    }
    
    /**
     * Get Payment token from request and save it.
     * validateToken should do necessary staff (create customer if that is necessary by paysystem API,
     * associate payment info with customer, etc..)
     * In result token should be stored using @see saveToken($invoice, $token)
     * @param Am_Event $event
     * @throws Am_Exception_Paysystem_TransactionInvalid
     * @throws Am_Exception
     * @return null
     */
    function onInvoiceBeforePaymentSignup(Am_Event $event)
    {
        $vars = $event->getVars();
        $invoice = $event->getInvoice();
        if ($this->isCCBrickSupported() && !empty($vars) && !empty($vars[$this->getJsTokenFieldName()])) {
            
            try {
                
                $this->saveToken($invoice,
                    $this->convertPaymentTokenToLifetime($this->getJsTokenFromRequest($vars), $invoice));
                
                $result = $this->doBill($invoice, $invoice->isFirstPayment(),
                    $cc = $this->getDi()->CcRecordTable->getRecordByUser($invoice->getUser(), $invoice->paysys_id));
                
                if ($result->isSuccess()) {
                    if (($invoice->rebill_times > 0) && !$cc->pk()) {
                        $this->storeCreditCard($cc, new Am_Paysystem_Result);
                    }
                    $this->getDi()->response->setRedirect($this->getReturnUrl());
                    return;
                }
                elseif ($result->isAction() && ($result->getAction() instanceof Am_Paysystem_Action_Redirect)) {
                    $result->getAction()->process(); // throws Am_Exception_Redirect (!)
                    return;
                }
            } catch (Am_Exception_Paysystem_TransactionInvalid $ex) {
                // Something went wrong here, we will ignore this and let customer to handle payment default way.
            }
            
        }
    }
    
    function findInvoiceForTokenUpdate($user_id)
    {
        $invoice = $this->getDi()->invoiceTable->findFirstBy([
            'user_id' => $user_id,
            'paysys_id' => $this->getId(),
            'status' => Invoice::RECURRING_ACTIVE
        ]);
        return $invoice;
    }
    
    function onValidateSavedForm(Am_Event_ValidateSavedForm $event)
    {
        $vars = $event->getForm()->getValue();
        if (($event->getForm() instanceof Am_Form_Profile) && !empty($vars) && !empty($vars[$this->getJsTokenFieldName()])) {
            try {
                $invoice = $this->findInvoiceForTokenUpdate($this->getDi()->auth->getUserId());
                $this->saveToken($invoice,
                    $this->convertPaymentTokenToLifetime($this->getJsTokenFromRequest($vars), $invoice));
            } catch (Am_Exception_Paysystem_TransactionInvalid $ex) {
                // Something went wrong here, we will ignore this and let customer to handle payment default way.
            }
            
        }
    }
    
    
    function isCCBrickSupported()
    {
        return false;
    }
    
}
