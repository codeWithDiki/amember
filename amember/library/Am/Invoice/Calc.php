<?php

/**
 * Base class for InvoiceItem amount calculations
 * @package Am_Invoice
 */
abstract class Am_Invoice_Calc
{
    /** @var Invoice */
    protected $invoiceBill;
    /** @var string */
    protected $currentPrefix; // to be retreived in calculatePiece

    // prefix of field names to calculate, order makes se
    static public $_prefixes = ['first_', 'second_',];

    // fields to pass into the function
    static public $_noPrefixFields = [
        'qty',
        'is_tangible',
        'tax_group',
        'tax_rate'
    ];
    static public $_prefixFields = [
        'price',
        'discount',
        'tax',
        'total',
        'shipping',
    ];

    /**
     * Calculate piece of information
     * @param stdClass $fields to be calculated and modified
     */
    public function calculatePiece(stdClass $fields) { }

    public function calculate(Invoice $invoiceBill)
    {
        $this->invoiceBill = $invoiceBill;
        foreach ($invoiceBill->getItems() as $item)
        {
            $this->item = $item;
            foreach (self::$_prefixes as $prefix)
            {
                $this->currentPrefix = $prefix;
                $fields = new stdClass;
                foreach (self::$_noPrefixFields as $k)
                    $fields->$k = empty($item->$k) ? null : $item->$k;
                foreach (self::$_prefixFields as $k)
                {
                    $kk = $prefix ? $prefix .$k : $k;
                    if (isset($fields->$kk))
                        throw new Am_Exception_InternalError("Field is already defined [$k]");
                    $fields->$k = empty($item->$kk) ? 0 : $item->$kk;
                }
                $this->calculatePiece($fields);
                foreach (self::$_noPrefixFields as $k)
                    $item->$k = $fields->$k;
                foreach (self::$_prefixFields as $k)
                {
                    $kk = $prefix ? $prefix . $k : $k;
                    $item->$kk = $fields->$k;
                }
            }
            unset($this->item);
        }
    }
}

class Am_Invoice_Calc_Coupon extends Am_Invoice_Calc
{
    /** @var Coupon */
    protected $coupon;
    protected $user;

    public function calculate(Invoice $invoiceBill)
    {
        $this->coupon = $invoiceBill->getCoupon();
        $this->user = $invoiceBill->getUser();
        $isFirstPayment = $invoiceBill->isFirstPayment();
        $currency = $invoiceBill->currency;

        foreach ($invoiceBill->getItems() as $item) {
            $item->first_discount = $item->second_discount = 0;
            $item->_calculateTotal();
        }
        if (!$this->coupon) return;

        if ($this->coupon->getBatch()->discount_type == Coupon::DISCOUNT_PERCENT){
            foreach ($invoiceBill->getItems() as $item) {
                if ($this->coupon->isApplicable($item, $isFirstPayment))
                    $item->first_discount = $item->qty * Am_Currency::moneyRound($item->first_price * $this->coupon->getBatch()->discount / 100, $currency);
                if ($this->coupon->isApplicable($item, false))
                    $item->second_discount = $item->qty * Am_Currency::moneyRound($item->second_price * $this->coupon->getBatch()->discount / 100, $currency);
            }
        } else { // absolute discount
            $discountFirst = $this->coupon->getBatch()->discount;
            $discountSecond = $this->coupon->getBatch()->discount;

            $first_discountable = $second_discountable = [];
            $first_total = $second_total = 0;
            $second_total = array_reduce($second_discountable, function($s, $item) {return $s+=$item->second_total;}, 0);
            foreach ($invoiceBill->getItems() as $item) {
                if ($this->coupon->isApplicable($item, true)) {
                    $first_total += $item->first_total;
                    $first_discountable[] = $item;
                }
                if ($this->coupon->isApplicable($item, false)) {
                    $second_total += $item->second_total;
                    $second_discountable[] = $item;
                }
            }
            if ($first_total) {
                foreach ($first_discountable as $item) {
                    $k = max(0,min($discountFirst / $first_total, 1)); // between 0 and 1!
                    $item->first_discount = Am_Currency::moneyRound($item->first_total * $k, $currency);
                    $discountFirst -= $item->first_discount;
                    $first_total -= $item->first_total;
                }
            }
            if ($second_total) {
                foreach ($second_discountable as $item) {
                    $k = max(0,min($discountSecond / $second_total, 1)); // between 0 and 1!
                    $item->second_discount = Am_Currency::moneyRound($item->second_total * $k, $currency);
                    $discountFirst -= $item->first_discount;
                    $first_total -= $item->first_total;
                }
            }
        }

        foreach ($invoiceBill->getItems() as $item) {
            $item->_calculateTotal();
        }
    }
}

class Am_Invoice_Calc_Discount extends Am_Invoice_Calc
{
    protected $first, $second;

    public function __construct($first, $second)
    {
        $this->first = $first;
        $this->second = $second;
    }

