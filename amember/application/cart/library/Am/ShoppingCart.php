<?php

class Am_ShoppingCart
{
    /** Invoice */
    protected $invoice;
    protected $stick = [];
    /**
     * @var Invoice[]
     */
    protected $basketInvoices = [];

    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    function addItem($product, $qty = 1, $options = [])
    {
        $this->invoice->add($product, $qty, $options);
        if ($this->invoice->paysys_id) {
            $this->invoice->calculate();
            $plugin = $this->invoice->getDi()->plugins_payment->loadGet($this->invoice->paysys_id);
            if ($plugin->isNotAcceptableForInvoice($this->invoice)) {
                $this->invoice->paysys_id = null;
            }
        }
    }

    function deleteItem($product)
    {
        if ($this->isStick($product))
            throw new Am_Exception_InputError("This item is stick and cannot be removed");
        if ($item = $this->getInvoice()->findItem($product->getType(), $product->getProductId())) {
            $this->getInvoice()->deleteItem($item);
        }
    }

    /**
     * @return Invoice
     */
    function getInvoice()
    {
        return $this->invoice;
    }

    /**
     * @return array of InvoiceItem
     */
    function getItems()
    {
        return $this->invoice->getItems();
    }

    /**
     * @return Am_Currency
     */
    function getCurrency($amount)
    {
        return $this->invoice->getCurrency($amount);
    }

    function hasItem($product)
    {
        foreach($this->getItems() as $item) {
            if ($item->item_type == $product->getType() && $item->item_id == $product->pk()) return true;
        }
        return false;
    }

    function getItem($product)
    {
        foreach($this->getItems() as $item) {
            if ($item->item_id == $product->pk()) return $item;
        }
        return null;
    }

    /**
     * Item for this product_id cannot be removed from the cart
     */
    function stickItem($product)
    {
        $this->stick[$product->pk()] = true;
    }

    function unstickItem($product)
    {
        unset($this->stick[$product->pk()]);
    }

    function isStick($productOrItem)
    {
        if ($productOrItem instanceof Product)
            $k = $productOrItem->pk();
        elseif ($productOrItem instanceof InvoiceItem)
            $k = $productOrItem->item_id;
        else
            return false;
        return !empty($this->stick[$k]);
    }

    function getText()
    {
        $items = $this->invoice->getItems();
        if (!$this->invoice->getItems())
            return ___('You have no items in shopping cart');
        $c = count($items);
        return ___('You have %d items in shopping cart', $c);
    }

    /**
     * @param string $code
     * @return null|string null if ok, or error message
     */
    function setCouponCode($code)
    {
        $this->invoice->setCouponCode($code);
        $errors = $this->invoice->validateCoupon();
        if ($errors) $this->invoice->setCouponCode(null);
        return $errors;
    }

    function getCouponCode()
    {
        $coupon = $this->invoice->getCoupon();
        if ($coupon) return $coupon->code;
    }

    function setUser(User $user)
    {
        $this->invoice->setUser($user);
    }

    function calculate()
    {
        $this->invoice->calculate();
    }

    function clear()
    {
        $this->invoice = Am_Di::getInstance()->invoiceRecord;
        $this->stick = [];
    }

    function getInvoiceHash()
    {
        $hashParts = [];

        array_push($hashParts, $this->invoice->toArray());

        $data = $this->invoice->data()->getAll();

        uksort($data, function($a, $b){
            return strcasecmp($a, $b);
        });

        array_push($hashParts, $data);

        $items = $this->invoice->getItems();
        usort($items, function($a, $b){
            return strcasecmp($a->item_title, $b->item_title);
        });

        foreach($items as $item)
        {
            array_push($hashParts, $item);

            $data = $item->data()->getAll();

            uksort($data, function($a, $b){
                return strcasecmp($a, $b);
            });

            array_push($hashParts, $data);
        }

        return md5(json_encode($hashParts));
    }

    /**
     * @return true if cart has completed invoice
     */
    function isCompleted()
    {
        foreach($this->basketInvoices as $invoice) {
            if ($invoice->pk()) {
                $invoice->refresh();
            }
            if($invoice->isCompleted())
                return true;
        }
        return false;
    }

    function getInvoiceForCheckout()
    {
        $hash = $this->getInvoiceHash();

        if (isset($this->basketInvoices[$hash]))
            return $this->basketInvoices[$hash];

        $this->basketInvoices[$hash] = $checkoutInvoice = clone $this->invoice;
        $checkoutInvoice->save();

        return $checkoutInvoice;
    }

    function __wakeup()
    {
    }
}