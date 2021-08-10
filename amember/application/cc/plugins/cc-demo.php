<?php

/**
 * @am_payment_api 6.0
*/
class Am_Paysystem_CcDemo extends Am_Paysystem_CreditCard implements Am_Paysystem_TokenPayment
{
    use Am_Paysystem_TokenPayment_ValidateToken;

    const PLUGIN_STATUS = self::STATUS_PRODUCTION;
    const PLUGIN_DATE = '$Date$';
    const PLUGIN_REVISION = '6.3.6';

    public function __construct(Am_Di $di, array $config, $id = false)
    {
        $this->defaultTitle = ___("CC Demo");
        $this->defaultDescription = ___("use test credit card# for successful transaction");
        parent::__construct($di, $config, $id);
        if ($this->getConfig('allowed_ips') && ($_ = explode("\n", $this->getConfig('allowed_ips'))) && !in_array($_SERVER['REMOTE_ADDR'], $_)) {
            foreach ($di->paysystemList->getList() as $k => $p)
            {
                if ($p->getId() == $this->getId())
                    $p->setPublic(false);
            }
        }
    }

    public function storesCcInfo()
    {
        return true;
    }

    public function allowPartialRefunds()
    {
        return true;
    }

    public function getRecurringType()
    {
        return self::REPORTS_CRONREBILL;
    }

    public function getSupportedCurrencies()
    {
        return array_keys(Am_Currency::getFullList()); // support any
    }

    public function getCreditCardTypeOptions()
    {
        return array('visa' => 'Visa', 'mastercard' => 'MasterCard');
    }

    public function _doBill(Invoice $invoice, $doFirst, CcRecord $cc, Am_Paysystem_Result $result)
    {
        if ($this->getConfig('set_failed')) {
            $result->setFailed('Transaction declined.');
        } elseif ($cc->cc_number != $this->getConfig('cc_num', '4111111111111111')) {
            $result->setFailed("Please use configured test credit card number for successful payments with demo plugin");
        } elseif ($doFirst && (doubleval($invoice->first_total) <= 0)) { // free trial
            $tr = new Am_Paysystem_Transaction_Free($this);
            $tr->setInvoice($invoice);
            $tr->process();
            $result->setSuccess($tr);
        } else {
            $tr = new Am_Paysystem_Transaction_CcDemo($this, $invoice, null, $doFirst);
            $result->setSuccess($tr);
            $tr->processValidated();
        }
    }

    public function processRefund(InvoicePayment $payment, Am_Paysystem_Result $result, $amount)
    {
        $transaction = new Am_Paysystem_Transaction_CcDemo_Refund($this, $payment->getInvoice(), new Am_Mvc_Request(array('receipt_id'=>'rr')), false);
        $transaction->setAmount($amount);
        $result->setSuccess($transaction);
    }

    public function getGenerateCcNumJs()
    {
        return <<<CUT
function demo_cc_gen() {
	var pos;
	var str = new Array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0);
	var sum = 0;
	var final_digit = 0;
	var t = 0;
	var len_offset = 0;
	var len = 0;

	//
	// Fill in the first values of the string based with the specified bank's prefix.
	//

        str[0] = 4;
        pos = 1;
        len = 16;

	while (pos < len - 1) {
            str[pos++] = Math.floor(Math.random() * 10) % 10;
	}

	len_offset = (len + 1) % 2;
	for (pos = 0; pos < len - 1; pos++) {
		if ((pos + len_offset) % 2) {
			t = str[pos] * 2;
			if (t > 9) {
				t -= 9;
			}
			sum += t;
		}
		else {
			sum += str[pos];
		}
	}

	final_digit = (10 - (sum % 10)) % 10;
	str[len - 1] = final_digit;

	t = str.join('');
	t = t.substr(0, len);
	return t;
}

function generate_demo_cc_num()
{
   jQuery('#demo_cc_num').val(demo_cc_gen());
}
CUT;
    }

    public function getFormOptions()
    {
        $ret = $this->getConfig('address_info') ? [self::CC_CODE, self::CC_ADDRESS, self::CC_COUNTRY, self::CC_STATE, self::CC_CITY, self::CC_STREET, self::CC_ZIP, self::CC_PHONE] : [];
        return $ret;
    }

    public function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addTextarea('allowed_ips', ['class'=>'one-per-line'])
            ->setLabel("Show this plugin only to users with these IPs\n" .
                "useful for tests on live site, keep empty if there is not restrictions.\n" .
                "Your current IP is {$_SERVER['REMOTE_ADDR']}");

        $gr = $form->addGroup()
            ->setLabel("Test Credit Card#\ndefault value is 4111-1111-1111-1111")
            ->setSeparator(' ');

        $gr->addText('cc_num', 'id=demo_cc_num');
        $gr->addHtml('generate')->setHtml('<input type="button" value="Generate" onclick="generate_demo_cc_num()">'.
            '<script type="text/javascript">'.$this->getGenerateCcNumJs() . "</script>");

        $form->addAdvCheckbox('set_failed')
            ->setLabel(___("Decline all transactions\n" .
            'Plugin will decline all payment attempts'));
        $form->addAdvCheckbox("address_info")
            ->setLabel(___("Show Billing Address Info
                Display billing address info fields on CC info page"));
    }


    function supportsTokenPayment()
    {
        return true;
    }

    public function getToken(\Invoice $invoice)
    {
        return $invoice->data()->get('ccdemo-token');
    }

    public function processTokenPayment(\Invoice $invoice, $token = null)
    {
        $token = $this->validateToken($invoice, $token);

        $result = new Am_Paysystem_Result();

        if ($this->getConfig('set_failed'))
        {

            $result->setFailed('Transaction declined.');
        }
        else
        {
            $tr = new Am_Paysystem_Transaction_CcDemo($this, $invoice, null, $invoice->status == Invoice::PENDING);
            $result->setSuccess($tr);
            $tr->processValidated();
        }

        return $result;

    }

    public function saveToken(\Invoice $invoice, $token)
    {
        $invoice->data()->set('ccdemo-token', $token)->update();
        return $token;
    }


}

class Am_Paysystem_Transaction_CcDemo extends Am_Paysystem_Transaction_CreditCard
{
    protected $_id;
    protected static $_tm;

    public function getUniqId()
    {
        if (!$this->_id)
            $this->_id = 'D'. str_replace('.', '-', substr(sprintf('%.4f', microtime(true)), -7));
        return $this->_id;
    }

    public function parseResponse()
    {
    }

    public function getTime()
    {
        if (self::$_tm) return self::$_tm;
        return parent::getTime();
    }

    static function _setTime(DateTime $tm)
    {
        self::$_tm = $tm;
    }
}

class Am_Paysystem_Transaction_CcDemo_Refund extends Am_Paysystem_Transaction_CcDemo
{
    protected $_amount = 0.0;

    public function setAmount($amount)
    {
        $this->_amount = $amount;
    }

    public function getAmount()
    {
        return $this->_amount;
    }
}