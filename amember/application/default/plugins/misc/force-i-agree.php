<?php

/**
 * This Plugin force user to accept your Agreement if user did not do it before.
 * Also you can reset Accept status for all users in database if you changed your agreement
 * and you want that all users accept new one.
 * @am_plugin_api 6.0
 */
class Am_Plugin_ForceIAgree extends Am_Plugin
{
    const PLUGIN_STATUS = self::STATUS_PRODUCTION;
    const PLUGIN_COMM = self::COMM_FREE;
    const PLUGIN_REVISION = '6.3.6';

    protected $_configPrefix = 'misc.';

    static function getDbXml()
    {
        return <<<CUT
<schema version="4.0.0">
    <table name="user">
        <field name="require_consent" type="varchar" len='255' notnull="0" />
    </table>
    <table name="agreement">
        <field name="rules" type="text" />
    </table>
</schema>
CUT;
    }

    function init()
    {
        $this->getDi()->front->registerPlugin(new Am_Mvc_Controller_Plugin_ForceIAgree($this));
    }

    function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addProlog(sprintf('<div class="info">
            <a href="%s" class="link">%s</a></div>', $link = $this->getDi()->url("admin-force-i-agree"),
            ___('Request to update consent for Users')
        ));

        $form->setTitle(___('Update User Consent'));

        $form->addText('redirect_url', ['class' => 'am-el-wide', 'placeholder' => ROOT_URL])
            ->setLabel(___("Redirect URL\n" .
                'aMember will redirect user to this url after user click Accept ' .
                'button. User will be redirected to aMember root url in case of ' .
                'this option is empty'));

        $form->addText('page_title', ['class' => 'am-el-wide'])
            ->setLabel(___('Page Title'))
            ->setValue('New Terms & Conditions');

        $form->addHtmleditor('msg', null, ['showInPopup' => true])
            ->setLabel(___("Message\n" .
                'This message will be shown on accept page. ' .
                'You can clarify situation for user here '
            ))
            ->setValue('<div class="am-info">' . ___('We updated our Terms & Conditions. Please accept new one.') . '</div>' . '%button%');

        $form->addText('button_title')
            ->setLabel(___('Button Title'))
            ->setValue('I Accept');