    public function calculate(Invoice $invoiceBill)
    {
        if (!$this->first && !$this->second) return;

        $discountFirst = $this->first;
        $discountSecond = $this->second;
        $currency = $invoiceBill->currency;

        $first_discountable = $second_discountable = [];
        $first_total = $second_total = 0;
        foreach ($invoiceBill->getItems() as $item) {
            $first_total += $item->first_total;
            $first_discountable[] = $item;
            $second_total += $item->second_total;
            $second_discountable[] = $item;
        }
        if ($first_total) {
            $k = max(0,min($discountFirst / $first_total, 1)); // between 0 and 1!
            foreach ($first_discountable as $item) {
                $item->first_discount += Am_Currency::moneyRound($item->first_total * $k, $currency);
            }
        }
        if ($second_total) {
            $k = max(0, min($discountSecond / $second_total, 1)); // between 0 and 1!
            foreach ($second_discountable as $item) {
                $item->second_discount += Am_Currency::moneyRound($item->second_total * $k, $currency);
            }
        }
        foreach ($invoiceBill->getItems() as $item) {
            $item->_calculateTotal();
        }
    }
}

class Am_Invoice_Calc_Shipping extends Am_Invoice_Calc
{
    public function calculatePiece(stdClass $fields)
    {
        $fields->shipping = 0;
    }
}

// @todo 2 different taxes
class Am_Invoice_Calc_Tax extends Am_Invoice_Calc
{
    /** @var float */
    protected $tax_rate = 0.0;

    /** @var Am_Invoice_Tax **/
    protected $plugin;

    public function __construct($tax_id, $tax_title, Am_Invoice_Tax $plugin)
    {
        $this->tax_id = $tax_id;
        $this->tax_type = $tax_title;
        $this->plugin = $plugin;
    }

    public function calculatePiece(stdClass $fields)
    {
        $this->tax_rate = $this->plugin->getRate($this->invoiceBill, $this->item);
        $this->invoiceBill->tax_rate = $this->tax_rate;
        $fields->tax_rate = $this->tax_rate;
        $currency = $this->invoiceBill->currency;

        if ($fields->tax_group && ($fields->tax_group != IProduct::NO_TAX)) {
            if($this->plugin->getConfig('shipping')) {
                $fields->tax = Am_Currency::moneyRound(($fields->total) * $this->tax_rate / 100, $currency);
            } else {
                $fields->tax = Am_Currency::moneyRound(($fields->total - $fields->shipping) * $this->tax_rate / 100, $currency);
            }
        } else {
            $fields->tax = 0.0;
        }
        $fields->total += $fields->tax;
    }
}

class Am_Invoice_Calc_Tax_Absorb extends Am_Invoice_Calc_Tax
{
    function getPlugin()
    {
        return $this->plugin;
    }
    
    function getTaxRate()
    {
        return $this->plugin->getRate($this->invoiceBill, $this->item);
    }
    
    public function calculatePiece(stdClass $fields)
    {
        $this->tax_rate = $this->getTaxRate();

        $obj = $this->item ?: $this->invoiceBill;
        $b2b_rate = $obj->data()->get('vat-b2b-rate');

        $this->invoiceBill->tax_rate = $fields->tax_rate = $this->tax_rate;
        $currency = $this->invoiceBill->currency;

        if ($fields->tax_group && ($fields->tax_group != IProduct::NO_TAX))
        {
            $tax_rate = $this->tax_rate ?: $b2b_rate;
            $price = $fields->price;
            if($this->getPlugin()->getConfig('shipping')){
                $price_precise = ($price+$fields->shipping) / (1+($tax_rate/100)) - $fields->shipping;
            }else{
                $price_precise = $price / (1+($tax_rate/100));
            }

            $fields->price = floor($price_precise * 100)/100;

            $discount = $fields->discount;
            $this->item->data()->set("orig_{$this->currentPrefix}discount", $discount);

            $fields->discount = Am_Currency::moneyRound($fields->discount / (1 + $tax_rate/100), $currency);

            $fields->tax = $fields->tax_rate ? ($price * $fields->qty - $discount) - ($fields->price * $fields->qty - $fields->discount) : 0;
        } else {
            $fields->tax = 0.0;
        }
    }
}

class Am_Invoice_Calc_Total extends Am_Invoice_Calc
{
    public function calculate(Invoice $invoiceBill) {
        foreach ($invoiceBill->getItems() as $item)
            $item->_calculateTotal();
    }
}

class Am_Invoice_Calc_Zero extends Am_Invoice_Calc
{
    public function calculatePiece(stdClass $fields)
    {
        $orig_price = $this->item->data()->get('orig_' . $this->currentPrefix . 'price');
        $fields->price = $orig_price ? $orig_price : $fields->price;
        $fields->tax = $fields->discount = $fields->shipping = 0.0;
        $fields->total = Am_Currency::moneyRound($fields->price * $fields->qty, $this->invoiceBill->currency);
    }
}

class Am_Invoice_Calc_Tax_ChangeRate extends Am_Invoice_Calc_Tax_Absorb
{
    protected $tax_rate;
    
    function __construct($rate)
    {
        $this->tax_rate = $rate;
    }
    
    function getTaxRate()
    {
        return $this->tax_rate;
    }
    function getPlugin() : Am_Invoice_Tax
    {
        foreach (Am_Di::getInstance()->plugins_tax->getAllEnabled() as $plugin){
            if ($rate = $plugin->getRate($this->invoiceBill)){
                break;
            }
        }
        return $plugin;
    }
    function calculatePiece(stdClass $fields)
    {
        $fields->price = $fields->total+$fields->discount;
        
        return parent::calculatePiece($fields); // TODO: Change the autogenerated stub
    }
    
}

