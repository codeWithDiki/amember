<?php

use Stripe\PaymentIntent;

class Am_Paysystem_Stripe_Transaction_Thanks extends Am_Paysystem_Transaction_Incoming_Thanks
{
    use Am_Paysystem_Stripe_Helper_Transaction_PaymentIntent;
    
    /**
     * @var PaymentIntent
     */
    protected $pi;
    
    /**
     * Am_Paysystem_Stripe_Transaction_Thanks constructor.
     * @param Am_Paysystem_Stripe_PaymentMethod $plugin
     * @param Am_Mvc_Request $request
     * @param Am_Mvc_Response $response
     * @param $invokeArgs
     * @throws \Stripe\Exception\ApiErrorException
     */
    function __construct($plugin, $request, $response, $invokeArgs)
    {
        parent::__construct($plugin, $request, $response, $invokeArgs);
        
        if ($_ = $request->getParam('payment_intent')) {
            $this->pi = PaymentIntent::retrieve($_);
            $this->getPlugin()->logOther("THANKS", $this->pi);
        }
    }
    
    function getPaymentIntent()
    {
        return !empty($this->pi) ? $this->pi : false;
    }
}