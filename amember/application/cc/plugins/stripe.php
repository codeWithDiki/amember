<?php

/**
 * @table paysystems
 * @id stripe
 * @title Stripe
 * @visible_link https://stripe.com/
 * @logo_url stripe.png
 * @recurring amember
 */
class Am_Paysystem_Stripe extends Am_Paysystem_CreditCard_Token implements Am_Paysystem_TokenPayment
{
    const PLUGIN_STATUS = self::STATUS_PRODUCTION;
    const PLUGIN_DATE = '$Date$';
    const PLUGIN_REVISION = '6.3.6';

    const TOKEN = 'stripe_token';
    const CC_EXPIRES = 'stripe_cc_expires';
    const CC_MASKED = 'stripe_cc_masked';
    const CUSTOMER_ID = 'customer-id';
    const PAYMENT_METHOD = 'payment-method';
    const PAYMENT_INTENT = 'payment-intent';
    const SETUP_INTENT = 'setup-intent';

    protected $defaultTitle = "Stripe";
    protected $defaultDescription = "Credit Card Payments";

    public function allowPartialRefunds()
    {
        return true;
    }


    function getOldStripeTokenKey()
    {
        return $this->getId()."_token";
    }

    public function getSupportedCurrencies()
    {
        return array_keys(Am_Currency::getFullList());
    }

    function isCCBrickSupported()
    {
        return true;
    }

    protected function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addText('public_key', ['class' => 'am-el-wide'])
            ->setLabel('Publishable Key')
            ->addRule('required')
            ->addRule('regex', 'Publishable Key must start with "pk_"', '/^pk_.*$/');

        $form->addSecretText('secret_key', ['class' => 'am-el-wide'])
            ->setLabel('Secret Key')
            ->addRule('required')
            ->addRule('regex', 'Secret Key must start with "sk_"', '/^sk_.*$/');

        $fs = $this->getExtraSettingsFieldSet($form);

