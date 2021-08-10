<?php

/**
 * File contains available form bricks for saved forms
 */

/**
 * @package Am_SavedForm
 */
abstract class Am_Form_Brick
{
    const HIDE = 'hide';
    const HIDE_DONT = 0;
    const HIDE_DESIRED = 1;
    const HIDE_ALWAYS = 2;

    protected $config = [];
    protected $hideIfLoggedInPossible = self::HIDE_DESIRED;
    protected $hideIfLoggedIn = false;
    protected $id, $name, $container, $alias;
    protected $labels = [];
    protected $childs = [];
    protected $customLabels = [];

    abstract public function insertBrick(HTML_QuickForm2_Container $form);

    public function __construct($id = null, $config = null)
    {
        // transform labels to array with similar key->values
        if ($this->labels && is_int(key($this->labels))) {
            $ll = array_values($this->labels);
            $this->labels = array_combine($ll, $ll);
        }
        if ($id !== null)
            $this->setId($id);
        if ($config !== null)
            $this->setConfigArray($config);
        if ($this->hideIfLoggedInPossible() == self::HIDE_ALWAYS)
            $this->hideIfLoggedIn = true;
        // format labels
    }

    /**
     * this function can be used to bind some special processing
     * to hooks
     */
    public function init()
    {

    }

    function getClass()
    {
        return fromCamelCase(str_replace('Am_Form_Brick_', '', get_class($this)), '-');
    }

    function getName()
    {
        if (!$this->name)
            $this->name = str_replace('Am_Form_Brick_', '', get_class($this));
        return $this->name;
    }

    /**
     * for admin reference in UI
     * @return string|null
     */
    function getAlias()
    {
        return $this->alias;
    }

    function getId()
    {
        if (!$this->id) {
            $this->id = $this->getClass();
            if ($this->isMultiple())
                $this->id .= '-0';
        }
        return $this->id;
    }

    function setId($id)
    {
        $this->id = (string) $id;
    }

    function getContainer()
    {
        return $this->container;
    }

    function setContainer($c)
    {
        $this->container = $c;
    }

    function getConfigArray()
    {
        return $this->config;
    }

    function setConfigArray(array $config)
    {
        $this->config = $config;
    }

    function getConfig($k, $default = null)
    {
        return array_key_exists($k, $this->config) ?
            $this->config[$k] : $default;
    }

    function getStdLabels()
    {
        return $this->labels;
    }

    function getCustomLabels()
    {
        return $this->customLabels;
    }

    function setCustomLabels(array $labels)
    {
        $this->customLabels = array_map(
                function($_){return preg_replace("/\r?\n/", "\r\n", $_);},
                $labels);
    }

    function getChilds()
    {
        return $this->childs;
    }

    function setChilds(array $items)
    {
        $this->childs = $items;
    }

    function ___($id)
    {
        $args = func_get_args();
        $args[0] = array_key_exists($id, $this->customLabels) ?
            $this->customLabels[$id] :
            $this->labels[$id];
        return call_user_func_array('___', $args);
    }

    function initConfigForm(Am_Form $form)
    {

    }

    /** @return bool true if initConfigForm is overridden */
    function haveConfigForm()
    {
        $r = new ReflectionMethod(get_class($this), 'initConfigForm');
        return $r->getDeclaringClass()->getName() != __CLASS__;
    }

    function setFromRecord(array $brickConfig)
    {
        if ($brickConfig['id'])
            $this->id = $brickConfig['id'];
        $this->setConfigArray(empty($brickConfig['config']) ? [] : $brickConfig['config']);
        if (isset($brickConfig[self::HIDE]))
            $this->hideIfLoggedIn = $brickConfig[self::HIDE];
        if (isset($brickConfig['labels']))
            $this->setCustomLabels($brickConfig['labels']);
        if (isset($brickConfig['alias']))
            $this->alias = $brickConfig['alias'];
        $this->container = !empty($brickConfig['container']) ? $brickConfig['container'] : null;
        return $this;
    }

    /** @return array */
    function getRecord()
    {
        $ret = [
            'id' => $this->getId(),
            'class' => $this->getClass(),
        ];
        if ($this->hideIfLoggedIn)
            $ret[self::HIDE] = $this->hideIfLoggedIn;
        if ($this->config)
            $ret['config'] = $this->config;
        if ($this->customLabels)
            $ret['labels'] = $this->customLabels;
        if ($this->alias)
            $ret['alias'] = $this->alias;
        $ret['container'] = $this->container;
        return $ret;
    }

    function isAcceptableForForm(Am_Form_Bricked $form)
    {
        return true;
    }

    public function hideIfLoggedIn()
    {
        return $this->hideIfLoggedIn;
    }

    public function hideIfLoggedInPossible()
    {
        return $this->hideIfLoggedInPossible;
    }

    /** if user can add many instances of brick right in the editor */
    public function isMultiple()
    {
        return false;
    }

    /**
     * if special info is passed into am-order-data, check it here and change brick config
     * @return if bool false returned , brick will be removed from the form
     */
    public function applyOrderData(array $orderData)
    {
        if (isset($orderData['hide-bricks']) &&
            in_array($this->getClass(), $orderData['hide-bricks'])) {

            return false;
        }
        return true;
    }

    /**
     * Must return array of data to be used with javascript
     * Returned arrays are merged into one and will be
     * added as JSON to <form data-bricks="..."> field
     * It will then be used for Javascript as config values
     *
     * $this->jsData() is always called after $this->insertBrick()
     *
     * "class" is used to choose a function to init
     * "callback" can be set to use another function to call (expected to be a global JS function)
     *
     * @return array
     */
    public final function getJsData()
    {
        $data = [ 'class' => $this->getClass() ];
        $this->jsData($data);
        return [
            $this->getId() => $data,
        ];
    }

    protected function jsData(array &$data)
    {
        // add values to $data array as necessary
    }

    static function createAvailableBricks($className)
    {
        return new $className;
    }

    /**
     * @param array $brickConfig - must have keys: 'id', 'class', may have 'hide', 'config'
     *
     * @return Am_Form_Brick */
    static function createFromRecord(array $brickConfig)
    {
        if (empty($brickConfig['class']))
            throw new Am_Exception_InternalError("Error in " . __METHOD__ . " - cannot create record without [class]");
        if (empty($brickConfig['id']))
            throw new Am_Exception_InternalError("Error in " . __METHOD__ . " - cannot create record without [id]");
        $className = 'Am_Form_Brick_' . ucfirst(toCamelCase($brickConfig['class']));
        if (!class_exists($className, true)) {
            Am_Di::getInstance()->logger->error("Missing form brick: [$className] - not defined");
            return;
        }
        $b = new $className($brickConfig['id'], empty($brickConfig['config']) ? [] : $brickConfig['config']);
        if (array_key_exists(self::HIDE, $brickConfig))
            $b->hideIfLoggedIn = (bool) @$brickConfig[self::HIDE];
        if (!empty($brickConfig['labels']))
            $b->setCustomLabels($brickConfig['labels']);
        if (!empty($brickConfig['container'])) {
            $b->setContainer($brickConfig['container']);
        }
        return $b;
    }

    static function getAvailableBricks(Am_Form_Bricked $form)
    {
        $ret = [];
        foreach (get_declared_classes () as $className) {
            if (is_subclass_of($className, 'Am_Form_Brick')) {
                $class = new ReflectionClass($className);
                if ($class->isAbstract())
                    continue;
                $obj = call_user_func([$className, 'createAvailableBricks'], $className);
                if (!is_array($obj)) {
                    $obj = [$obj];
                }
                foreach ($obj as $k => $o)
                    if (!$o->isAcceptableForForm($form))
                        unset($obj[$k]);
                $ret = array_merge($ret, $obj);
            }
        }
        return $ret;
    }
}

class Am_Form_Brick_Name extends Am_Form_Brick
{
    const DISPLAY_BOTH = 0;
    const DISPLAY_FIRSTNAME = 1;
    const DISPLAY_LASTNAME = 2;
    const DISPLAY_BOTH_SINGLE_INPUT = 3;
    const DISPLAY_BOTH_REVERSE = 4;

    protected $labels = [
        'First & Last Name',
        'Last & First Name',
        'First Name',
        'Last Name',
        'Please enter your First Name',
        'Please enter your First & Last Name',
        'Please enter your Last & First Name',
        'Please enter your Last Name',
    ];
    protected $hideIfLoggedInPossible = self::HIDE_DESIRED;

    public function __construct($id = null, $config = null)
    {
        $this->name = ___('Name');
        parent::__construct($id, $config);
    }

    public function insertBrick(HTML_QuickForm2_Container $form)
    {
        $user = Am_Di::getInstance()->auth->getUser();
        $disabled = $user && ($user->name_f || $user->name_l) && $this->getConfig('disabled');

        if ($this->getConfig('two_rows') && $this->getConfig('display') != self::DISPLAY_BOTH_SINGLE_INPUT) {
            if ($this->getConfig('display') != self::DISPLAY_LASTNAME) {
                $row1 = $form->addGroup('', ['id' => 'name-name_f'])->setLabel($this->___('First Name'));
                if (!$this->getConfig('not_required') && !$disabled) {
                    $row1->addRule('required');
                }
            }
            if ($this->getConfig('display') != self::DISPLAY_FIRSTNAME) {
                $row2 = $form->addGroup('',  ['id' => 'name-name_l'])->setLabel($this->___('Last Name'));
                if (!$this->getConfig('not_required') && !$disabled) {
                    $row2->addRule('required');
                }
            }
        } else {
            $row1 = $form->addGroup('', ['id' => 'name-0'])
                ->setLabel($this->label());
            if (!$this->getConfig('not_required') && !$disabled) {
                $row1->addRule('required', '', in_array($this->getConfig('display'), [self::DISPLAY_BOTH, self::DISPLAY_BOTH_REVERSE]) ? 2 : 1);
            }
            $row2 = $row1;
        }

        if (!$this->getConfig('display') || $this->getConfig('display') == self::DISPLAY_FIRSTNAME) {
            $this->_addNameF($row1, $disabled);
            $row1->addHtml()->setHtml(' ');
        }
        if ($this->getConfig('display') == self::DISPLAY_BOTH_REVERSE) {
            $this->_addNameL($row1, $disabled);
            $row1->addHtml()->setHtml(' ');
        }

        if (!$this->getConfig('display') || $this->getConfig('display') == self::DISPLAY_LASTNAME) {
            $this->_addNameL($row2, $disabled);
        }
        if ($this->getConfig('display') == self::DISPLAY_BOTH_REVERSE) {
            $this->_addNameF($row2, $disabled);
        }

        if ($this->getConfig('display') == self::DISPLAY_BOTH_SINGLE_INPUT) {
            $name = $row1->addName('_name');
            $name->setAttribute('placeholder', $this->___('First & Last Name'));
            if (!$this->getConfig('not_required') && !$disabled) {
                $name->addRule('required', $this->___('Please enter your First & Last Name'));
            }
            if (!$disabled) {
                $name->addRule('regex', $this->___('Please enter your First & Last Name'), '/^[^=:<>{}()"]+$/D');
            }

            $filter = function ($v) {return trim($v);};
            if ($this->getConfig('ucfirst')) {
                $filter = function ($v) use ($filter) {
                    return mb_convert_case(call_user_func($filter, $v), MB_CASE_TITLE);
                };
            }

            $form->addFilter(function($v) use ($filter) {
                if (isset($v['_name'])) {
                    [$v['name_f'], $v['name_l']] = array_pad(array_map($filter, explode(' ', $v['_name'], 2)), 2, '');
                    unset($v['_name']);
                }
                return $v;
            });

            if ($disabled) {
                $name->toggleFrozen(true);
            }
        }
    }

    protected function label()
    {
        switch ($this->getConfig('display')) {
            case self::DISPLAY_BOTH:
            case self::DISPLAY_BOTH_SINGLE_INPUT:
                return $this->___('First & Last Name');
            case self::DISPLAY_BOTH_REVERSE:
                return $this->___('Last & First Name');
            case self::DISPLAY_FIRSTNAME:
                return $this->___('First Name');
            case self::DISPLAY_LASTNAME:
                return $this->___('Last Name');
        }
    }

    protected function _addNameF($container, $disabled)
    {
        $this->_addName('name_f', $container, $disabled, $this->___('Please enter your First Name'));
    }

    protected function _addNameL($container, $disabled)
    {
        $this->_addName('name_l', $container, $disabled, $this->___('Please enter your Last Name'));
    }

    protected function _addName($token, $container, $disabled, $error)
    {
        $el = $container->addText($token, ['id' => $token]);
        if (!$this->getConfig('not_required') && !$this->getConfig('disabled')) {
            $el->addRule('required', $error);
        }
        if (!$disabled) {
            $el->addRule('regex', $error, '/^[^=:<>{}()"]+$/D');
        }

        switch ($token)
        {
            case 'name_f':
                $el->setAttribute('placeholder', $this->___('First Name'));
                break;
            case 'name_l':
                $el->setAttribute('placeholder', $this->___('Last Name'));
                break;
        }

        $el->addFilter('trim');

        if ($this->getConfig('ucfirst'))
            $el->addFilter(function($v){return mb_convert_case($v, MB_CASE_TITLE);});

        if ($disabled)
            $el->toggleFrozen(true);
    }

    public function initConfigForm(Am_Form $form)
    {
        $form->addSelect('display')
            ->setId('name-display')
            ->loadOptions([
                self::DISPLAY_BOTH => ___('both First and Last Name'),
                self::DISPLAY_BOTH_REVERSE => ___('both Last and First Name'),
                self::DISPLAY_FIRSTNAME => ___('only First Name'),
                self::DISPLAY_LASTNAME => ___('only Last Name'),
                self::DISPLAY_BOTH_SINGLE_INPUT => ___('both First and Last Name in Single Input')

            ])->setLabel(___('User must provide'));

        $form->addAdvCheckbox('two_rows')
            ->setId('name-two_rows')
            ->setLabel(___('Display in 2 rows'));

        $form->addAdvCheckbox('not_required')->setLabel(___('Do not require to fill in these fields'));
        $form->addAdvCheckbox('ucfirst')->setLabel(___('Make the first letters of first and last name Uppercase'));
        $form->addAdvCheckbox('disabled')->setLabel(___("Disallow Name Change\nin event of user already fill in this field then he will not be able to alter it"));

        $both = self::DISPLAY_BOTH;
        $form->addScript()
            ->setScript(<<<CUT
jQuery(function(){
    jQuery('#name-display').change(function(){
        jQuery('#name-two_rows').closest('.am-row').toggle(jQuery(this).val() == '$both');
    }).change();
})
CUT
                );
    }
}

