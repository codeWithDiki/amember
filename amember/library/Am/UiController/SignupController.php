<?php

class SignupController extends Am_Mvc_Controller
{
    /** @var Am_Form_Signup */
    protected $form;
    /** @var array */
    protected $vars;

    use Am_Mvc_Controller_User_Create;

    function init()
    {
        if (!class_exists('Am_Form_Brick', false)) {
            class_exists('Am_Form_Brick', true);
            Am_Di::getInstance()->hook->call(Am_Event::LOAD_BRICKS);
        }
        parent::init();
    }

    function loadForm()
    {
        if ($c = $this->getFiltered('c')) {
            if ($c == 'cart') {
                if ($this->_request->getParam('amember_redirect_url'))
                    $this->getSession()->redirectUrl = $this->_request->getParam('amember_redirect_url');
                if ($this->getDi()->auth->getUser() != null) {
                    $url = $this->getSession()->redirectUrl;
                    $this->getSession()->redirectUrl = '';
                    $this->_redirect($url ?: $this->getDi()->url('cart'));
                } else {
                    $this->record = $this->getDi()->savedFormTable->getByType(SavedForm::T_CART);
                }
            } else {
                $this->record = $this->getDi()->savedFormTable->findFirstBy([
                    'code' => $c,
                    'type' => SavedForm::T_SIGNUP,
                ]);
            }
        } else {
            $this->record = $this->getDi()->savedFormTable->getDefault($this->getDi()->auth->getUserId() ? SavedForm::D_MEMBER : SavedForm::D_SIGNUP);
        }

        $this->record = $this->getDi()->hook->filter($this->record, Am_Event::LOAD_SIGNUP_FORM, [
            'request' => $this->_request,
            'user'    => $this->getDi()->auth->getUser(),
        ]);

        if ($this->record->is_disabled) {
            throw new Am_Exception_InputError;
        }

        if (!$this->record) {
            $this->getDi()->logger->error("Wrong signup form code - the form does not exists. Redirect Customer to default form. Referrer: " . $this->getRequest()->getHeader('REFERER'));
            $this->redirect('/signup', ['code'=>302]);
        }

        /* @var $this->record SavedForm */
        if (!$this->record->isSignup())
            throw new Am_Exception_InputError("Wrong signup form loaded [$this->record->saved_form_id] - it is not a signup form!");

        if ($this->record->meta_title)
            $this->view->meta_title = $this->record->meta_title;
        if ($this->record->meta_keywords)
            $this->view->headMeta()->setName('keywords', $this->record->meta_keywords);
        if ($this->record->meta_description)
            $this->view->headMeta()->setName('description', $this->record->meta_description);
        if ($this->record->meta_robots)
            $this->view->headMeta()->setName('robots', $this->record->meta_robots);
        $this->view->code = $this->record->code;
        $this->view->record = $this->record;
    }

    function indexAction()
    {
        /*
         *  First check user's login. user can be logged in plugin or user's login info can be in cookies.
         *  Result does not matter here so skip it;
         */
        if(!$this->getDi()->auth->getUserId() && $this->_request->isGet())
            $this->getDi()->auth->checkExternalLogin($this->_request);
        /*==TRIAL_SPLASH==*/

        if (!$this->getDi()->auth->getUserId() && $this->getDi()->config->get('signup_disable')) {
            $e = new Am_Exception_InputError(___('New Signups are Disabled'));
            $e->setLogError(false);
            throw $e;
        }

        $this->loadForm();
        $this->view->title = ___($this->record->title);
        $this->form = new Am_Form_Signup("signup_{$this->record->pk()}");
        $this->form->setParentController($this);

        if (($h = $this->getDi()->request->getFiltered('order-data')) &&
            ($hdata = $this->getDi()->store->get('am-order-data-'.$h)))
        {
            $dhdata = json_decode($hdata, true);
            if ($this->getDi()->auth->getUser() && isset($dhdata['redirect'])) {
                $this->getDi()->store->delete('am-order-data-'.$h);
                Am_Mvc_Response::redirectLocation($dhdata['redirect']);
            }
            $this->form->getSessionContainer()->storeOpaque('am-order-data', $dhdata);
        }
        $this->form->initFromSavedForm($this->record);
        //case when we redirect back to signup page after 3rd party login (eg.: google signin) and form has not any
        //bricks (hide if logged in enabled for all)
        if (count($this->form->getIterator()) == 0) {
            Am_Mvc_Response::redirectLocation($this->url('member', false));
        }
        try {
            $this->form->run();
        } catch (Am_Exception_QuietError $e){
            $e->setPublicTitle($this->record->title);
            throw $e;
        }
    }

    function display(Am_Form $form, $pageTitle)
    {
        $this->view->form = $form;
        $this->view->title = $pageTitle ?: $this->record->title;

        if ($this->getRequest()->isXmlHttpRequest()) {
            $this->view->display('_form.phtml');
        } else {
            $this->view->display($this->record->tpl ? ('signup/' . basename($this->record->tpl)) : 'signup/signup.phtml');
        }
    }