        $fs->addAdvCheckbox('efw_refund')->setLabel('Refund Transaction  on Early Fraud Warning
        You need to configure Stripe to send radar.early_fraud_warning.created event notifications
        Plugin will refund transactions when EFW is created before receiving dispute');

        $fs->addAdvCheckbox("checkout-mode")
            ->setLabel(___('Use Stripe Checkout to collect Payment information
            user will be redirected to Stripe to specify information first time.
            Recurrent payments will be handled by aMember
            '));
    }

    function _process($invoice, $request, $result)
    {
        if ($this->getConfig('checkout-mode'))
        {
            $tr = new Am_Paysystem_Transaction_Stripe_CreateCheckoutSession($this,$invoice);
            $tr->run($result);
            if($result->isSuccess())
            {
                $result->reset();
                $session_id = $tr->getUniqId();
                $public_key = $this->getConfig('public_key');
                $cancel_url = $this->getCancelUrl();

                $a = new Am_Paysystem_Action_HtmlTemplate("pay.phtml");

                $a->invoice = $invoice;
                $msg = ___('Please wait while you are being redirected');
                $a->form  = <<<CUT
<div>{$msg}</div>
<script src="https://js.stripe.com/v3/"></script>
<script>
var stripe = Stripe('{$public_key}');
stripe.redirectToCheckout({
  sessionId: '{$session_id}'
}).then(function (result) {
    document.location.href= '{$cancel_url}';
});
</script>
CUT;
                $result->setAction($a);
            }
        } else {
            parent::_process($invoice, $request, $result);
        }
    }

    public function _doBill(Invoice $invoice, $doFirst, CcRecord $cc, Am_Paysystem_Result $result)
    {
        try {
            $token = $this->validateToken($invoice, $cc->getToken());
        } catch (Exception $e) {
            $result->setFailed($e->getMessage());
            return;
        }

        if ($doFirst && (doubleval($invoice->first_total) <= 0)) { // free trial
            $tr = new Am_Paysystem_Transaction_Free($this);
            $tr->setInvoice($invoice);
            $tr->process();
            $result->setSuccess($tr);
        }
        else {

            $tr = new Am_Paysystem_Transaction_Stripe($this, $invoice, $doFirst);
            $tr->run($result);
        }
    }

    public function canReuseCreditCard(Invoice $invoice)
    {
        $x = $this->getDi()->CcRecordTable->findBy(['user_id' => $invoice->user_id, 'paysys_id' => $this->getId()]);
        $ret = [];
        foreach ($x as $cc)
        {
            $t = json_decode($cc->token, true);
            if (empty($t['customer-id'])) continue;

            if(!empty($t[self::PAYMENT_METHOD])){
                $_ = new Am_Paysystem_Result();
                $tr = new Am_Paysystem_Transaction_Stripe_GetPaymentMethod($this, $invoice, $t);
                try {
                    $tr->run($_);
                }catch(Am_Exception_Paysystem $ex){
                    continue;
                }
                if($_->isSuccess())
                {
                    $card = $tr->getCard();
                    if(!empty($card['last4'])){
                        $cc->cc = $cc->cc_number =  "************".$card['last4'];
                        $cc->cc_expire = $card['exp_month'].($card['exp_year']-2000);
                    }
                }
            }

            $ret[] = $cc;
        }
        return $ret;
    }

    public function getUpdateCcLink($user)
    {

        $invoice = $this->findInvoiceForTokenUpdate($user->pk());
        if (!$invoice) {

            return;
        }
        return $this->getPluginUrl('update');
    }

    public function onDeletePersonalData(Am_Event $event)
    {
        $user = $event->getUser();
        $user->data()
            ->set(self::CC_EXPIRES, null)
            ->set(self::CC_MASKED, null)
            ->set($this->getOldStripeTokenKey(), null)
            ->update();
    }

    public function processRefund(InvoicePayment $payment, Am_Paysystem_Result $result, $amount)
    {
        $tr = new Am_Paysystem_Transaction_Stripe_Refund($this, $payment->getInvoice(), $payment->receipt_id, $amount);
        $tr->run($result);
    }

    public function createTransaction($request, $response, array $invokeArgs)
    {
        return Am_Paysystem_Transaction_Stripe_Webhook::create($this, $request, $response, $invokeArgs);
    }


    function directAction($request, $response, $invokeArgs)
    {
        $action = $request->getActionName();
        if (in_array($action, ['create', 'confirm'])) {
            $result = new Am_Paysystem_Result();

            try {
                $payload = @json_decode($request->getRawBody(), true);
                if (empty($payload)) {
                    throw new Am_Exception_InternalError(___('Access denied'));
                }

                $invoice = $this->getDi()->invoiceTable->findBySecureId($payload['invoice_id'], $this->getId());

                if (empty($invoice)) {
                    throw new Am_Exception_Security(___("Unable to fetch invoice record"));
                }

                switch ($action) {

                    case 'create' :
                        if (empty($payload['payment_method_id'])) {
                            throw new Am_Exception_InternalError(___("Payment method is empty"));
                        }

                        $token = $this->updateToken($invoice, [self::PAYMENT_METHOD => $payload['payment_method_id']]);

                        if(empty($token[self::CUSTOMER_ID]))
                        {
                            $tr = new Am_Paysystem_Transaction_Stripe_CreateCustomer($this,$invoice, $token[self::PAYMENT_METHOD], 'payment_method');
                            $tr->run($result);
                            if($result->isFailure())
                                break;
                            else
                                $result->reset();
                        }

                        $tr = new Am_Paysystem_Transaction_Stripe_PaymentIntentsCreate($this, $invoice,
                            $invoice->isFirstPayment());
                        $tr->run($result);
                        break;
                    case 'confirm' :
                        if (empty($payload['payment_intent_id'])) {
                            throw new Am_Exception_InternalError(___("Payment intent is empty"));
                        }
                        $this->updateToken($invoice, [self::PAYMENT_INTENT => $payload['payment_intent_id']]);

                        $tr = new Am_Paysystem_Transaction_Stripe_PaymentIntentsConfirm($this, $invoice,
                            $invoice->isFirstPayment());
                        $tr->run($result);
                        break;
                }

                $intent = $tr->getObject();

                if ($result->isFailure()) {
                    if (!empty($intent['status']) && in_array($intent['status'],
                            ['requires_action', 'requires_source_action'])) {
                        $response->setBody(json_encode(['requires_action' => 1, 'paymentIntent' => $intent]));
                    }
                    else {
                        $response->setBody(json_encode(['error' => ['message' => $result->getLastError()]]));
                    }
                }
                else {
                    $token = $this->getToken($invoice);
                    $result = new Am_Paysystem_Result();
                    if (!empty($token[self::CUSTOMER_ID])) {

                        $tr = new Am_Paysystem_Transaction_Stripe_GetPaymentMethod($this, $invoice);
                        $tr->run($result);
                        if($result->isSuccess() && empty($tr->getCustomer())) {

                            $result->reset();

                            $tr = new Am_Paysystem_Transaction_Stripe_AttachCustomer($this, $invoice,
                                $token[self::PAYMENT_METHOD], $token[self::CUSTOMER_ID]);
                            $tr->run($result);
                        }
                    }
                    else {
                        $tr = new Am_Paysystem_Transaction_Stripe_CreateCustomer($this, $invoice,
                            $token[self::PAYMENT_METHOD], 'payment_method');
                        $tr->run($result);
                    }

                    $response->setBody(json_encode($intent));
                }

            } catch (Exception $e) {
                $response->setBody(json_encode(['error' => ['message' => $e->getMessage()]]));
            }
        }
        else {
            parent::directAction($request, $response, $invokeArgs);
        }
    }

    public function validateToken(Invoice $invoice, $token = null)
    {
        if (empty($token)) {
            return parent::validateToken($invoice);
        }

        if (is_string($token)) {
            if (strpos($token, 'tok_') === 0) {
                $token = $this->_legacySaveToken($invoice, $token);
            }
            else {
                $token = @json_decode($token, true);
                if (empty($token)) {
                    throw new Am_Exception_Paysystem_TransactionInvalid('Token is empty in incoming request');
                }
            }
        }

        if (!empty($token['setupIntent'])) {
            $token = $this->convertPaymentTokenToLifetime($token, $invoice);
        }

        // Legacy token, need to query for default payment method.
        if (!empty($token[self::CUSTOMER_ID]) && empty($token[self::PAYMENT_METHOD])) {
            $result = new Am_Paysystem_Result();
            $tr = new Am_Paysystem_Transaction_Stripe_GetCustomerPaymentMethod($this, $invoice,
                $token[self::CUSTOMER_ID]);
            $tr->run($result);
            if ($result->isSuccess()) {
                $token[self::PAYMENT_METHOD] = $tr->getUniqId();
            }
            else {
                throw new Am_Exception_Paysystem_TransactionInvalid($result->getLastError());
            }
        }

        $this->saveToken($invoice, $token);
        return $token;
    }

    public function getToken(Invoice $invoice)
    {
        if ($customer_id = $invoice->getUser()->data()->get($this->getOldStripeTokenKey())) {
            // Convert to new token format
            $this->getDi()->CcRecordTable->getRecordByUser($invoice->getUser(),
                $invoice->paysys_id)->updateToken([self::CUSTOMER_ID => $customer_id])->save();
            $invoice->getUser()->data()->set($this->getOldStripeTokenKey(), null)->update();
        }

        return $this->getDi()->CcRecordTable->getRecordByUser($invoice->getUser(), $invoice->paysys_id)->getToken();
    }

    public function processTokenPayment(Invoice $invoice, $token = null)
    {
        $result = new Am_Paysystem_Result();
        try {
            $token = $this->validateToken($invoice, $token);
        } catch (Exception $e) {
            $result->setFailed($e->getMessage());
            return $result;
        }

        $tr = new Am_Paysystem_Transaction_Stripe($this, $invoice, $invoice->isFirstPayment());
        $tr->run($result);

        return $result;
    }

    function _legacySaveToken(Invoice $invoice, $token)
    {
        $result = new Am_Paysystem_Result();

        $tr = new Am_Paysystem_Transaction_Stripe_CreateCardSource($this, $invoice, $token);
        $tr->run($result);

        if ($result->isFailure()) {
            throw new Am_Exception_Paysystem_TransactionInvalid($result->getLastError());
        }

        $source = $tr->getInfo();

        $tr = new Am_Paysystem_Transaction_Stripe_CreateCustomer($this, $invoice, $source['id']);
        $tr->run($result);

        if ($result->isSuccess()) {
            $ccRecord = $this->getDi()->CcRecordTable->getRecordByInvoice($invoice);
            if ($card = $tr->getCard()) {
                $card = $card[0]['card'];
                $ccRecord->cc = str_pad($card['last4'], 12, "*", STR_PAD_LEFT);
                $ccRecord->cc_expire = sprintf('%02d%02d', $card['exp_month'], $card['exp_year'] - 2000);
            }
            $ccRecord->save();
        }
        else {
            throw new Am_Exception_Paysystem_TransactionInvalid(implode("\n", $result->getErrorMessages()));
        }
        return $ccRecord->getToken();
    }

    function _addStylesToForm(HTML_QuickForm2_Container $form)
    {
        $f = $form;
        while ($container = $f->getContainer())
            $f = $container;

        $f->addProlog(<<<CUT
<script src="https://js.stripe.com/v3/"></script>
<style>
.StripeElement {
  box-sizing: border-box;
  padding: .5em .75em;
  border: 1px solid #ced4da;;
  border-radius: 3px;
  background-color: white;
}

.StripeElement--focus {
  background-color: #ffffcf;
  border-color: #c1def5;
  box-shadow: 0 0 2px #c1def5;
}

.StripeElement--invalid {
  border-color: #fa755a;
}

.StripeElement--webkit-autofill {
  background-color: #fefde5 !important;
}
</style>
CUT
        );
    }

    function insertPaymentFormBrick(HTML_QuickForm2_Container $form)
    {
        if ($this->invoice->isFirstPayment() && doubleval($this->invoice->first_total) == 0) {
            return $this->insertUpdateFormBrick($form);
        }

        $id = $this->getId();

        $title = ___('Credit Card Info');
        $gr = $form->addGroup('')->setLabel($title);
        $gr->addHtml()->setHtml("<div id='{$id}-card-element'></div>");

        $jsTokenFieldName = $this->getJsTokenFieldName();

        $gr->addText($jsTokenFieldName,
            "id='{$jsTokenFieldName}' style='display:none; visibility:hidden;'");

        $gr->addHidden("{$id}_validator_enable", ['value' =>1])->addRule('required');

        $pubkey = $this->getConfig('public_key');

        $this->_addStylesToForm($form);

        $jsTokenFieldName = $this->getJsTokenFieldName();
        $returnUrl = $this->getReturnUrl();
        $createUrl = $this->getDi()->url('payment/' . $this->getId() . "/create");
        $confirmUrl = $this->getDi()->url('payment/' . $this->getId() . "/confirm");

        $locale = json_encode($this->getDi()->locale->getLanguage());

        $form->addScript()->setScript(<<<CUT
var stripe = Stripe('$pubkey', {locale: {$locale}});
var elements = stripe.elements();
var style = {
  base: {
    color: '#32325d',
    fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
    fontSmoothing: 'antialiased',
    fontSize: '16px',
    '::placeholder': {
      color: '#aab7c4'
    }
  },
  invalid: {
    color: '#fa755a',
    iconColor: '#fa755a'
  }
};
var card = elements.create('card', {style: style});
card.mount('#{$id}-card-element');
jQuery("#{$id}-card-element").closest('.am-row')
    .addClass('paysystem-toggle')
    .addClass('paysystem-toggle-{$id}');

card.addEventListener('change', function(event) {
    const frm = jQuery('#{$id}-card-element').closest('form');
    const validator = frm.validate();
    if (event.error) {
        validator.showErrors({
           '{$jsTokenFieldName}' : event.error.message
        });
    } else {
        validator.showErrors({
           '{$jsTokenFieldName}' : null
        });
    }
    
});
jQuery('#{$id}-card-element').closest('form').on("amFormSubmit", function(event){
    const frm = jQuery(event.target);
    const callback = event.callback;
    event.callback = null; // wait for completion
        stripe.createPaymentMethod('card', card).then(function(result) {
            if (result.error) {
                // Inform the user if there was an error.
                const validator = frm.validate();
                validator.showErrors({
               '{$jsTokenFieldName}' : result.error.message
               });
               frm.trigger('unlockui');
            } else {
                fetch('{$createUrl}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        payment_method_id: result.paymentMethod.id,
                        invoice_id: frm.find('input[name=id]').val()
                    })
                }).then(function(result) {
                    // Handle server response (see Step 3)
                    result.json().then(function(json) {
                        handleServerResponse(json);
                    })
                });
            }
        });
        function handleServerResponse(response){
            if (response.error) {
                const validator = frm.validate();
                validator.showErrors({
                    '{$jsTokenFieldName}' : response.error.message
                });
                frm.trigger('unlockui');
            } else if (response.requires_action) {
                handleAction(response);
            } else {
                document.location.href='{$returnUrl}';
            }
        }
        function handleAction(response) {
          stripe.handleCardAction(
            response.paymentIntent.client_secret
          ).then(function(result) {
            if (result.error) {
                const validator = frm.validate();
                validator.showErrors({
                    '{$jsTokenFieldName}' : result.error.message
                });
                frm.trigger('unlockui');
            } else {
              fetch('{$confirmUrl}', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                  payment_intent_id: result.paymentIntent.id,
                  invoice_id: frm.find('input[name=id]').val()
                })
              }).then(function(confirmResult) {
                return confirmResult.json();
              }).then(handleServerResponse);
            }
          });
}

});
CUT
        );
    }

    function insertUpdateFormBrick(HTML_QuickForm2_Container $form)
    {
        $id = $this->getId();
        $jsTokenFieldName = $this->getJsTokenFieldName();
        $invoice = !empty($this->invoice) ? $this->invoice : $this->getDi()->invoiceRecord;

        $result = new Am_Paysystem_Result();

        $title = ___('Credit Card Info');
        $gr = $form->addGroup('')->setLabel($title);
        $gr->addHtml()->setHtml("<div id='{$id}-card-element'></div>");

        $gr->addText($jsTokenFieldName,
            "id='{$jsTokenFieldName}' style='display:none; visibility:hidden;'");
        $gr->addHidden("{$id}_validator_enable", ['value' =>1])->addRule('required');

        $pubkey = $this->getConfig('public_key');

        $this->_addStylesToForm($form);

        $transaction = new Am_Paysystem_Transaction_Stripe_SetupIntent($this, $invoice);
        $transaction->run($result);

        if ($result->isFailure()) {
            return $form->addHTML()->setHTML(sprintf("<div class='am-error'>%s</div>",
                implode(", ", $result->getErrorMessages())));
        }

        $clientSecret = $transaction->getClientSecret();

        $locale = json_encode($this->getDi()->locale->getLanguage());

        $form->addScript()->setScript(<<<CUT
var stripe = Stripe('$pubkey', {locale: {$locale}});
var elements = stripe.elements();
var style = {
  base: {
    color: '#32325d',
    fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
    fontSmoothing: 'antialiased',
    fontSize: '16px',
    '::placeholder': {
      color: '#aab7c4'
    }
  },
  invalid: {
    color: '#fa755a',
    iconColor: '#fa755a'
  }
};
var card = elements.create('card', {style: style});
card.mount('#{$id}-card-element');
jQuery("#{$id}-card-element").closest('.am-row')
    .addClass('paysystem-toggle')
    .addClass('paysystem-toggle-{$id}');

card.addEventListener('change', function(event) {
    const frm = jQuery('#{$id}-card-element').closest('form');
    if(jQuery("#{$id}-card-element").closest('.am-row').is(':visible')){
    
        const validator = frm.validate();
        if (event.error) {
            validator.showErrors({
               '{$jsTokenFieldName}' : event.error.message
            });
        } else {
            validator.showErrors({
               '{$jsTokenFieldName}' : null
            });
        }
    }
    
});
jQuery('#{$id}-card-element').closest('form').on("amFormSubmit", function(event){
    const frm = jQuery(event.target);
    const callback = event.callback;
    event.callback = null; // wait for completion
    if(jQuery("#{$id}-card-element").closest('.am-row').is(':visible')){
        stripe.handleCardSetup('{$clientSecret}', card).then(function(result) {
            if (result.error) {
                // Inform the user if there was an error.
                const validator = frm.validate();
                    validator.showErrors({
                   '{$jsTokenFieldName}' : result.error.message
                });
                frm.trigger('unlockui');
            } else {
               document.getElementById('{$jsTokenFieldName}').value = JSON.stringify(result);
               callback();
            }
        });
    }else{
        callback();
    }
});

CUT
        );
    }

    /**
     * @param $shortTimeToken
     * @return array|mixed
     * @throws Am_Exception_Paysystem_TransactionInvalid
     */
    function convertPaymentTokenToLifetime($shortTimeToken, Invoice $invoice)
    {
        if ($shortTimeToken['setupIntent']['status'] !== 'succeeded') {
            throw new Am_Exception_Paysystem_TransactionInvalid(___('Payment Declined'));
        }

        $savedToken = $this->getToken($invoice);
        $result = new Am_Paysystem_Result();
        if (!empty($savedToken[self::CUSTOMER_ID])) {
            $tr = new Am_Paysystem_Transaction_Stripe_AttachCustomer(
                $this, $invoice, $shortTimeToken['setupIntent']['payment_method'], $savedToken[self::CUSTOMER_ID]
            );
            $tr->run($result);
            $customer_id = $savedToken[self::CUSTOMER_ID];

        }
        else {
            $tr = new Am_Paysystem_Transaction_Stripe_CreateCustomer($this, $invoice,
                $shortTimeToken['setupIntent']['payment_method'], 'payment_method');
            $tr->run($result);
            $customer_id = $tr->getUniqId();
        }

        if ($result->isFailure()) {
            throw new Am_Exception_Paysystem_TransactionInvalid(___('Payment Declined'));
        }
        return [
            self::CUSTOMER_ID => $customer_id,
            self::PAYMENT_METHOD => $shortTimeToken['setupIntent']['payment_method']
        ];
    }
}