class Am_Form_Brick_HTML_Container {

    protected $chunks = [];
    protected $template = '
    </div>
    <div class="am-form-sidebar">
        <div class="am-form-sidebar-sidebar">
            %s
        </div>
    </div>
</div>
';

    function add($chunk)
    {
        $this->chunks[] = $chunk;
    }

    function __toString()
    {
        return sprintf($this->template, implode($this->chunks));
    }
}

class Am_Form_Brick_HTML extends Am_Form_Brick
{
    static $counter = 0;
    static $sidebar_container;

    use Am_Form_Brick_Conditional;

    public function __construct($id = null, $config = null)
    {
        $this->name = ___('HTML text');
        parent::__construct($id, $config);
    }

    public function initConfigForm(Am_Form $form)
    {
        $form->addSelect('position', ['class' => 'brick-html-position'])
            ->setLabel(___('Position for HTML'))
            ->loadOptions([
                '' => ___('Default'),
                'header' => ___('Above Form (Header)'),
                'footer' => ___('Below Form (Footer)'),
                'sidebar' => ___('Sidebar')
            ]);

        $form->addHtmlEditor('html', ['rows' => 15, 'class' => 'html-editor'], ['dontInitMce' => true])
            ->setLabel(___('HTML Code that will be displayed'))
            ->setMceOptions(['placeholder_items' => $this->getUserTagOptions()]);

        $form->addText('label', ['class' => 'am-el-wide', 'rel' => 'position-default'])->setLabel(___('Label'));
        $form->addAdvCheckbox('no_label', ['rel' => 'position-default'])->setLabel(___('Remove Label'));

        $form->addMagicSelect('lang')
            ->setLabel(___("Language\n" .
                'Display this brick only for the following languages. ' .
                'Keep it empty to display for any language.'))
            ->loadOptions($this->getLangaugeList());

        $this->addCondConfig($form);

        $form->addScript()
            ->setScript(<<<CUT
jQuery(function(){
    if (!window.brick_html_init) {
        window.brick_html_init = true;

        jQuery(document).on('change', '.brick-html-position', function(){
            jQuery(this).
                closest('form').
                find('[rel=position-default]').
                closest('.am-row').
                toggle(jQuery(this).val() == '');
        });
        jQuery('.brick-html-position').change();
    }
});
CUT
            );
    }

    public function getLangaugeList()
    {
        $di = Am_Di::getInstance();
        $avail = $di->languagesListUser;
        $_list = [];
        if ($enabled = $di->getLangEnabled(false))
            foreach ($enabled as $lang)
                if (!empty($avail[$lang]))
                    $_list[$lang] = $avail[$lang];
        return $_list;
    }

    public function getLanguage()
    {
        $_list = $this->getLangaugeList();
        $_locale = key(Zend_Locale::getDefault());
        if (!array_key_exists($_locale, $_list))
            [$_locale] = explode('_', $_locale);
        return $_locale;
    }

    function getDi()
    {
        return Am_Di::getInstance();
    }

    function getUserTagOptions()
    {
        $tagOptions = [
            '%user.name_f%' => 'User First Name',
            '%user.name_l%' => 'User Last Name',
            '%user.login%' => 'Username',
            '%user.email%' => 'E-Mail',
            '%user.user_id%' => 'User Internal ID#',
            '%user.street%' => 'User Street',
            '%user.street2%' => 'User Street (Second Line)',
            '%user.city%' => 'User City',
            '%user.state%' => 'User State',
            '%user.zip%' => 'User ZIP',
            '%user.country%' => 'User Country',
            '%user.status%' => 'User Status (0-pending, 1-active, 2-expired)'
        ];

        foreach ($this->getDi()->userTable->customFields()->getAll() as $field) {
            if (@$field->sql && @$field->from_config) {
                $tagOptions['%user.' . $field->name . '%'] = 'User ' . $field->title;
            }
        }

        $placeholder_items = [];
        foreach ($tagOptions as $k => $v) {
            $placeholder_items[] = [$v, $k];
        }

        return $placeholder_items;
    }

    public function insertBrick(HTML_QuickForm2_Container $form)
    {
        $root = $form;
        while ($_ = $root->getContainer()) {
            $root = $_;
        }

        self::$counter++;
        $html = $this->getConfig('html');
        $t = new Am_SimpleTemplate();
        if ($user = Am_Di::getInstance()->auth->getUser()) {
            $t->assign('user', $user);
        }
        $html = $t->render($html);

        $id = 'html' . self::$counter;
        $html = "<div id='{$id}'>{$html}</div>";
        $lang = $this->getConfig('lang');
        if ($lang && !in_array($this->getLanguage(), $lang)) return;

        switch ($this->getConfig('position')) {
            case 'sidebar' :
                if (empty(self::$sidebar_container)) {
                    self::$sidebar_container = new Am_Form_Brick_HTML_Container;

                    $root->addProlog(<<<CUT
<div class="am-form-container">
    <div class="am-form-form">
CUT
                    );
                    $root->addEpilog(self::$sidebar_container);
                }
                self::$sidebar_container->add($html);
                $this->addConditionalIfNecessary($form, "#{$id}");
                break;
            case 'header' :
                $root->addProlog($html);
                $this->addConditionalIfNecessary($form, "#{$id}");
                break;
            case 'footer' :
                $root->addEpilog($html);
                $this->addConditionalIfNecessary($form, "#{$id}");
                break;
            default:
                $attrs = $data = [];
                $data['content'] = $html;
                if ($this->getConfig('no_label')) {
                    $attrs['class'] = 'am-no-label';
                } else {
                    $data['label'] = $this->getConfig('label');
                }
                $form->addStatic('html' . self::$counter, $attrs, $data);
                $this->addConditionalIfNecessary($form, "#{$id}", true);
        }
    }

    public function isMultiple()
    {
        return true;
    }

    public function setConfigArray(array $config)
    {
        $this->_setConfigArray($config);
        parent::setConfigArray($config);
    }
}

class Am_Form_Brick_JavaScript extends Am_Form_Brick
{
    public function initConfigForm(Am_Form $form)
    {
        $form->addTextarea('code', ['rows' => 15, 'class' => 'am-el-wide'])
            ->setLabel(___("JavaScript Code\n" .
                "it will be injected on signup form"));
    }

    public function insertBrick(HTML_QuickForm2_Container $form)
    {
        $form->addScript()->setScript($this->getConfig('code'));
    }

    public function isMultiple()
    {
        return true;
    }
}

class Am_Form_Brick_Email extends Am_Form_Brick
{
    protected $labels = [
        "Your E-Mail Address\na confirmation email will be sent to you at this address",
        'Please enter valid Email',
        'Confirm Your E-Mail Address',
        'E-Mail Address and E-Mail Address Confirmation are different. Please reenter both',
        'An account with the same email already exists.',
        'Please %slogin%s to your existing account.%sIf you have not completed payment, you will be able to complete it after login',
        'There is a pending request to change your email to <strong>%s</strong>. Please check your email (including your spam) and click the link to confirm the change.',
    ];
    protected $hideIfLoggedInPossible = self::HIDE_ALWAYS;

    public function __construct($id = null, $config = null)
    {
        $this->name = ___('E-Mail');
        parent::__construct($id, $config);
    }

    public function initConfigForm(Am_Form $form)
    {
        $form->addAdvCheckbox('validate')
            ->setLabel(___('Validate E-Mail Address by sending e-mail message with code'))
            ->setId('email-validate');
        $el = $form->addAdvRadio('validate_mode')
            ->setLabel("Validate Mode\nit make sense only in case of multi-page form, in case of single page both mode is identical");
        $el->addOption(<<<CUT
<b>Default</b><br />
pause signup process on page with email address and send confirmation email,
continue other pages after email confirmation<br />
CUT
            , '');
        $el->addOption(<<<CUT
<b>Last Step</b><br />
complete all pages in signup form and send confirmation email just before account creation
CUT
            , 'last_step');

        $form->addAdvCheckbox('confirm')
            ->setLabel(___("Confirm E-Mail Address\n" .
                'second field will be displayed to enter email address twice'))
            ->setId('email-confirm');
        $form->addAdvCheckbox('do_not_allow_copy_paste')
            ->setLabel(___('Does not allow to Copy&Paste to confirmation field'))
            ->setId('email-do_not_allow_copy_paste');
        $form->addAdvCheckbox("disabled")->setLabel(___('Read-only'));
        $form->addScript()
            ->setScript(<<<CUT
jQuery(function(){
    jQuery('#email-confirm').change(function(){
        jQuery('#email-do_not_allow_copy_paste').closest('div.am-row').toggle(this.checked);
    }).change();
    jQuery('#email-validate').change(function(){
        jQuery('[name=validate_mode]').closest('div.am-row').toggle(this.checked);
    }).change()
})
CUT
        );
    }

    public function check($email)
    {
        $user_id = Am_Di::getInstance()->auth->getUserId();
        if (!$user_id)
            $user_id = Am_Di::getInstance()->session->signup_member_id;

        if (!Am_Di::getInstance()->userTable->checkUniqEmail($email, $user_id))
            return $this->___('An account with the same email already exists.') . '<br />' .
            $this->___('Please %slogin%s to your existing account.%sIf you have not completed payment, you will be able to complete it after login', '<a href="' . Am_Di::getInstance()->url('login', ['amember_redirect_url'=>$_SERVER['REQUEST_URI']]) . '">', '</a>', '<br />');
        return Am_Di::getInstance()->banTable->checkBan(['email' => $email]);
    }

    public function insertBrick(HTML_QuickForm2_Container $form)
    {
        $email = $form->addText('email', ['size' => 30])
                ->setLabel($this->___("Your E-Mail Address\na confirmation email will be sent to you at this address"));
        $email->addRule('required', $this->___('Please enter valid Email'))
            ->addRule('callback', $this->___('Please enter valid Email'), ['Am_Validate', 'email']);
        if ($this->getConfig('disabled'))
            $email->toggleFrozen(true);
        $redirect = isset($_GET['amember_redirect_url']) ? $_GET['amember_redirect_url'] : $_SERVER['REQUEST_URI'];
        $email->addRule('callback2', '--wrong email--', [$this, 'check'])
            ->addRule('remote', '--wrong email--', [
                'url' => Am_Di::getInstance()->url('ajax/check-uniq-email', ['_url'=>$redirect], false)
            ]);
        $di = Am_Di::getInstance();
        if ($di->auth->getUser() && ($_ = $di->store->getBlob("member-verify-email-profile-{$di->auth->getUser()->pk()}"))) {
            $_ = unserialize($_);
            $form->addHtml(null, ['id' => 'email-change-pending-request-warning'])
                ->setHtml(
                    '<div class="am-email-change-pending-request-warning">'
                        . $this->___('There is a pending request to change your email to <strong>%s</strong>. Please check your email (including your spam) and click the link to confirm the change.',
                        Am_Html::escape($_['email']))
                        . '</div>'
                );
        }
        if ($this->getConfig('confirm', 0)) {
            $email0 = $form->addText('_email', ['size' => 30])
                    ->setLabel($this->___("Confirm Your E-Mail Address"))
                    ->setId('email-confirm');
            $email0->addRule('required');
            $email0->addRule('eq', $this->___('E-Mail Address and E-Mail Address Confirmation are different. Please reenter both'), $email);
            return [$email, $email0];
        }
    }

    function jsData(array &$data)
    {
        $data['do_not_allow_copy_paste'] = (bool)$this->getConfig('do_not_allow_copy_paste');
    }
}

class Am_Form_Brick_Login extends Am_Form_Brick
{
    protected $labels = [
        "Choose a Username\nit must be %d or more characters in length\nmay only contain letters, numbers, and underscores",
        'Please enter valid Username. It must contain at least %d characters',
        'Username contains invalid characters - please use digits, letters or spaces',
        'Username contains invalid characters - please use digits, letters, dash and underscore',
        'Username %s is already taken. Please choose another username',
    ];
    protected $hideIfLoggedInPossible = self::HIDE_ALWAYS;

    public function __construct($id = null, $config = null)
    {
        $this->name = ___("Username");
        parent::__construct($id, $config);
    }

    public function insertBrick(HTML_QuickForm2_Container $form)
    {
        $len = Am_Di::getInstance()->config->get('login_min_length', 6);
        $login = $form->addText('login', ['size' => 30, 'maxlength' => Am_Di::getInstance()->config->get('login_max_length', 64)])
                ->setLabel($this->___("Choose a Username\nit must be %d or more characters in length\nmay only contain letters, numbers, and underscores", $len));
        $login->addRule('required', sprintf($this->___('Please enter valid Username. It must contain at least %d characters'), $len))
            ->addRule('length', sprintf($this->___('Please enter valid Username. It must contain at least %d characters'), $len), [$len, Am_Di::getInstance()->config->get('login_max_length', 64)])
            ->addRule('regex', !Am_Di::getInstance()->config->get('login_disallow_spaces') ?
                    $this->___('Username contains invalid characters - please use digits, letters or spaces') :
                    $this->___('Username contains invalid characters - please use digits, letters, dash and underscore'),
                Am_Di::getInstance()->userTable->getLoginRegex())
            ->addRule('callback2', "--wrong login--", [$this, 'check'])
            ->addRule('remote', '--wrong login--', [
                'url' => Am_Di::getInstance()->url('ajax', ['do'=>'check_uniq_login'], false),
            ]);

        if (!Am_Di::getInstance()->config->get('login_dont_lowercase'))
            $login->addFilter('mb_strtolower');

        $this->form = $form;
    }

    public function check($login)
    {
        if (!Am_Di::getInstance()->userTable->checkUniqLogin($login, Am_Di::getInstance()->session->signup_member_id))
            return sprintf($this->___('Username %s is already taken. Please choose another username'), Am_Html::escape($login));
        return Am_Di::getInstance()->banTable->checkBan(['login' => $login]);
    }

    public function isAcceptableForForm(Am_Form_Bricked $form)
    {
        return $form instanceof Am_Form_Signup;
    }
}

