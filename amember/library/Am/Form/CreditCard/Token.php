<?php

class Am_Form_CreditCard_Token extends Am_Form_CreditCard
{
    const PAYFORM = 'payform';
    const USER_UPDATE = 'user-update';
    const ADMIN_UPDATE = 'admin-update';
    const ADMIN_INSERT = 'admin-insert';
    
    protected $payButtons = [];
    
    /** @var Am_Paysystem_CreditCard_Token */
    protected $plugin;
    protected $formType = self::PAYFORM;
    
    public function __construct(Am_Paysystem_CreditCard_Token $plugin, $formType = self::PAYFORM)
    {
        $this->plugin = $plugin;
        $this->formType = $formType;
        $this->payButtons = [
            self::PAYFORM => ___('Subscribe And Pay'),
            self::ADMIN_UPDATE => ___('Update Credit Card Info'),
            self::USER_UPDATE => ___('Update Credit Card Info'),
            self::ADMIN_INSERT => ___('Update Credit Card Info'),
        ];
        Am_Form::__construct('cc');
    }
    
    public function init()
    {
        Am_Form::init();
        
        if($this->formType == self::USER_UPDATE){
            $this->plugin->insertUpdateFormBrick($this);
        }else{
            $this->plugin->insertPaymentFormBrick($this);
        }
        
        
        $this->addSubmit('_cc_', ['value' => $this->payButtons[$this->formType], 'class' => 'am-cta-pay']);
        
        $this->plugin->onFormInit($this);
    }
    
    /**
     * Return array of default values based on $user record
     * @param User $user
     */
    public function getDefaultValues(User $user)
    {
        return [];
    }
    
    public function validate()
    {
        return parent::validate() && $this->plugin->onFormValidate($this);
    }
    
    public function toCcRecord(CcRecord $cc)
    {
        $values = $this->getValue();
        if(!empty($values[$this->plugin->getJsTokenFieldName()]))
        {
            $token = @json_decode($values[$this->plugin->getJsTokenFieldName()], true);
            if(!is_array($token)){
                $token = [$this->plugin->getJsTokenFieldName() => $token];
            }
            $cc->setToken($token);
        }
    }
}