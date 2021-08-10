<?php

trait Am_Paysystem_TokenPayment_ValidateToken
{

    function validateToken(Invoice $invoice, $token = null)
    {
        if (empty($token))
        {
            $token = $this->getToken($invoice);
        }
        else
        {
            $token = $this->saveToken($invoice, $token);
        }

        if (!$token)
        {
            throw new Am_Exception_Paysystem_TransactionInvalid(___('Payment Token is empty'));
        }
        return $token;
    }

}
