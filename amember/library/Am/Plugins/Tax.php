<?php
/**
 * Tax plugins storage
 * @package Am_Invoice
 */
class Am_Plugins_Tax extends Am_Plugins
{
    /** @return array of calculators */
    function match(Invoice $invoice)
    {
        $di = $invoice->getDi();
        $ret = [];
        foreach ($this->getEnabled() as $id)
        {
            $obj = $this->get($id);
            $calcs = $obj->getCalculators($invoice);
            if ($calcs && !is_array($calcs))
                $calcs = [$calcs];
            if ($calcs)
                $ret = array_merge($ret, $calcs);
        }
        return $ret;
    }

    function getAvailable()
    {
        class_exists('Am_Invoice_Tax', true);
        $result = [];
        foreach (get_declared_classes() as $class) {
            if (is_subclass_of($class, 'Am_Invoice_Tax'))
            {
                $c = ucfirst(str_replace('Am_Invoice_Tax_', '', $class));
                $id = fromCamelCase($c, '-');
                $title = ucwords(str_replace('-', ' ', $id));
                $result[$id] = $title;
            }
        }
        // set some well-known names
        $result['global-tax'] = ___('Global Tax');
        $result['regional']   = ___('Regional Tax');
        $result['vat2015']    = ___('EU VAT');
        $result['gst']        = ___('GST (Inclusive Tax)');

        return $result;
    }
}