abstract class Am_Paysystem_Transaction_Stripe_Abstract extends Am_Paysystem_Transaction_CreditCard
{
    protected $parsedResponse = [];

    public function __construct(Am_Paysystem_Abstract $plugin, Invoice $invoice, $doFirst = true)
    {

        $plugin->getDi()->store->set(sprintf('%s-%s-api-request', $plugin->getId(), $invoice->pk()), 1, "+2 minutes");

        $request = $this->_createRequest($plugin, $invoice, $doFirst);
        $request
            ->setAuth($plugin->getConfig('secret_key'), '');

        parent::__construct($plugin, $invoice, $request, $doFirst);

    }

    abstract function _createRequest(Am_Paysystem_Abstract $plugin, Invoice $invoice, $doFirst = true);

    function getObject()
    {
        return $this->parsedResponse;
    }

    public function parseResponse()
    {
        $this->parsedResponse = json_decode($this->response->getBody(), true);
    }
}

class Am_Paysystem_Transaction_Stripe_SetupIntent extends Am_Paysystem_Transaction_Stripe_Abstract
{
    function _createRequest(Am_Paysystem_Abstract $plugin, Invoice $invoice = null, $doFirst = true)
    {
        $request = new Am_HttpRequest("https://api.stripe.com/v1/setup_intents", 'POST');
        $request->addPostParameter('usage', 'off_session');
        return $request;
    }

