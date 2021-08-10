<?php

use Stripe\Exception\InvalidRequestException;
use Stripe\PaymentIntent;

trait Am_Paysystem_Stripe_Helper_ActionRedirect
{
    
    /**
     * Payment intent data specific for payment method:
     * {
     *    'payment_method_data' => {}
     * }
     * if empty won't be included
     *
     * @return array $paymentIntentData
     */
    
    abstract function getPaymentMethodData(Invoice $invoice);
    
    /**
     * @param Invoice $invoice
     * @param Am_Mvc_Request $request
     * @param Am_Paysystem_Result $result
     */
    public function _process($invoice, $request, $result)
    {
        /**
         * @var Am_Paysystem_Stripe_PaymentMethod $this
         */
        $paymentIntentData = [
            'confirm' => true,
            'amount' => intval($invoice->first_total * 100),
            'currency' => $invoice->currency,
            'payment_method_types' => [$this->getStripePaymentMethodId()],
            'description' => $invoice->getLineDescription(),
            'return_url' => $this->getPluginUrl('thanks'),
            'metadata' => [
                'invoice' => $invoice->public_id
            ]
        ];
        
        $paymentMethodData = $this->getPaymentMethodData($invoice);
        
        if (!empty($paymentMethodData)) {
            $paymentIntentData['payment_method_data'] = $paymentMethodData;
        }
        $this->logRequest($paymentIntentData);
        
        try {
            $response = PaymentIntent::create($paymentIntentData);
            
            if (in_array($response->status, [
                    'requires_action',
                    'requires_source_action'
                ]) && $response->next_action->type == 'redirect_to_url') {
                $this->setPaymentIntentId($invoice, $response->id);
                $a = new Am_Paysystem_Action_Redirect($response->next_action->redirect_to_url->url);
                $result->setAction($a);
            } else {
                throw new Am_Exception_Paysystem_TransactionInvalid(___('Unable to start payment'));
            }
        } catch (InvalidRequestException $ex) {
            $response = $ex->getJsonBody();
        } catch (Exception $ex) {
            $response = !empty($response) ? $response : $ex->getMessage();
            $result->setFailed($ex->getMessage());
        } finally {
            $this->logResponse($response);
        }
    }
    
}