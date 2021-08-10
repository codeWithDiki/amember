<?php

/**
 * Special processing for free invoices
 * @am_api_version 6.0
 * @am_version @AM_VERSION@
 */
class Am_Paysystem_Free extends Am_Paysystem_Abstract
{
    const PLUGIN_STATUS = self::STATUS_PRODUCTION;
    const PLUGIN_REVISION = '6.3.6';

    protected $defaultTitle = "Free Signup";
    protected $defaultDescription = "Totally free";

    function isNotAcceptableForInvoice(Invoice $invoice)
    {
        if (!$invoice->isZero())
            return array(___('Cannot use FREE payment plugin with a product which cost more than 0.0'));
    }

    function _process($invoice, $request, $result)
    {
        $result->setSuccess(new Am_Paysystem_Transaction_Free($this));
    }

    public function getRecurringType()
    {
        return self::REPORTS_NOT_RECURRING;
    }

    public function createTransaction($request, $response, array $invokeArgs)
    {
        return null;
    }

    public function onSetupForms(Am_Event_SetupForms $e)
    {
        return;
    }
}