    function getUniqId()
    {
        return $this->parsedResponse['id'];
    }

    function getClientSecret()
    {
        return $this->parsedResponse['client_secret'];
    }

    function validate()
    {
        if (!empty($this->parsedResponse['error'])) {
            $this->result->setFailed($this->parsedResponse['error']['message']);
        }
        else {
            if (empty($this->parsedResponse['client_secret'])) {
                $this->result->setFailed(___('Unable to initialize payment'));
            }
            else {
                $this->result->setSuccess($this);
            }
        }
    }

    function processValidated()
    {
    }
}

class Am_Paysystem_Transaction_Stripe_PaymentIntentsCreate extends Am_Paysystem_Transaction_Stripe_Abstract
{
    function _createRequest(Am_Paysystem_Abstract $plugin, Invoice $invoice, $doFirst = true)
    {
        $request = new Am_HttpRequest("https://api.stripe.com/v1/payment_intents", 'POST');
        $token = $plugin->getToken($invoice);

        $amount = $doFirst ? $invoice->first_total : $invoice->second_total;

        $vars = [
            'amount' => $amount * (pow(10, Am_Currency::$currencyList[$invoice->currency]['precision'])),
            'payment_method' => $token[Am_Paysystem_Stripe::PAYMENT_METHOD],
            'currency' => $invoice->currency,
            'confirmation_method' => 'manual',
            'confirm' => 'true',
            'setup_future_usage' => 'off_session',
            'description' => "Invoice #{$invoice->public_id}: {$invoice->getLineDescription()}",
            'metadata[invoice]' => $invoice->public_id,
            'metadata[Full Name]'=> $invoice->getName(),
            'metadata[Email]' =>  $invoice->getEmail(),
            'metadata[Username]'=> $invoice->getLogin(),
            'metadata[Address]' => "{$invoice->getCity()} {$invoice->getState()} {$invoice->getZip()} {$invoice->getCountry()}",
            'metadata[Order Date]' => $invoice->tm_added,
            'metadata[Purchase Type]'=>$invoice->rebill_times ? "Recurring" : "Regular",
            'metadata[Total]' => Am_Currency::render($doFirst ? $invoice->first_total : $invoice->second_total, $invoice->currency)
        ];
        if(isset($token[Am_Paysystem_Stripe::CUSTOMER_ID])){
            $vars['customer'] = $token[Am_Paysystem_Stripe::CUSTOMER_ID];
        }
        $request->addPostParameter($vars);
        return $request;

    }

    function getUniqId()
    {
        return @$this->parsedResponse['charges']['data'][0]['id']?:$this->parsedResponse['id'];
    }

    function validate()
    {
        if (!empty($this->parsedResponse['error'])) {
            $this->result->setFailed($this->parsedResponse['error']['message']);
        }
        else {
            if ($this->parsedResponse['status'] != 'succeeded') {
                $this->result->setFailed(___('Status is not succeeded'));
            }
            else {
                $this->result->setSuccess($this);
            }
        }
    }
}

class Am_Paysystem_Transaction_Stripe_GetPaymentMethod extends Am_Paysystem_Transaction_Stripe_Abstract
{

    function getUniqId()
    {
        return $this->parsedResponse['id'];
    }

    function getCard()
    {
        return $this->parsedResponse['card'];
    }
    function getCustomer()
    {
        return $this->parsedResponse['customer'];
    }

    function _createRequest(Am_Paysystem_Abstract $plugin, Invoice $invoice, $doFirst = true)
    {
        $token = $plugin->getToken($invoice);
        $request = new Am_HttpRequest("https://api.stripe.com/v1/payment_methods/".$token[Am_Paysystem_Stripe::PAYMENT_METHOD], 'GET');
        return $request;
    }

    function validate()
    {
        if (!empty($this->parsedResponse['error'])) {
            $this->result->setFailed($this->parsedResponse['error']['message']);
        }
        else {
            $this->result->setSuccess($this);
        }
    }

    function processValidated()
    {
    }
}

class Am_Paysystem_Transaction_Stripe_GetPaymentIntent extends Am_Paysystem_Transaction_Stripe_Abstract
{
    function getUniqId()
    {
        return $this->parsedResponse['id'];
    }

    function getCustomer()
    {
        return $this->parsedResponse['customer'];
    }

    function getLastChargeId()
    {
        return $this->parsedResponse['charges']['data'][0]['id'];
    }

    function _createRequest(Am_Paysystem_Abstract $plugin, Invoice $invoice, $doFirst = true)
    {
        $token = $plugin->getToken($invoice);
        $request = new Am_HttpRequest("https://api.stripe.com/v1/payment_intents/".$token[Am_Paysystem_Stripe::PAYMENT_INTENT], 'GET');
        return $request;
    }