class Am_Form_Brick_NewLogin extends Am_Form_Brick
{
    protected $labels = [
        "Username\nyou can choose new username here or keep it unchanged.\nUsername must be %d or more characters in length and may\nonly contain small letters, numbers, and underscore",
        "Please enter valid Username. It must contain at least %d characters",
        "Username contains invalid characters - please use digits, letters or spaces",
        "Username contains invalid characters - please use digits, letters, dash and underscore",
        'Username %s is already taken. Please choose another username',
    ];

    public function __construct($id = null, $config = null)
    {
        $this->name = ___('Change Username');
        parent::__construct($id, $config);
    }

    public function insertBrick(HTML_QuickForm2_Container $form)
    {
        $len = Am_Di::getInstance()->config->get('login_min_length', 6);
        $login = $form->addText('login', ['maxlength' => Am_Di::getInstance()->config->get('login_max_length', 64)])
                ->setLabel(sprintf($this->___("Username\nyou can choose new username here or keep it unchanged.\nUsername must be %d or more characters in length and may\nonly contain small letters, numbers, and underscore"), $len)
        );
        if ($this->getConfig('disabled')) {
            $login->toggleFrozen(true);
        } else {
            $login
                ->addRule('required')
                ->addRule('length', sprintf($this->___("Please enter valid Username. It must contain at least %d characters"), $len), [$len, Am_Di::getInstance()->config->get('login_max_length', 64)])
                ->addRule('regex', !Am_Di::getInstance()->config->get('login_disallow_spaces') ?
                        $this->___("Username contains invalid characters - please use digits, letters or spaces") :
                        $this->___("Username contains invalid characters - please use digits, letters, dash and underscore"),
                    Am_Di::getInstance()->userTable->getLoginRegex())
                ->addRule('callback2', $this->___('Username %s is already taken. Please choose another username'), [$this, 'checkNewUniqLogin']);
        }
    }

    function checkNewUniqLogin($login)
    {
        $auth_user = Am_Di::getInstance()->auth->getUser();
        if (strcasecmp($login, $auth_user->login) !== 0)
            if (!$auth_user->getTable()->checkUniqLogin($login, $auth_user->pk()))
                return sprintf($this->___('Username %s is already taken. Please choose another username'), Am_Html::escape($login));
    }

    public function isAcceptableForForm(Am_Form_Bricked $form)
    {
        return $form instanceof Am_Form_Profile;
    }

    public function initConfigForm(Am_Form $form)
    {
        $form->addAdvCheckbox("disabled")->setLabel(___('Read-only'));
    }
}

class Am_Form_Brick_Password extends Am_Form_Brick
{
    protected $labels = [
        "Choose a Password\nmust be %d or more characters",
        'Confirm Your Password',
        'Please enter Password',
        'Password must contain at least %d letters or digits',
        'Password and Password Confirmation are different. Please reenter both',
        'Password should contain at least 2 capital letters, 2 or more numbers and 2 or more special chars',
    ];
    protected $hideIfLoggedInPossible = self::HIDE_ALWAYS;

    public function __construct($id = null, $config = null)
    {
        $this->name = ___("Password");
        parent::__construct($id, $config);
    }

    public function initConfigForm(Am_Form $form)
    {
        $form->addAdvCheckbox('do_not_confirm')
            ->setLabel(___("Does not Confirm Password\n" .
                'second field will not be displayed to enter password twice'))
            ->setId('password-do_not_confirm');
        $form->addAdvCheckbox('do_not_allow_copy_paste')
            ->setLabel(___('Does not allow to Copy&Paste to confirmation field'))
            ->setId('password-do_not_allow_copy_paste');
        $form->addScript()
            ->setScript(<<<CUT
jQuery(function(){
    jQuery('#password-do_not_confirm').change(function(){
        jQuery('#password-do_not_allow_copy_paste').closest('div.am-row').toggle(!this.checked);
    }).change()
})
CUT
        );
    }

    public function insertBrick(HTML_QuickForm2_Container $form)
    {
        $len = Am_Di::getInstance()->config->get('pass_min_length', 6);
        $pass = $form->addPassword('pass', ['size' => 30, 'autocomplete'=>'off', 'maxlength' => Am_Di::getInstance()->config->get('pass_max_length', 64), 'class' => 'am-pass-indicator'])
                ->setLabel($this->___("Choose a Password\nmust be %d or more characters", $len));

        $pass->addRule('required', $this->___('Please enter Password'));
        $pass->addRule('length', sprintf($this->___('Password must contain at least %d letters or digits'), $len),
            [$len, Am_Di::getInstance()->config->get('pass_max_length', 64)]);

        if (Am_Di::getInstance()->config->get('require_strong_password')) {
            $pass->addRule('regex', $this->___('Password should contain at least 2 capital letters, 2 or more numbers and 2 or more special chars'),
                Am_Di::getInstance()->userTable->getStrongPasswordRegex());
        }

        if (!$this->getConfig('do_not_confirm')) {
            $pass0 = $form->addPassword('_pass', ['size' => 30, 'autocomplete'=>'off', 'maxlength' => Am_Di::getInstance()->config->get('pass_max_length', 64)])
                    ->setLabel($this->___('Confirm Your Password'))
                    ->setId('pass-confirm');
            $pass0->addRule('required');
            $pass0->addRule('eq', $this->___('Password and Password Confirmation are different. Please reenter both'), [$pass]);
            return [$pass, $pass0];
        } else {
            $pass->setAttribute('class', 'am-pass-reveal am-with-action am-pass-indicator');
        }
        return $pass;
    }

    public function isAcceptableForForm(Am_Form_Bricked $form)
    {
        return $form instanceof Am_Form_Signup;
    }
    protected function jsData(array &$data)
    {
        $data['do_not_allow_copy_paste'] = (bool)$this->getConfig('do_not_allow_copy_paste');
    }
}

class Am_Form_Brick_NewPassword extends Am_Form_Brick
{
    protected $labels = [
        "Password",
        "Change",
        "Your Current Password\nif you are changing password, please\n enter your current password for validation",
        "New Password\nyou can choose new password here or keep it unchanged\nmust be %d or more characters",
        'Confirm New Password',
        'Please enter Password',
        'Password must contain at least %d letters or digits',
        'Password and Password Confirmation are different. Please reenter both',
        'Please enter your current password for validation',
        'Current password entered incorrectly, please try again',
        'Password should contain at least 2 capital letters, 2 or more numbers and 2 or more special chars',
    ];

    public function __construct($id = null, $config = null)
    {
        $this->name = ___('Change Password');
        parent::__construct($id, $config);
    }

    public function initConfigForm(Am_Form $form)
    {
        $form->addAdvCheckbox('do_not_ask_current_pass')
            ->setLabel(___("Does not Ask Current Password\n" .
                'user will not need to enter his current password to change it'));
        $form->addAdvCheckbox('do_not_confirm')
            ->setLabel(___("Does not Confirm Password\n" .
                'second field will not be displayed to enter password twice'))
            ->setId('new-password-do_not_confirm');
        $form->addAdvCheckbox('do_not_allow_copy_paste')
            ->setLabel(___('Does not allow to Copy&Paste to confirmation field'))
            ->setId('new-password-do_not_allow_copy_paste');
        $form->addScript()
            ->setScript(<<<CUT
jQuery(function(){
    jQuery('#new-password-do_not_confirm').change(function(){
        jQuery('#new-password-do_not_allow_copy_paste').closest('div.am-row').toggle(!this.checked);
    }).change()
})
CUT
        );
    }

    public function insertBrick(HTML_QuickForm2_Container $form)
    {
        $change = Am_Html::escape($this->___('Change'));
        $form->addHtml()
            ->setLabel($this->___('Password'))
            ->setHtml(<<<CUT
<a href="javascript:;" class="local-link am-change-pass-toggle">$change</a>
CUT
            );

        $len = Am_Di::getInstance()->config->get('pass_min_length', 6);
        if (!$this->getConfig('do_not_ask_current_pass')) {
            $oldPass = $form->addPassword('_oldpass', ['size' => 30, 'autocomplete'=>'off', 'class'=>'am-change-pass'])
                    ->setLabel($this->___("Your Current Password\nif you are changing password, please\n enter your current password for validation"));
            $oldPass->addRule('callback2', 'wrong', [$this, 'validateOldPass']);
        }
        $pass = $form->addPassword('pass', ['size' => 30, 'autocomplete'=>'off', 'maxlength' => Am_Di::getInstance()->config->get('pass_max_length', 64), 'class'=>'am-change-pass'])
                ->setLabel($this->___("New Password\nyou can choose new password here or keep it unchanged\nmust be %d or more characters", $len));
        $pass->addRule('length', sprintf($this->___('Password must contain at least %d letters or digits'), $len),
            [$len, Am_Di::getInstance()->config->get('pass_max_length', 64)]);

        if (Am_Di::getInstance()->config->get('require_strong_password')) {
            $pass->addRule('regex', $this->___('Password should contain at least 2 capital letters, 2 or more numbers and 2 or more special chars'),
                Am_Di::getInstance()->userTable->getStrongPasswordRegex());
        }

        if (!$this->getConfig('do_not_confirm')) {
            $pass0 = $form->addPassword('_pass', ['size' => 30, 'autocomplete'=>'off', 'class'=>'am-change-pass'])
                    ->setLabel($this->___('Confirm New Password'))
                    ->setId('pass-confirm');

            $pass0->addRule('eq', $this->___('Password and Password Confirmation are different. Please reenter both'), [$pass]);
            return [$pass, $pass0];
        }

        return $pass;
    }
    protected function jsData(array &$data)
    {
        $data['do_not_allow_copy_paste'] = (bool)$this->getConfig('do_not_allow_copy_paste');
    }

    public function validateOldPass($vars, HTML_QuickForm2_Element_InputPassword $el)
    {
        $vars = $el->getContainer()->getValue();
        if ($vars['pass'] != '') {
            if ($vars['_oldpass'] == '')
                return $this->___('Please enter your current password for validation');

            $protector = new Am_Auth_BruteforceProtector(
                    Am_Di::getInstance()->db,
                    Am_Di::getInstance()->config->get('protect.php_include.bruteforce_count', 5),
                    Am_Di::getInstance()->config->get('protect.php_include.bruteforce_delay', 120),
                    Am_Auth_BruteforceProtector::TYPE_USER);

            if ($wait = $protector->loginAllowed($_SERVER['REMOTE_ADDR'])) {
                return ___('Please wait %d seconds before next attempt', $wait);
            }

            if (!Am_Di::getInstance()->user->checkPassword($vars['_oldpass'])) {
                $protector->reportFailure($_SERVER['REMOTE_ADDR']);
                return $this->___('Current password entered incorrectly, please try again');
            }
        }
    }

    public function isAcceptableForForm(Am_Form_Bricked $form)
    {
        return $form instanceof Am_Form_Profile;
    }
}

class Am_Form_Brick_Address extends Am_Form_Brick
{
    protected $labels = [
        'Address Information' => 'Address Information',
        'Street' => 'Street',
        'Street (Second Line)' => 'Street (Second Line)',
        'City' => 'City',
        'State' => 'State',
        'ZIP Code' => 'ZIP Code',
        'Country' => 'Country',
    ];

    public function __construct($id = null, $config = null)
    {
        $this->name = ___('Address Information');

        if (empty($config['fields'])) {
            $config['fields'] = [
                'street' => 1,
                'city' => 1,
                'country' => 1,
                'state' => 1,
                'zip' => 1,
            ];
        }
        parent::__construct($id, $config);
    }

    public function insertBrick(HTML_QuickForm2_Container $form)
    {
        $ro = $this->getConfig('disabled');

        $fieldSet = $this->getConfig('hide_fieldset') ?
            $form :
            $form->addElement('fieldset', 'address', ['id' => 'row-address-0'])->setLabel($this->___('Address Information'));

        foreach ($this->getConfig('fields', []) as $f => $required) {
            switch ($f) {
                case 'street' :
                    $street = $fieldSet->addText('street', ['class' => 'am-el-wide'])->setLabel($this->___('Street'));
                    if ($ro) {
                        $street->toggleFrozen(true);
                    } elseif ($required) {
                        $street->addRule('required', ___('Please enter %s', $this->___('Street')));
                    }
                    break;
                case 'street2' :
                    $street2 = $fieldSet->addText('street2',
                        ['class' => 'am-el-wide'])->setLabel($this->___('Street (Second Line)'));
                    if ($ro) {
                        $street2->toggleFrozen(true);
                    } elseif ($required) {
                        $street2->addRule('required', ___('Please enter %s', $this->___('Street (Second Line)')));
                    }
                    break;
                case 'city' :
                    $city = $fieldSet->addText('city', ['class' => 'am-el-wide'])->setLabel($this->___('City'));
                    if ($ro) {
                        $city->toggleFrozen(true);
                    } elseif ($required) {
                        $city->addRule('required', ___('Please enter %s', $this->___('City')));
                    }
                    break;
                case 'zip' :
                    $zip = $fieldSet->addText('zip')->setLabel($this->___('ZIP Code'));
                    if ($ro) {
                        $zip->toggleFrozen(true);
                    } elseif ($required) {
                        $zip->addRule('required', ___('Please enter %s', $this->___('ZIP Code')));
                    }
                    break;
                case 'country' :
                    $country = $fieldSet->addSelect('country')->setLabel($this->___('Country'))
                            ->setId('f_country')
                            ->loadOptions(Am_Di::getInstance()->countryTable->getOptions(true));
                    if ($ro) {
                        $country->toggleFrozen(true);
                    } elseif ($required) {
                        $country->addRule('required', ___('Please enter %s', $this->___('Country')));
                    }
                    break;
                case 'state' :
                    $group = $fieldSet->addGroup(null, ['id' => 'grp-state'])->setLabel($this->___('State'));
                    $stateSelect = $group->addSelect('state')
                            ->setId('f_state')
                            ->loadOptions($stateOptions = Am_Di::getInstance()->stateTable->getOptions(@$_REQUEST['country'], true));
                    $stateText = $group->addText('state')->setId('t_state');
                    $disableObj = $stateOptions ? $stateText : $stateSelect;
                    $disableObj->setAttribute('disabled', 'disabled')->setAttribute('style', 'display: none');
                    if ($ro) {
                        $group->toggleFrozen(true);
                    } elseif ($required) {
                        $group->addRule('required', ___('Please enter %s', $this->___('State')));
                    }
                    break;
            }
        }

        if (!$ro && $this->getConfig('country_default')) {
            $f = $form;
            while ($container = $f->getContainer())
                $f = $container;
            $f->addDataSource(new HTML_QuickForm2_DataSource_Array([
                    'country' => $this->getConfig('country_default')
            ]));
        }
    }