        $form->addHTML()->setHTML(<<<CUT
Please use <a href='{$link}'>this link</a>. To configure who need to update consent.
CUT
        )->setLabel(___('Request User to Update Consent'));
    }

    function onAuthGetOkRedirect(Am_Event $e)
    {
        if ($this->needShow($e->getUser())) {
            $url = $e->getReturn();
            $this->getDi()->session->ns($this->getId())->redirect = $url;
            $e->setReturn($this->getDi()->url("misc/{$this->getId()}"));
            $e->stop();
        }
    }

    function onGridUserInitGrid(Am_Event_Grid $e)
    {
        $e->getGrid()->actionAdd(new Am_Grid_Action_Group_ResetIAgreeStatus);
    }

    function onGridAgreementInitGrid(Am_Event_Grid $event)
    {
        $grid = $event->getGrid();
        $grid->addField('rules', ___('Require Active Consent'))->setRenderFunction(function (Am_Record $record)
        {
            return sprintf("<td>%s</td>", implode(", ", json_decode($record->rules ?: '{}', true)));
        });

        $grid->addCallback(Am_Grid_Editable::CB_VALUES_FROM_FORM, function (& $values, Am_Record $record)
        {
            $record->rules = json_encode($values['_rules']);
            unset($values['_rules']);
        });

        $grid->addCallback(Am_Grid_Editable::CB_VALUES_TO_FORM, function (&$values, Am_Record $record)
        {
            $values['_rules'] = json_decode($record->rules ?: '{}', true);
        });

        $form = $grid->createForm();
        $poptions = $cat_options = [];
        foreach ($this->getDi()->productTable->getOptions() as $pid => $ptitle) {
            $poptions["PRODUCT-" . $pid] = $ptitle;
        }
        foreach ($this->getDi()->productCategoryTable->getOptions() as $cat_id => $cat_title) {
            $cat_options["CATEGORY-" . $cat_id] = $cat_title;
        }

        $rules = [
            'USER-ACTIVE' => 'Any Active User',
            'USER-AFFILIATE' => 'User is affiliate',
            'User have active subscription to a product' => $poptions,
            'User have active subscription to a product category' => $cat_options
        ];
        if ($this->getDi()->modules->isEnabled('subusers')) {
            $rules['USER-SUBUSER'] = 'User is subuser';
        }
        $form->addMagicSelect("_rules")->setLabel(___("Require Active Consent
        Depends on selected rules, user must have active consent to current document type
        If user doesn't have active consent to current document,
        it will be asked when user login"))
            ->loadOptions($rules);
    }

    function directAction(Am_Mvc_Request $request, Am_Mvc_Response $response, array $invokeArgs)
    {
        $this->getDi()->auth->requireLogin();
        $user = $this->getDi()->auth->getUser();
        $redirect = $this->getDi()->session->ns($this->getId())->redirect ?:
            $this->getConfig('redirect_url', $this->getDi()->surl('', false));
        $documents = [];
        $consent = $this->getMissingConsent($user);
        if (empty($consent)) {
            return $response->redirectLocation($redirect);
        }
        else {
            foreach ($consent as $type) {
                if ((!$this->getDi()->userConsentTable->hasConsent($user,
                        $type)) && ($document = $this->getDi()->agreementTable->getCurrentByType($type))) {
                    $documents[] = $document;
                }
            }
        }

        if (empty($documents)) {
            $user->updateQuick('require_consent', null);
            return $response->redirectLocation($redirect);
        }
        $form = new Am_Form();

        foreach ($documents as $agreement) {
            if (empty($agreement->url)) {
                $el = $form->addStatic($agreement->type, ['class' => 'am-no-label']);
                $el->setContent('<div class="agreement">' . $agreement->body . '</div>');
            }
            $form->addAdvCheckbox('i_agree[' . $agreement->type . ']')
                ->setLabel(___(
                    'Please Read and Accept  "%s%s%s"',
                    !empty($agreement->url) ? "<a href='{$agreement->url}' target='_blank'>" : "", $agreement->title,
                    !empty($agreement->url) ? "</a>" : ""
                ))
                ->addRule('required');
        }
        $form->setDataSources([$request]);
        $form->addSaveButton(___($this->getConfig('button_title', 'Submit')));


        if ($form->isSubmitted() && $form->validate()) {
            $vars = $form->getValue();
            foreach ($vars['i_agree'] as $type => $accepted) {
                $this->getDi()->userConsentTable->recordConsent($user, $type, $request->getClientIp(),
                    ___('Accepted from force-i-agree plugin'));
            }
            $user->updateQuick('require_consent', null);
            $response->redirectLocation($redirect);
        } else {
            $view = $this->getDi()->view;
            $view->layoutNoMenu = true;
            $view->title = ___($this->getConfig('page_title'));

            $tmp = new Am_SimpleTemplate();
            $tmp->assign('button', (string)$form);

            $view->content = $tmp->render($this->getConfig('msg', '%button%'));
            $view->display('layout.phtml');
        }
    }

    function getProductIds($options)
    {
        return $this->getDi()->productTable->extractProductIds($options);
    }

    function getMissingConsent(User $user)
    {
        $require_consent = [];
        foreach ($this->getDi()->agreementTable->getTypes() as $type) {
            $agreement = $this->getDi()->agreementTable->getCurrentByType($type);
            if (!empty($agreement->rules)) {
                $r = json_decode($agreement->rules ?: '{}', true);
                foreach ($r as $v) {
                    list($rule, $key) = explode("-", $v);
                    switch ($rule) {
                        case 'USER' :
                            if (
                                ($key == 'ACTIVE' && $user->status == 1) ||
                                ($key == 'AFFILIATE' && $user->get('is_affiliate') > 0) ||
                                ($key == 'SUBUSER' && $user->get('subusers_parent_id'))
                            ) {
                                $require_consent[] = $type;
                                continue 2;
                            }
                            break;
                        case 'PRODUCT' :
                            if (in_array($key, $user->getActiveProductIds())) {
                                $require_consent[] = $type;
                                continue 2;
                            }
                            break;
                        case 'CATEGORY' :
                            $cat = $user->getActiveCategoriesExpiration();
                            if (isset($cat[$key])) {
                                $require_consent[] = $type;
                                continue 2;
                            }
                            break;
                    }
                }
            }
        }

        if ($rc = $user->require_consent) {
            $rc = json_decode($rc, true);
            $require_consent = array_merge($require_consent, $rc);
        }

        array_filter($require_consent);
        $consent = [];
        if (!empty($require_consent)) {
            foreach ((array)$require_consent as $type) {
                if (!$this->getDi()->userConsentTable->hasConsent($user, $type)) {
                    $consent[] = $type;
                }
            }
        }
        return array_filter($consent);
    }

    function needShow(User $user)
    {
        $consent = $this->getMissingConsent($user);

        if (empty($consent) && $user->require_consent) {
            $user->updateQuick('require_consent', null);
        }
        return !empty($consent);
    }
}

class Am_Grid_Action_Group_ResetIAgreeStatus extends Am_Grid_Action_Group_Abstract
{
    var $title = 'Request User Consent update for "Terms & Policy" Documents';
    protected $needConfirmation = true;

    public
    function renderConfirmationForm(
        $btn = null,
        $addHtml = null
    ) {
        if (empty($btn)) {
            $btn = ___("Yes, continue");
        }
        $vars = $this->grid->getCompleteRequest()->toArray();
        $vars[$this->grid->getId() . '_confirm'] = 'yes';
        $hidden = Am_Html::renderArrayAsInputHiddens($vars);
        $btn = $this->grid->escape($btn);
        $url_yes = $this->grid->makeUrl(null);
        $select = ___('Please select agreement document');
        $select .= "<select multiple name='document_type[]' id='document-type'>";
        foreach (Am_Di::getInstance()->agreementTable->getTypeOptions() as $k => $v) {
            $select .= "<option value='{$k}'>$v</option>";
        }
        $select .= "</select><br/>";
        return <<<CUT
<form method="post" action="$url_yes" style="display: inline;">
    $hidden

    $addHtml
    {$select}
    <input type="submit" value="$btn" id='group-action-continue' />
</form>
<script type="text/javascript">
  jQuery(function(){
      jQuery("#document-type").magicSelect();
   });
   jQuery('#group-action-continue').click(function(){
    jQuery(this).closest('.am-grid-wrap').
        find('input[type=submit], input[type=button]').
        attr('disabled', 'disabled');
    jQuery(this).closest('form').submit();
    return false;
  })
</script>
CUT;
    }

    public function handleRecord($id, $record)
    {
        $user = $this->grid->getDi()->userTable->load($id);
        $types = $this->grid->getDi()->request->get('document_type');

        if (empty($types)) {
            $types = Am_Di::getInstance()->agreementTable->getTypes();
        }

        if (!empty($types)) {
            $user->updateQuick('require_consent', json_encode($types));
        }
    }
}

class AdminForceIAgreeController extends Am_Mvc_Controller
{
    function checkAdminPermissions(Admin $admin)
    {
        return $admin->isSuper();
    }

    function indexAction()
    {
        $form = new Am_Form_Admin('fia');
        $access = ['Products' => $this->getDi()->productTable->getOptions()];
        if ($this->getDi()->modules->isEnabled('aff')) {
            $access['Affiliate Program'] = ['aff' => ___('User is Affiliate')];
        }
        $form->addMagicSelect('access', ['class' => 'am-combobox'])
            ->setLabel(___("Reset Accept Status for Active Users of\n" .
                "leave empty to reset for all users"))
            ->loadOptions($access);
        $form->addMagicSelect('agreement')
            ->setLabel(___('Terms Documents that user have to accept'))
            ->loadOptions($this->getDi()->agreementTable->getTypeOptions());
        $form->addSaveButton(___('Reset'));

        if ($form->isSubmitted()) {
            $var = $form->getValue();
            if ($var['agreement']) {
                $agreement = json_encode($var['agreement']);
                if (in_array('aff', $var['access'])) {
                    $conditions[] = ' u.is_affiliate>0 ';
                }
                $product_ids = array_filter($var['access'], 'intval');
                if ($product_ids) {
                    $conditions[] = ' u.user_id = a.user_id ';
                }
                $where = !empty($conditions) ? "WHERE " . implode(' AND ', $conditions) : "";

                $q = $this->getDi()->db->queryResultOnly("
                    UPDATE ?_user u{, (SELECT user_id FROM ?_access a where a.product_id IN (?a) AND a.begin_date<=? AND a.expire_date>=?) a}
                    SET require_consent=? $where
                    ", ($product_ids ? $product_ids : DBSIMPLE_SKIP), sqlDate('now'), sqlDate('now'), $agreement
                );
            }

            $this->_response->redirectLocation($this->getDi()->url('admin-setup/force-i-agree', false));
        }

        $this->view->title = ___('Reset Consent Status for Users');
        $this->view->content = (string)$form;
        $this->view->display('admin/layout.phtml');
    }
}

class Am_Mvc_Controller_Plugin_ForceIAgree extends Zend_Controller_Plugin_Abstract
{
    public function __construct(Am_Plugin_ForceIAgree $plugin)
    {
        $this->plugin = $plugin;
    }

    public function preDispatch(Zend_Controller_Request_Abstract $request)
    {
        if (stripos($this->getRequest()->getControllerName(), 'admin') === 0) {
            return;
        } //exception for admin

        if ($request->getModuleName() == 'default' &&
            $request->getControllerName() == 'login' &&
            $request->getActionName() == 'logout') {
            return;
        } //exception for logout

        if ($request->getModuleName() == 'default' &&
            $request->getControllerName() == 'direct' &&
            $request->getParam('plugin_id') == 'avatar') {
            return;
        } //exception for avatar

        if ($request->getModuleName() == 'default' &&
            $request->getControllerName() == 'upload' &&
            $request->getActionName() == 'get') {
            return;
        } //exception for theme logo

        $di = Am_Di::getInstance();
        if ($di->auth->getUserId() &&
            $this->needShow($di->auth->getUser())) {
            $request->setControllerName('direct')
                ->setActionName('index')
                ->setModuleName('default')
                ->setParam('type', 'misc')
                ->setParam('plugin_id', 'force-i-agree');
        }
    }

    public function postDispatch(Zend_Controller_Request_Abstract $request)
    {
        $di = Am_Di::getInstance();
        if ($di->auth->getUserId() &&
            $this->needShow($di->auth->getUser()) &&
            ($_ = $this->isRedirect())) {

            $di->session->ns($this->plugin->getId())->redirect = $_;
            header("Location: {$di->url('misc/force-i-agree', false)}");

        }
    }

    protected function isRedirect()
    {
        foreach (headers_list() as $_) {
            if (preg_match('/^Location: (.*)$/', $_, $m)) {
                return $m[1];
            }
        }
    }

    protected function needShow(User $user)
    {
        return $this->plugin->needShow($user);
    }
}