    function validate()
    {
        if (!empty($this->parsedResponse['error'])) {
            $this->result->setFailed($this->parsedResponse['error']['message']);
        }
        else {
            $this->result->setSuccess($this);
        }
    }

    function processValidated()
    {
        $this->getPlugin()->updateToken($this->invoice, [
            Am_Paysystem_Stripe::CUSTOMER_ID => $this->getCustomer(),
            Am_Paysystem_Stripe::PAYMENT_METHOD => $this->parsedResponse['payment_method']
        ]);
    }
}

class Am_Paysystem_Transaction_Stripe_GetSetupIntent extends Am_Paysystem_Transaction_Stripe_Abstract
{
    function getUniqId()
    {
        return $this->parsedResponse['id'];
    }

    function _createRequest(Am_Paysystem_Abstract $plugin, Invoice $invoice, $doFirst = true)
    {
        $token = $plugin->getToken($invoice);
        $request = new Am_HttpRequest("https://api.stripe.com/v1/setup_intents/".$token[Am_Paysystem_Stripe::SETUP_INTENT], 'GET');
        return $request;
    }

    function validate()
    {
        if (!empty($this->parsedResponse['error'])) {
            $this->result->setFailed($this->parsedResponse['error']['message']);
        }
        else {
            $this->result->setSuccess($this);
        }
    }

    function processValidated()
    {
        $this->getPlugin()->validateToken($this->invoice, ['setupIntent' => $this->parsedResponse]);
    }
}


class Am_Paysystem_Transaction_Stripe_PaymentIntentsConfirm extends Am_Paysystem_Transaction_Stripe_Abstract
{
    function _createRequest(Am_Paysystem_Abstract $plugin, Invoice $invoice, $doFirst = true)
    {
        $token = $plugin->getToken($invoice);
        $request = new Am_HttpRequest(sprintf("https://api.stripe.com/v1/payment_intents/%s/confirm",
            $token[Am_Paysystem_Stripe::PAYMENT_INTENT]), 'POST');


        $amount = $doFirst ? $invoice->first_total : $invoice->second_total;
        $request->addPostParameter([
            'payment_method' => $token[Am_Paysystem_Stripe::PAYMENT_METHOD]
        ]);
        return $request;
    }

    function getUniqId()
    {
        return @$this->parsedResponse['charges']['data'][0]['id']?:$this->parsedResponse['id'];
    }

    function validate()
    {
        if (!empty($this->parsedResponse['error'])) {
            $this->result->setFailed($this->parsedResponse['error']['message']);
        }
        else {
            if ($this->parsedResponse['status'] != 'succeeded') {
                $this->result->setFailed(___('Status is not succeeded'));
            }
            else {
                $this->result->setSuccess($this);
            }
        }
    }
}

class Am_Paysystem_Transaction_Stripe extends Am_Paysystem_Transaction_Stripe_Abstract
{
    public function _createRequest(Am_Paysystem_Abstract $plugin, Invoice $invoice, $doFirst=true)
    {
        $token = $plugin->getToken($invoice);
        $request = new Am_HttpRequest('https://api.stripe.com/v1/payment_intents', 'POST');
        $amount = $doFirst ? $invoice->first_total : $invoice->second_total;
        $request->setAuth($plugin->getConfig('secret_key'), '')
            ->addPostParameter('amount',
                $amount * (pow(10, Am_Currency::$currencyList[$invoice->currency]['precision'])))
            ->addPostParameter('currency', $invoice->currency)
            ->addPostParameter('payment_method_types', ['card'])
            ->addPostParameter('customer', $token[Am_Paysystem_Stripe::CUSTOMER_ID])
            ->addPostParameter('description', "Invoice #{$invoice->public_id}: {$invoice->getLineDescription()}")
            ->addPostParameter('payment_method', $token[Am_Paysystem_Stripe::PAYMENT_METHOD])
            ->addPostParameter('off_session', 'true')
            ->addPostParameter('confirm', 'true')
            ->addPostParameter('metadata[invoice]', $invoice->public_id)
            ->addPostParameter('metadata[Full Name]', $invoice->getName())
            ->addPostParameter('metadata[Email]', $invoice->getEmail())
            ->addPostParameter('metadata[Username]', $invoice->getLogin())
            ->addPostParameter('metadata[Address]',
                "{$invoice->getCity()} {$invoice->getState()} {$invoice->getZip()} {$invoice->getCountry()}")
            ->addPostParameter('metadata[Order Date]', $invoice->tm_added)
            ->addPostParameter('metadata[Purchase Type]', $invoice->rebill_times ? "Recurring" : "Regular")
            ->addPostParameter('metadata[Total]',
                Am_Currency::render($doFirst ? $invoice->first_total : $invoice->second_total, $invoice->currency));
        return $request;
    }

    public function getUniqId()
    {
        return @$this->parsedResponse['charges']['data'][0]['id']?:$this->parsedResponse['id'];
    }

    public function validate()
    {
        if (@$this->parsedResponse['status'] != 'succeeded') {
            if ($this->parsedResponse['error']['type'] == 'card_error') {
                $this->result->setFailed($this->parsedResponse['error']['message']);
            }
            else {
                $this->result->setFailed(___('Payment failed'));
            }
            return false;
        }
        $this->result->setSuccess($this);
        return true;
    }
}

class Am_Paysystem_Transaction_Stripe_Charge extends Am_Paysystem_Transaction_CreditCard
{
    protected $parsedResponse = [];

    public function __construct(Am_Paysystem_Abstract $plugin, Invoice $invoice, $source)
    {
        $request = new Am_HttpRequest('https://api.stripe.com/v1/charges', 'POST');
        $amount = $invoice->first_total;
        $request->setAuth($plugin->getConfig('secret_key'), '')
            ->addPostParameter('amount',
                $amount * (pow(10, Am_Currency::$currencyList[$invoice->currency]['precision'])))
            ->addPostParameter('currency', $invoice->currency)
            ->addPostParameter('source', $source)
            ->addPostParameter('metadata[invoice]', $invoice->public_id)
            ->addPostParameter('description',
                'Invoice #' . $invoice->public_id . ': ' . $invoice->getLineDescription());
        parent::__construct($plugin, $invoice, $request, true);
    }

    public function getUniqId()
    {
        return $this->parsedResponse['id'];
    }

    public function parseResponse()
    {
        $this->parsedResponse = json_decode($this->response->getBody(), true);
    }

    public function validate()
    {
        if (empty($this->parsedResponse['paid']) || $this->parsedResponse['paid'] != 'true') {
            if ($this->parsedResponse['error']['type'] == 'card_error') {
                $this->result->setFailed($this->parsedResponse['error']['message']);
            }
            else {
                $this->result->setFailed(___('Payment failed'));
            }
            return false;
        }
        $this->result->setSuccess($this);
        return true;
    }
}

class Am_Paysystem_Transaction_Stripe_AttachCustomer extends Am_Paysystem_Transaction_CreditCard
{
    protected $parsedResponse = [];

    public function __construct(Am_Paysystem_Abstract $plugin, Invoice $invoice, $paymentMethod, $customer)
    {
        $request = new Am_HttpRequest(sprintf('https://api.stripe.com/v1/payment_methods/%s/attach', $paymentMethod),
            'POST');
        $request->setAuth($plugin->getConfig('secret_key'), '')
            ->addPostParameter('customer', $customer);
        parent::__construct($plugin, $invoice, $request, true);
    }