    public function setConfigArray(array $config)
    {
        // Deal with old style Address required field.
        if (isset($config['required']) && $config['required'] && !array_key_exists('street_display', $config)) {
            foreach (['zip', 'street', 'city', 'state', 'country'] as $f) {
                $config[$f . '_display'] = 1; // Required
            }
        }
        unset($config['required']);

        if (isset($config['street_display'])) {
            //backwards compatability
            //prev it stored as fieldName_display = enum(-1, 0, 1)
            //-1 - do not display
            // 0 - display
            // 1 - display and required
            isset($config['fields']) || ($config['fields'] = []);

            $farr = ['street', 'street2', 'city', 'zip', 'country', 'state'];

            foreach ($farr as $f) {
                if (-1 != ($val = @$config[$f . '_display'])) {
                    $config['fields'][$f] = (int) $val;
                }
                unset($config[$f . '_display']);
            }
        }

        parent::setConfigArray($config);
    }

    public function initConfigForm(Am_Form $form)
    {
        $farr = ['street', 'street2', 'city', 'zip', 'country', 'state'];

        $fieldsVal = $this->getConfig('fields');

        $fields = $form->addElement(new Am_Form_Element_AddressFields('fields'));
        $fields->setLabel(___('Fields To Display'));
        foreach ($farr as $f) {
            $attr = [
                'data-label' => ucfirst($f) . ' <input type="checkbox" onChange = "jQuery(this).closest(\'div\').find(\'input[type=hidden]\').val(this.checked ? 1 : 0)" /> required',
                'data-value' => !empty($fieldsVal[$f]),
            ];
            $fields->addOption(ucfirst($f), $f, $attr);
        }

        $fields->setJsOptions('{
            sortable : true,
            getOptionName : function (name, option) {
                return name.replace(/\[\]$/, "") + "[" + option.value + "]";
            },
            getOptionValue : function (option) {
                return jQuery(option).data("value");
            },
            onOptionAdded : function (context, option) {
                if (jQuery(context).find("input[type=hidden]").val() == 1) {
                    jQuery(context).find("input[type=checkbox]").prop("checked", "checked");
                }
            }
        }');

        $form->addSelect('country_default')->setLabel('Default Country')->loadOptions(Am_Di::getInstance()->countryTable->getOptions(true));
        $form->addAdvCheckbox('hide_fieldset')->setLabel(___('Hide Brick Title'));
        $form->addAdvCheckbox("disabled")->setLabel(___('Read-only'));
    }
}

class Am_Form_Brick_Phone extends Am_Form_Brick
{
    protected $labels = [
        'Phone Number' => 'Phone Number',
    ];

    public function insertBrick(HTML_QuickForm2_Container $form)
    {
        $phone = $form->addText('phone')->setLabel($this->___('Phone Number'));
        if ($this->getConfig('required')) {
            $phone->addRule('required', ___('Please enter %s', $this->___('Phone Number')));
        }
    }

    public function initConfigForm(Am_Form $form)
    {
        $form->addAdvCheckbox('required')->setLabel(___('Required'));
    }
}

class Am_Form_Brick_Product extends Am_Form_Brick
{
    const DISPLAY_ALL = 0;
    const DISPLAY_CATEGORY = 1;
    const DISPLAY_PRODUCT = 2;
    const DISPLAY_BP = 3;

    const REQUIRE_DEFAULT = 0;
    const REQUIRE_ALWAYS = 1;
    const REQUIRE_NEVER = 2;
    const REQUIRE_ALTERNATE = 3;

    protected $labels = [
        'Membership Type',
        'Please choose a membership type',
        'Add Membership',
        'This field is required',
        'There are no products available for purchase. Please come back later.',
    ];
    protected $hideIfLoggedInPossible = self::HIDE_DONT;
    protected static $bricksAdded = 0;
    protected static $bricksWhichCanBeRequiredAdded = 0;
    protected static $bricksAlternateAdded = 0;

    protected $__name; // for js

    public function __construct($id = null, $config = null)
    {
        $this->name = ___('Product');
        parent::__construct($id, $config);
    }

    function shortRender(Product $p, BillingPlan $plan = null, $bpTitle = false)
    {
        $plan = $plan ?: $p->getBillingPlan();
        return ($bpTitle ? $plan->title : $p->getTitle()) . ' - ' . $plan->getTerms();
    }

    function renderProduct(Product $p, BillingPlan $plan = null, $short = false, $bpTitle = false)
    {
        $bp = $plan ?: $p->getBillingPlan();
        return sprintf('<span class="am-product-title" id="am-product-title-%d-%d">%s</span> <span class="am-product-terms" id="am-product-terms-%d-%d">%s</span> <span class="am-product-desc" id="am-product-desc-%d-%d">%s</span>',
            $p->pk(),
            $bp->pk(),
            ($bpTitle ? $bp->title : $p->getTitle(false)),
            $p->pk(),
            $bp->pk(),
            $plan ? ___($plan->getTerms()) : "",
            $p->pk(),
            $bp->pk(),
            $p->getDescription(false)
        );
    }

    function getProducts()
    {
        $ret = [];
        switch ($this->getConfig('type', 0)) {
            case self::DISPLAY_CATEGORY:
                $ret = Am_Di::getInstance()->productTable->getVisible($this->getConfig('groups', []));
                break;
            case self::DISPLAY_PRODUCT:
                $ret = [];
                $ids = $this->getConfig('products', []);
                $arr = Am_Di::getInstance()->productTable->loadIds($ids);
                foreach ($ids as $id) {
                    foreach ($arr as $p)
                        if ($p->product_id == $id) {
                            if ($p->is_disabled)
                                continue;
                            $ret[] = $p;
                        }
                }
                break;
            case self::DISPLAY_BP:
                $ret = [];
                $ids = array_map('intval', $this->getConfig('bps', [])); //strip bp
                $arr = Am_Di::getInstance()->productTable->loadIds($ids);
                foreach ($ids as $id) {
                    foreach ($arr as $p)
                        if ($p->product_id == $id) {
                            if ($p->is_disabled)
                                continue;
                            $ret[] = $p;
                        }
                }
                break;
            default:
                $ret = Am_Di::getInstance()->productTable->getVisible(null);
        }
        return Am_Di::getInstance()->hook->filter(
            $ret,
            Am_Event::SIGNUP_FORM_GET_PRODUCTS,
            ['brick' => $this, 'savedForm' => Am_Di::getInstance()->savedFormTable->load($this->getConfig('saved_form_id'))]
        );
    }

    function getBillingPlans($products)
    {
        switch ($this->getConfig('type', 0)) {
            case self::DISPLAY_BP:
                $map = [];
                foreach ($products as $p) {
                    $map[$p->pk()] = $p;
                }
                $res = [];
                foreach ($this->getConfig('bps', []) as $item) {
                    [$p_id, $bp_id] = explode('-', $item);
                    if (isset($map[$p_id])) {
                        foreach ($map[$p_id]->getBillingPlans(true) as $bp) {
                            if ($bp->pk() == $bp_id)
                                $res[] = $bp;
                        }
                    }
                }
                break;
            case self::DISPLAY_ALL:
            case self::DISPLAY_CATEGORY:
            case self::DISPLAY_PRODUCT:
            default:
                $res = [];
                foreach ($products as $product) {
                    $res = array_merge($res, $product->getBillingPlans(true));
                }
        }
        return Am_Di::getInstance()->hook->filter(
            $res,
            Am_Event::SIGNUP_FORM_GET_BILLING_PLANS,
            ['brick' => $this, 'savedForm' => Am_Di::getInstance()->savedFormTable->load($this->getConfig('saved_form_id'))]
        );
    }

    function getProductsFiltered()
    {
        $products = $this->getProducts();
        if ($this->getConfig('display-type', 'hide') == 'display')
            return $products;

        $user = Am_Di::getInstance()->auth->getUser();
        $haveActive = $haveExpired = [];
        if (!is_null($user)) {
            $haveActive = $user->getActiveProductIds();
            $haveExpired = $user->getExpiredProductIds();
        }
        $ret = Am_Di::getInstance()->productTable
                ->filterProducts($products, $haveActive, $haveExpired,
                    ($this->getConfig('display-type') != 'hide-always' && $this->getConfig('input-type') == 'checkbox') ? true : false);
        return Am_Di::getInstance()->hook->filter(
            $ret,
            Am_Event::SIGNUP_FORM_GET_PRODUCTS_FILTERED,
            ['brick' => $this, 'savedForm' => Am_Di::getInstance()->savedFormTable->load($this->getConfig('saved_form_id'))]
        );
    }

    /**
     * Reset config to just one product for usage in fixed order forms
     */
    function applyOrderData(array $orderData)
    {
        $_ = parent::applyOrderData($orderData);

        if (!empty($orderData['billing_plan_id'])) {
            $bp = Am_Di::getInstance()->billingPlanTable->load($orderData['billing_plan_id']);
            $this->config['type'] = self::DISPLAY_BP;
            $id = $bp->product_id . '-' . $bp->pk() ;
            $this->config['bps'] = [$id];
            $this->config['default'] = $id;
            $this->config['input-type'] = 'advradio';
            $this->config['require'] = 1;
        }

        return $_;
    }

    public function insertProductOptions(HTML_QuickForm2_Container $form, $pid, array $productOptions,
            BillingPlan $plan)
    {
        foreach ($productOptions as $option)
        {
            $elName = 'productOption[' . $pid . '][0][' . $option->name . ']';
            $isEmpty = empty($_POST['productOption'][$pid][0][$option->name]);
            /* @var $option ProductOption */
            $el = null;
            switch ($option->type)
            {
                case 'text':
                    $el = $form->addElement('text', $elName);
                    if ($isEmpty)
                        $el->setValue($option->getDefault());
                    break;
                case 'radio':
                    $el = $form->addElement('advradio', $elName);
                    $el->loadOptions($option->getSelectOptionsWithPrice($plan));
                    if ($isEmpty)
                        $el->setValue($option->getDefault());
                    break;
                case 'select':
                    $el = $form->addElement('select', $elName);
                    $el->loadOptions($option->getSelectOptionsWithPrice($plan));
                    if ($isEmpty)
                        $el->setValue($option->getDefault());
                    break;
                case 'multi_select':
                    $el = $form->addElement('magicselect', $elName);
                    $el->loadOptions($option->getSelectOptionsWithPrice($plan));
                    if ($isEmpty)
                        $el->setValue($option->getDefaults());
                    break;
                case 'textarea':
                    $el = $form->addElement('textarea', $elName, 'class=am-el-wide rows=5');
                    if ($isEmpty)
                        $el->setValue($option->getDefault());
                    break;
                case 'checkbox':
                    $opts = $option->getSelectOptionsWithPrice($plan);
                    if ($opts)
                    {
                        $el = $form->addGroup($elName);
                        $el->setSeparator("<br />");
                        foreach ($opts as $k => $v) {
                            $chkbox = $el->addAdvCheckbox(null, ['value' => $k])->setContent(___($v));
                            if ($isEmpty && in_array($k, (array)$option->getDefaults()))
                                $chkbox->setAttribute('checked', 'checked');
                        }
                        $el->addHidden(null, ['value' => '']);
                        $el->addFilter('array_filter');
                        if (count($opts) == 1 && $option->is_required) {
                            $chkbox->addRule('required', $this->___('This field is required'), null, HTML_QuickForm2_Rule::ONBLUR_CLIENT);
                        }
                    } else {
                        $el = $form->addElement('advcheckbox', $elName);
                    }
                    break;
                case 'date':
                    $el = $form->addElement('date', $elName);
                    break;
                }
                if ($el && $option->is_required)
                {
                    // onblur client set to only validate option fields with javascript
                    // else there is a problem with hidden fields as quickform2 does not skip validation for hidden
                    $el->addRule('required', $this->___('This field is required'), null, HTML_QuickForm2_Rule::ONBLUR_CLIENT);
                }
                if ($el) {
                    $el->setLabel([___($option->title), isset($option->desc)?___($option->desc):""]);
                }
        }
    }

    protected function jsData(array &$data)
    {
        $data['name'] = $this->__name;
        $data['popup'] = ($this->getConfig('input-type') == 'checkbox') && $this->getConfig('display-popup');
    }

