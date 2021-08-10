<?php

interface Am_Paysystem_TokenPayment
{

    /**
     * @return true if token payments are supported, false if not
     */
    function supportsTokenPayment();

    /**
     * Process payment on invoice object using payment token received. 
     * @return Am_Paysystem_Result
     * @throws Am_Exception in case of failure
     */
    function processTokenPayment(Invoice $invoice, $token = null);

    /**
     * Retrun stored payment token from invoice record;
     * @param Invoice $invoice
     * @throws Am_Exception  in case of failure
     */
    function getToken(Invoice $invoice);

    /**
     * Save received payment token into invocie record for recurring billing
     * @param Invoice $invoice
     * @throws Am_Exception  in case of failure
     */
    function saveToken(Invoice $invoice, $token);

    /**
     * If token is presented you should validate it and save it;
     * If token is not presented itr should be loaded; 
     * @return mixed $token;
     * @throws Am_Exception on error;
     */
    function validateToken(Invoice $invoice, $token = null);
    
}