    public function getUniqId()
    {
        return $this->parsedResponse['id'];
    }

    function getObject()
    {
        return $this->parsedResponse;
    }

    public function parseResponse()
    {
        $this->parsedResponse = json_decode($this->response->getBody(), true);
    }

    public function validate()
    {
        if (empty($this->parsedResponse['id'])) {
            $code = @$this->parsedResponse['error']['code'];
            $message = @$this->parsedResponse['error']['message'];
            $error = "Error storing customer profile";
            if ($code) {
                $error .= " [{$code}]";
            }
            if ($message) {
                $error .= " ({$message})";
            }
            $this->result->setFailed($error);
            return false;
        }
        $this->result->setSuccess($this);
        return true;
    }

    public function processValidated()
    {
    }
}

class Am_Paysystem_Transaction_Stripe_CreateCustomer extends Am_Paysystem_Transaction_CreditCard
{
    protected $parsedResponse = [];

    public function __construct(Am_Paysystem_Abstract $plugin, Invoice $invoice, $token, $tokenName = 'card')
    {
        $request = new Am_HttpRequest('https://api.stripe.com/v1/customers', 'POST');
        $request->setAuth($plugin->getConfig('secret_key'), '')
            ->addPostParameter($tokenName, $token)
            ->addPostParameter('email', $invoice->getEmail())
            ->addPostParameter('name', $invoice->getUser()->getName())
            ->addPostParameter('description', 'Username:' . $invoice->getUser()->login);
        parent::__construct($plugin, $invoice, $request, true);
    }

    public function getUniqId()
    {
        return $this->parsedResponse['id'];
    }

    public function getCard()
    {
        switch (true) {
            case isset($this->parsedResponse['cards']) :
                return $this->parsedResponse['cards']['data'];
            case isset($this->parsedResponse['sources']) :
                return $this->parsedResponse['sources']['data'];
            default:
                return null;
        }
    }

    function getObject()
    {
        return $this->parsedResponse;
    }

    public function parseResponse()
    {
        $this->parsedResponse = json_decode($this->response->getBody(), true);
    }

    public function validate()
    {
        if (empty($this->parsedResponse['id'])) {
            $code = isset($this->parsedResponse['error']['code'])?$this->parsedResponse['error']['code'] : null;
            $message = isset($this->parsedResponse['error']['message'])?$this->parsedResponse['error']['message']:null;
            $error = "";

            if (!empty($message)) {
                $error .= $message;
            }

            if(empty($error)){
                $error = ___('Declined');
                if (!empty($code)) {
                    $error .= " [{$code}]";
                }
            }
            $this->result->setFailed($error);
            return false;
        }
        $this->result->setSuccess($this);
        return true;
    }

    public function processValidated()
    {
        $this->getPlugin()->updateToken($this->invoice, [Am_Paysystem_Stripe::CUSTOMER_ID => $this->getUniqId()]);
    }
}

class Am_Paysystem_Transaction_Stripe_CreateCardSource extends Am_Paysystem_Transaction_CreditCard
{
    protected $parsedResponse = [];

    public function __construct(Am_Paysystem_Abstract $plugin, Invoice $invoice, $token)
    {
        $request = new Am_HttpRequest('https://api.stripe.com/v1/sources', 'POST');
        $request->setAuth($plugin->getConfig('secret_key'), '')
            ->addPostParameter('type', 'card')
            ->addPostParameter('token', $token);
        parent::__construct($plugin, $invoice, $request, true);
    }

    public function getUniqId()
    {
        return $this->parsedResponse['id'];
    }

    public function parseResponse()
    {
        $this->parsedResponse = json_decode($this->response->getBody(), true);
    }

    public function validate()
    {
        if (empty($this->parsedResponse['id'])) {
            $this->result->setFailed(
                !empty($this->parsedResponse['error']['message']) ?
                    $this->parsedResponse['error']['message'] :
                    'Unable to fetch payment profile'
            );
        }
        else {
            $this->result->setSuccess($this);
        }
    }

    public function getInfo()
    {
        return $this->parsedResponse;
    }

    public function processValidated()
    {
    }
}

class Am_Paysystem_Transaction_Stripe_Create3dSecureSource extends Am_Paysystem_Transaction_CreditCard
{
    protected $parsedResponse = [];

    public function __construct(Am_Paysystem_Abstract $plugin, Invoice $invoice, $card)
    {
        $request = new Am_HttpRequest('https://api.stripe.com/v1/sources', 'POST');
        $request->setAuth($plugin->getConfig('secret_key'), '')
            ->addPostParameter('type', 'three_d_secure')
            ->addPostParameter('amount', $invoice->first_total * (pow(10, Am_Currency::$currencyList[$invoice->currency]['precision'])))
            ->addPostParameter('currency', $invoice->currency)
            ->addPostParameter('three_d_secure[card]', $card)
            ->addPostParameter('redirect[return_url]',
                $plugin->getPluginUrl('3ds', ['id' => $invoice->getSecureId("{$plugin->getId()}-3DS")]));
        parent::__construct($plugin, $invoice, $request, true);
    }

    public function getUniqId()
    {
        return $this->parsedResponse['id'];
    }

    public function parseResponse()
    {
        $this->parsedResponse = json_decode($this->response->getBody(), true);
    }

    public function validate()
    {
        if (empty($this->parsedResponse['id'])) {
            $this->result->setFailed(
                !empty($this->parsedResponse['error']['message']) ?
                    $this->parsedResponse['error']['message'] :
                    'Unable to fetch payment profile'
            );
        }
        else {
            $this->result->setSuccess($this);
        }
    }

    public function getInfo()
    {
        return $this->parsedResponse;
    }

    public function processValidated()
    {
    }
}

class Am_Paysystem_Transaction_Stripe_GetCustomer extends Am_Paysystem_Transaction_CreditCard
{
    protected $parsedResponse = [];

    public function __construct(Am_Paysystem_Abstract $plugin, Invoice $invoice, $token)
    {
        $request = new Am_HttpRequest('https://api.stripe.com/v1/customers/' . $token, 'GET');
        $request->setAuth($plugin->getConfig('secret_key'), '');
        parent::__construct($plugin, $invoice, $request, true);
    }

    public function getUniqId()
    {
        return $this->parsedResponse['id'];
    }

    public function parseResponse()
    {
        $this->parsedResponse = json_decode($this->response->getBody(), true);
    }

    public function validate()
    {
        if (empty($this->parsedResponse['id'])) {
            $this->result->setFailed('Unable to fetch payment profile');
        }
        else {
            $this->result->setSuccess($this);
        }
    }

    public function getInfo()
    {
        return $this->parsedResponse;
    }

    public function processValidated()
    {
    }
}

class Am_Paysystem_Transaction_Stripe_GetCustomerPaymentMethod extends Am_Paysystem_Transaction_CreditCard
{
    protected $parsedResponse = [];