    public function insertBrick(HTML_QuickForm2_Container $form)
    {
        $product_paysys = Am_Di::getInstance()->config->get('product_paysystem');

        $root = $form;
        while ($_ = $root->getContainer()) {
            $root = $_;
        }

        $base_name = 'product_id_' . $root->getId();
        $name = self::$bricksAdded ? $base_name . '_' . self::$bricksAdded : $base_name;
        $this->__name = $name;
        $productOptions = [];
        $products = $this->getProductsFiltered();
        if (!$products) {
            if ($this->getConfig('skip_if_empty')) return;
            if ($this->getConfig('require', self::REQUIRE_DEFAULT) == self::REQUIRE_NEVER) return;
            if (self::$bricksWhichCanBeRequiredAdded && $this->getConfig('require', self::REQUIRE_DEFAULT) != self::REQUIRE_ALWAYS) return;
            throw new Am_Exception_QuietError($this->___("There are no products available for purchase. Please come back later."));
        }

        self::$bricksAdded++;

        if ($this->getConfig('require', self::REQUIRE_DEFAULT) != self::REQUIRE_NEVER)
            self::$bricksWhichCanBeRequiredAdded++;

        if ($this->getConfig('require', self::REQUIRE_DEFAULT) == self::REQUIRE_ALTERNATE)
            self::$bricksAlternateAdded++;

        $options = $shortOptions = $attrs = $dataOptions = [];
        if ($this->getConfig('empty-option')) {
            $shortOptions[null] = $this->getConfig('empty-option-text', ___('Please select'));
            $options[null] = '<span class="am-product-title am-product-empty">' . $shortOptions[null] .
                '</span><span class="am-product-terms"></span><span class="am-product-desc"></span>';
            $attrs[null] = [];
            $dataOptions[null] = [
                'value' => null,
                'label' => $options[null],
                'selected' => false,
                'variable_qty' => false,
                'qty' => 1,
            ];
        }
        $useBpTitle = $this->getConfig('title_source') == 'bp';
        foreach ($this->getBillingPlans($products) as $plan) {
            $p = $plan->getProduct();
            $pid = $p->product_id . '-' . $plan->plan_id;
            $options[$pid] = $this->renderProduct($p, $plan, false, $useBpTitle);
            $shortOptions[$pid] = $this->shortRender($p, $plan, $useBpTitle);
            $attrs[$pid] = [
                'data-currency' => $plan->currency,
                'data-first_price' => $plan->first_price,
                'data-second_price' => $plan->second_price,
                'data-paysys' => $product_paysys && $plan->paysys_id,
                'data-paysys_ids' => json_encode($product_paysys ? array_filter(explode(',', $plan->paysys_id)) : []),
            ];
            $dataOptions[$pid] = [
                'label' => $options[$pid],
                'value' => $pid,
                'variable_qty' => $plan->variable_qty,
                'qty' => $plan->qty,
                'selected' => false,
                'data-product-title' => $useBpTitle ? $plan->title : $p->getTitle(false),
                'data-product-terms' => $plan ? ___($plan->getTerms()) : "",
                'data-product-desc' => $p->getDescription(false),
            ];
            $productOptions[$pid] = $p->getOptions();
            $billingPlans[$pid] = $plan;
        }
        $inputType = $this->getConfig('input-type', 'advradio');
        if (count($options) == 1) {
            if ($this->getConfig('hide_if_one'))
                $inputType = 'none';
            elseif ($inputType != 'checkbox')
                $inputType = 'hidden';
        }
        $oel = null; //outer element
        $productOptionsDontHide = false;
        switch ($inputType) {
            case 'none':
                [$pid, $label] = [key($options), current($options)];
                $oel = $el = $form->addHidden($name, $attrs[$pid]);
                $el->setValue($pid);
                $el->toggleFrozen(true);
                $productOptionsDontHide = true; // normally options display with js but not in this case!
                break;
            case 'checkbox':
                $data = [];
                foreach ($this->getBillingPlans($products) as $plan) {
                    $p = $plan->getProduct();
                    $data[$p->product_id . '-' . $plan->pk()] = [
                        'data-currency' => $plan->currency,
                        'data-first_price' => $plan->first_price,
                        'data-second_price' => $plan->second_price,
                        'data-paysys' => $product_paysys && $plan->paysys_id,
                        'data-paysys_ids' => json_encode($product_paysys ? array_filter(explode(',', $plan->paysys_id)) : []),
                        'options' => [
                            'value' => $p->product_id . '-' . $plan->pk(),
                            'label' => $this->renderProduct($p, $plan, false, $this->getConfig('title_source') == 'bp'),
                            'variable_qty' => $plan->variable_qty,
                            'qty' => $plan->qty,
                            'selected' => false,
                            'data-product-title' => $useBpTitle ? $plan->title : $p->getTitle(false),
                            'data-product-terms' => ___($plan->getTerms()),
                            'data-product-desc' => $p->getDescription(false),
                        ],
                    ];
                }
                if ($this->getConfig('display-popup')) {
                    $search = '';
                    if ($this->getConfig('cat-filter')) {
                        $search = [];
                        $all_cats = [];
                        foreach ($this->getBillingPlans($products) as $plan) {
                            $p = $plan->getProduct();
                            $p_cats = $p->getCategoryTitles();
                            $all_cats = array_merge($all_cats, $p_cats);
                            $data[$p->product_id . '-' . $plan->pk()]['rel'] = implode(', ', array_merge(['All'], $p_cats));
                        }
                        $exclude = array_map(function($el) {return $el->title;}, $_ = Am_Di::getInstance()->productCategoryTable->loadIds($this->getConfig('cat-filter-exclude', [])));
                        $all_cats = array_unique($all_cats);
                        $all_cats = array_diff($all_cats, $exclude);
                        sort($all_cats);
                        array_unshift($all_cats, 'All');
                        foreach ($all_cats as $t) {
                            $search[] = sprintf('<a href="javascript:;" data-title="%s" class="local-link am-brick-product-popup-cat">%s</a>', $t, $t);
                        }
                        $search = sprintf('<div class="am-brick-product-popup-cats">%s</div>', implode(' | ', $search));
                    }

                    $oel = $gr = $form->addGroup();
                    $gr->addStatic()
                        ->setContent(sprintf('<div id="%s-preview"></div>', $name));

                    $gr->addStatic()
                        ->setContent(sprintf('<div><a id="%s" class="local-link" href="javascript:;" data-title="%s">%s</a></div>',
                                $name, $this->___('Membership Type'),
                                $this->___('Add Membership')));
                    $gr->addStatic()
                        ->setContent(sprintf('<div id="%s-list" class="am-brick-product-popup" style="display:none">%s<div style="height:350px; overflow-y:scroll;" class="am-brick-product-popup-list">', $name, $search));
                    $el = $gr->addElement(new Am_Form_Element_SignupCheckboxGroup($name, $data, 'checkbox'));
                    $gr->addStatic()
                        ->setContent('</div></div>');
                } else {
                    $oel = $el = $form->addElement(new Am_Form_Element_SignupCheckboxGroup($name, $data, 'checkbox'));
                }

                break;
            case 'select':
                $oel = $el = $form->addSelect($name);
                foreach ($shortOptions as $pid => $label)
                    $el->addOption($label, $pid, empty($attrs[$pid]) ? null : $attrs[$pid]);
                break;
            case 'hidden':
            case 'advradio':
            default:
                $data = [];
                $first = 0;
                foreach ($options as $pid => $label) {
                    $data[$pid] = $attrs[$pid];
                    $data[$pid]['options'] = $dataOptions[$pid];
                    if (!$first++ && Am_Di::getInstance()->request->isGet() && !$this->getConfig('unselect')) // pre-check first option
                        $data[$pid]['options']['selected'] = true;
                }
                $oel = $el = $form->addElement(new Am_Form_Element_SignupCheckboxGroup($name, $data,
                            $inputType == 'advradio' ? 'radio' : $inputType));
                break;
        }

        $oel->setLabel($this->___('Membership Type'));
        if ($this->getConfig('no_label')) {
            $oel->setAttribute('class', 'am-no-label');
        }

        switch ($this->getConfig('require', self::REQUIRE_DEFAULT)) {
            case self::REQUIRE_DEFAULT :
                if (self::$bricksWhichCanBeRequiredAdded == 1)
                    $el->addRule('required', $this->___('Please choose a membership type'));
                break;
            case self::REQUIRE_ALWAYS :
                $el->addRule('required', $this->___('Please choose a membership type'));
                break;
            case self::REQUIRE_NEVER :
                break;
            case self::REQUIRE_ALTERNATE :
                if (self::$bricksAlternateAdded == 1) {
                    $f = $form;
                    while ($container = $f->getContainer())
                        $f = $container;

                    $f->addRule('callback2', $this->___('Please choose a membership type'), [$this, 'formValidate']);
                }
                break;
            default:
                throw new Am_Exception_InternalError('Unknown require type [%s] for product brick', $this->getConfig('require', self::REQUIRE_DEFAULT));
        }

        $d = Am_Di::getInstance()->hook->filter($this->getConfig('default'), Am_Event::SIGNUP_FORM_DEFAULT_PRODUCT);
        if ($d && $inputType != 'none' && $inputType != 'hidden') {
            $f = $form;
            while ($container = $f->getContainer()) {
                $f = $container;
            }
            $f->addDataSource(new HTML_QuickForm2_DataSource_Array([
                $name => ($inputType == 'checkbox' || $inputType == 'advradio') ? [$d] : $d
            ]));
        }
        foreach ($productOptions as $pid => $productOptions)
        {
            if ($productOptions)
            {
                $fs = $form->addElement('fieldset', '', [
                    'class' => 'am-product-options-' . $pid,
                    'data-parent' => $el ? $el->getName() : '',
                    'style' => $productOptionsDontHide ? '' : 'display:none;'
                ]);
                $this->insertProductOptions($fs, $pid, $productOptions, $billingPlans[$pid]);
            }
        }
    }

    public function initConfigForm(Am_Form $form)
    {
        $radio = $form->addSelect('type')
            ->setLabel(___('What to Display'));
        $radio->loadOptions([
            self::DISPLAY_ALL => ___('Display All Products'),
            self::DISPLAY_CATEGORY => ___('Products from selected Categories'),
            self::DISPLAY_PRODUCT => ___('Only Products selected below'),
            self::DISPLAY_BP => ___('Only Billing Plans selected below')
        ]);

        $groups = $form->addMagicSelect('groups', ['data-type' => self::DISPLAY_CATEGORY, 'class' => 'am-combobox-fixed'])
            ->setLabel(___('Product Gategories'));
        $groups->loadOptions(Am_Di::getInstance()->productCategoryTable->getAdminSelectOptions([ProductCategoryTable::COUNT => 1]));

        $products = $form->addSortableMagicSelect('products', ['data-type' => self::DISPLAY_PRODUCT, 'class' => 'am-combobox-fixed'])
            ->setLabel(___('Product(s) to display'));
        $products->loadOptions(Am_Di::getInstance()->productTable->getOptions(true));

        $bpOptions = [];
        foreach (Am_Di::getInstance()->productTable->getVisible() as $product) {
            /* @var $product Product */
            foreach ($product->getBillingOptions() as $bp_id => $title) {
                $bpOptions[$product->pk() . '-' . $bp_id] = sprintf('%s (%s)', $product->title, $title);
            }
        }

        $bps = $form->addSortableMagicSelect('bps', ['data-type' => self::DISPLAY_BP, 'class' => 'am-combobox-fixed'])->setLabel(___('Billing Plan(s) to display'));
        $bps->loadOptions($bpOptions);

        $form->addSelect('default', ['class' => 'am-combobox-fixed'])
            ->setLabel(___('Select by default'))
            ->loadOptions([''=>''] + $bpOptions);

        $inputType = $form->addSelect('input-type')->setLabel(___('Input Type'));
        $inputType->loadOptions([
            'advradio' => ___('Radio-buttons (one product can be selected)'),
            'select' => ___('Select-box (one product can be selected)'),
            'checkbox' => ___('Checkboxes (multiple products can be selected)'),
        ]);

        $form->addAdvCheckbox('display-popup')
            ->setlabel(___('Display Products in Popup'));
        $form->addAdvCheckbox('cat-filter')
            ->setlabel(___('Add Category Filter to Popup'));
        $form->addMagicSelect('cat-filter-exclude', ['class'=>'cat-filter-exclude'])
            ->setLabel(___('Exclude the following categories from Filter'))
            ->loadOptions(Am_Di::getInstance()->productCategoryTable->getOptions());

        $form->addSelect('display-type', ['style' => 'max-width:400px'])
            ->setLabel(___('If product is not available because of require/disallow settings'))
            ->loadOptions([
                'hide' => ___('Remove It From Signup Form'),
                'hide-always' => ___('Remove It From Signup Form Even if Condition can meet in Current Purchase'),
                'display' => ___('Display It Anyway')
            ]);

        $form->addAdvRadio('title_source')
            ->setLabel(___("Title Source\n" .
                "where to get title to represent product"))
            ->loadOptions([
                'product' => ___('Product'),
                'bp' => ___('Billing Plan')
            ]);

        $form->addCheckboxedGroup('empty-option')
            ->setLabel(___("Add an 'empty' option to select box\nto do not choose any products"))
            ->addText('empty-option-text');

        $form->addAdvCheckbox('unselect')
            ->setLabel(___("Do not pre select first option"));

        $form->addAdvCheckbox('hide_if_one')
            ->setLabel(___("Hide Select\n" .
                'if there is only one choice'));

        $form->addAdvRadio('require')
            ->setLabel(___('Require Behaviour'))
            ->loadOptions([
                self::REQUIRE_DEFAULT => sprintf('<strong>%s</strong>: %s', ___('Default'), ___('Make this Brick Required Only in Case There is not any Required Brick on Page Above It')),
                self::REQUIRE_ALWAYS => sprintf('<strong>%s</strong>: %s', ___('Always'), ___('Force User to Choose Some Product from this Brick')),
                self::REQUIRE_NEVER => sprintf('<strong>%s</strong>: %s', ___('Never'), ___('Products in this Brick is Optional (Not Required)')),
                self::REQUIRE_ALTERNATE => sprintf('<strong>%s</strong>: %s', ___('Alternate'), ___('User can Choose Product in any Brick of Such Type on Page but he Should Choose at least One Product still'))
            ])
            ->setValue(self::REQUIRE_DEFAULT);

        $script = <<<EOF
        jQuery(document).ready(function($) {
            // there can be multiple bricks like that :)
            if (!window.product_brick_hook_set)
            {
                window.product_brick_hook_set = true;
                jQuery(document).on('change',"select[name='type']", function (event){
                    var val = jQuery(event.target).val();
                    var frm = jQuery(event.target).closest("form");
                    jQuery("[data-type]", frm).closest(".am-row").hide();
                    jQuery("[data-type='"+val+"']", frm).closest(".am-row").show();
                })
                jQuery("select[name='type']").change();
                jQuery(document).on('change',"select[name='input-type']", function (event){
                    var val = jQuery(event.target).val();
                    var frm = jQuery(event.target).closest("form");
                    jQuery("input[name='display-popup']", frm).closest(".am-row").toggle(val == 'checkbox');
                    jQuery("input[name='empty-option']", frm).closest(".am-row").toggle(val == 'advradio' || val == 'select');
                    jQuery("input[name='unselect']", frm).closest(".am-row").toggle(val == 'advradio');
                })
                jQuery(document).on('change',"[name='display-popup']", function (event){
                    var frm = jQuery(event.target).closest("form");
                    jQuery("input[name='cat-filter']", frm).closest(".am-row").toggle(event.target.checked);
                })
                jQuery(document).on('change',"[name='cat-filter']", function (event){
                    var frm = jQuery(event.target).closest("form");
                    jQuery(".cat-filter-exclude", frm).closest(".am-row").toggle(event.target.checked);
                })
                jQuery("[name='cat-filter']").change();
                jQuery("select[name='input-type']").change();
                jQuery("[name='display-popup']").change();
            }
        });
EOF;
        $form->addAdvCheckbox('skip_if_empty')
            ->setLabel(___("Skip this block if there is not any purchase options"));
        $form->addScript()->setScript($script);

        $form->addAdvCheckbox('no_label')->setLabel(___('Remove Label'));
    }

    public function formValidate(array $values)
    {
        foreach ($values as $k => $v) {
            //product_id_90 exception for donation brick
            if (strpos($k, 'product_id') === 0 && strpos($k, 'product_id_90') === false) {
                if (!empty($v)) {
                    return;
                }
            }
        }

        return $this->___('Please choose a membership type');
    }

