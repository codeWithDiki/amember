<?php

/**
 * Renderable users query
 * @package Am_Query
 */
class Am_Query_User extends Am_Query_Renderable
{
    protected $template = 'admin/_user-search.phtml';

    function __construct()
    {
        parent::__construct(Am_Di::getInstance()->userTable, 'u');
    }

    function initPossibleConditions()
    {
        if ($this->possibleConditions) return; // already initialized
        $t = new Am_View;
        $record = $this->table->createRecord();
        $baseFields = $record->getTable()->getFields();
        $customFields = array_reduce(Am_Di::getInstance()->userTable->customFields()->getAll(), function($c, $i){return $c + [$i->name => $i];}, []);
        foreach ($baseFields as $field => $def){
            if($field == 'pass') continue;
            $title = ucwords(str_replace('_', ' ',$field));
            $f = new Am_Query_User_Condition_Field($field, isset($customFields[$field]) ? "{$customFields[$field]->title} ({$field})" : $title, $def->type, $def->null == 'YES');
            $this->possibleConditions[] = $f;
        }

        foreach (Am_Di::getInstance()->userTable->customFields()->getAll() as $field) {
            if((!isset($field->sql) || !$field->sql) && ($field->type!='hidden')) {
                $f = new Am_Query_User_Condition_Data($field->name, "{$field->title} ({$field->name})", $field->type, (isset($field->options)?$field->options:null), $field->isArray());
                $this->possibleConditions[] = $f;
            }
        }

        $this->possibleConditions[] = new Am_Query_User_Condition_AddedBetween;
        $this->possibleConditions[] = new Am_Query_User_Condition_HaveSubscriptionTo(null, null, 'any-completed', ___('Subscribed to any of (including expired)'));
        $this->possibleConditions[] = new Am_Query_User_Condition_HaveNoSubscriptionTo(null, 'none-completed', ___('Having no active subscription to'));
        $this->possibleConditions[] = new Am_Query_User_Condition_HaveSubscriptionTo(null, User::STATUS_ACTIVE, 'active', ___('Having active subscription to'));
        $this->possibleConditions[] = new Am_Query_User_Condition_HaveSubscriptionActiveOrFuture;
        $this->possibleConditions[] = new Am_Query_User_Condition_HaveSubscriptionTo(null, User::STATUS_EXPIRED, 'expired', ___('Having expired subscription to'));
        $this->possibleConditions[] = new Am_Query_User_Condition_HaveSubscriptionDue;
        $this->possibleConditions[] = new Am_Query_User_Condition_HaveCancellationDue;
        $this->possibleConditions[] = new Am_Query_User_Condition_HaveStartDue;
        $this->possibleConditions[] = new Am_Query_User_Condition_HavePaymentBetween;
        $this->possibleConditions[] = new Am_Query_User_Condition_HaveSubscriptionDate;
        $this->possibleConditions[] = new Am_Query_User_Condition_HaveSubscriptionBetween;
        $this->possibleConditions[] = new Am_Query_User_Condition_PurchasedProductWithOption;
        $this->possibleConditions[] = new Am_Query_User_Condition_HaveNumberProduct;
        $this->possibleConditions[] = new Am_Query_User_Condition_NeverSubscribed;
        $this->possibleConditions[] = new Am_Query_User_Condition_SpentAmount;
        $this->possibleConditions[] = new Am_Query_User_Condition_RefundAmount;
        $this->possibleConditions[] = new Am_Query_User_Condition_UsedCoupon;
        $this->possibleConditions[] = new Am_Query_User_Condition_LastSignin;
        $this->possibleConditions[] = new Am_Query_User_Condition_SameSignupIp;
        $this->possibleConditions[] = new Am_Query_User_Condition_ImportId;
        $this->possibleConditions[] = new Am_Query_User_Condition_UsedPaysys;
        $this->possibleConditions[] = new Am_Query_User_Condition_NotUsedPaysys;
        $this->possibleConditions[] = new Am_Query_User_Condition_HavePendingInvoice;
        $this->possibleConditions[] = new Am_Query_User_Condition_Usergroup;
        $this->possibleConditions[] = new Am_Query_User_Condition_NoUsergroup;
        $this->possibleConditions[] = new Am_Query_User_Condition_Agree;
        $this->possibleConditions[] = new Am_Query_User_Condition_NotAgree;
        $this->possibleConditions[] = new Am_Query_User_Condition_SameFirstLast;
        $this->possibleConditions[] = new Am_Query_User_Condition_DuplicateValue;
        $this->possibleConditions[] = new Am_Query_User_Condition_UserConsent;
        $this->possibleConditions[] = new Am_Query_User_Condition_UserNote;

        // add payment search options
        $this->possibleConditions[] = new Am_Query_User_Condition_Filter;
        $this->possibleConditions[] = new Am_Query_User_Condition_UserId;

        $this->possibleConditions = Am_Di::getInstance()->hook->filter($this->possibleConditions, Am_Event::USER_SEARCH_CONDITIONS);
    }
}

class Am_Query_User_Condition_Field extends Am_Query_Renderable_Condition_Field
{
    protected $fieldGroupTitle = 'User Base Fields';
    protected static $knownSelects = null;

    public function renderElement(HTML_QuickForm2_Container $form)
    {
        if (is_null(self::$knownSelects)) {

            $di = Am_Di::getInstance();

            $avail = $di->languagesListUser;
            $lang_list = [];
            if ($enabled = $di->getLangEnabled(false)) {
                foreach ($enabled as $lang) {
                    if (!empty($avail[$lang])) {
                        $lang_list[Am_Locale::guessLocaleByLang($lang)] = $avail[$lang];
                    }
                }
            }

            self::$knownSelects = [
                'status' => [0 => ___('Pending'), 1 => ___('Active'), 2 => ___('Expired')],
                'is_affiliate' => [0 => ___('Not Affiliate'), 1 => ___('Affiliate'), 2 => ___('Only Affiliate, not a member')],
                'is_approved' => [0 => ___('No'), 1 => ___('Yes')],
                'unsubscribed' => [0 => ___('No'), 1 => ___('Yes')],
                'is_locked' => [0 => ___('No'), 1 => ___('Yes, locked'), -1 => ___('Auto-locking is Disabled')],
                'i_agree' => [0 => ___('No'), 1 => ___('Yes')],
                'email_verified' => [0 => ___('No'), 1 => ___('Yes')],
                'saved_form_id' => $di->savedFormTable->getOptions(SavedForm::T_SIGNUP),
                'country' => $di->countryTable->getOptions(),
                'lang' => $lang_list,
            ];

            foreach ($di->userTable->customFields()->getAll() as $field) {
                if (isset($field->sql) && $field->sql && in_array($field->type, ['select', 'radio'])) {
                    self::$knownSelects[$field->name] = $field->options;
                }
            }
        }
        if (array_key_exists($this->field, self::$knownSelects)){
           $group = $this->addGroup($form);
           $group->addSelect('op')->loadOptions(['IN' => 'IN', 'NOT IN' => 'NOT IN']);
           $group->addMagicSelect('val')->loadOptions(self::$knownSelects[$this->field]);
        } else {
            return parent::renderElement($form);
        }
    }
}

class Am_Query_User_Condition_Data extends Am_Query_Condition_Data
{
    protected $title, $fieldType, $isNull, $options, $alias;
    protected $fieldGroupTitle = "Common (data) fields";
    static $renderOperations = ['=' => '=', '<>' => '<>', 'LIKE' => 'LIKE', 'NOT LIKE' => 'NOT LIKE'];
    static protected $validOperations = ['<','<>','=','>','<=','>=','<=>','IS NULL', 'IS NOT NULL', 'LIKE', 'NOT LIKE', 'IN', 'REGEXP'];

    function __construct($field, $title, $type, $options = null, $checkBlob = false)
    {
        $this->field = $field;
        $this->title = $title;
        $this->checkBlob = $checkBlob;
        $this->options = $options;
        $this->fieldType = $type;
    }

    protected function init($op, $value)
    {
        if (!in_array($op, self::$validOperations))
            throw new Am_Exception_InternalError("Invalid operator provided: " . htmlentities($op) . " in ".__METHOD__);
        $this->op = $op;
        $this->value = $value;
    }

    public function setFromRequest(array $input)
    {
        $id = $this->getId();
        if (isset($input[$id]) && ((array_key_exists($id, $input) && is_array($input[$id]['val']) && array_filter($input[$id]['val'])) || (!is_array($input[$id]['val']) && array_filter($input[$id], 'strlen')))) {
            if (is_array($input[$id]['val'])) {
                $input[$id]['op'] = 'REGEXP';
            } else {
                if (empty($input[$id]['op']))
                    $input[$id]['op'] = '=';
                if (($input[$id]['op'] == 'LIKE') && (strpos($input[$id]['val'], '%') === false)) {
                    $input[$id]['val'] = '%' . $input[$id]['val'] . '%';
                }

            }
            $this->init(@$input[$id]['op'], @$input[$id]['val']);
            return true;
        } else {
            $this->empty = true;
            $this->op = null;
        }
    }

    /**
     * @return HTML_QuickForm2_Container
     */
    public function addGroup(HTML_QuickForm2_Container $form)
    {
        $form->options[$this->fieldGroupTitle][$this->getId()] = $this->title;
        return $form->addGroup($this->getId())
                ->setLabel($this->title)
                ->setAttribute('id', $this->getId())
                ->setAttribute('class', 'searchField empty');
    }

    public function getId()
    {
        return 'data-field-' . $this->field;
    }

