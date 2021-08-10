<?php

use Stripe\Event;
use Stripe\Webhook as StripeWebhook;

abstract class Am_Paysystem_Stripe_Transaction_Incoming extends Am_Paysystem_Transaction_Incoming
{
    /**
     * @var Event $event;
     */
    protected $event;
    
    function __construct($plugin, $request, $response, $invokeArgs)
    {
        parent::__construct($plugin, $request, $response, $invokeArgs);
        $this->event = Event::constructFrom(json_decode($request->getRawBody(), true));
    }

    /**
     * @param Am_Paysystem_Stripe_PaymentMethod $plugin
     * @param Am_Mvc_Request $request
     * @param Am_Mvc_Response $response
     * @param $invokeArgs
     */
    static function create($plugin, $request, $response, $invokeArgs)
    {
        $event = json_decode($request->getRawBody(), true);
        switch($event['type'])
        {
            case 'payment_intent.succeeded' :
            case 'payment_intent.payment_failed' :
            case 'payment_intent.processing' :
                return new Am_Paysystem_Stripe_Transaction_PaymentIntent($plugin, $request, $response, $invokeArgs);
        }
    }
}