    public function __construct(Am_Paysystem_Abstract $plugin, Invoice $invoice, $token)
    {
        $request = new Am_HttpRequest('https://api.stripe.com/v1/payment_methods?' . http_build_query([
                'limit' => 1,
                'type' => 'card',
                'customer' => $token
            ]), 'GET');
        $request->setAuth($plugin->getConfig('secret_key'), '');
        parent::__construct($plugin, $invoice, $request, true);
    }

    public function getUniqId()
    {
        return $this->parsedResponse['data'][0]['id'];
    }

    public function parseResponse()
    {
        $this->parsedResponse = json_decode($this->response->getBody(), true);
    }

    public function validate()
    {

        if (empty($this->parsedResponse['data'][0]['id'])) {
            $this->result->setFailed('Unable to fetch payment method');
        }
        else {
            $this->result->setSuccess($this);
        }
    }

    public function getInfo()
    {
        return $this->parsedResponse;
    }

    public function processValidated()
    {
    }
}

class Am_Paysystem_Transaction_Stripe_Refund extends Am_Paysystem_Transaction_CreditCard
{
    protected $parsedResponse = [];
    protected $charge_id;
    protected $amount;

    public function __construct(Am_Paysystem_Abstract $plugin, Invoice $invoice, $charge_id, $amount = null)
    {
        $this->charge_id = $charge_id;
        $this->amount = $amount > 0 ? $amount : null;
        $request = new Am_HttpRequest('https://api.stripe.com/v1/charges/' . $this->charge_id . '/refund', 'POST');
        $request->setAuth($plugin->getConfig('secret_key'), '');
        if ($this->amount > 0) {
            $request->addPostParameter('amount',
                $this->amount * (pow(10, Am_Currency::$currencyList[$invoice->currency]['precision'])));
        }
        parent::__construct($plugin, $invoice, $request, true);
    }

    public function getUniqId()
    {
        $r = null;
        $data = isset($this->parsedResponse['refunds']['data']) ? $this->parsedResponse['refunds']['data'] : $this->parsedResponse['refunds'];
        foreach ($data as $refund) {
            if (is_null($r) || $refund['created'] > $r['created']) {
                $r = $refund;
            }
        }
        return $r['id'];
    }

    public function parseResponse()
    {
        $this->parsedResponse = json_decode($this->response->getBody(), true);
    }

    public function validate()
    {
        if (empty($this->parsedResponse['id'])) {
            $this->result->setFailed('Unable to fetch payment profile');
        }
        else {
            $this->result->setSuccess();
        }
    }

    public function processValidated()
    {
        $this->invoice->addRefund($this, $this->charge_id, $this->amount);
    }
}

class Am_Paysystem_Transaction_Stripe_Webhook extends Am_Paysystem_Transaction_Incoming
{
    protected $event;

    function __construct(
        Am_Paysystem_Abstract $plugin,
        Am_Mvc_Request $request,
        Am_Mvc_Response $response,
        $invokeArgs
    ) {
        parent::__construct($plugin, $request, $response, $invokeArgs);
        $this->event = json_decode($request->getRawBody(), true);
    }

    static function create(
        Am_Paysystem_Abstract $plugin,
        Am_Mvc_Request $request,
        Am_Mvc_Response $response,
        $invokeArgs
    ) {
        $event = json_decode($request->getRawBody(), true);
        switch ($event['type']) {
            case "charge.refunded" :
                return new Am_Paysystem_Transaction_Stripe_Webhook_Refund($plugin, $request, $response, $invokeArgs);
                break;
            case "charge.succeeded" :
                return new Am_Paysystem_Transaction_Stripe_Webhook_Charge($plugin, $request, $response, $invokeArgs);
                break;
            case "radar.early_fraud_warning.created" :
                return new Am_Paysystem_Transaction_Stripe_Webhook_Efw($plugin, $request, $response,$invokeArgs);
                break;
            case "checkout.session.completed" :
                return new Am_Paysystem_Transaction_Stripe_Webhook_CheckoutSessionCompleted($plugin, $request, $response, $invokeArgs);
        }
    }

    public function validateSource()
    {
        return (bool)$this->event;
    }

    public function validateTerms()
    {
        return true;
    }

    public function validateStatus()
    {
        return true;
    }

    /**
     * Function must return an unique identified of transaction, so the same
     * transaction will not be handled twice. It can be for example:
     * txn_id form paypal, invoice_id-payment_sequence_id from other paysystem
     * invoice_id and random is not accceptable here
     * timestamped date of transaction is acceptable
     * @return string (up to 32 chars)
     */
    function getUniqId()
    {
        // TODO: Implement getUniqId() method.
    }

    /**
     * @return Am_Paysystem_Stripe
     */
    function getPlugin()
    {
        return parent::getPlugin();
    }
}

class Am_Paysystem_Transaction_Stripe_Webhook_Efw extends Am_Paysystem_Transaction_Stripe_Webhook
{
    function getUniqId()
    {
        return $this->event['data']['object']['id'];
    }

    function getChargeId()
    {
        return $this->event['data']['object']['charge'];
    }

    public function findInvoiceId()
    {
        $invoice = Am_Di::getInstance()->invoiceTable->findByReceiptIdAndPlugin($this->getChargeId(),
            $this->getPlugin()->getId());
        return ($invoice ? $invoice->public_id : null);
    }

    function processValidated()
    {
        if ($this->getPlugin()->getConfig('efw_refund') && $this->event['data']['object']['actionable']) {
            $payment = $this->getPlugin()->getDi()->invoicePaymentTable->findFirstBy([
                'invoice_public_id' => $this->findInvoiceId(),
                'receipt_id' => $this->getChargeId()
            ]);
            if ($payment) {
                $result = new Am_Paysystem_Result();

                $this->getPlugin()->processRefund($payment, $result, $payment->amount);

                if ($result->isFailure()) {
                    $this->getPlugin()->getDi()->logger->warning("Stripe EWF: Unable to process refund for {charge}. Got {error} from stripe",
                        [
                            'charge' => $this->getChargeId(),
                            'error' => $result->getLastError()
                        ]);
                }
                else {

                    $note = $this->getPlugin()->getDi()->userNoteRecord;
                    $note->user_id = $payment->user_id;
                    $note->dattm = $this->getPlugin()->getDi()->sqlDateTime;
                    $note->content = ___('Stripe EFW notification received for user payment  %s. Payment refunded. User disabled', $this->getChargeId());
                    $note->insert();

                    $payment->getUser()->lock();

                    if($this->getPlugin()->getDi()->plugins_misc->isEnabled('user-signup-disable'))
                    {
                        $payment->getUser()->updateQuick('signup_disable', 1);
                    }
                }
            }
        }
    }
}

class Am_Paysystem_Transaction_Stripe_Webhook_Refund extends Am_Paysystem_Transaction_Stripe_Webhook
{
    public function process()
    {
        $r = null;
        $refundsList = (isset($this->event['data']['object']['refunds']['data']) ? $this->event['data']['object']['refunds']['data'] : $this->event['data']['object']['refunds']);
        foreach ($refundsList as $refund) {
            if (is_null($r) || $refund['created'] > $r['created']) {
                $r = $refund;
            }
        }
        $this->refund = $r;
        return parent::process();
    }