    public function isAcceptableForForm(Am_Form_Bricked $form)
    {
        return $form instanceof Am_Form_Signup;
    }

    public function isMultiple()
    {
        return true;
    }
}

class Am_Form_Brick_Paysystem extends Am_Form_Brick
{
    protected $labels = [
        'Payment System',
        'Please choose a payment system',
    ];
    protected $hide_if_one = false;
    protected $hideIfLoggedInPossible = self::HIDE_DONT;
    protected $_psHide;
    protected $_paysysIds;

    public function __construct($id = null, $config = null)
    {
        $this->name = ___('Payment System');
        parent::__construct($id, $config);
    }

    function renderPaysys(Am_Paysystem_Description $p)
    {
        return sprintf('<span class="am-paysystem-title" id="am-paysystem-%s-title">%s</span> <span class="am-paysystem-desc" id="am-paysystem-%s-desc">%s</span>',
            $p->getId(), ___($p->getTitle()),
            $p->getId(), ___($p->getDescription()));
    }

    public function getPaysystems()
    {
        $psList = Am_Di::getInstance()->paysystemList->getAllPublic();
        $_psList = [];
        foreach ($psList as $k => $ps) {
            $ps->title = ___($ps->title);
            $ps->description = ___($ps->description);
            $_psList[$ps->getId()] = $ps;
        }

        $psEnabled = $this->getConfig('paysystems', array_keys($_psList));
        $event = new Am_Event(Am_Event::SIGNUP_FORM_GET_PAYSYSTEMS);
        $event->setReturn($psEnabled);
        Am_Di::getInstance()->hook->call($event);
        $psEnabled = $event->getReturn();

        //we want same order of paysystems as in $psEnabled
        $ret = [];
        foreach ($psEnabled as $psId) {
            if (isset($_psList[$psId]))
                $ret[] = $_psList[$psId];
        }

        return $ret;
    }

    public function insertBrick(HTML_QuickForm2_Container $form)
    {
        $paysystems = $this->getPaysystems();
        if ((count($paysystems) == 1) && $this->getConfig('hide_if_one')) {
            reset($paysystems);
            $paysys_id = current($paysystems)->getId();
            $form->addHidden('paysys_id', ['data-paysys_id' => $paysys_id])->setValue($paysys_id)->toggleFrozen(true);
            return;
        }
        $psOptions = $psHide = $psIndex = [];
        foreach ($paysystems as $ps) {
            $psOptions[$ps->getId()] = $this->renderPaysys($ps);
            $psIndex[$ps->getId()] = $ps;
            $psHide[$ps->getId()] = Am_Di::getInstance()->plugins_payment->loadGet($ps->getId())->hideBricks();
        }
        $this->_psHide = $psHide;
        if (count($paysystems) != 1) {
            $el0 = $el = $form->addAdvRadio('paysys_id', ['id' => 'paysys_id'], ['intrinsic_validation' => false])
                ->setSeparator('');
            $first = 0;
            foreach ($psOptions as $k => $v) {
                $attrs = [
                    'data-recurring' => json_encode((bool)$psIndex[$k]->isRecurring())
                ];
                if (!$first++ && Am_Di::getInstance()->request->isGet() && !$this->getConfig('unselect'))
                    $attrs['checked'] = 'checked';
                $el->addOption($v, $k, $attrs);
            }
        } else {
            /** @todo display html here */
            reset($psOptions);
            $el = $form->addStatic('_paysys_id', ['id' => 'paysys_id'])->setContent(current($psOptions));
            $el->toggleFrozen(true);
            $el0 = $form->addHidden('paysys_id', ['data-paysys_id' => key($psOptions)])->setValue(key($psOptions));
        }
        $el0->addRule('required', $this->___('Please choose a payment system'),
            // the following is added to avoid client validation if select is hidden
            null, HTML_QuickForm2_Rule::SERVER);
        $el0->addFilter('filterId');
        $el->setLabel($this->___('Payment System'));
    }

    protected function jsData(array &$data)
    {
        $data['psHide'] = $this->_psHide; // hide bricks functionality is unused and disabled in JS until we need it
    }

    public function isAcceptableForForm(Am_Form_Bricked $form)
    {
        return $form instanceof Am_Form_Signup;
    }

    public function initConfigForm(Am_Form $form)
    {
        Am_Di::getInstance()->plugins_payment->loadEnabled();
        $ps = $form->addSortableMagicSelect('paysystems')
            ->setLabel(___("Payment Options\n" .
                'if none selected, all enabled will be displayed'))
            ->loadOptions(Am_Di::getInstance()->paysystemList->getOptionsPublic());
        $form->addAdvCheckbox('unselect')
            ->setLabel(___("Do not pre select first option"));
        $form->addAdvCheckbox('hide_if_one')
            ->setLabel(___("Hide Select\n" .
                'if there is only one choice'));
    }
}

class Am_Form_Brick_Recaptcha extends Am_Form_Brick
{
    protected $name = 'reCAPTCHA';
    protected $labels = [
        'Anti Spam',
        'Anti Spam check failed'
    ];
    /** @var HTML_QuickForm2_Element_Static */
    protected $static;

    public function initConfigForm(Am_Form $form)
    {
        $form->addSelect('theme')
            ->setLabel(___("reCAPTCHA Theme"))
            ->loadOptions(['light' => 'light', 'dark' => 'dark']);
        $form->addSelect('size')
            ->setLabel(___("reCAPTCHA Size"))
            ->loadOptions(['normal' => 'normal', 'compact' => 'compact']);
    }

    public function insertBrick(HTML_QuickForm2_Container $form)
    {
        $captcha = $form->addGroup(null, ['id' => 'grp-captcha'])
                ->setLabel($this->___("Anti Spam"));
        $captcha->addRule('callback', $this->___('Anti Spam check failed'), [$this, 'validate']);
        $this->static = $captcha->addStatic('captcha')->setContent(Am_Di::getInstance()->recaptcha
            ->render($this->getConfig('theme'), $this->getConfig('size')));
    }

    public static function createAvailableBricks($className)
    {
        return Am_Recaptcha::isConfigured() ?
            parent::createAvailableBricks($className) :
            [];
    }

    public function validate()
    {
        $form = $this->static;
        while ($np = $form->getContainer())
            $form = $np;

        foreach ($form->getDataSources() as $ds) {
            if ($resp = $ds->getValue('g-recaptcha-response'))
                break;
        }

        $status = false;
        if ($resp)
            $status = Am_Di::getInstance()->recaptcha->validate($resp);
        return $status;
    }
}

class Am_Form_Brick_Coupon extends Am_Form_Brick
{
    protected $labels = [
        'Enter coupon code',
        'No coupons found with such coupon code',
        'Please enter coupon code',
        'The coupon entered is not valid with any product(s) being purchased. No discount will be applied',
    ];
    protected $hideIfLoggedInPossible = self::HIDE_DESIRED;

    public function __construct($id = null, $config = null)
    {
        $this->name = ___('Coupon');
        parent::__construct($id, $config);
    }

    public function initConfigForm(Am_Form $form)
    {
        $form->addAdvCheckbox('required')
            ->setLabel(___('Required'));
        $form->addText('coupon_default')
            ->setLabel(___("Default Coupon\npre populate field with this code"));
        $form->addAdvCheckbox('no_show_error_zero_discount')
            ->setLabel(___('Do not Show error if correct coupon code but do not apply to any of selected products'));
    }

    public function insertBrick(HTML_QuickForm2_Container $form)
    {
        $di = Am_Di::getInstance();

        $coupon = $form->addText('coupon', [], [
                'no_show_error_zero_discount' => $this->getConfig('no_show_error_zero_discount'),
                'zero_discount_error' => $this->___('The coupon entered is not valid with any product(s) being purchased. No discount will be applied'),
            ])->setLabel($this->___('Enter coupon code'));
        $coupon->addFilter('trim');
        if ($this->getConfig('required')) {
            $coupon->addRule('required', $this->___('Please enter coupon code'));
        }
        $coupon->addRule('callback2', '--error--', [$this, 'validateCoupon'])
            ->addRule('remote', '--error--', [
                'url' => $di->url('ajax', ['do'=>'check_coupon'], false),
            ]);

        if (($code = $this->getConfig('coupon_default')) &&
            ($c = $di->couponTable->findFirstByCode($code)) &&
            !$c->validate($di->auth->getUserId())) {

            $f = $form;
            while ($container = $f->getContainer()) {
                $f = $container;
            }

            $f->addDataSource(new HTML_QuickForm2_DataSource_Array([
                'coupon' => $code
            ]));
        }
    }

    function validateCoupon($value)
    {
        if ($value == "")
            return null;
        $coupon = htmlentities($value);
        $coupon = Am_Di::getInstance()->couponTable->findFirstByCode($coupon);
        $msg = $coupon ? $coupon->validate(Am_Di::getInstance()->auth->getUserId()) : $this->___('No coupons found with such coupon code');
        return $msg === null ? null : $msg;
    }

    public function isAcceptableForForm(Am_Form_Bricked $form)
    {
        return $form instanceof Am_Form_Signup;
    }
}

class Am_Form_Brick_Field extends Am_Form_Brick
{
    const TYPE_NORMAL = 'normal';
    const TYPE_READONLY = 'disabled';
    const TYPE_HIDDEN = 'hidden';

    protected $field = null;

    use Am_Form_Brick_Conditional;

    static function createAvailableBricks($className)
    {
        $res = [];
        foreach (Am_Di::getInstance()->userTable->customFields()->getAll() as $field) {
            if (strpos($field->name, 'aff_') === 0)
                continue;

            // Do not create bricks for fields started with _
            if (strpos($field->name, '_') === 0)
                continue;

            $res[] = new self('field-' . $field->getName());
        }
        return $res;
    }

    public function __construct($id = null, $config = null)
    {
        $fieldName = str_replace('field-', '', $id);
        $this->field = Am_Di::getInstance()->userTable->customFields()->get($fieldName);
        // to make it fault-tolerant when customfield is deleted
        if (!$this->field)
            $this->field = new Am_CustomFieldText($fieldName, $fieldName);
        $this->labels = [$this->field->title, $this->field->description];
        if (in_array($this->field->type, ['text', 'textarea'])
            && isset($this->field->placeholder))
        {
            $this->labels[] = $this->field->placeholder;
        }
        parent::__construct($id, $config);
    }

    function getName()
    {
        return $this->field->title;
    }

    function insertBrick(HTML_QuickForm2_Container $form)
    {
        if (!$this->getConfig('skip_access_check') && isset($this->field->from_config) && $this->field->from_config) {
            $hasAccess = Am_Di::getInstance()->auth->getUserId() ?
                Am_Di::getInstance()->resourceAccessTable->userHasAccess(Am_Di::getInstance()->auth->getUser(), amstrtoint($this->field->name), Am_CustomField::ACCESS_TYPE) :
                Am_Di::getInstance()->resourceAccessTable->guestHasAccess(amstrtoint($this->field->name), Am_CustomField::ACCESS_TYPE);

            if (!$hasAccess)
                return;
        }

        $this->field->title = $this->___($this->field->title);
        $this->field->description = $this->___($this->field->description);
        if ($this->getConfig('validate_custom')) {
            $this->field->validateFunc = $this->getConfig('validate_func');
        }
        if ($this->getConfig('display_type', self::TYPE_NORMAL) == self::TYPE_READONLY) {
            $this->field->validateFunc = [];
        }
        switch ($this->getConfig('display_type', self::TYPE_NORMAL)) {
            case self::TYPE_HIDDEN :
                $v = $this->getConfig('value');
                $form->addHidden($this->field->getName())
                    ->setValue($v ? $v : @$this->field->default);
                break;
            case self::TYPE_READONLY :
                $el = $this->field->addToQF2($form);
                $el->toggleFrozen(true);
                break;
            case self::TYPE_NORMAL :
                $this->field->addToQF2($form, [], [],
                    $this->getConfig('cond_enabled') ?
                        HTML_QuickForm2_Rule::ONBLUR_CLIENT :
                        HTML_QuickForm2_Rule::CLIENT_SERVER);
                break;
            default:
                throw new Am_Exception_InternalError(sprintf('Unknown display type [%s] in %s::%s',
                        $this->getConfig('display_type', self::TYPE_NORMAL), __CLASS__, __METHOD__));
        }
        $name = $this->getFieldName();
        $sel = '[name=' . $name . '], [data-name="' . $name . '[]"], [name="' . $name . '[]"][type!=hidden]';
        $this->addConditionalIfNecessary($form, $sel, true);
    }

    function getFieldName()
    {
        return $this->field->name;
    }

    public function initConfigForm(Am_Form $form)
    {
        $id = $this->field->name . '-display-type';
        $id_value = $this->field->name . '-value';

        $form->addSelect('display_type')
            ->setLabel(___('Display Type'))
            ->setId($id)
            ->loadOptions([
                self::TYPE_NORMAL => ___('Normal'),
                self::TYPE_READONLY => ___('Read-only'),
                self::TYPE_HIDDEN => ___('Hidden')
            ]);
        $form->addText('value', [
                'placeholder' => ___('Keep empty to use default value from field settings'),
                'class' => 'am-el-wide'
        ])
            ->setId($id_value)
            ->setLabel(___("Default Value for this field\n" .
                'hidden field will be populated with this value'));

        $type_hidden = self::TYPE_HIDDEN;
        $form->addScript()
            ->setScript(<<<CUT
jQuery(function(){
    jQuery('#$id').change(function(){
        jQuery('#$id_value').closest('.am-row').toggle(jQuery(this).val() == '$type_hidden');
    }).change()
});
CUT
        );
        $id_validate_custom = $this->field->name . '-validate_custom';
        $id_validate_func = $this->field->name . '-validate_func';
        $form->addAdvCheckbox('validate_custom', ['id' => $id_validate_custom])
            ->setLabel(___("Use Custom Validation Settings\n" .
                'otherwise Validation settings from field definition is used'));

        $form->addMagicSelect('validate_func', ['id' => $id_validate_func])
            ->setLabel(___('Validation'))
            ->loadOptions([
                'required' => ___('Required Value'),
                'integer' => ___('Integer Value'),
                'numeric' => ___('Numeric Value'),
                'email' => ___('E-Mail Address'),
                'emails' => ___('List of E-Mail Address'),
                'url' => ___('URL'),
                'ip' => ___('IP Address')
            ]);
        $form->addScript()
            ->setScript(<<<CUT
jQuery(function(){
    jQuery('#$id_validate_custom').change(function(){
        jQuery('#$id_validate_func').closest('.am-row').toggle(this.checked);
    }).change()
});
CUT
        );

        $form->addAdvCheckbox('skip_access_check')
            ->setLabel(___("Do not check Access Permissions\n" .
                "for this field on this form (show it without any conditions)"));

        $this->addCondConfig($form);
    }

