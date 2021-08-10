<?php

class AjaxController extends Am_Mvc_Controller
{
    public function preDispatch()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            $e = new Am_Exception_InputError;
            $e->setLogError(false);
            throw $e;
        }
    }

    function ajaxError($msg)
    {
        $this->_response->ajaxResponse(['msg' => $msg]);
    }

    function ajaxGetStates($vars)
    {
        $this->_response->setHeader('Cache-Control', 'public; max-age=86400');
        return $this->_response->ajaxResponse($this->getDi()->stateTable->getOptions($vars['country']));
    }

    function ajaxCheckUniqLogin($vars)
    {
        $user_id = $this->getDi()->auth->getUserId();
        if (!$user_id) {
            $user_id = $this->getDi()->session->signup_member_id;
        }
        $login = $vars['login'];
        $msg = null;
        if (!$this->getDi()->userTable->checkUniqLogin($login, $user_id)) {
            $msg = ___('Username %s is already taken. Please choose another username', Am_Html::escape($login));
        }
        if (!$msg) {
            $msg = $this->getDi()->banTable->checkBan(['login'=>$login]);
        }

        return $this->_response->ajaxResponse($msg ? $msg : true);
    }

    function checkUniqEmailAction()
    {
        $this->ajaxCheckUniqEmail($this->getRequest()->toArray());
    }

    function ajaxCheckUniqEmail($vars)
    {
        $user_id = $this->getDi()->auth->getUserId();
        if (!$user_id) {
            $user_id = $this->getDi()->session->signup_member_id;
        }

        $email = !empty($vars['email']) ? $vars['email'] : null;
        $msg = null;
        if(isset($vars['_url'])) {
            $url = $this->getDi()->surl('login', ['amember_redirect_url' => $vars['_url']]);
        } else {
            $url = $this->getDi()->surl('member');
        }
        if (!$this->getDi()->userTable->checkUniqEmail($email, $user_id)) {
            $msg = ___('An account with the same email already exists.') . '<br />' .
                ___('Please %slogin%s to your existing account.%sIf you have not completed payment, you will be able to complete it after login',
                    '<a href="' . $url . '" class="ajax-link" title="' . Am_Html::escape($this->getDi()->config->get('site_title')) . '">',
                    '</a>', '<br />');
        }
        if (!$msg) {
            $msg = $this->getDi()->banTable->checkBan(['email'=>$email]);
        }
        if (!$msg && !Am_Validate::email($email)) {
            $msg = ___('Please enter valid Email');
        }

        return $this->_response->ajaxResponse($msg ? $msg : true);
    }

    function ajaxCheckCoupon($vars)
    {
        if (empty($vars['coupon'])) return $this->_response->ajaxResponse(true);
        $user_id = $this->getDi()->auth->getUserId();
        if (!$user_id)
            $user_id = $this->getDi()->session->signup_member_id;

        $coupon = $this->getDi()->couponTable->findFirstByCode(trim($vars['coupon']));
        $msg = $coupon ? $coupon->validate($user_id) : ___('No coupons found with such coupon code');
        return $this->_response->ajaxResponse(is_null($msg) ? true : $msg);
    }

    function indexAction()
    {
        $vars = $this->_request->toArray();
        switch ($this->_request->getFiltered('do')){
            case 'get_states':
                $this->ajaxGetStates($vars);
                break;
            case 'check_uniq_login':
                $this->ajaxCheckUniqLogin($vars);
                break;
            case 'check_uniq_email':
                $this->ajaxCheckUniqEmail($vars);
                break;
            case 'check_coupon':
                $this->ajaxCheckCoupon($vars);
                break;
            default:
                $this->ajaxError('Unknown Request: ' . $vars['do']);
        }
    }

    function invoiceSummaryAction()
    {
        $vars = $this->getRequest()->getParams();

        $param = [];
        $page_current = $this->getRequest()->getParam('_save_');
        $vars_added = false;
        $ns = $this->getDi()->session->ns("am_form_container_signup_{$vars['_saved_form_id']}");
        foreach ((array)$ns->data['values'] as $page => $v) {
            if ($page == $page_current) {
                $v = array_merge($v, $vars);
                $vars_added = true;
                $param = array_merge_recursive($param, $v);
                break;
            }
            $param = array_merge_recursive($param, $v);
        }
        $vars = $vars_added ? $param : array_merge($param, $vars);

        if(!$user = $this->getDi()->auth->getUser()) {
            $user = $this->getDi()->userRecord;
            $user->user_id = -1;
        }
        $user->toggleFrozen(true);
        $user->setForInsert($vars);

        $user->remote_addr = $this->_request->getClientIp();

        if (!empty($vars['_button']) && ($btn = $this->getDi()->buttonTable->findFirstByHash($vars['_button']))) {
            $invoice = $btn->createInvoice();
            if ($btn->use_coupons && !empty($vars['coupon'])) {
                $invoice->setCouponCode(trim($vars['coupon']));
                if ($error = $invoice->validateCoupon()) {
                    $invoice->setCouponCode('');
                }
            }
            $invoice->setUser($user);
            $invoice->calculate();
        } else {
            $invoice = $this->getDi()->invoiceRecord;
            $invoice->setUser($user);
            if (!empty($vars['giftVoucherCode'])) {
                $invoice->data()->set('giftVoucherCode', $vars['giftVoucherCode']);
            }

            foreach ($vars as $k => $v) {
                if (strpos($k, 'product_id')===0) {
                    foreach ((array)$vars[$k] as $key => $product_id) {
                        if (substr($key, 0, 4) == '_qty') continue;
                        @list($product_id, $plan_id, $qty) = explode('-', $product_id, 3);

                        $qty_key = sprintf('_qty-%d-%d', $product_id, $plan_id);
                        if (isset($vars[$k][$qty_key]))
                            $qty = $vars[$k][$qty_key];

                        $product_id = (int)$product_id;
                        if (!$product_id) continue;
                        $p = $this->getDi()->productTable->load($product_id);
                        if ($plan_id > 0) $p->setBillingPlan(intval($plan_id));
                        $qty = (int)$qty;
                        if (!$p->getBillingPlan()->variable_qty || ($qty <= 0))
                            $qty = 1;
                        $plan_id = $p->getBillingPlan()->pk();
                        $options = [];
                        if (!empty($vars['productOption']["$product_id-$plan_id"])) {
                            $options = $vars['productOption']["$product_id-$plan_id"][0];
                        }
                        $prOpt = $p->getOptions(true);
                        foreach ($options as $opk => $opv) {
                            $options[$opk] = [
                                'value' => $opv, 'optionLabel' => $prOpt[$opk]->title,
                                'valueLabel' => $prOpt[$opk]->getOptionLabel($opv)
                            ];
                        }
                        $invoice->add($p, $qty, $options);
                    }
                }
            }
            if (!empty($vars['coupon'])) {
                $invoice->setCouponCode(trim($vars['coupon']));
                if ($error = $invoice->validateCoupon()) {
                    $invoice->setCouponCode('');
                }
            }
            $this->_handleDonation($invoice, $vars);

            $invoice->calculate();
            if (($invoice->first_total > 0 || $invoice->second_total > 0) &&
                isset($vars['paysys_id'])) {
                try {
                    $invoice->setPaysystem($vars['paysys_id']);
                } catch (Exception $e) {
                    //nop
                }
            }
        }
        $v = $this->getDi()->view;
        $v->invoice = $invoice;
        $html = $v->render('_invoice-summary.phtml');
        $this->_response->ajaxResponse([
            'html' => $html,
            'hash' => md5($html)
        ]);
    }

    function _handleDonation(Invoice $invoice, $vars)
    {
        if (!$this->getDi()->plugins_misc->isEnabled('donation')) return;

        foreach ($invoice->getItems() as $item) {
            if ($item->item_type == 'product' && isset($vars['donation'][$item->item_id])) {
                if (!$vars['donation'][$item->item_id] && !$vars['donation_allow_free'][$item->item_id]) {
                    $invoice->deleteItem($item);
                } else {
                    $item->first_price = $vars['donation'][$item->item_id];
                    $item->data()->set('orig_first_price', $item->first_price);

                    if (
                        $vars['donation_force_recurring'][$item->item_id]
                        || (isset($vars['recurring'][$item->item_id]) && $vars['recurring'][$item->item_id])
                    ) {
                        $item->second_price = $item->first_price;
                        $item->data()->set('orig_second_price', $item->second_price);
                    } else {
                        $item->rebill_times = 0;
                        $item->second_price = 0;
                        $item->second_period = null;
                        $item->data()->set('orig_second_price', $item->second_price);
                    }
                }
            }
        }
    }

    function unsubscribedAction()
    {
        $v = $this->_request->getPost('unsubscribed');
        if (strlen($v) != 1)
            throw new Am_Exception_InputError("Wrong input");
        $v = ($v > 0) ? 1 : 0;
        if (($s = $this->getFiltered('s')) && ($e = $this->getParam('e')) &&
            $this->getDi()->unsubscribeLink->validate($e, $s)) {
            $user = $this->getDi()->userTable->findFirstByEmail($e);
        } else {
            $user = $this->getDi()->user;
        }
        if (!$user)
            return $this->ajaxError(___('You must be logged-in to run this action'));
        if ($user->unsubscribed != $v) {
            $user->set('unsubscribed', $v)->update();
            if (!$v) {
                $this->getDi()->userConsentTable->recordConsent(
                        $user,
                        'site-emails',
                        $this->getRequest()->getClientIp(),
                        ___('Subscription Management Page: %s', ___('Dashboard')),
                        ___('Site Email Messages')
                    );
            } else {
                $this->getDi()->userConsentTable->cancelConsent(
                        $user,
                        'site-emails',
                        $this->getRequest()->getClientIp(),
                        ___('Subscription Management Page: %s', ___('Dashboard'))
                    );
            }
            $this->getDi()->hook->call(Am_Event::USER_UNSUBSCRIBED_CHANGED,
                ['user'=>$user, 'unsubscribed' => $v]);
        }
        $this->_response->ajaxResponse(['status' => 'OK', 'value' => $v,
            'msg' => ___('Status of your subscription has been changed.')]
        );
    }
}
