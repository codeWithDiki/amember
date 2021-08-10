<?php

use Stripe\PaymentIntent;

class Am_Paysystem_Stripe_Transaction_PaymentIntent extends Am_Paysystem_Stripe_Transaction_Incoming
{
    use Am_Paysystem_Stripe_Helper_Transaction_PaymentIntent;
    /**
     * @return PaymentIntent $pi;
     */
    function getPaymentIntent()
    {
        return $this->event->data->object;
    }
    
    function processValidated()
    {
        switch($this->getPaymentIntent()->status)
        {
            case 'succeeded' :
            case 'processing' :
                $this->invoice->addPayment($this);
                break;
            case 'requires_payment_method' :
                if($this->invoice->isCompleted())
                    $this->invoice->addVoid($this, $this->getPaymentIntent()->id);
                break;
        }
    }
}