    public function setConfigArray(array $config)
    {
        //backwards compatiability
        if (isset($config['disabled'])) {
            $config['display_type'] = $config['disabled'] ? self::TYPE_READONLY : self::TYPE_NORMAL;
            unset($config['disabled']);
        }
        if (!isset($config['display_type'])) {
            $config['display_type'] = self::TYPE_NORMAL;
        }

        $this->_setConfigArray($config);
        parent::setConfigArray($config);
    }
}

class Am_Form_Brick_Agreement extends Am_Form_Brick
{
    static $bricksAdded = 0;
    static $containerId = 0;
    protected $labels = [
        'User Agreement',
        'I have read and agree to the %s',
        'Please agree to %s',
    ];

    function __construct($id = null, $config = null)
    {
        $this->name = ___('User Consent');
        parent::__construct($id, $config);
        $di = Am_Di::getInstance();
        $di->hook->add('gridSavedFormAfterSave', function(Am_Event_Grid $event) use ($di)
        {
            $form = $event->getGrid()->getRecord();
            $fields = $event->getGrid()->getRecord()->getFields();
            foreach ($fields as $k => $field)
            {
                if ($field['class'] == 'agreement' && @$field['config']['_agreement_text'])
                {
                    // Create agreement record;
                    $agreement = $di->agreementRecord;
                    $agreement->type = sprintf('agreement-%s-%s', $form->type, $form->code ?: 'default');
                    $agreement->title = ___('Terms of Use');
                    $agreement->body = "<pre>" . $field['config']['_agreement_text'] . "</pre>";
                    $agreement->is_current = 1;
                    $agreement->comment = ___('Created from Forms Editor');
                    $agreement->save();

                    unset($fields[$k]['config']['_agreement_text']);
                    $fields[$k]['config']['agreement_type'] = $agreement->type;
                }
            }
            $form->setFields($fields);
            $form->save();
        });
    }

    function init()
    {
        $di = Am_Di::getInstance();
        $type = $this->getConfig('agreement_type') ?: array_keys(Am_Di::getInstance()->agreementTable->getTypeOptions());
        if (empty($type)) return;

        $di->hook->add([Am_Event::SIGNUP_USER_ADDED, Am_Event::SIGNUP_USER_UPDATED], function(Am_Event $e) use ($di, $type)
        {
            $user = $e->getUser();
            $vars = $e->getVars();
            $type = is_array($type) ? $type : [$type];
            $given_consent = [];
            if (!empty($vars['_i_agree']) && is_array($vars['_i_agree']))
            {
                foreach ($vars['_i_agree'] as $value)
                {
                    $v = json_decode($value, true);
                    $given_consent = array_merge($given_consent, $v);
                }
            }
            if (!empty($given_consent))
            {
                foreach ($type as $t)
                {
                    if (in_array($t, $given_consent))
                    {
                        $di->userConsentTable->recordConsent(
                            $user, $t, $di->request->getClientIp(), sprintf("Signup Form: %s", $di->surl($e->getSavedForm()->getUrl())));
                    }
                }
            }
        });
    }

    function insertBrick(HTML_QuickForm2_Container $form)
    {
        $root = $form;
        while ($_ = $root->getContainer()) {
            $root = $_;
        }

        $di = Am_Di::getInstance();

        $type = $this->getConfig('agreement_type') ?: array_keys(Am_Di::getInstance()->agreementTable->getTypeOptions());
        if (empty($type)) return;

        $type = is_array($type) ? $type : [$type];
        foreach ($type as $k => $v)
        {
            if (!$this->getConfig('agree_invoice') && ($user = $di->auth->getUser()) && $di->userConsentTable->hasConsent($user, $v))
                unset($type[$k]);
        }

        if (empty($type)) return;

        $el_name = "_i_agree[{$form->getId()}-" . (self::$bricksAdded++) . "]";

        $agreements = $this->getAgreements();
        $labels = [];
        if ($this->getConfig('do_not_show_agreement_text') || $this->getConfig('do_not_show_caption'))
        {
            $container = $form;
        } else {
            $c_id = self::$containerId ? 'fieldset-agreement-' . self::$containerId : 'fieldset-agreement';
            self::$containerId++;
            $container = $form->addFieldset()
                ->setId($c_id)
                ->setLabel($this->getTitles($agreements));
        }

        foreach ($agreements as $agreement)
        {
            if (!$this->getConfig('do_not_show_agreement_text'))
            {
                if ($this->getConfig("is_popup")) {
                    $root->addEpilog('<div class="agreement" style="display:none" id="' . $agreement->type . '">' . $agreement->body . '</div>');
                } else {
                    $agr = $container->addStatic('_agreement', ['class' => 'am-no-label']);
                    $agr->setContent('<div class="agreement">' . $agreement->body . '</div>');
                }
            }
            $header = json_encode($agreement->title);

            if (!empty($agreement->url))
            {
                $url = $agreement->url;
                if ($this->getConfig("is_popup")) {
                    $attrs = Am_Di::getInstance()->view->attrs([
                        'href' => $url,
                        'class' => 'ajax-link',
                        'data-popup-width' => '400',
                        'data-popup-height' => '400',
                        'title' => $agreement->title,
                    ]);
                } else {
                    $attrs = Am_Di::getInstance()->view->attrs([
                        'href' => $url,
                        'title' => $agreement->title,
                        'target' => '_blank'
                    ]);
                }
            } else {
                $attrs = Am_Di::getInstance()->view->attrs([
                    'href' => "javascript:",
                    'class' => 'local-link',
                    'onclick' => 'jQuery("#' . $agreement->type . '").amPopup({width:400, title:' . $header . '});'
                ]);
            }
            $labels[] = ($this->getConfig('is_popup') || !empty($agreement->url)) ? "<a {$attrs}>" . $agreement->title . "</a>" : $agreement->title;
        }

        $data = [];
        $label = $this->___('I have read and agree to the %s', implode(", ", $labels));

        if ($this->getConfig('no_label')) {
            $data['content'] = $label;
        }

        $checkbox = $container->addAdvCheckbox($el_name, ['value' => json_encode($type)], $data);

        if (!$this->getConfig('no_label')) {
            $checkbox->setLabel($label);
        }

        if (!$this->getConfig('not_required')) {
            $checkbox->addRule('required', $this->___('Please agree to %s', $this->getTitles($agreements)));
        }

        if ($this->getConfig('checked'))
        {
            $checkbox->setAttribute('checked');
        }
    }

    function getTitles($agreements)
    {
        return implode(", ", array_map(function($agreement) {return $agreement->title;}, $agreements));
    }

    function getAgreements()
    {
        $type = $this->getConfig('agreement_type') ?: array_keys(Am_Di::getInstance()->agreementTable->getTypeOptions());
        $type = is_array($type) ? $type : [$type];
        $ret = [];
        foreach ($type as $t)
        {
            $ret[] = Am_Di::getInstance()->agreementTable->getCurrentByType($t);
        }
        return array_filter($ret);
    }

    function isAcceptableForForm(Am_Form_Bricked $form)
    {
        return $form instanceof Am_Form_Signup;
    }

    function initConfigForm(Am_Form $form)
    {
        $form->addAdvCheckbox("do_not_show_agreement_text")
            ->setLabel(___("Hide Agreement Text\n" .
                    'display only tick box'))
            ->setId('do-not-show-agreement-text');
        $form->addAdvCheckbox("do_not_show_caption")
            ->setLabel(___('Hide Caption'))
            ->setAttribute('rel', 'agreement-text');

        $gr = $form->addGroup()->setLabel(___("Agreement Document\nkeep empty to use all"));
        if (!Am_Di::getInstance()->agreementTable->getTypeOptions()) {
            $gr->addTextarea("_agreement_text", ['rows' => 20, 'class' => 'am-el-wide']);
            $gr->addHidden('agreement_type');
        } else {
            $gr->addMagicSelect("agreement_type")
                ->loadOptions(Am_Di::getInstance()->agreementTable->getTypeOptions());
        }

        $url = Am_Di::getInstance()->url('admin-agreement');

        $linkTitle = ___('Create New Document / Manage Documents');

        $gr->addHtml()->setHtml(<<<CUT
            <br/>
            <a href="{$url}" target='_top'>{$linkTitle}</a>
CUT
        );

        $form->addAdvCheckbox("is_popup")
            ->setLabel(___('Display Agreement in Popup'))
            ->setAttribute('rel', 'agreement-text');

        $form->addScript()
            ->setScript(<<<CUT
jQuery('#do-not-show-agreement-text').change(function(){
    jQuery('[rel=agreement-text]').closest('.am-row').toggle(!this.checked);
}).change();
CUT
        );
        $form->addAdvCheckbox('no_label')
            ->setLabel(___("Move Label to Tickbox"));
        $form->addAdvCheckbox('checked')
            ->setLabel(___("Checked by Default"));
        $form->addAdvCheckbox("agree_invoice")
            ->setLabel(___("User should agree each time when use form\n" .
                    "agreement state will be recorded to invoice instead of user"));
        $form->addAdvCheckbox("not_required")
            ->setLabel(___("Agreement is optional\nuser can continue without give consent"));
    }

    function isMultiple()
    {
        return true;
    }
}

class Am_Form_Brick_PageSeparator extends Am_Form_Brick
{
    protected $labels = [
        'title',
        'back',
        'next',
    ];
    protected $hideIfLoggedInPossible = self::HIDE_DONT;

    public function __construct($id = null, $config = null)
    {
        $this->name = ___('Form Page Break');
        parent::__construct($id, $config);
    }

    public function insertBrick(HTML_QuickForm2_Container $form)
    {
        // nop;
    }

    public function isAcceptableForForm(Am_Form_Bricked $form)
    {
        return (bool)$form->isMultiPage();
    }

    public function isMultiple()
    {
        return true;
    }
}

class Am_Form_Brick_UserGroup extends Am_Form_Brick
{
    protected $hideIfLoggedInPossible = self::HIDE_DONT;

    public function init()
    {
        Am_Di::getInstance()->hook->add(Am_Event::SIGNUP_USER_ADDED, [$this, 'assignGroups']);
        Am_Di::getInstance()->hook->add(Am_Event::SIGNUP_USER_UPDATED, [$this, 'assignGroups']);
    }

    public function assignGroups(Am_Event $event)
    {
        /* @var $user User */
        $user = $event->getUser();

        $existing = $user->getGroups();
        $new = $this->getConfig('groups', []);
        $user->setGroups(array_unique(array_merge($existing, $new)));
    }

    public function __construct($id = null, $config = null)
    {
        $this->name = ___('Assign User Groups (HIDDEN)');
        parent::__construct($id, $config);
    }

    public function isAcceptableForForm(Am_Form_Bricked $form)
    {
        return $form instanceof Am_Form_Signup;
    }

    public function initConfigForm(Am_Form $form)
    {
        $form->addMagicSelect('groups')
            ->loadOptions(Am_Di::getInstance()->userGroupTable->getOptions())
            ->setLabel(___('Add user to these groups'));
    }

    public function insertBrick(HTML_QuickForm2_Container $form)
    {
        // nothing to do.
    }
}

class Am_Form_Brick_UserGroups extends Am_Form_Brick
{
    protected $hideIfLoggedInPossible = self::HIDE_DONT;
    protected $labels = ['User Groups'];

    public function init()
    {
        if ($this->getGroups()) {
            Am_Di::getInstance()->hook->add([
                Am_Event::SIGNUP_USER_ADDED,
                Am_Event::SIGNUP_USER_UPDATED,
                Am_Event::PROFILE_USER_UPDATED
            ], [$this, 'assignGroups']);
        }
    }

    public function assignGroups(Am_Event $event)
    {
        /* @var $user User */
        $user = $event->getUser();
        $vars = $event->getVars();

        $existing = $user->getGroups();
        $scope = $this->getConfig('scope') ?: array_keys(Am_Di::getInstance()->userGroupTable->getOptions());

        $existing = array_diff($existing, $scope);
        $user->setGroups(array_unique(array_merge($existing, $vars['_user_groups'])));
    }

    public function __construct($id = null, $config = null)
    {
        $this->name = ___('User Groups');
        parent::__construct($id, $config);
    }

    public function initConfigForm(Am_Form $form)
    {
        $form->addMagicSelect('scope')
            ->loadOptions(Am_Di::getInstance()->userGroupTable->getOptions())
            ->setLabel(___("User Groups\nuser can opt-in to selected groups,\n keep empty to make all user groups selectable"));
        $form->addAdvCheckbox('disabled')->setLabel(___('Read-only'));
    }

    public function insertBrick(HTML_QuickForm2_Container $form)
    {
        if ($op = $this->getGroups()) {
            $el  = $form->addMagicSelect('_user_groups')
                ->loadOptions($op)
                ->setLabel($this->___('User Groups'));
            if($this->getConfig('disabled'))
            {
                $el->toggleFrozen(true);
            }

            if ($user = Am_Di::getInstance()->auth->getUser()) {
                $form->addDataSource(new HTML_QuickForm2_DataSource_Array([
                        '_user_groups' => $user->getGroups()
                ]));
            }
        }
    }

    function getGroups()
    {
        return Am_Di::getInstance()->db->selectCol(<<<CUT
            SELECT title, user_group_id AS ARRAY_KEY FROM ?_user_group
                WHERE 1=1 {AND user_group_id IN (?a)}
CUT
            , $this->getConfig('scope') ?: DBSIMPLE_SKIP);
    }
}

class Am_Form_Brick_ManualAccess extends Am_Form_Brick
{
    protected $hideIfLoggedInPossible = self::HIDE_DONT;

    public function init()
    {
        Am_Di::getInstance()->hook->add(Am_Event::SIGNUP_USER_ADDED, [$this, 'addAccess']);
    }

