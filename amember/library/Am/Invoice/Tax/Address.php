<?php
trait Am_Invoice_Tax_Address
{
    /** Used for tax purposes */
    protected $_address;
    
    function getAddressSource()
    {
        if(!empty($this->_address))
            return $this->_address;
        
        if(!empty($this->tax_address_id))
        {
            $this->_address = $this->getDi()->addressTable->load($this->tax_address_id);
            return $this->_address;
        }
        
        return $this->getUser();
    }
    public function getStreet()
    {
        return trim($this->getStreet1() . ' ' . $this->getStreet2());
    }
    
    public function getStreet1()
    {
        return $this->getAddressSource()->street;
    }
    
    public function getStreet2()
    {
        return $this->getAddressSource()->street2;
    }
    
    public function getCity()
    {
        return $this->getAddressSource()->city;
    }
    
    public function getState()
    {
        return $this->getAddressSource()->state;
    }
    
    public function getCountry()
    {
        return $this->getAddressSource()->country;
    }
    
    public function getZip()
    {
        return $this->getAddressSource()->zip;
    }
    
}