    public function getUniqId()
    {
        return $this->refund['id'];
    }

    public function findInvoiceId()
    {
        if(!empty($this->event['data']['object']['metadata']['invoice']))
            return $this->event['data']['object']['metadata']['invoice'];
        if($invoice = Am_Di::getInstance()->invoiceTable->findByReceiptIdAndPlugin($this->event['data']['object']['id'], $this->getPlugin()->getId()))
            return $invoice->public_id;
    }

    public function processValidated()
    {
        try {
            $this->invoice->addRefund($this, $this->event['data']['object']["id"],
                $this->refund['amount'] / (pow(10, Am_Currency::$currencyList[$this->invoice->currency]['precision'])));
        } catch (Am_Exception_Db_NotUnique $e) {
            //nop, refund is added from aMemeber admin interface
        }
    }
}

class Am_Paysystem_Transaction_Stripe_Webhook_Charge extends Am_Paysystem_Transaction_Stripe_Webhook
{
    protected $_qty = [];

    function getUniqId()
    {
        return $this->event['data']['object']['id'];
    }

    function  generateInvoiceExternalId()
    {
        return $this->event['data']['object']['invoice'];
    }

    function generateUserExternalId(array $userInfo)
    {
        return $this->event['data']['object']['customer'];
    }

    function fetchUserInfo()
    {
        $req = new Am_HttpRequest('https://api.stripe.com/v1/customers/' . $this->generateUserExternalId([]), 'GET');
        $req->setAuth($this->getPlugin()->getConfig('secret_key'), '');

        $resp = $req->send();

        $parsedResponse = json_decode($resp->getBody(), true);
        [$name_f, $name_l] = explode(" ", $parsedResponse['name']);
        return [
            'email' => $parsedResponse['email'],
            'name_f' => $name_f,
            'name_l' => $name_l,
            'phone' => $parsedResponse['phone']
        ];
    }

    function findInvoiceId()
    {
        $public_id = @$this->event['data']['object']['metadata']['invoice'];
        if (empty($public_id)) {
            $public_id = $this->getPlugin()->getDi()->db->selectCell("
            SELECT invoice_public_id FROM ?_invoice_payment WHERE transaction_id=?
            ", $this->getUniqId());
        }
        return $public_id;
    }

    function autoCreateGetProducts()
    {
        $req = new Am_HttpRequest('https://api.stripe.com/v1/invoices/' . $this->generateInvoiceExternalId(), 'GET');
        $req->setAuth($this->getPlugin()->getConfig('secret_key'), '');

        $resp = $req->send();

        $parsedResponse = json_decode($resp->getBody(), true);
        if (empty($parsedResponse)) {
            return [];
        }
        $products = [];
        foreach ($parsedResponse['lines']['data'] as $line) {
            $bp = $this->getPlugin()->getDi()->billingPlanTable->findFirstByData('stripe_product_id',
                $line['plan']['product']);
            if ($bp) {
                $product = $bp->getProduct();
                $products[] = $product->setBillingPlan($bp);
                $this->_qty[$product->pk()] = @$line['quantity'] ?: 1;
            }
        }
        return $products;
    }

    function autoCreateGetProductQuantity(Product $pr)
    {
        return $this->_qty[$pr->pk()];
    }

    function validateStatus()
    {
        if($this->getPlugin()->getDi()->store->get(sprintf('%s-%s-api-request', $this->plugin->getId(), $this->invoice->pk()))){
            throw new Am_Exception_Paysystem_TransactionInvalid(___('Transaction is being processed by API at the moment'));
        }
        return true;
    }
}


class Am_Paysystem_Transaction_Stripe_Webhook_CheckoutSessionCompleted extends Am_Paysystem_Transaction_Stripe_Webhook
{
    protected $_qty = [];
    protected $charge_id;

    function getUniqId()
    {
        return $this->charge_id;
    }

    function validateStatus()
    {
        return true;
    }

    function findInvoiceId()
    {
        return $this->event['data']['object']['metadata']['invoice'];
    }

    function processValidated()
    {
        if(!empty($this->event['data']['object']['setup_intent']) && ($this->invoice->first_total==0)){
            $this->getPlugin()->updateToken($this->invoice, [Am_Paysystem_Stripe::SETUP_INTENT => $this->event['data']['object']['setup_intent']]);

            $tr = new Am_Paysystem_Transaction_Stripe_GetSetupIntent($this->getPlugin(), $this->invoice);
            $result = new Am_Paysystem_Result();
            $tr->run($result);

            if($result->isSuccess()){
                $this->invoice->addAccessPeriod($this);
            }

        }
        else if(!empty($this->event['data']['object']['payment_intent']))
        {
            $this->getPlugin()->updateToken($this->invoice, [Am_Paysystem_Stripe::PAYMENT_INTENT => $this->event['data']['object']['payment_intent']]);

            $tr = new Am_Paysystem_Transaction_Stripe_GetPaymentIntent($this->getPlugin(), $this->invoice);
            $result = new Am_Paysystem_Result();
            $tr->run($result);
            if($result->isSuccess()){
                $this->charge_id = $tr->getLastChargeId();
                $this->invoice->addPayment($this);
            }
        }
    }
}

class Am_Paysystem_Transaction_Stripe_CreateCheckoutSession extends Am_Paysystem_Transaction_Stripe_Abstract
{
    function _createRequest(Am_Paysystem_Abstract $plugin, Invoice $invoice = null, $doFirst = true)
    {
        $request = new Am_HttpRequest("https://api.stripe.com/v1/checkout/sessions", 'POST');
        $data = [
            'cancel_url' => $plugin->getCancelUrl(),
            'payment_method_types' => ['card'],
            'mode' => ($invoice->first_total>0) ? 'payment' : 'setup',
            'success_url' => $plugin->getReturnUrl(),
            'metadata[invoice]' => $invoice->public_id,
        ];


        if($data['mode'] == 'payment') {
            $data['payment_intent_data']['setup_future_usage'] ='off_session';
            $token = $plugin->getToken($invoice);

            if($customer_id = @$token[Am_Paysystem_Stripe::CUSTOMER_ID]){
                $data['customer'] = $customer_id;
            }

            $data['payment_intent_data']['description'] = "Invoice #{$invoice->public_id}: {$invoice->getLineDescription()}";
            $data['payment_intent_data']['metadata']['Country Code'] = $invoice->getCountry();

            $data['line_items'][] = [
                'amount' => $invoice->first_total * (pow(10,
                        Am_Currency::$currencyList[$invoice->currency]['precision'])),
                'currency' => $invoice->currency,
                'name' => $invoice->getLineDescription(),
                'description' => $invoice->getTerms(),
                'quantity' => 1
            ];
        }
        if(empty($data['customer'])){
            $data['customer_email'] = $invoice->getEmail();
        }
        $request->addPostParameter($data);
        return $request;
    }

    function getUniqId()
    {
        return $this->parsedResponse['id'];
    }

    function validate()
    {
        if (!empty($this->parsedResponse['error'])) {
            $this->result->setFailed($this->parsedResponse['error']['message']);
        }
        else {
            $this->result->setSuccess($this);
        }
    }

    function processValidated()
    {

    }
}