    public function addAccess(Am_Event $event)
    {
        /* @var $user User */
        $user = $event->getUser();
        $product_ids = $this->getConfig('product_ids');
        if (!$product_ids) return;
        foreach ($product_ids as $id) {
            $product = Am_Di::getInstance()->productTable->load($id, false);
            if (!$product) continue;

            //calculate access dates
            $invoice = Am_Di::getInstance()->invoiceRecord;
            $invoice->setUser($user);
            $invoice->add($product);

            $begin_date = $product->calculateStartDate(Am_Di::getInstance()->sqlDate, $invoice);
            $p = new Am_Period($product->getBillingPlan()->first_period);
            $expire_date = $p->addTo($begin_date);

            $access = Am_Di::getInstance()->accessRecord;
            $access->setForInsert([
                'user_id' => $user->pk(),
                'product_id' => $product->pk(),
                'begin_date' => $begin_date,
                'expire_date' => $expire_date,
                'qty' => 1
            ]);
            $access->insert();
            Am_Di::getInstance()->emailTemplateTable->sendZeroAutoresponders($user, $access);
        }
    }

    public function __construct($id = null, $config = null)
    {
        $this->name = ___('Add Subscription Before Payment (HIDDEN)');
        parent::__construct($id, $config);
    }

    public function isAcceptableForForm(Am_Form_Bricked $form)
    {
        return $form instanceof Am_Form_Signup;
    }

    public function initConfigForm(Am_Form $form)
    {
        $form->addMagicSelect('product_ids')
            ->loadOptions(Am_Di::getInstance()->productTable->getOptions())
            ->setLabel(___(
                "Add Subscription to the following products\n" .
                "right after signup form has been submitted, " .
                "subscription will be added only for new users"));
    }

    public function insertBrick(HTML_QuickForm2_Container $form)
    {
        // nothing to do.
    }
}

class Am_Form_Brick_Fieldset extends Am_Form_Brick
{
    static $counter = 0;

    use Am_Form_Brick_Conditional;

    protected $labels = [
        'Fieldset title'
    ];

    public function __construct($id = null, $config = null)
    {
        $this->name = ___('Fieldset');
        parent::__construct($id, $config);
    }

    public function insertBrick(HTML_QuickForm2_Container $form)
    {
        self::$counter++;
        $id = 'fieldset-brick-' . self::$counter;
        $fs = $form->addFieldset(null, ['class' => $this->getConfig('class')])
            ->setLabel($this->___('Fieldset title'))
            ->setId($id);
        $this->addConditionalIfNecessary($form, "#{$id}");
        return $fs;
    }

    public function initConfigForm(Am_Form $form)
    {
        $form->addText('class', ['class' => 'am-el-wide'])
            ->setLabel("CSS Classes\nfor fieldset element");
        $this->addCondConfig($form);
    }

    public function isMultiple()
    {
        return true;
    }
}

class Am_Form_Brick_RandomQuestions extends Am_Form_Brick
{
    protected $labels = [
        'Please answer above question',
        'Your answer is wrong'
    ];

    public function __construct($id = null, $config = null)
    {
        $this->name = ___('Random Questions');
        parent::__construct($id, $config);
    }

    public function isMultiple()
    {
        return false;
    }

    public function insertBrick(HTML_QuickForm2_Container $form)
    {
        if (!$this->getConfig('questions'))
            return;
        $questions = [];
        foreach (explode(PHP_EOL, $this->getConfig('questions')) as $line) {
            $line = explode('|', $line);
            $questions[] = array_shift($line);
        }
        $q_id = array_rand($questions);
        $question = $form->addText('question')
            ->setLabel($questions[$q_id] . "\n" . $this->___('Please answer above question'));
        $question->addRule('callback', $this->___('Your answer is wrong'), [$this, 'validate']);
        $form->addHidden('q_id')->setValue($q_id)->toggleFrozen(true);
        //setValue does not work right second time
        $_POST['q_id_sent'] = @$_POST['q_id'];
        $_POST['q_id'] = $q_id;
    }

    public function initConfigForm(Am_Form $form)
    {
        $form->addTextarea('questions', ['rows' => 10, 'class'=>'am-el-wide'])
            ->setLabel(___("Questions with possible answers\n" .
                "one question per line\n" .
                "question and answers should be\n" .
                "separated by pipe, for example\n" .
                "Question1?|Answer1|Answer2|Answer3\n" .
                "Question2?|Answer1|Answer2\n" .
                "register of answers does not matter"));
    }

    public function validate($answer)
    {
        if (!$answer)
            return false;
        $lines = explode(PHP_EOL, $this->getConfig('questions'));
        $line = $lines[(isset($_POST['q_id_sent']) ? $_POST['q_id_sent'] : $_POST['q_id'])];
        $q_ = explode('|', strtolower(trim($line)));
        array_shift($q_);
        if (@in_array(strtolower($answer), $q_))
            return true;
        else
            return false;
    }
}

class Am_Form_Brick_Unsubscribe extends Am_Form_Brick
{
    protected $labels = [
        'Unsubscribe from all e-mail messages'
    ];

    public function init()
    {
        Am_Di::getInstance()->hook->add(Am_Event::PROFILE_USER_UPDATED, [$this, 'triggerEvent']);
    }

    public function insertBrick(HTML_QuickForm2_Container $form)
    {
        $form->addAdvCheckbox('unsubscribed')
            ->setLabel($this->___('Unsubscribe from all e-mail messages'));
    }

    public function isAcceptableForForm(Am_Form_Bricked $form)
    {
        return $form instanceof Am_Form_Profile;
    }

    public function triggerEvent(Am_Event $e)
    {
        $oldUser = $e->getOldUser();
        $user = $e->getUser();
        if ($oldUser->unsubscribed != $user->unsubscribed) {
            Am_Di::getInstance()->hook->call(Am_Event::USER_UNSUBSCRIBED_CHANGED,
                ['user'=>$user, 'unsubscribed' => $user->unsubscribed]);
        }
    }
}

class Am_Form_Brick_InvoiceSummary extends Am_Form_Brick
{
    protected $hideIfLoggedInPossible = self::HIDE_DONT;
    protected $_out;
    protected $_selector;

    public function __construct($id = null, $config = null)
    {
        $this->name = ___('Invoice Summary');
        parent::__construct($id, $config);
    }

    public function initConfigForm(Am_Form $form)
    {
        $form->addSelect('position', ['class' => 'invoice-summary-position'])
            ->loadOptions([
                'above' => ___('Above Form'),
                'below' => ___('Below Form'),
                'brick' => ___('Brick Position'),
                'custom' => ___('Custom Element')
            ])->setLabel('Position');
        $form->addText('selector', ['placeholder' => '#invoice-summary'])
            ->setLabel(___('CSS Selector for container'));
        $form->addScript()
            ->setScript(<<<CUT
jQuery(document).on('change', '.invoice-summary-position', function() {
   jQuery(this).closest('form').find('[name=selector]').closest('.am-row').toggle(jQuery(this).val() == 'custom');
});
jQuery(function(){
    jQuery('.invoice-summary-position').change();
})
CUT
            );
        $form->addAdvCheckbox('show_terms')
            ->setLabel(___('Display Subscription Terms'));
    }

    public function insertBrick(HTML_QuickForm2_Container $form)
    {
        $root = $form;
        while ($_ = $root->getContainer()) {
            $root = $_;
        }

        $out = '<div class="invoice-summary" data-show-terms="' . ($this->getConfig('show_terms') ? 'true' : 'false') .'"></div>';

        switch ($this->getConfig('position', 'above')) {
            case 'above' :
                $root->addProlog($out);
                break;
            case 'below' :
                $root->addEpilog($out);
                break;
            case 'brick' :
                $form->addHtml(null, ['class'=>'am-row-wide'])
                    ->setHtml($out);
                break;
            default:
                $this->_selector =  $this->getConfig('selector', '#invoice-summary') ?: '#invoice-summary';
                $this->_out = $out; // will be added from brick js
        }
    }

    protected function jsData(array &$data)
    {
        $data['url'] = Am_Di::getInstance()->url('ajax/invoice-summary', false);
        $data['out'] = $this->_out;
        $data['selector'] = $this->_selector;
    }

    public function isAcceptableForForm(Am_Form_Bricked $form)
    {
        return $form instanceof Am_Form_Signup;
    }

    public function isMultiple()
    {
        return true;
    }
}

class Am_Form_Brick_VatId extends Am_Form_Brick
{
    use Am_Form_Brick_Conditional;

    protected $labels = [
        'VAT Settings are incorrect - no Vat Id configured',
        'Invalid VAT Id, please try again',
        'Cannot validate VAT Id, please try again',
        'Invalid EU VAT Id format',
        'EU VAT Id (optional)',
        'Please enter EU VAT Id'
    ];

    protected function isVatEnabled()
    {
        foreach (Am_Di::getInstance()->plugins_tax->getAllEnabled() as $_) {
            if ($_ instanceof Am_Invoice_Tax_Vat2015) {
                return true;
            }
        }

        return false;
    }

    function isAcceptableForForm(Am_Form_Bricked $form)
    {
        return $this->isVatEnabled();
    }

    public function initConfigForm(Am_Form $form)
    {
        $form->addAdvCheckbox('dont_validate')->setLabel(___('Disable online VAT Id Validation'));
        $form->addAdvCheckbox('required')->setLabel(___('Required'));

        $this->addCondConfig($form);
    }

    public function insertBrick(HTML_QuickForm2_Container $form)
    {
        if (!$this->isVatEnabled())
            return;

        $el = $form->addText('tax_id')
            ->setLabel($this->___("EU VAT Id (optional)"));

        $el->addFilter(function($value) {
                return str_replace(' ', '', $value);
            });
        $el->addRule('regex', $this->___('Invalid EU VAT Id format'), '/^[A-Za-z]{2}[a-zA-Z0-9\s]+$/');
        if (!$this->getConfig('dont_validate'))
            $el->addRule('callback2', '-error-', [$this, 'validate']);
        if ($this->getConfig('required')) {
            $el->addRule(
                'required',
                $this->___("Please enter EU VAT Id"),
                null,
                $this->getConfig('cond_enabled') ?
                    HTML_QuickForm2_Rule::ONBLUR_CLIENT :
                    HTML_QuickForm2_Rule::CLIENT_SERVER
            );
        }
        $this->addConditionalIfNecessary($form, '[name=tax_id]', true);
    }

    public function validate($id)
    {
        if (!$id) return; //skip validation in case of VAT was not supplied

        $plugins = Am_Di::getInstance()->plugins_tax->getAllEnabled();
        $me = is_array($plugins) ? $plugins[0]->getConfig('my_id') : "";
        if (!$me) return $this->___('VAT Settings are incorrect - no Vat Id configured');

        $cacheKey = 'vc_' . preg_replace('/[^A-Z0-9a-z_]/', '_', $me) . '_' .
            preg_replace('/[^A-Z0-9a-z_]/', '_', $id);

        if (($ret = Am_Di::getInstance()->cache->load($cacheKey)) !== false) {
            return $ret === 1 ? null : $this->___('Invalid VAT Id, please try again');
        }

        $country = strtoupper(substr($id, 0, 2));
        $number = substr($id, 2);
        $request = new Am_HttpRequest('http://ec.europa.eu/taxation_customs/vies/services/checkVatService', Am_HttpRequest::METHOD_POST);
        $request->setBody(<<<CUT
<s11:Envelope xmlns:s11='http://schemas.xmlsoap.org/soap/envelope/'>
<s11:Body>
  <tns1:checkVat xmlns:tns1='urn:ec.europa.eu:taxud:vies:services:checkVat:types'>
    <tns1:countryCode>{$country}</tns1:countryCode>
    <tns1:vatNumber>{$number}</tns1:vatNumber>
  </tns1:checkVat>
</s11:Body>
</s11:Envelope>
CUT
            );

        $resp = $request->send();

        if ($resp->getStatus() != 200) {
            return $this->___("Cannot validate VAT Id, please try again");
        }

        $xml = simplexml_load_string($resp->getBody());

        if ($xml === false) {
            return $this->___("Cannot validate VAT Id, please try again");
        }

        if (($res = $xml->xpath("//*[local-name()='checkVatResponse']/*[local-name()='valid']"))
            && strval($res[0]) == 'true') {

            Am_Di::getInstance()->cache->save(1, $cacheKey);
            return;
        }

        Am_Di::getInstance()->cache->save(0, $cacheKey);
        return $this->___('Invalid VAT Id, please try again');
    }
}

class Am_Form_Brick_CreditCardToken extends Am_Form_Brick
{
    /**
     * @var Am_Paysystem_CreditCard_Token $plugin
     */
    protected $plugin;
    protected $hideIfLoggedInPossible = self::HIDE_DONT;

    static function createCreditCardBrick(Am_Paysystem_CreditCard_Token $paysystem)
    {
        $brick = new self('brick-' . $paysystem->getId());
        $brick->setPaysystem($paysystem);
        return $brick;
    }

    function setPaysystem(Am_Paysystem_CreditCard_Token $paysystem)
    {
        $this->plugin = $paysystem;
    }

    static function createAvailableBricks($className)
    {
        $ret = [];
        foreach (Am_Di::getInstance()->plugins_payment->loadEnabled()->getAllEnabled() as $plugin) {
            if(($plugin instanceof Am_Paysystem_CreditCard_Token) && $plugin->isCCBrickSupported())
                $ret[] = self::createCreditCardBrick($plugin);
        }
        return $ret;
    }

    function getName()
    {
        return ___('%s Credit Card', $this->plugin->getTitle());
    }

    function getPlugin()
    {
        [,$paysys_id] = explode("brick-", $this->getId());
        return Am_Di::getInstance()->plugins_payment->loadGet($paysys_id);
    }

    function isPluginEnabled()
    {
        [,$paysys_id] = explode("brick-", $this->getId());
        return Am_Di::getInstance()->plugins_payment->isEnabled($paysys_id);
    }

    function insertBrick(HTML_QuickForm2_Container $form)
    {
        if(!$this->isPluginEnabled())
            return;

        if ($form instanceof Am_Form_CreditCard_Token) {
            return $this->getPlugin()->insertPaymentFormBrick($form);
        } else {
            if(($form instanceof Am_Form_Profile) && !$this->getPlugin()->findInvoiceForTokenUpdate(Am_Di::getInstance()->auth->getUserId())){
                return;
            }
            return $this->getPlugin()->insertUpdateFormBrick($form);
        }
    }

    function isAcceptableForForm(Am_Form_Bricked $form)
    {
        return true;
    }

    function hideIfLoggedIn()
    {
        return false;
    }

    function hideIfLoggedInPossible()
    {
        return self::HIDE_DONT;
    }
}