    function autoLoginIfNecessary()
    {
        if (($this->getConfig('auto_login_after_signup') || ($this->record->type == SavedForm::T_CART)) && $this->user->isApproved())
        {
            $this->user->refresh();
            $adapter = new Am_Auth_Adapter_User($this->user);
            $this->getDi()->auth->login($adapter, $this->getRequest()->getClientIp(), false);
        }
    }

    function process(array $vars, $name, HTML_QuickForm2_Controller_Page $page)
    {
        $this->getDi()->hook->call(Am_Event::SIGNUP_PAGE_BEFORE_PROCESS, [
           'vars' => $vars,
           'savedForm' => $this->record
        ]);

        $this->vars = $vars;
        // do actions here
        $this->user = $this->getDi()->auth->getUser();
        if ($this->getSession()->signup_member_id && $this->getSession()->signup_member_login)
        {
            $user = $this->getDi()->userTable->load((int)$this->getSession()->signup_member_id, false);
            if ($user && ((($this->getDi()->time - strtotime($user->added)) < 24*3600) && ($user->status == User::STATUS_PENDING)))
            {
                // prevent attacks as if someone has got ability to set signup_member_id to session
                if ($this->getSession()->signup_member_login == $user->login) {
                    /// there is a potential problem
                    /// because user password is not updated second time - @todo
                    $this->user = $user;
                    $this->autoLoginIfNecessary();
                } else {
                    $this->getSession()->signup_member_id = null;
                    $this->getSession()->signup_member_login = null;
                }
            } else {
                $this->getSession()->signup_member_id = null;
            }
        }

        $this->user = $this->getDi()->hook->filter($this->user, Am_Event::SIGNUP_LOAD_USER, [
           'vars' => $vars,
           'savedForm' => $this->record
        ]);

        if (!$this->user)
        {
            $this->vars['saved_form_id'] = $this->record->pk();

            $this->vars['email_confirmed'] = isset($this->form->getSessionContainer()->email_confirmed)?$this->form->getSessionContainer()->email_confirmed:false;
            $this->vars['email_confirmation_date'] = $this->vars['email_confirmed']? $this->getDi()->sqlDateTime : null;

            $this->user = $this->createUser($this->vars);

            $this->getSession()->signup_member_id = $this->user->pk();
            $this->getSession()->signup_member_login = $this->user->login;

            $this->autoLoginIfNecessary();

            $this->getDi()->hook->call(Am_Event::SIGNUP_USER_ADDED, [
                'vars' => $this->vars,
                'user' => $this->user,
                'form' => $this->form,
                'savedForm' => $this->record
            ]);
        } else {
            if ($this->record->isCart()) {
                $url = $this->getSession()->redirectUrl;
                $this->getSession()->redirectUrl = '';
                $this->getDi()->response->redirectLocation($url ? urldecode($url) : $this->getDi()->url('cart', false));
            }
            unset($this->vars['pass']);
            unset($this->vars['login']);
            unset($this->vars['email']);
            $this->user->setForUpdate($this->vars)->update();
            // user updated
            $this->getDi()->hook->call(Am_Event::SIGNUP_USER_UPDATED, [
                'vars' => $this->vars,
                'user' => $this->user,
                'form' => $this->form,
                'savedForm' => $this->record
            ]);
        }

        // keep reference to e-mail confirmation link so it still working after signup
        if (!empty($this->vars['code']))
        {
            $this->getDi()->store->setBlob(Am_Form_Signup_Action_SendEmailCode::STORE_PREFIX . $this->vars['code'],
                $this->user->pk(), '+7 days');
        }

        $amOrderData = $this->form->getSessionContainer()->getOpaque('am-order-data');

        if ($amOrderData && isset($amOrderData['redirect'])) {
            $this->form->getSessionContainer()->destroy();

            $redirect = $amOrderData['redirect'];
            $data = [];
            if (!empty($amOrderData['pass-data'])) {
                foreach ($amOrderData['pass-data'] as $token) {
                    $data[$token] = $vars[$token];
                }
                $redirect .= "?" . http_build_query($data);
            }

            $this->getDi()->response->redirectLocation($redirect);
        }
        if ($this->record->isCart())
        {
            $url = $this->getSession()->redirectUrl;
            $this->getSession()->redirectUrl = '';
            $this->getDi()->response->redirectLocation($url ? urldecode($url) : $this->getDi()->url('cart', false));
            return true;
        }

        /// now the ordering process
        /** @var Invoice $invoice */
        $invoice = $this->getDi()->invoiceRecord;
        $invoice->saved_form_id = $this->record->pk();
        $this->getDi()->hook->call(Am_Event::INVOICE_SIGNUP, [
            'vars' => $this->vars,
            'user' => $this->user,
            'form' => $this->form,
            'invoice' => $invoice,
            'savedForm' => $this->record
        ]);
        if ($hdata = $this->form->getSessionContainer()->getOpaque('am-order-data'))
        {
            foreach ($hdata as $k => $v)
                $invoice->data()->set($k, $v);
        }
        $invoice->setUser($this->user);
        foreach ($this->vars as $k => $v) {
            if (strpos($k, 'product_id')===0)
                foreach ((array)$this->vars[$k] as $product_id)
                {
                    @list($product_id, $plan_id, $qty) = explode('-', $product_id, 3);
                    $product_id = (int)$product_id;
                    if (!$product_id) continue;
                    $p = $this->getDi()->productTable->load($product_id);
                    if (intval($plan_id) > 0) $p->setBillingPlan(intval($plan_id));
                    $qty = (int)$qty;
                    if (!$p->getBillingPlan()->variable_qty || ($qty <= 0))
                        $qty = 1;
                    $options = [];
                    if (!empty($this->vars['productOption']["$product_id-$plan_id"]))
                    {
                        $options = $this->vars['productOption']["$product_id-$plan_id"][0];
                    }
                    $prOpt = $p->getOptions(true);
                    foreach ($options as $k => $v)
                    {
                        $options[$k] = [
                            'value' => $v, 'optionLabel' => $prOpt[$k]->title,
                            'valueLabel' => $prOpt[$k]->getOptionLabel($v)
                        ];
                    }
                    $invoice->add($p, $qty, $options);
                }
        }

        $invoice = $this->getDi()->hook->filter($invoice, Am_Event::SIGNUP_INVOICE_ITEMS, [
            'vars' => $this->vars,
            'form' => $this->form,
            'invoice' => $invoice,
            'savedForm' => $this->record
        ]);

        if (!$invoice->getItems()) {
            $this->form->getSessionContainer()->destroy();
            Am_Mvc_Response::redirectLocation($this->user->is_approved ?
                $this->getDi()->url('login', false) :
                $this->getDi()->url('thanks', [
                    'uid'=> $this->user->login,
                    'h' => $this->getDi()->security->hash($this->user->login, 8)
                ], false));
            return true;
        }

        if (!empty($this->vars['coupon']))
        {
            $invoice->setCouponCode($this->vars['coupon']);
            $invoice->validateCoupon();
        }

        $invoice->calculate();
        $invoice->setPaysystem(isset($this->vars['paysys_id']) ? $this->vars['paysys_id'] : 'free');
        $err = $invoice->validate();
        if ($err) {
            $page = $this->form->getFirstPage();
            $page->getForm()->setError($err[0]);
            $page->handle('display');
            return false;
        }

        if (!empty($this->vars['coupon']) &&
            !(float)$invoice->first_discount &&
            !(float)$invoice->second_discount) {

            $coupon = $this->getDi()->couponTable->findFirstByCode($this->vars['coupon']);
            $batch = $coupon->getBatch();
            if ($batch->discount > 0) {
                $page = $this->form->findPageByElementName('coupon');
                if (!$page) throw new Am_Exception_InternalError('Coupon brick is not found but coupon code presents in request');

                [$el] = $page->getForm()->getElementsByName('coupon');
                $data = $el->getData();

                if (empty($data['no_show_error_zero_discount'])) {
                    $el->setError($data['zero_discount_error']);

                    //now active datasource is datasource of current page
                    //retrieve datasource for page with coupon element from
                    //session and set it to form to populate it correctly
                    $values = $page->getController()->getSessionContainer()->getValues($page->getForm()->getId());
                    $page->getForm()->setDataSources([
                        new HTML_QuickForm2_DataSource_Array($values)
                    ]);
                    $page->handle('display');
                    return false;
                }
            }
        }

        $invoice->insert();
        $this->getDi()->hook->call(Am_Event::INVOICE_BEFORE_PAYMENT_SIGNUP, [
            'vars' => $this->vars,
            'form' => $this->form,
            'invoice' => $invoice,
            'savedForm' => $this->record
        ]);
        try {
            $payProcess = new Am_Paysystem_PayProcessMediator($this, $invoice);
            $result = $payProcess->process();
        } catch (Am_Exception_Redirect $e) {
            $this->form->getSessionContainer()->destroy();
            $invoice->refresh();
            if ($invoice->isCompleted())
            { // relogin customer if free subscription was ok
                $this->autoLoginIfNecessary();
            }
            throw $e;
        }
        // if we got back here, there was an error in payment!
        /** @todo offer payment method if previous failed */

        $page = $this->form->findPageByElementName('paysys_id');
        if (!$page) $page = $this->form->getFirstPage(); // just display first page
        foreach ($page->getForm()->getElementsByName('paysys_id') as $el)
            $el->setValue(null)->setError(current($result->getErrorMessages()));
        $page->handle('display');
        return false;
   }

   function getCurrentUrl()
   {
       $c = $this->getFiltered('c');
       return $this->_request->getScheme() . '://' .
              $this->_request->getHttpHost() .
              $this->_request->getBaseUrl() . '/' .
              $this->_request->getControllerName() .
              ($c ? "/$c" : '');
   }

   public function getForm()
   {
       return $this->form;
   }
}