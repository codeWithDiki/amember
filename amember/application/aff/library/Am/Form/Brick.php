<?php

class Am_Form_Brick_Payout extends Am_Form_Brick
{
    protected $hideIfLoggedInPossible = self::HIDE_DONT;

    public function __construct($id = null, $config = null)
    {
        $this->name = ___('Payout Method');
        parent::__construct($id, $config);
    }

    public function insertBrick(HTML_QuickForm2_Container $form)
    {
        $module = Am_Di::getInstance()->modules->loadGet('aff');
        if ($module->getConfig('payout_methods'))
            Am_Di::getInstance()->modules->loadGet('aff')->addPayoutInputs($form);
    }

    public function isAcceptableForForm(Am_Form_Bricked $form)
    {
        return $form instanceof Am_Form_Signup_Aff;
    }
}

class Am_Form_Brick_ReferredBy extends Am_Form_Brick
{
    protected $hideIfLoggedInPossible = self::HIDE_ALWAYS;
    protected $labels = [
        'you were referred by %s'
    ];

    public function __construct($id = null, $config = null)
    {
        $this->name = ___('Referred By');
        if (empty($config['display'])) {
            $config['display'] = 'login';
        }
        parent::__construct($id, $config);
    }

    public function initConfigForm(Am_Form $form)
    {
        $form->addSelect('position')
            ->loadOptions([
                'below' => ___('Below Form'),
                'above' => ___('Above Form'),
                'inline' => ___('Brick Position')
            ])->setLabel('Position');
        $form->addAdvRadio('display')
            ->setLabel(___('Display'))
            ->loadOptions([
                'login' => ___('Username'),
                'name' => ___('Full Name'),
                'email' => ___('E-Mail')
            ]);
    }

    public function insertBrick(HTML_QuickForm2_Container $form)
    {
        if (($aff_id = Am_Di::getInstance()->modules->loadGet('aff')->findAffId()) &&
            ($aff = Am_Di::getInstance()->userTable->load($aff_id, false))) {

            switch ($this->getConfig('display')) {
                case 'name' :
                    $id = $aff->getName();
                    break;
                case 'email' :
                    $id = $aff->email;
                    break;
                case 'login' :
                default:
                    $id = $aff->login;
            }

            $text = $this->___('you were referred by %s', $id);
            $html = '<div class="am-aff-referred-by">' . $text . '</div>';

            switch ($this->getConfig('position', 'below')) {
                case 'above' :
                    $form->addProlog($html);
                    break;
                case 'below' :
                    $form->addEpilog($html);
                    break;
                default:
                    $form->addHtml(null, ['class'=>'no-lable am-row-wide'])
                        ->setHtml($html);
            }
        }
    }

    public function isAcceptableForForm(Am_Form_Bricked $form)
    {
        return $form instanceof Am_Form_Signup;
    }
}

class Am_Form_Brick_Aff extends Am_Form_Brick
{
    protected $hideIfLoggedInPossible = self::HIDE_ALWAYS;
    protected $labels = [
        "Affiliate\nemail or username",
        'This field is required',
        'there is not such affiliate',
    ];

    public function __construct($id = null, $config = null)
    {
        $this->name = ___('Affiliate');
        parent::__construct($id, $config);
    }

    public function init()
    {
        if (Am_Di::getInstance()->modules->loadGet('aff')->findAffId()) return;
        Am_Di::getInstance()->hook->add(Am_Event::SIGNUP_USER_ADDED, [$this, '_do']);
    }

    function _do(Am_Event $e)
    {
        $vars = $e->getVars();
        if (!empty($vars['_aff_id'])
            && $u = Am_Di::getInstance()->userTable->getByLoginOrEmail($vars['_aff_id'])
        ) {
            Am_Di::getInstance()->modules->loadGet('aff')->setAffiliate($e->getUser(), $u->pk());
            $e->getUser()->save();
        }
    }

    function _check($v)
    {
        return empty($v) || (($u = Am_Di::getInstance()->userTable->getByLoginOrEmail($v)) && $u->is_affiliate);
    }

    public function insertBrick(HTML_QuickForm2_Container $form)
    {
        if (Am_Di::getInstance()->modules->loadGet('aff')->findAffId()) return;

        $el = $form->addText('_aff_id')
            ->setLabel($this->___("Affiliate\nemail or username"));
        $el->addRule('callback', ___('there is not such affiliate'), [$this, '_check']);
        if ($this->getConfig('required')) {
            $el->addRule('required', $this->___('This field is required'));
        }
    }

    public function initConfigForm(Am_Form $form)
    {
        $form->addAdvCheckbox('required')
            ->setLabel(___('Required'));
    }

    public function isAcceptableForForm(Am_Form_Bricked $form)
    {
        return $form instanceof Am_Form_Signup;
    }
}