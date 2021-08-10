<?php

use Stripe\PaymentIntent;

trait Am_Paysystem_Stripe_Helper_Transaction_PaymentIntent
{
    /**
     * @return PaymentIntent|bool $pi
     */
    abstract function getPaymentIntent();
    
    public function validateSource()
    {
        return $this->getPaymentIntent() !== false;
    }
    
    
    public function validateTerms()
    {
        return $this->getPaymentIntent()->amount == intval($this->invoice->first_total * 100);
    }
    
    public function validateStatus()
    {
        $status = ['succeeded'];
        
        if ($this->getPlugin()->isDelayedNotification()) {
            $status[] = 'processing';
        }
        
        return in_array($this->getPaymentIntent()->status, $status);
    }
    
    function findInvoiceId()
    {
        if (isset($this->getPaymentIntent()->metadata->invoice)) {
            return $this->getPaymentIntent()->metadata->invoice;
        } else {
            if ($invoice = $this->getPlugin()->findInvoiceByPaymentIntent($this->getPaymentIntent()->id)) {
                return $invoice->public_id;
            }
        }
    }
    
    function getUniqId()
    {
        if ($charge = $this->getPaymentIntent()->charges->first()) {
            return $charge->id;
        } else {
            return $this->getPaymentIntent()->id;
        }
    }
    
    
}