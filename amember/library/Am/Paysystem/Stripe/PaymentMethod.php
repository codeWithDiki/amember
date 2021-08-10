<?php

use Stripe\Stripe;

abstract class Am_Paysystem_Stripe_PaymentMethod extends Am_Paysystem_CreditCard
{
    protected $_pciDssNotRequired = true;
    
    const PAYMENT_INTENT_ID = 'stripe-payment-intent';
    
    /**
     * Can payment method be rechargable?
     * @return bool $isSingleUse
     */
    abstract function isSingleUse();
    
    abstract function getPaymentMethodTitle();
    
    abstract function getPaymentMethodDescription();
    
    abstract function getStripePaymentMethodId();
    
    /**
     * Should return true if plugin is delayed notification payment method.
     * Determines how postback will be processed, if delayed notification,
     * payment will be activated while payment intent is in processing stage
     * @return bool
     */
    abstract function isDelayedNotification();
    
    function isConfigured()
    {
        return $this->getConfig('public_key') && $this->getConfig('secret_key');
    }
    
    function init()
    {
        if ($this->isConfigured()) {
            Stripe::setApiKey($this->getConfig('secret_key'));
        }
    }
    
    
    function getTitle()
    {
        return ___($this->getConfig('title', $this->getPaymentMethodTitle()));
    }
    
    function getDescription()
    {
        return ___($this->getConfig('description', $this->getPaymentMethodDescription()));
    }
    
    public function storesCcInfo()
    {
        return false;
    }
    
    
    function getRecurringType()
    {
        return $this->isSingleUse() ? self::REPORTS_NOT_RECURRING : self::REPORTS_CRONREBILL;
    }
    
    public function _doBill(Invoice $invoice, $doFirst, CcRecord $cc, Am_Paysystem_Result $result)
    {
        // TODO: Implement _doBill() method.
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
        $form->setDefault('title', $this->getPaymentMethodTitle());
        $form->setDefault('description', $this->getPaymentMethodDescription());
    }
    
    function getReadme()
    {
        $url = $this->getPluginUrl('ipn');
        return <<<README
In your Stripe account set the following url to listen Webhook for events:
payment_intent.succeeded,  payment_intent.payment_failed, payment_intent.processing

Webhook Endpoint: {$url}
README;
    }
    
    function setPaymentIntentId(Invoice $invoice, $id)
    {
        $invoice->data()->set(self::PAYMENT_INTENT_ID, $id)->update();
    }
    
    function getPaymentIntentId(Invoice $invoice)
    {
        return $invoice->data()->get(self::PAYMENT_INTENT_ID);
    }
    
    function findInvoiceByPaymentIntent($id)
    {
        return $this->getDi()->invoiceTable->findFirstByData(self::PAYMENT_INTENT_ID, $id);
    }
    
    function createThanksTransaction($request, $response, array $invokeArgs)
    {
        return new Am_Paysystem_Stripe_Transaction_Thanks($this, $request, $response, $invokeArgs);
    }
    
    function createTransaction($request, $response, array $invokeArgs)
    {
        return Am_Paysystem_Stripe_Transaction_Incoming::create($this, $request, $response, $invokeArgs);
    }
    
}