    public function renderElement(HTML_QuickForm2_Container $form)
    {
        $group = $this->addGroup($form);
        switch ($this->fieldType)
        {
            case 'checkbox' :
            case 'multi_select' :
                $group->addMagicSelect('val', null, ['options' => $this->options]);
                break;
            default:
                $group->addSelect('op')->loadOptions(self::$renderOperations);
                $group->addText('val');
        }
    }

    public function isEmpty()
    {
        return $this->op === null;
    }

    public function getDescription()
    {
        if(is_array($this->value)) {
            $op = ___('has all values selected');
            $val = $this->value;
            if(!is_null($this->options)){
                array_walk($val, [$this, 'getValue']);
            }
            $val = Am_Html::escape(implode(', ', array_filter($val)));
        } else {
            $val = Am_Html::escape($this->value);
            $op = $this->op;
        }
        return $this->title . ' ' . $op . ' [' . $val . ']';
    }

    function getValue(&$value)
    {
        $value = $this->options[$value];
    }

    function _getWhere(Am_Query $q) {
        if(is_array($this->value)) {
            $parts = [];
            foreach($this->value as $v)
            {
                $parts[] = sprintf(
                    "(%s.`%s`  REGEXP '.*;s:[0-9]+:\"%s\".*')",
                    $this->selfAlias(),
                    ($this->checkBlob ? 'blob' : 'value'),
                    str_replace('\'', '', $q->escape($v)));
            }
            return implode(' AND ',$parts);

        } else {
            return parent::_getWhere($q);
        }
    }

    private function getAlias(Am_Query $q)
    {
        return (is_null($this->tableAlias) ? $q->getAlias() : $this->tableAlias);
    }

    function selfAlias()
    {
        if(is_null($this->alias)){
            static $i;
            $i++;
            $this->alias = parent::selfAlias().$i;
        }
        return $this->alias;
    }
}

class Am_Query_User_Condition_AddedBetween extends Am_Query_Condition implements Am_Query_Renderable_Condition
{
    protected $start, $stop, $empty;

    public function setFromRequest(array $input)
    {
        $id = $this->getId();
        $this->start = null;
        $this->stop = true;
        if (!empty($input[$id]['start']) && !empty($input[$id]['stop']))
        {
            $this->start = $input[$id]['start'];
            $this->stop = $input[$id]['stop'];
            $this->empty = false;
            return true;
        }
        return false;
    }

    public function renderElement(HTML_QuickForm2_Container $form)
    {
       $title = ___('Added between dates:');

       $form->options[___('User Base Fields')][$this->getId()] = $title;
       $group = $form->addGroup($this->getId())
           ->setLabel($title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
       $group->addDate('start');
       $group->addDate('stop');
    }

    public function isEmpty()
    {
        return $this->empty;
    }

    public function getId(){ return 'added-between'; }

    public function getDescription()
    {
        return htmlentities(___('added between %s and %s',
            amDate($this->start), amDate($this->stop)));
    }

    function _getWhere(Am_Query $db)
    {
        $a = $db->getAlias();
        $start = $db->escape($this->start . ' 00:00:00');
        $stop = $db->escape($this->stop . ' 23:59:59');
        if (!$start || !$stop) return null;

        return "($a.added BETWEEN $start AND $stop)";
    }
}

class Am_Query_User_Condition_SameFirstLast extends Am_Query_Condition implements Am_Query_Renderable_Condition
{
    protected $val = null;

    public function renderElement(HTML_QuickForm2_Container $form)
    {
        $title = ___('Has Same First and Last Name');

        $form->options[___('Misc')][$this->getId()] = $title;
        $group = $form->addGroup($this->getId())
           ->setLabel($title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
        $group->addSelect('val')
            ->loadOptions([
                'yes' => 'Yes',
                'no' => 'No'
            ]);
    }

    public function getId(){ return 'slf'; }

    public function getDescription()
    {
        return htmlentities(___('has same first and last name'));
    }

    public function setFromRequest(array $input)
    {
        $this->val = isset($input[$this->getId()]['val']) ? $input[$this->getId()]['val'] : null;
        if (!is_null($this->val))
            return true;
    }

    public function isEmpty()
    {
        return !$this->val;
    }

    function _getWhere(Am_Query $db)
    {
        $a = $db->getAlias();
        $op = $this->val == 'yes' ? '=' : '<>';
        return "({$a}.name_f {$op} {$a}.name_l)";
    }
}

class Am_Query_User_Condition_SpentAmount
    extends Am_Query_Condition
    implements Am_Query_Renderable_Condition
{
    protected $title;
    protected $val = null;

    public function __construct()
    {
        $this->title = ___('Spent Amount');
    }

    public function getId() {
        return 'spent-amount';
    }

    public function isEmpty() {
        return !$this->val;
    }

    public function renderElement(HTML_QuickForm2_Container $form) {
       $form->options[___('Misc')][$this->getId()] = $this->title;
       $group = $form->addGroup($this->getId())
           ->setLabel($this->title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
        $group->addSelect('op')->loadOptions(['=' => '=', '<>'=>'<>', '>'=>'>', '<'=>'<', '>='=>'>=', '<='=>'<=',]);
        $group->addText('val', ['size'=>6]);
    }

    public function setFromRequest(array $input)
    {
        $this->op = @$input[$this->getId()]['op'];
        $this->val = isset($input[$this->getId()]['val']) ? $input[$this->getId()]['val'] : null;
        if (!is_null($this->val))
            return true;
    }

    public function getJoin(Am_Query $q)
    {
        $a = $q->getAlias();
        return "LEFT JOIN (SELECT user_id, SUM(amount) AS spent FROM
            (SELECT user_id, amount FROM ?_invoice_payment
            UNION ALL
            SELECT user_id, -1 * amount FROM ?_invoice_refund) tr GROUP BY tr.user_id) sp ON $a.user_id = sp.user_id";
    }

    function _getWhere(Am_Query $db)
    {
        return sprintf("(ifnull(spent, 0) %s %s)", $this->op, $db->escape($this->val));
    }

    public function getDescription()
    {
        return ___('spent %s %s', $this->op, Am_Currency::render($this->val));
    }
}

class Am_Query_User_Condition_RefundAmount
    extends Am_Query_Condition
    implements Am_Query_Renderable_Condition
{
    protected $title;
    protected $val = null;

    public function __construct()
    {
        $this->title = ___('Refund Amount');
    }

    public function getId()
    {
        return 'r-amnt';
    }

    public function isEmpty()
    {
        return !$this->val;
    }

    public function renderElement(HTML_QuickForm2_Container $form) {
       $form->options[___('Misc')][$this->getId()] = $this->title;
       $group = $form->addGroup($this->getId())
           ->setLabel($this->title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
        $group->addSelect('op')->loadOptions(['=' => '=', '<>'=>'<>', '>'=>'>', '<'=>'<', '>='=>'>=', '<='=>'<=',]);
        $group->addText('val', ['size'=>6]);
    }

    public function setFromRequest(array $input)
    {
        $this->op = @$input[$this->getId()]['op'];
        $this->val = isset($input[$this->getId()]['val']) ? $input[$this->getId()]['val'] : null;
        if (!is_null($this->val))
            return true;
    }

    public function getJoin(Am_Query $q)
    {
        $a = $q->getAlias();
        return <<<CUT
            LEFT JOIN
                (SELECT user_id, SUM(amount) AS refund_amount
                    FROM ?_invoice_refund rtr GROUP BY rtr.user_id) rsp ON $a.user_id = rsp.user_id
CUT;
    }

    function _getWhere(Am_Query $db)
    {
        return sprintf("(IFNULL(refund_amount, 0) %s %s)", $this->op, $db->escape($this->val));
    }

    public function getDescription()
    {
        return ___('Refund Amount %s %s', $this->op, Am_Currency::render($this->val));
    }
}

class Am_Query_User_Condition_UsedCoupon
extends Am_Query_Condition
implements Am_Query_Renderable_Condition
{
    protected $title;
    protected $val = null;

    public function __construct()
    {
        $this->title = ___('Used Coupon');
    }

    public function getId()
    {
        return 'coupon';
    }

    public function isEmpty()
    {
        return !$this->val;
    }

    public function renderElement(HTML_QuickForm2_Container $form) {
       $form->options[___('Misc')][$this->getId()] = $this->title;
       $group = $form->addGroup($this->getId())
           ->setLabel($this->title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
        $group->addText('val', ['size'=>8]);
    }

    public function setFromRequest(array $input)
    {
        $this->val = @$input[$this->getId()]['val'];
        if ($this->val)
            return true;
    }

    public function getJoin(Am_Query $q)
    {
        $a = $q->getAlias();
        return "LEFT JOIN ?_invoice uci ON $a.user_id = uci.user_id AND tm_started
            AND coupon_code = " . $q->escape($this->val);
    }

    function _getWhere(Am_Query $db)
    {
        return sprintf("(uci.coupon_code = %s)", $db->escape($this->val));
    }

    public function getDescription()
    {
        return ___('used coupon %s', $this->val);
    }
}

class Am_Query_User_Condition_Agree
extends Am_Query_Condition
implements Am_Query_Renderable_Condition
{
    protected $title;
    protected $val = null;

    public function __construct()
    {
        $this->title = ___('Agree to');
    }

    public function getId()
    {
        return 'agree_to';
    }

    public function isEmpty()
    {
        return !$this->val;
    }

    public function renderElement(HTML_QuickForm2_Container $form) {
       $form->options[___('Misc')][$this->getId()] = $this->title;
       $group = $form->addGroup($this->getId())
           ->setLabel($this->title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
        $group->addSelect('val')
            ->loadOptions(Am_Di::getInstance()->agreementTable->getTypeOptions());
    }

    public function setFromRequest(array $input)
    {
        $this->val = @$input[$this->getId()]['val'];
        if ($this->val)
            return true;
    }

    function _getWhere(Am_Query $db)
    {
        $a = $db->getAlias();
        $rev_id = Am_Di::getInstance()->db->selectCell(<<<CUT
            SELECT agreement_revision_id
                FROM ?_agreement
                WHERE type=?
                    AND is_current=1
CUT
            , $this->val);
        return sprintf(<<<CUT
            EXISTS (SELECT * FROM ?_user_consent uc
                WHERE uc.user_id={$a}.user_id
                AND uc.type=%s
                AND uc.cancel_dattm IS NULL
                AND uc.revision_id=%d)
CUT
            , $db->escape($this->val), $rev_id);
    }

    public function getDescription()
    {
        return ___('agree to %s', $this->val);
    }
}

class Am_Query_User_Condition_NotAgree
extends Am_Query_Condition
implements Am_Query_Renderable_Condition
{
    protected $title;
    protected $val = null;

    public function __construct()
    {
        $this->title = ___('Not Agree to');
    }

    public function getId()
    {
        return 'n_agree_to';
    }

    public function isEmpty()
    {
        return !$this->val;
    }

    public function renderElement(HTML_QuickForm2_Container $form) {
       $form->options[___('Misc')][$this->getId()] = $this->title;
       $group = $form->addGroup($this->getId())
           ->setLabel($this->title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
        $group->addSelect('val')
            ->loadOptions(Am_Di::getInstance()->agreementTable->getTypeOptions());
    }

    public function setFromRequest(array $input)
    {
        $this->val = @$input[$this->getId()]['val'];
        if ($this->val)
            return true;
    }

    function _getWhere(Am_Query $db)
    {
        $a = $db->getAlias();
        $rev_id = Am_Di::getInstance()->db->selectCell(<<<CUT
            SELECT agreement_revision_id
                FROM ?_agreement
                WHERE type=?
                    AND is_current=1
CUT
            , $this->val);
        return sprintf(<<<CUT
            NOT EXISTS (SELECT * FROM ?_user_consent uc
                WHERE uc.user_id={$a}.user_id
                AND uc.type=%s
                AND uc.cancel_dattm IS NULL
                AND uc.revision_id=%d)
CUT
            , $db->escape($this->val), $rev_id);
    }

    public function getDescription()
    {
        return ___('not agree to %s', $this->val);
    }
}

class Am_Query_User_Condition_LastSignin
extends Am_Query_Condition
implements Am_Query_Renderable_Condition
{
    protected $title;
    protected $val = null;

    public function __construct()
    {
        $this->title = ___('Last Signin');
    }

    public function getId()
    {
        return 'never-signin';
    }

    public function isEmpty()
    {
        return !$this->op;
    }

    public function renderElement(HTML_QuickForm2_Container $form)
    {
       $form->options[___('Misc')][$this->getId()] = $this->title;
       $group = $form->addGroup($this->getId())
           ->setLabel($this->title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
        $group->addSelect('op', ['id' => "{$this->getId()}-type"])
            ->loadOptions([
                'never' => ___('Never'),
                'between'=> ___('Between Dates'),
                'match-signup' => ___('Match Signup Date'),
                'match-signup-time' => ___('Match Signup Date and Time')
            ]);
        $group->addDate('from', ['placeholder' => ___('From'), 'rel' => "{$this->getId()}-type"]);
        $group->addDate('to', ['placeholder' => ___('To'), 'rel' => "{$this->getId()}-type"]);

        $group->addScript()
            ->setScript(<<<CUT
jQuery(function(){
    jQuery('#{$this->getId()}-type').change(function(){
        jQuery(this).closest('td').find("[rel={$this->getId()}-type]").toggle(jQuery(this).val() == 'between');
    }).change();
});
CUT
            );
    }

    public function setFromRequest(array $input)
    {
        $this->op = @$input[$this->getId()]['op'];
        $this->from = @$input[$this->getId()]['from'];
        $this->to = @$input[$this->getId()]['to'];
        if ($this->op)
            return true;
    }

    function _getWhere(Am_Query $db)
    {
        $where = '';
        switch ($this->op) {
            case 'never' :
                $where = '(u.last_login IS NULL)';
                break;
            case "match-signup" :
                $where = '(DATE(u.last_login) = DATE(u.added))';
                break;
            case "match-signup-time" :
                $where = "(DATE(u.last_login) = DATE(u.added) AND HOUR(u.last_login) = HOUR(u.added) AND MINUTE(u.last_login) = MINUTE(u.added))";
                break;
            case 'between' :
                $from = $this->from ? date('Y-m-d 00:00:00', amstrtotime($this->from)) : sqlTime('- 10 years');

                $to = $this->to ? date('Y-m-d 23:59:59', amstrtotime($this->to)) : sqlTime('tommorow');
                $where = sprintf('(u.last_login BETWEEN %s AND %s)',
                    $db->escape($from), $db->escape($to));
                break;
            default:
                new Am_Exception_InputError("Unknown operation type [$this->op]");
        }
        return $where;
    }

    public function getDescription()
    {
        switch ($this->op) {
            case 'never' :
                return ___('never signin');
            case 'match-signup' :
                return ___('last signin match signup date');
            case 'match-signup-time' :
                return ___('last signin match signup date and time');
            case 'between' :
                return ___('last signin between %s and %s',
                    $this->from ? amDate($this->from) : '-',
                    $this->to ? amDate($this->to) : '-'
                );
        }
    }
}

class Am_Query_User_Condition_SameSignupIp
    extends Am_Query_Condition
    implements Am_Query_Renderable_Condition
{
    protected $title;

    public function __construct()
    {
        $this->title = ___('Share Same Signup IP with other users');
    }

    public function getId()
    {
        return 'share-signup-ip';
    }

    public function isEmpty()
    {
        return !$this->do;
    }

    public function renderElement(HTML_QuickForm2_Container $form)
    {
        $form->options[___('Misc')][$this->getId()] = $this->title;
        $group = $form->addGroup($this->getId())
            ->setLabel($this->title)
            ->setAttribute('id', $this->getId())
            ->setAttribute('class', 'searchField empty');
        $group->addSelect('do')
            ->loadOptions([
                'yes' => ___('Yes'),
                'no' => ___('No'),
            ]);
    }

    public function setFromRequest(array $input)
    {
        $this->do = @$input[$this->getId()]['do'];
        if ($this->do)
            return true;
    }

    function _getWhere(Am_Query $db)
    {
        $a = $db->getAlias();
        $c = $this->do == 'yes' ? 'EXISTS' : 'NOT EXISTS';
        return "$c (SELECT * FROM ?_user WHERE remote_addr=$a.remote_addr AND user_id!=$a.user_id)";
    }

    public function getDescription()
    {
        return $this->do == 'yes' ?
            ___('share same signup IP with other users') :
            ___('not share same signup IP with other users');
    }
}

class Am_Query_User_Condition_UsedPaysys
extends Am_Query_Condition
implements Am_Query_Renderable_Condition
{
    protected $title;
    protected $paysys_id = null;

    public function __construct()
    {
        $this->title = ___('Has Used Payment System');
    }

    public function getId()
    {
        return 'paysys';
    }

    public function isEmpty()
    {
        return !$this->paysys_id;
    }

    public function renderElement(HTML_QuickForm2_Container $form)
    {
       $form->options[___('Misc')][$this->getId()] = $this->title;
       $group = $form->addGroup($this->getId())
           ->setLabel($this->title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
        $options = [];
        foreach (Am_Di::getInstance()->plugins_payment->loadEnabled()->getAllEnabled() as $pl) {
            if ($pl->getId() != 'free') $options[$pl->getId()] = $pl->getTitle();
        }
        $group->addMagicSelect('paysys_id')
           ->loadOptions($options);
    }

    public function setFromRequest(array $input)
    {
        $this->paysys_id = @$input[$this->getId()]['paysys_id'];
        if ($this->paysys_id)
            return true;
    }

    function _getWhere(Am_Query $db)
    {
        $a = $db->getAlias();
        $ids = implode(',', array_filter(array_map([Am_Di::getInstance()->db, 'escape'], $this->paysys_id)));
        if (!$ids) return null;
        return "EXISTS
            (SELECT * FROM ?_invoice_payment ups
            WHERE ups.user_id=$a.user_id AND ups.paysys_id IN ($ids))";
    }

    public function getDescription()
    {
        return ___('has used payment system %s', implode(', ', $this->paysys_id));
    }
}

class Am_Query_User_Condition_NotUsedPaysys
extends Am_Query_Condition
implements Am_Query_Renderable_Condition
{
    protected $title;
    protected $paysys_id = null;

    public function __construct()
    {
        $this->title = ___('Has Not Used Payment System');
    }

    public function getId()
    {
        return 'no-paysys';
    }

    public function isEmpty()
    {
        return !$this->paysys_id;
    }

    public function renderElement(HTML_QuickForm2_Container $form)
    {
       $form->options[___('Misc')][$this->getId()] = $this->title;
       $group = $form->addGroup($this->getId())
           ->setLabel($this->title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
        $options = [];
        foreach (Am_Di::getInstance()->plugins_payment->getAllEnabled() as $pl) {
            if ($pl->getId() != 'free') $options[$pl->getId()] = $pl->getTitle();
        }
        $group->addSelect('paysys_id', ['multiple'=>'multiple', 'size' => 5])
           ->loadOptions($options);
    }

    public function setFromRequest(array $input)
    {
        $this->paysys_id = @$input[$this->getId()]['paysys_id'];
        if ($this->paysys_id)
            return true;
    }

    function _getWhere(Am_Query $db)
    {
        $a = $db->getAlias();
        $ids = implode(',', array_filter(array_map([Am_Di::getInstance()->db, 'escape'], $this->paysys_id)));
        if (!$ids) return null;
        return "NOT EXISTS
            (SELECT * FROM ?_invoice_payment ups
            WHERE ups.user_id=$a.user_id AND ups.paysys_id IN ($ids))";
    }

    public function getDescription()
    {
        return ___('has not used payment system %s', implode(', ', $this->paysys_id));
    }
}

class Am_Query_Condition_Subscription extends Am_Query_Condition
{
    function getProductOptions()
    {
        return Am_Di::getInstance()->productTable->getProductOptions();
    }

    function extractProductIds($p)
    {
        return Am_Di::getInstance()->productTable->extractProductIds($p);
    }
}

class Am_Query_User_Condition_HavePendingInvoice
    extends Am_Query_Condition_Subscription
    implements Am_Query_Renderable_Condition {

    protected $product_ids, $empty;

    function getJoin(Am_Query $q)
    {
        $alias = 'piii';
        $i_alias = 'ipiii';
        $ids = array_map('intval', $this->product_ids);
        $productsCond = $ids ? " AND $alias.item_type = 'product' AND $alias.item_id IN (" . implode(',',$ids) .')' : '';
        return "INNER JOIN ?_invoice $i_alias ON u.user_id = $i_alias.user_id AND $i_alias.status=0 "
            . "INNER JOIN ?_invoice_item $alias ON $i_alias.invoice_id=$alias.invoice_id {$productsCond} ";
    }

    public function getId()
    {
        return 'pending-inv';
    }

    public function isEmpty() {
        return $this->empty;
    }

    public function setFromRequest(array $input)
    {
        $id = $this->getId();
        $this->product_ids = null;
        $this->empty = true;
        if (!empty($input[$id]['product_ids'])) {
            $this->product_ids = $this->extractProductIds($input[$id]['product_ids']);
            $this->empty = false;
            return true;
        }
    }

    public function renderElement(HTML_QuickForm2_Container $form)
    {
       $form->options[___('Misc')][$this->getId()] = ___('Has pending invoice with products');
       $group = $form->addGroup($this->getId())
           ->setLabel(___('Has pending invoice with products'))
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
       $group->addMagicSelect('product_ids', ['class' => 'am-combobox-fixed'])
           ->loadOptions($this->getProductOptions());
    }

    public function getDescription()
    {
        $ids = $this->product_ids ? 'products # ' . implode(',', $this->product_ids) : 'any product';
        return Am_Html::escape("has pending invoices with $ids");
    }
}

class Am_Query_User_Condition_NeverSubscribed
    extends Am_Query_Condition_Subscription
    implements Am_Query_Renderable_Condition {

    protected $product_ids, $empty;

    function getJoin(Am_Query $q)
    {
        $alias = 'nonsa';
        $ids = array_map('intval', $this->product_ids);
        $productsCond = $ids ? " AND $alias.product_id IN (" . implode(',',$ids) .')' : '';
        return "LEFT JOIN ?_access $alias ON u.user_id = $alias.user_id {$productsCond} ";
    }

    function _getWhere(Am_Query $db)
    {
        $alias = 'nonsa';
        return "$alias.product_id IS NULL";
    }

    public function getId()
    {
        return 'non-sub';
    }

    public function isEmpty()
    {
        return $this->empty;
    }

    public function setFromRequest(array $input)
    {
        $id = $this->getId();
        $this->product_ids = null;
        $this->empty = true;
        if (!empty($input[$id]['product_ids'])) {
            $this->product_ids = $this->extractProductIds($input[$id]['product_ids']);
            $this->empty = false;
            return true;
        }
    }

    public function renderElement(HTML_QuickForm2_Container $form)
    {
       $form->options[___('User Subscriptions Status')][$this->getId()] = ___('Never subscribed to');
       $group = $form->addGroup($this->getId())
           ->setLabel(___('Never subscribed to'))
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
       $group->addMagicSelect('product_ids', ['class' => 'am-combobox-fixed'])
           ->loadOptions($this->getProductOptions());
    }

    public function getDescription()
    {
        $ids = $this->product_ids ? 'products #' . implode(',', $this->product_ids) : 'any product';
        return Am_Html::escape("never subscribed to $ids");
    }
}

class Am_Query_User_Condition_HaveSubscriptionTo
    extends Am_Query_Condition_Subscription
    implements Am_Query_Renderable_Condition
{
    protected $product_ids;
    protected $currentStatus = null;
    protected $alias = null;
    protected $title = null;
    protected $id;
    protected $empty = true;

    function __construct(array $product_ids=null, $currentStatus=null, $id=null, $title=null)
    {
        $this->product_ids = $product_ids ?: [];
        if ($currentStatus !== null)
            $this->currentStatus = (int)$currentStatus;
        $this->id = $id;
        $this->title = $title;
    }

    function setAlias($alias = null)
    {
        $this->alias = $alias===null ? 'p'.substr(uniqid(), -4, 4) : $alias;
    }

    function getAlias()
    {
        if (!$this->alias)
            $this->setAlias();
        return $this->alias;
    }

    function _getJoin(Am_Query $q)
    {
        return "LEFT JOIN ?_user_status {$this->getAlias()} ON u.user_id={$this->getAlias()}.user_id";
    }

    function _getWhere(Am_Query $db)
    {
        $ids = array_filter(array_map('intval', $this->product_ids));
        $productsCond = $ids ? "{$this->getAlias()}.product_id IN (" . implode(',',$ids) . ")" : '1=1';
        $statusCond = ($this->currentStatus !== null) ? "{$this->getAlias()}.status=" . (int)$this->currentStatus : '1=1';
        return "{$productsCond} AND {$statusCond}";
    }

    public function setFromRequest(array $input)
    {
        $id = $this->getId();
        $this->product_ids = null;
        $this->empty = true;
        if (!empty($input[$id]['product_ids'])) {
            $this->product_ids = $this->extractProductIds($input[$id]['product_ids']);
            $this->empty = false;
            return true;
        }
    }

    public function getId()
    {
        return '-payments-' . $this->id;
    }

    public function renderElement(HTML_QuickForm2_Container $form)
    {
       $form->options[___('User Subscriptions Status')][$this->getId()] = $this->title;
       $group = $form->addGroup($this->getId())
           ->setLabel($this->title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
       $group->addMagicSelect('product_ids', ['class' => 'am-combobox-fixed'])
           ->loadOptions($this->getProductOptions());
    }

    public function isEmpty()
    {
        return $this->empty;
    }

    public function getDescription()
    {
        if (is_null($this->currentStatus)) {
            $completedCond = "any";
        } else {
            switch ($this->currentStatus) {
                case User::STATUS_ACTIVE :
                    $completedCond = 'active';
                    break;
                case User::STATUS_EXPIRED :
                    $completedCond = 'expired';
                    break;
                default:
                    $completedCond = 'pending';
            }
        }
        $ids = $this->product_ids ? 'products # ' . implode(', ', $this->product_ids) : 'any product';
        return Am_Html::escape("have {$completedCond} subscription to $ids");
    }
}

class Am_Query_User_Condition_HaveNoSubscriptionTo
    extends Am_Query_Condition_Subscription
    implements Am_Query_Renderable_Condition
{
    protected $product_ids;
    protected $currentStatus = null;
    protected $alias = null;
    protected $title = null;
    protected $id;
    protected $empty = true;

    function __construct(array $product_ids=null, $id=null, $title=null)
    {
        $this->product_ids = $product_ids ? $product_ids : [];
        $this->id = $id;
        $this->title = $title;
    }

    public function getId()
    {
        return '-no-payments-' . $this->id;
    }

    function _getWhere(Am_Query $db)
    {
        $a = $db->getAlias();
        $ids = implode(',', array_filter(array_map('intval', $this->product_ids)));
        if (!$ids) return null;
        return "NOT EXISTS
            (SELECT * FROM ?_user_status ncmss
            WHERE ncmss.user_id=$a.user_id AND ncmss.product_id IN ($ids) AND ncmss.status = 1)";
    }

    public function getDescription()
    {
        $ids = $this->product_ids ? 'products # ' . implode(',', $this->product_ids) : 'any product';
        return htmlentities(___('have no active subscriptions to %s', $ids));
    }

    public function renderElement(HTML_QuickForm2_Container $form)
    {
       $form->options[___('User Subscriptions Status')][$this->getId()] = $this->title;
       $group = $form->addGroup($this->getId())
           ->setLabel($this->title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
       $group->addMagicSelect('product_ids', ['class' => 'am-combobox-fixed'])
           ->loadOptions($this->getProductOptions());
    }

    public function setFromRequest(array $input)
    {
        $id = $this->getId();
        $this->product_ids = null;
        $this->empty = true;
        if (!empty($input[$id]['product_ids'])) {
            $this->product_ids = $this->extractProductIds($input[$id]['product_ids']);
            $this->empty = false;
            return true;
        }
    }

    public function isEmpty()
    {
        return $this->empty;
    }
}

class Am_Query_User_Condition_HaveSubscriptionDue
    extends Am_Query_Condition_Subscription
    implements Am_Query_Renderable_Condition
{
    protected $product_ids = [];
    protected $date_start;
    protected $date_end;
    protected $empty = true;

    public function setFromRequest(array $input)
    {
        $id = $this->getId();
        $this->product_ids = $this->date_start = $this->date_end = null;
        $this->empty = true;

        $this->product_ids = isset($input[$id]['product_ids']) ? $this->extractProductIds($input[$id]['product_ids']) : [];
        $this->date_start = @$input[$id]['date_start'];
        $this->date_end = @$input[$id]['date_end'];

        if ($this->date_start && $this->date_end)
            $this->empty = false;

        return !$this->empty;
    }

    public function isEmpty()
    {
        return $this->empty;
    }

    public function renderElement(HTML_QuickForm2_Container $form)
    {
       $title = ___('Has subscription that expire between dates');

       $form->options[___('User Subscriptions Status')][$this->getId()] = $title;
       $group = $form->addGroup($this->getId())
           ->setLabel($title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
       $group->addMagicSelect('product_ids', ['class' => 'am-combobox-fixed-compact'])
           ->loadOptions($this->getProductOptions());
       $group->addDate('date_start');
       $group->addDate('date_end');
    }

    public function getId()
    {
        return 'subscription-due';
    }

    public function getDescription()
    {
        $ids = $this->product_ids ? 'products # ' . implode(', ', $this->product_ids) : 'any product';
        return Am_Html::escape(___("have subscription for %s
            that expire between %s and %s", $ids,
            amDate($this->date_start), amDate($this->date_end)));
    }

    function _getWhere(Am_Query $db)
    {
        $a = $db->getAlias();
        $ids = implode(',', array_filter(array_map('intval', $this->product_ids)));
        $product_cond = $ids ? "hsdac.id IN ($ids)" : '1';
        $date_start = $db->escape($this->date_start);
        $date_end = $db->escape($this->date_end);
        if (!$date_start || !$date_end) return null;
        return "EXISTS (SELECT * FROM ?_access_cache hsdac
            WHERE hsdac.user_id=$a.user_id AND $product_cond AND hsdac.fn = 'product_id' AND hsdac.expire_date BETWEEN $date_start AND $date_end)";
    }
}

class Am_Query_User_Condition_HaveSubscriptionDate
    extends Am_Query_Condition_Subscription
    implements Am_Query_Renderable_Condition
{
    public function setFromRequest(array $input)
    {
        $id = $this->getId();
        $this->product_ids = $this->date_start = null;
        $this->empty = true;

        $this->product_ids = isset($input[$id]['product_ids']) ? $this->extractProductIds($input[$id]['product_ids']) : [];
        $this->date_start = @$input[$id]['date_start'];

        if ($this->date_start)
            $this->empty = false;

        return !$this->empty;
    }

    public function isEmpty()
    {
        return $this->empty;
    }

    public function renderElement(HTML_QuickForm2_Container $form)
    {
       $title = ___('Has subscription on date');

       $form->options[___('User Subscriptions Status')][$this->getId()] = $title;
       $group = $form->addGroup($this->getId())
           ->setLabel($title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
       $group->addMagicSelect('product_ids', ['class' => 'am-combobox-fixed-compact'])
           ->loadOptions($this->getProductOptions());
       $group->addDate('date_start');
    }

    public function getId()
    {
        return 'subscription-date';
    }

    public function getDescription()
    {
        $ids = $this->product_ids ? 'products # ' . implode(', ', $this->product_ids) : ___('any product');
        return Am_Html::escape(___("have subscription for %s for date %s ", $ids, amDate($this->date_start)));
    }

    function _getWhere(Am_Query $db)
    {
        $a = $db->getAlias();
        $ids = implode(',', array_filter(array_map('intval', $this->product_ids)));
        $product_cond = $ids ? "hsdt.product_id IN ($ids)" : '1';
        $date_start = $db->escape($this->date_start);
        if (!$date_start) return null;
        return "EXISTS (SELECT * FROM ?_access hsdt
            WHERE hsdt.user_id=$a.user_id AND $product_cond AND $date_start BETWEEN hsdt.begin_date AND hsdt.expire_date )";
    }
}

class Am_Query_User_Condition_HaveSubscriptionActiveOrFuture
    extends Am_Query_Condition_Subscription
    implements Am_Query_Renderable_Condition
{
    public function setFromRequest(array $input)
    {
        $id = $this->getId();
        $this->product_ids = null;
        $this->empty = true;

        $this->product_ids = isset($input[$id]['product_ids']) ? $this->extractProductIds($input[$id]['product_ids']) : [];

        if ($this->product_ids)
            $this->empty = false;

        return !$this->empty;
    }

    public function isEmpty()
    {
        return $this->empty;
    }

    public function renderElement(HTML_QuickForm2_Container $form)
    {
       $title = ___('Having active or future subscription to');

       $form->options[___('User Subscriptions Status')][$this->getId()] = $title;
       $group = $form->addGroup($this->getId())
           ->setLabel($title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
       $group->addMagicSelect('product_ids', ['class' => 'am-combobox-fixed-compact'])
           ->loadOptions($this->getProductOptions());
    }

    public function getId()
    {
        return 'sub-ac-ft';
    }

    public function getDescription()
    {
        $ids = $this->product_ids ? 'products # ' . implode(', ', $this->product_ids) : ___('any product');
        return Am_Html::escape(___("having active or future subscription for %s", $ids));
    }

    function _getWhere(Am_Query $db)
    {
        $a = $db->getAlias();
        $ids = implode(',', array_filter(array_map('intval', array_merge($this->product_ids, [-1]))));
        $now = $db->escape(sqlDate('now'));
        return <<<CUT
            EXISTS (SELECT * FROM ?_access
            WHERE user_id=$a.user_id
                AND product_id IN ($ids)
                AND expire_date > $now
            )
CUT;
    }
}

class Am_Query_User_Condition_HaveSubscriptionBetween
    extends Am_Query_Condition_Subscription
    implements Am_Query_Renderable_Condition
{
    public function setFromRequest(array $input)
    {
        $id = $this->getId();
        $this->product_ids = $this->date_start = $this->date_end = null;
        $this->empty = true;

        $this->product_ids = isset($input[$id]['product_ids']) ? $this->extractProductIds($input[$id]['product_ids']) : [];
        $this->date_start = @$input[$id]['date_start'];
        $this->date_end = @$input[$id]['date_end'];
        if ($this->date_start && $this->date_end)
            $this->empty = false;

        return !$this->empty;
    }

    public function isEmpty()
    {
        return $this->empty;
    }

    public function renderElement(HTML_QuickForm2_Container $form)
    {
       $title = ___('Has subscription between dates');

       $form->options[___('User Subscriptions Status')][$this->getId()] = $title;
       $group = $form->addGroup($this->getId())
           ->setLabel($title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
       $group->addMagicSelect('product_ids', ['class' => 'am-combobox-fixed-compact'])
           ->loadOptions($this->getProductOptions());
       $group->addDate('date_start');
       $group->addDate('date_end');
    }

    public function getId()
    {
        return 'sub-between';
    }

    public function getDescription()
    {
        $ids = $this->product_ids ? 'products # ' . implode(', ', $this->product_ids) : ___('any product');
        return Am_Html::escape(___("have subscription for %s between %s and %s", $ids,
            amDate($this->date_start), amDate($this->date_end)));
    }

    function _getWhere(Am_Query $db)
    {
        $a = $db->getAlias();
        $mya = 'hsbd';
        $ids = implode(',', array_filter(array_map('intval', $this->product_ids)));
        $product_cond = $ids ? "$mya.product_id IN ($ids)" : '1';
        $date_start = $db->escape($this->date_start);
        $date_end = $db->escape($this->date_end);
        if (!($date_start && $date_end)) return null;
        return "EXISTS (SELECT * FROM ?_access $mya
            WHERE $mya.user_id=$a.user_id AND $product_cond "
            . "AND (($mya.begin_date BETWEEN $date_start AND $date_end) OR "
            . "(($mya.begin_date <= $date_start) AND ($mya.expire_date >= $date_end)) OR "
            . "($mya.expire_date BETWEEN $date_start AND $date_end)))";
    }
}

class Am_Query_User_Condition_PurchasedProductWithOption
    extends Am_Query_Condition
    implements Am_Query_Renderable_Condition
{
    public function setFromRequest(array $input)
    {
        $id = $this->getId();
        $this->pid = $this->opt = $this->txt = $this->dat  = $this->sel = null;
        $this->empty = true;
        if (!empty($input[$id])) {
            $this->pid = $input[$id]['pid'];
            $this->opt = isset($input[$id]['opt']) ? $input[$id]['opt'] :  null;
            $this->txt = isset($input[$id]['txt']) ? $input[$id]['txt'] :  null;
            $this->dat = isset($input[$id]['dat']) ? $input[$id]['dat'] :  null;
            $this->sel = isset($input[$id]['sel']) ? $input[$id]['sel'] :  null;

            if ($this->pid && $this->opt) {
                $this->empty = false;
            }
        }

        return !$this->empty;
    }

    public function isEmpty()
    {
        return $this->empty;
    }

    public function renderElement(HTML_QuickForm2_Container $form)
    {
        $title = ___('Purchased product with option');

        $form->options[___('User Subscriptions Status')][$this->getId()] = $title;
        $group = $form->addGroup($this->getId())
            ->setLabel($title)
            ->setAttribute('id', $this->getId())
            ->setAttribute('class', 'searchField empty')
            ->setSeparator(' ');
        $group->addSelect('pid', ['class' => 'am-combobox-fixed-compact', 'id'=>"{$this->getId()}-pid"])
            ->loadOptions(['' => '-- Please Select --'] + $this->getProductOptions());
        $group->addSelect('opt', ['id'=>"{$this->getId()}-pid-opt", 'rel' => "{$this->getId()}-pid"], ['intrinsic_validation' => false]);
        $group->addText('txt', ['id'=>"{$this->getId()}-pid-opt-txt", 'rel' => "{$this->getId()}-pid-opt"]);
        $group->addDate('dat', ['id'=>"{$this->getId()}-pid-opt-dat", 'rel' => "{$this->getId()}-pid-opt"]);
        $group->addSelect('sel', ['id'=>"{$this->getId()}-pid-opt-sel", 'rel' => "{$this->getId()}-pid-opt"], ['intrinsic_validation' => false]);

        //used only to restore state of input elements after form submit
        $group->addText('res', ['id'=>"{$this->getId()}-pid-opt-res", 'style'=>'display:none']);

        $ppo = json_encode($this->getProductProductOptions());

        $group->addScript()
            ->setScript(<<<CUT
jQuery(function(){
    const ppo = {$ppo};
    function sync()
    {
        const res = {
            pid: jQuery('#{$this->getId()}-pid').val(),
            opt: jQuery('#{$this->getId()}-pid-opt').val(),
            txt: jQuery('#{$this->getId()}-pid-opt-txt').val(),
            dat: jQuery('#{$this->getId()}-pid-opt-dat').val(),
            sel: jQuery('#{$this->getId()}-pid-opt-sel').val(),
        };
        jQuery('#{$this->getId()}-pid-opt-res').val(JSON.stringify(res));
    }
    jQuery('#{$this->getId()}-pid').change(function(){
        jQuery(this).closest('td').find("[rel={$this->getId()}-pid]").hide();
        jQuery(this).closest('td').find("[rel={$this->getId()}-pid-opt]").hide();
        if (jQuery(this).val()) {
            jQuery('#{$this->getId()}-pid-opt').empty();
            jQuery('#{$this->getId()}-pid-opt').append('<option>-- Option --</option>');
            ppo[jQuery(this).val()].forEach(function(opt){
                jQuery('#{$this->getId()}-pid-opt').append(jQuery('<option>').attr('value', opt.id).data('def', opt).html(opt.title));
            });
            jQuery('#{$this->getId()}-pid-opt').show();
        }
    }).change();
    jQuery('#{$this->getId()}-pid-opt').change(function(){
        jQuery(this).closest('td').find("[rel={$this->getId()}-pid-opt]").hide();
        if (jQuery(this).val()) {
            const def = jQuery('#{$this->getId()}-pid-opt option:selected').data('def');
            switch (def.type) {
                case 'text':
                case 'textarea':
                    jQuery(this).closest('td').find('#{$this->getId()}-pid-opt-txt').show();
                    break;
                case 'select':
                case 'multi_select':
                case 'checkbox':
                case 'radio':
                    const inpt = jQuery(this).closest('td').find('#{$this->getId()}-pid-opt-sel'); 
                    inpt.empty();
                    inpt.append('<option>');
                    def.options.forEach(function(el) {
                        inpt.append(jQuery('<option>').attr('value', el[0]).html(el[1]));
                    });
                    inpt.show();
                    break;
                case 'date':
                    jQuery(this).closest('td').find('#{$this->getId()}-pid-opt-dat').show();
                    break;
            }
        }
    });
    jQuery('#{$this->getId()}-pid, #{$this->getId()}-pid-opt, #{$this->getId()}-pid-opt-txt, #{$this->getId()}-pid-opt-dat, #{$this->getId()}-pid-opt-sel')
        .change(function(){sync();});
    if (jQuery('#{$this->getId()}-pid-opt-res').val()) {
         const res = JSON.parse(jQuery('#{$this->getId()}-pid-opt-res').val());
         jQuery('#{$this->getId()}-pid').val(res.pid).change();
         jQuery('#{$this->getId()}-pid-opt').val(res.opt).change();
         jQuery('#{$this->getId()}-pid-opt-txt').val(res.txt).change();
         jQuery('#{$this->getId()}-pid-opt-dat').val(res.dat).change();
         jQuery('#{$this->getId()}-pid-opt-sel').val(res.sel).change();
    }
});
CUT
        );
    }

    public function getId()
    {
        return 'ppwo';
    }

    public function getDescription()
    {
        $product = $this->getDi()->productTable->load($this->pid);
        $option = $this->getDi()->productOptionTable->load($this->opt);

        return ___("Purchased %s with %s = %s", $product->title, $option->title, $this->_val($option));
    }

    function _val($opt)
    {
        $_ = json_decode($opt->options, true);

        switch ($opt->type) {
            case 'text':
            case 'textarea':
                return $this->txt;
            case 'select':
            case 'multi_select':
            case 'checkbox':
            case 'radio':
                $o = array_reduce($_['options'], function ($c, $i) {$c[$i[0]] = $i[1]; return $c;}, []);
                return $o[$this->sel];
            case 'date':
                return amDate($this->dat);
        }
    }

    function _getWhere(Am_Query $db)
    {
        $a = $db->getAlias();
        $option = $this->getDi()->productOptionTable->load($this->opt);
        $name = $db->escape($option->title);
        $item_id = (int)$this->pid;

        switch ($option->type) {
            case 'text':
            case 'textarea':
                $val = $this->txt;
                break;
            case 'select':
            case 'multi_select':
            case 'checkbox':
            case 'radio':
                $val = $this->sel;
                break;
            case 'date':
                $val = $this->dat;
                break;
        }

        switch ($option->type) {
            case 'multi_select':
            case 'checkbox':
                $value_cond = sprintf(
                    "(`value` = %s OR `value` LIKE %s OR `value` LIKE %s OR `value` LIKE %s)",
                    $db->escape($val),
                    $db->escape("$val,%"),
                    $db->escape("%,$val,%"),
                    $db->escape("%,$val")
                );
                break;
            default:
                $value_cond = sprintf("`value` = %s", $db->escape($val));
        }

        return <<<CUT
EXISTS (SELECT * FROM ?_invoice i
    LEFT JOIN ?_invoice_item_option iio USING (invoice_id)
    WHERE tm_started IS NOT NULL
        AND i.user_id = {$a}.user_id
        AND iio.item_id={$item_id}
        AND iio.name={$name}
        AND {$value_cond})
CUT;
    }

    function getDi()
    {
        return Am_Di::getInstance();
    }

    function getProductOptions()
    {
        return $this->getDi()->db->selectCol(<<<CUT
            SELECT
                CONCAT('(', product_id, ')', ' ', title), product_id AS ARRAY_KEY
                FROM ?_product p
                WHERE EXISTS (SELECT * FROM ?_product_option po WHERE p.product_id=po.product_id)
CUT
        );
    }

    function getProductProductOptions()
    {
        $res = [];
        foreach ($this->getDi()->productOptionTable->findBy() as $op) {
            if (!isset($res[$op->product_id])) {
                $res[$op->product_id] = [];
            }

            $_ = json_decode($op->options, true);

            $res[$op->product_id][] = [
                'id' => $op->pk(),
                'title' => $op->title,
                'type' => $op->type,
                'options' => $_['options'],
            ];
        }
        return $res;
    }
}

class Am_Query_User_Condition_HaveNumberProduct
    extends Am_Query_Condition_Subscription
    implements Am_Query_Renderable_Condition
{
    protected $product_ids, $val, $op;
    protected $empty = true;

    public function getId()
    {
        return 'hnp';
    }

    function _getWhere(Am_Query $db)
    {
        $a = $db->getAlias();
        $ids = implode(',', array_filter(array_map('intval', $this->product_ids)));
        if (!$ids) return null;
        $now = Am_Di::getInstance()->sqlDate;
        return <<<CUT
                (
                    SELECT COUNT(*)
                        FROM ?_access
                        WHERE user_id=$a.user_id
                            AND product_id IN ($ids)
                            AND begin_date <= '{$now}'
                            AND expire_date >= '{$now}'
                ) {$this->op} {$this->val}
CUT;
    }

    public function getDescription()
    {
        $ids = 'products # ' . implode(', ', $this->product_ids);
        return htmlentities(___('have %s %d subscriptions to %s', $this->op, $this->val, $ids));
    }

    public function renderElement(HTML_QuickForm2_Container $form)
    {
        $title = ___('Have Number of Active Subscriptions to Product(s)');

        $form->options[___('User Subscriptions Status')][$this->getId()] = $title;
        $group = $form->addGroup($this->getId())
            ->setLabel($title)
            ->setAttribute('id', $this->getId())
            ->setAttribute('class', 'searchField empty');
        $group->addMagicSelect('product_ids', ['class' => 'am-combobox-fixed'])
            ->loadOptions($this->getProductOptions());
        $group->addSelect('op')
            ->loadOptions([
                '>' => '>',
                '=' => '=',
                '<' => '<'
            ]);
        $group->addInteger('val');
    }

    public function setFromRequest(array $input)
    {
        $id = $this->getId();
        $this->product_ids = null;
        $this->empty = true;
        if (!empty($input[$id]['product_ids'])) {
            $this->product_ids = $this->extractProductIds($input[$id]['product_ids']);
            $this->val = (int)$input[$id]['val'];
            $this->op = $input[$id]['op'];
            $this->empty = false;
            return true;
        }
    }

    public function isEmpty()
    {
        return $this->empty;
    }
}

class Am_Query_User_Condition_HavePaymentBetween extends Am_Query_User_Condition_HaveSubscriptionDue
implements Am_Query_Renderable_Condition {

    public function renderElement(HTML_QuickForm2_Container $form)
    {
       $title = ___('Has payment made between dates');

       $form->options[___('User Subscriptions Status')][$this->getId()] = $title;
       $group = $form->addGroup($this->getId())
           ->setLabel($title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
       $group->addMagicSelect('product_ids', ['class' => 'am-combobox-fixed-compact'])
           ->loadOptions($this->getProductOptions());
       $group->addDate('date_start');
       $group->addDate('date_end');
    }

    public function getId()
    {
        return 'payment-between';
    }

    public function getDescription()
    {
        $ids = $this->product_ids ? 'products # ' . implode(', ', $this->product_ids) : ___('any product');
        return Am_Html::escape(___("have payment for %s that made between %s and %s", $ids,
            amDate($this->date_start), amDate($this->date_end)));
    }

    function _getWhere(Am_Query $db)
    {
        $a = $db->getAlias();
        $ids = implode(',', array_filter(array_map('intval', $this->product_ids)));
        $product_cond = $ids ? "hspbetit.item_id IN ($ids)" : '1';
        $date_start = $db->escape($this->date_start);
        $date_end = $db->escape($this->date_end);
        if (!$date_start || !$date_end) return null;
        return "EXISTS (SELECT hspbet.* FROM ?_invoice_payment hspbet , ?_invoice hspbeti, ?_invoice_item hspbetit
            WHERE hspbet.user_id=$a.user_id
                AND hspbet.invoice_id = hspbeti.invoice_id
                AND hspbetit.invoice_id=hspbet.invoice_id
                AND $product_cond AND  DATE(hspbet.dattm) BETWEEN $date_start AND $date_end)";
    }
}

class Am_Query_User_Condition_HaveCancellationDue extends Am_Query_User_Condition_HaveSubscriptionDue
implements Am_Query_Renderable_Condition {

    public function renderElement(HTML_QuickForm2_Container $form)
    {
       $title = ___('Has invoice canceled between dates');

       $form->options[___('User Subscriptions Status')][$this->getId()] = $title;
       $group = $form->addGroup($this->getId())
           ->setLabel($title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
       $group->addMagicSelect('product_ids', ['class' => 'am-combobox-fixed-compact'])
           ->loadOptions($this->getProductOptions());
       $group->addDate('date_start');
       $group->addDate('date_end');
    }

    public function getId()
    {
        return 'canceled-between';
    }

    public function getDescription()
    {
        $ids = $this->product_ids ? 'products # ' . implode(',', $this->product_ids) : ___('any product');
        return Am_Html::escape(___("have invoice for %s that canceled between %s and %s", $ids,
            amDate($this->date_start), amDate($this->date_end)));
    }

    function _getWhere(Am_Query $db)
    {
        $a = $db->getAlias();
        $ids = implode(',', array_filter(array_map('intval', $this->product_ids)));
        $product_cond = $ids ? "hspbetit.item_id IN ($ids)" : '1';
        $date_start = $db->escape($this->date_start);
        $date_end = $db->escape($this->date_end);
        if (!$date_start || !$date_end) return null;
        return "EXISTS (SELECT hspbet.* FROM ?_invoice_payment hspbet , ?_invoice hspbeti, ?_invoice_item hspbetit
            WHERE hspbet.user_id=$a.user_id
                AND hspbet.invoice_id = hspbeti.invoice_id
                AND hspbetit.invoice_id=hspbet.invoice_id
                AND $product_cond AND  DATE(hspbeti.tm_cancelled) BETWEEN $date_start AND $date_end)";
    }
}

class Am_Query_User_Condition_HaveStartDue extends Am_Query_User_Condition_HaveSubscriptionDue
implements Am_Query_Renderable_Condition {

    public function renderElement(HTML_QuickForm2_Container $form)
    {
       $title = ___('Has invoice started between dates');

       $form->options[___('User Subscriptions Status')][$this->getId()] = $title;
       $group = $form->addGroup($this->getId())
           ->setLabel($title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
       $group->addMagicSelect('product_ids', ['class' => 'am-combobox-fixed-compact'])
           ->loadOptions($this->getProductOptions());
       $group->addDate('date_start');
       $group->addDate('date_end');
    }

    public function getId()
    {
        return 'started-between';
    }

    public function getDescription()
    {
        $ids = $this->product_ids ? 'products # ' . implode(',', $this->product_ids) : ___('any product');
        return Am_Html::escape(___("have invoice for %s that started between %s and %s", $ids,
            amDate($this->date_start), amDate($this->date_end)));
    }

    function _getWhere(Am_Query $db)
    {
        $a = $db->getAlias();
        $ids = implode(',', array_filter(array_map('intval', $this->product_ids)));
        $product_cond = $ids ? "ii.item_id IN ($ids)" : '1';
        $date_start = $db->escape($this->date_start);
        $date_end = $db->escape($this->date_end);
        if (!$date_start || !$date_end) return null;
        return "EXISTS (SELECT i.* FROM ?_invoice i, ?_invoice_item ii
            WHERE i.user_id=$a.user_id
                AND i.invoice_id = ii.invoice_id
                AND $product_cond AND DATE(i.tm_started) BETWEEN $date_start AND $date_end)";
    }
}

class Am_Query_User_Condition_Filter
extends Am_Query_Condition
implements Am_Query_Renderable_Condition
{
    protected $filter;

    public function __construct()
    {
        $this->title = ___('Quick Filter');
    }

    public function getId()
    {
        return 'filter';
    }

    public function isEmpty()
    {
        return $this->filter === null;
    }

    public function renderElement(HTML_QuickForm2_Container $form)
    {
       $form->options[___('Quick Filter')][$this->getId()] = $this->title;
       $group = $form->addGroup($this->getId())
           ->setLabel($this->title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
        $group->addText('val');
    }

    public function setFromRequest(array $input) {
        if (is_string($input)) {
            $this->filter = $input;
            return true;
        } elseif (@$input['filter']['val']!='') {
            $this->filter = $input['filter']['val'];
            return true;
        }
    }

    public function _getWhere(Am_Query $q)
    {
        $a = $q->getAlias();
        $f = '%'.$this->filter.'%';
        if(filter_var($this->filter, FILTER_VALIDATE_IP))
            $ip = $this->filter;

        return $q->escapeWithPlaceholders("($a.login LIKE ?) OR ($a.email LIKE ?) OR ($a.name_f LIKE ?) OR ($a.name_l LIKE ?)
            OR CONCAT($a.name_f, ' ', $a.name_l) LIKE ?
            OR ($a.remote_addr LIKE ?)
            OR ($a.user_id IN (SELECT user_id FROM ?_invoice WHERE public_id=? OR CAST(invoice_id as char(11))=?))
            OR ($a.user_id IN (SELECT user_id FROM ?_invoice_payment WHERE receipt_id=?))
            {OR ($a.user_id IN (SELECT DISTINCT user_id from ?_access_log WHERE remote_addr=?))}
            OR $a.user_id=?
            ",
                $f, $f, $f, $f, $f, $f, $this->filter, $this->filter, $this->filter, isset($ip) ? $ip : DBSIMPLE_SKIP, $this->filter);
    }

    public function getDescription()
    {
        $f = Am_Html::escape($this->filter);
        return ___('username, e-mail or name contains string [%s]', $f);
    }
}

class Am_Query_User_Condition_UserId
extends Am_Query_Condition
implements Am_Query_Renderable_Condition
{
    protected $title = "UserId#";
    protected $ids = null;

    public function getId()
    {
        return 'member_id_filter';
    }

    public function isEmpty()
    {
        return !empty($this->ids);
    }

    public function renderElement(HTML_QuickForm2_Container $form)
    {
       //$form->options['Quick Filter'][$this->getId()] = $this->title;
       $group = $form->addGroup($this->getId())
           ->setLabel($this->title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
        $group->addText('val');
    }

    public function setIds($ids)
    {
        if (!is_array($ids)) $ids = explode(',', $ids);
        $this->ids = array_filter(array_map('intval', $ids));
    }

    public function setFromRequest(array $input)
    {
        if (@$input[$this->getId()]['val']!='') {
            $this->setIds($input[$this->getId()]['val']);
            return true;
        }
    }

    public function _getWhere(Am_Query $q)
    {
        if (!$this->ids) return null;
        $a = $q->getAlias();
        $ids = implode(',', $this->ids);
        return "$a.user_id IN ($ids)";
    }

    public function getDescription()
    {
        $ids = implode(',', $this->ids);
        return "user_id IN ($ids)";
    }
}

class Am_Query_User_Condition_ImportId
extends Am_Query_Condition
implements Am_Query_Renderable_Condition
{
    protected $title;
    protected $id = '';

    public function __construct()
    {
        $this->title = ___('Added During Import');
    }

    public function getId()
    {
        return 'import';
    }

    public function isEmpty()
    {
        return !$this->id;
    }

    public function renderElement(HTML_QuickForm2_Container $form)
    {
       $form->options[___('Misc')][$this->getId()] = $this->title;
       $group = $form->addGroup($this->getId())
           ->setLabel($this->title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
        $group->addText('id');
    }

    public function setFromRequest(array $input)
    {
        $this->id = @$input[$this->getId()]['id'];
        if ($this->id)
            return true;
    }

    public function getJoin(Am_Query $q)
    {
        $a = $q->getAlias();
        if (!$this->id) return null;
        return "LEFT JOIN ?_data itd ON $a.user_id = itd.id AND itd.`table` = 'user' AND itd.`key` = 'import-id'";
    }

    public function _getWhere(Am_Query $q)
    {
        if (!$this->id) return null;
        $id = $q->escape($this->id);
        return "itd.value = $id";
    }

    public function getDescription()
    {
        return "import-id = $this->id";
    }
}

class Am_Query_User_Condition_Usergroup
extends Am_Query_Condition
implements Am_Query_Renderable_Condition
{
    protected $title;
    protected $ids = [];
    protected $empty = true;

    public function __construct($gids = null)
    {
        $this->ids = $gids ?: [];
        $this->title = ___('Assigned to usergroup');
    }

    public function getId()
    {
        return 'user-group';
    }

    public function isEmpty()
    {
        return $this->empty;
    }

    public function renderElement(HTML_QuickForm2_Container $form)
    {
       $form->options[___('User Groups')][$this->getId()] = $this->title;
       $group = $form->addGroup($this->getId())
           ->setLabel($this->title)
           ->setAttribute('id', $this->getId())
           ->setAttribute('class', 'searchField empty');
        $group->addMagicSelect('ids')->loadOptions($this->getOptions());
    }

    protected function getOptions()
    {
        return Am_Di::getInstance()->userGroupTable->getSelectOptions();
    }

    public function setFromRequest(array $input)
    {
        if (isset($input[$this->getId()]['ids'])) {
            if ($ids = array_filter(array_map('intval', $input[$this->getId()]['ids']))) {
                $this->ids = $ids;
                $this->empty = false;
                return true;
            }
        }
    }

    public function _getJoin(Am_Query $q)
    {
        return "LEFT JOIN ?_user_user_group uug ON {$q->getAlias()}.user_id = uug.user_id";
    }

    public function _getWhere(Am_Query $q)
    {
        $ids = array_filter(array_map('intval', $this->ids));
        $ids = $ids ? implode(',', $ids) : '-1';
        return "uug.user_group_id IN ($ids)";
    }

    public function getDescription()
    {
        $g = $this->getOptions();
        $g = array_intersect_key($g, array_combine($this->ids, $this->ids));
        $g = array_map(['Am_Html', 'escape'], $g);
        $g = implode(',', $g);
        return ___('assigned to usergroups [%s]', $g);
    }
}

class Am_Query_User_Condition_NoUsergroup
    extends Am_Query_User_Condition_Usergroup
{
    public function __construct()
    {
        $this->title = ___('Not assigned to usergroup');
    }

    public function getId()
    {
        return 'no-user-group';
    }

    public function getJoin(Am_Query $q)
    {
        return;
    }

    public function _getWhere(Am_Query $q)
    {
        $a = $q->getAlias();
        $ids = array_filter(array_map('intval', $this->ids));
        if (!$ids) return;
        $ids = implode(',', $ids);
        return "NOT EXISTS (SELECT * FROM ?_user_user_group uug WHERE $a.user_id = uug.user_id AND uug.user_group_id IN ($ids))";
    }
}

class Am_Query_User_Condition_DuplicateValue
    extends Am_Query_Condition
    implements Am_Query_Renderable_Condition
{
    protected $field = false;

    function _getWhere(Am_Query $q)
    {
        $fn = $q->escape($this->field, true);
        $uids = Am_Di::getInstance()->db->selectCol(<<<CUT
                SELECT GROUP_CONCAT(user_id SEPARATOR ',') AS uids
                    FROM ?_user
                    WHERE $fn<>''
                        AND $fn IS NOT NULL
                    GROUP BY $fn HAVING COUNT(*)>1
CUT
        );
        $_ = [];
        foreach ($uids as $list) {
            $_ = array_merge($_, explode(',', $list));
        }
        $_[] = -1; //case of emty list
        $alias = $q->getAlias();
        $uids = implode(',', $_);
        return "$alias.user_id IN ({$uids})";
    }

    public function getId()
    {
        return 'duplicate-value';
    }

    public function isEmpty()
    {
        return !$this->field;
    }

    public function setFromRequest(array $input)
    {
        if (!empty($input[$this->getId()]['fn'])) {
            $this->field = $input[$this->getId()]['fn'];
            return true;
        }
    }

    public function renderElement(HTML_QuickForm2_Container $form)
    {
        $form->options[___('Misc')][$this->getId()] = ___('Duplicate Value In Field');
        $group = $form->addGroup($this->getId())
            ->setLabel(___('Duplicate Value In Field'))
            ->setAttribute('id', $this->getId())
            ->setAttribute('class', 'searchField empty');
        $group->addSelect('fn', ['class' => 'am-combobox-fixed'])
            ->loadOptions($this->getFieldOptions());
    }

    public function getDescription()
    {
        return Am_Html::escape("duplicate value: {$this->field}");
    }

    function getFieldOptions()
    {
        $options = [];
        $di = Am_Di::getInstance();
        $baseFields = $di->userTable->getFields();
        foreach ($baseFields as $field => $def){
            if($field == 'pass') continue;
            $title = ucwords(str_replace('_', ' ',$field));
            $options[$field] = $title;
        }
        return $options;
    }
}

class Am_Query_User_Condition_UserConsent
    extends Am_Query_Condition
    implements Am_Query_Renderable_Condition
{
    protected $agreement;
    protected $revision_id;
    protected $status;
    protected $empty = true;

    function _getJoin(Am_Query $q)
    {
        return "LEFT JOIN ?_user_consent uconsent ON u.user_id=uconsent.user_id AND uconsent.revision_id={$this->revision_id}";
    }

    function _getWhere(Am_Query $db)
    {
        return $this->status ? "uconsent.dattm IS NOT NULL AND uconsent.cancel_dattm IS NULL" : "uconsent.dattm IS NULL OR uconsent.cancel_dattm IS NOT NULL";
    }

    public function setFromRequest(array $input)
    {
        $id = $this->getId();
        $this->agreement = $this->revision_id = null;
        $this->empty = true;
        if (!empty($input[$id]['agreement'])) {
            $this->agreement = $input[$id]['agreement'];
            $this->status = $input[$id]['status'];
            $this->revision_id = Am_Di::getInstance()->db->selectCell(
                "SELECT agreement_revision_id FROM ?_agreement WHERE type=? AND is_current=?",
                $this->agreement, 1
            );
            $this->empty = false;
            return true;
        }
    }

    public function getId()
    {
        return 'user-consent';
    }

    public function renderElement(HTML_QuickForm2_Container $form)
    {
        $form->options[___('Misc')][$this->getId()] = ___('User Consent');
        $group = $form->addGroup($this->getId())
            ->setLabel(___('User Consent'))
            ->setAttribute('id', $this->getId())
            ->setAttribute('class', 'searchField empty');
        $group->addSelect('status')
            ->loadOptions([
               1 => 'Have',
               0 => 'Have Not',
            ]);
        $group->addSelect('agreement', ['class' => 'am-combobox-fixed'])
            ->loadOptions($this->getAgreementOptions());
    }

    public function isEmpty()
    {
        return $this->empty;
    }

    public function getDescription()
    {
        $status = $this->status ? 'have' : 'have not';
        return Am_Html::escape("{$status} consent to {$this->agreement}");
    }

    function getAgreementOptions()
    {
        return Am_Di::getInstance()->db->selectCol("SELECT title, `type` AS ARRAY_KEY FROM ?_agreement WHERE is_current=1");
    }
}

class Am_Query_User_Condition_UserNote
    extends Am_Query_Condition
    implements Am_Query_Renderable_Condition {

    protected $q, $date_start, $date_end;
    protected $empty = true;

    public function renderElement(HTML_QuickForm2_Container $form)
    {
        $title = ___('Has User Note');

        $form->options[___('Misc')][$this->getId()] = $title;
        $group = $form->addGroup($this->getId())
            ->setLabel($title)
            ->setAttribute('id', $this->getId())
            ->setAttribute('class', 'searchField empty');
        $group->addText('q');
        $group->addDate('date_start');
        $group->addDate('date_end');
    }

    public function getId()
    {
        return 'user-note';
    }

    public function setFromRequest(array $input)
    {
        $id = $this->getId();
        $this->q = $this->date_start = $this->date_end = null;
        $this->empty = true;

        foreach (['q', 'date_start', 'date_end'] as $token) {
            if (!empty($input[$id][$token])) {
                $this->{$token} = $input[$id][$token];
                $this->empty = false;
            }
        }
        return !$this->empty;
    }

    public function isEmpty()
    {
        return $this->empty;
    }

    public function getDescription()
    {
        if ($this->date_start && $this->date_end) {
            $s = amDate($this->date_start);
            $e = amDate($this->date_end);
            $date_text = " between $s and $e";
        } elseif ($this->date_start) {
            $s = amDate($this->date_start);
            $date_text = " since $s";
        } elseif ($this->date_end) {
            $e = amDate($this->date_end);
            $date_text = " until $e";
        } else {
            $date_text = '';
        }

        if ($this->q) {
            $q = Am_Html::escape($this->q);
            $q_text = " like '{$q}'";
        } else {
            $q_text = "";
        }

        return "has user note{$q_text}{$date_text}";
    }

    function _getWhere(Am_Query $db)
    {
        $a = $db->getAlias();
        $date_start = $db->escape(($this->date_start ?: '1970-01-01') . ' 00:00:00');
        $date_end = $db->escape($this->date_end ?: Am_Period::MAX_SQL_DATE);
        $q = $db->escape("%{$this->q}%");
        if (!$date_start || !$date_end) return null;
        return "EXISTS (SELECT * FROM ?_user_note WHERE user_id={$a}.user_id AND content LIKE $q AND dattm BETWEEN $date_start AND $date_end)";
    }
}