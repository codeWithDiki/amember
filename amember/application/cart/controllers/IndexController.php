<?php

/**
 *     Author: Alex Scott
 *      Email: alex@cgi-central.net
 *        Web: http://www.cgi-central.net
 *    Details: Signup Page
 *    FileName $RCSfile$
 *    Release: 6.3.6 ($Revision: 4867 $)
 *
 * Please direct bug reports,suggestions or feedback to the cgi-central forums.
 * http://www.cgi-central.net/forum/
 *
 * aMember PRO is a commercial software. Any distribution is strictly prohibited.
 *
 */

class Cart_IndexController extends Am_Mvc_Controller
{
    /** @var Am_Query */
    protected $query;

    public function preDispatch()
    {
        if (!$this->getDi()->auth->getUserId()) {
            $this->getDi()->auth->checkExternalLogin($this->getRequest());
        }

        if ($this->getModule()->getConfig('require_login')) {
            $this->getDi()->auth->requireLogin($this->getDi()->url('cart', false));
        }
    }

    public function init()
    {
        parent::init();
        $this->view->cart = $this->getCart();

        $cc = $this->getModule()->getCategoryCode();
        $this->view->cc = $cc;

        $this->getModule()->getHiddenCatCodes(); // set and send cookies for hidden cat codes

        $list = $this->getConfig('domains');
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            $origin = $_SERVER['HTTP_ORIGIN'];
            $host = parse_url($origin, PHP_URL_HOST);
            if (in_array($host, explode("\n", $list))) {
                header("Access-Control-Allow-Origin: $origin");
                header("Access-Control-Allow-Credentials: true");
                header("Access-Control-Allow-Headers: origin, x-requested-with, cookie");
            }
        }
    }

    public function indexAction()
    {
        $category = $this->getModule()->getIndexPageCategory();
        if ($category) {
            $this->view->header = $category->title;
            $this->view->description = $category->description;
        }

        if (!$category && $this->getModule()->getConfig('front') == Bootstrap_Cart::FRONT_PAGE) {
            $page = $this->getDi()->pageTable->load($this->getModule()->getConfig('front_page_id'));
            echo $page->render($this->view, $this->getDi()->auth->getUser(), 'cart/layout.phtml');
            return;
        }

        if (!$category && $this->getModule()->getConfig('front') == Bootstrap_Cart::FRONT_CATEGORIES) {
            $w = new Am_Widget_CartCategoryList;
        } else {
            $w = new Am_Widget_CartProducts;
        }

        $this->view->content = $w->render(new Am_View);
        $this->view->title = $this->getDi()->config->get('site_title') . ( !empty($category) ? (' : ' . $category->title) : '');
        $this->view->display('cart/layout.phtml');
    }

    public function tagAction()
    {
        $this->view->header = ___('Search Results');
        $w = new Am_Widget_CartProducts;
        $view = new Am_View;
        $view->tag = $this->getParam('tag');
        $this->view->content = $w->render($view);
        $this->view->title = $this->getDi()->config->get('site_title') . ( !empty($category) ? (' : ' . $category->title) : '');
        $this->view->display('cart/layout.phtml');
    }

    public function productAction()
    {
        $id = null;
        if ($path = $this->getParam('path')) {
            if ($product = $this->getDi()->productTable->findFirstByPath($path)) {
                $id = $product->pk();
            }
        }
        $id = $id ?: $this->getInt('id');
        if ($id <= 0) {
            throw new Am_Exception_InputError(___("Invalid product id specified [%d]", $id));
        }

        $w = new Am_Widget_CartProduct;
        $v = new Am_View;
        $v->id = $id;
        $v->displayProductDetails = true;
        $v->headMeta = $this->view->headMeta();
        $this->view->content = $w->render($v);
        if ($v->meta_title)
            $this->view->meta_title = $v->meta_title; // passed back from the widget
        $this->view->id = $id;
        $this->view->display('cart/product.phtml');
    }

    public function searchAction()
    {
        $this->view->header = ___('Search Results');
        $w = new Am_Widget_CartProducts;
        $view = new Am_View;
        $view->search = $this->getParam('q');
        $this->view->content = $w->render($view);
        $this->view->title = $this->getDi()->config->get('site_title');
        $this->view->display('cart/layout.phtml');
    }

    public function viewBasketAction()
    {
        if ($this->getParam('do-return')) {
            if ($this->getParam('b'))
                return $this->_response->redirectLocation($this->getParam('b'));
            return $this->_redirect('cart');
        }
        $d = (array) $this->getParam('d', []);
        $qty = (array) $this->getParam('qty', []);

        foreach ($qty as $item_id => $newQty) {
            if ($item = $this->getCart()->getInvoice()->findItem('product', intval($item_id)))
                if ($this->getCart()->isStick($item))
                    throw new Am_Exception_InputError("This item is stick and cannot be modified in shopping cart");
                if ($item->is_countable && $item->variable_qty) {
                    if ($newQty == 0) {
                        $this->getCart()->getInvoice()->deleteItem($item);
                    } else {
                        $item->qty = 0;
                        $item->add($newQty);
                    }
                }
        }

        foreach ($d as $item_id => $val) {
            [$id, $type] = explode('-', $item_id);
            if ($item = $this->getCart()->getInvoice()->findItem(!empty($type) ? $type : 'product', intval($id))) {
                if ($this->getCart()->isStick($item)) {
                    throw new Am_Exception_InputError("This item is stick and cannot be modified in shopping cart");
                }
                $this->getCart()->getInvoice()->deleteItem($item);
            }
        }

        if (
            $this->getModule()->getConfig('use_coupons')
            && ($code = $this->getParam('coupon')) !== null
        ) {
            $this->view->coupon_error = $this->getCart()->setCouponCode($code);
        }

        if ($this->getDi()->auth->getUserId())
            $this->getCart()->setUser($this->getDi()->user);
        $this->getCart()->calculate();
        if (empty($this->view->coupon_error) && $this->getParam('do-checkout')) {
            Am_Mvc_Response::redirectLocation($this->getDi()->url('cart/checkout', false));
        }
        $this->getDi()->blocks->remove('cart-basket');
        $this->view->isAjax = $this->getRequest()->isXmlHttpRequest();
        $this->view->b = $this->getParam('b', '');
        $this->view->display('cart/basket.phtml');
    }

    public function addAndCheckoutAction()
    {
//        $this->addFromRequest();
        Am_Mvc_Response::redirectLocation($this->getDi()->url('cart/checkout', false));
    }

    public function checkoutAction()
    {
        if ($this->getFiltered('a')!= 'checkout') $this->getCart()->getInvoice()->paysys_id = null;
        $this->getCart()->getInvoice()->calculate();
        return $this->doCheckout();
    }

    public function setPaysysAction()
    {
        try {
            $paysystems = $this->getModule()->getAvailablePaysystems($this->getCart()->getInvoice());

            if (!$paysystems)
                throw new Am_Exception_InternalError("Sorry, no payment plugins enabled to handle this invoice");
            if ($paysys_id = $this->getFiltered('paysys_id'))
            {
                if (!in_array($paysys_id, array_keys($paysystems)))
                    throw new Am_Exception_InputError("Sorry, paysystem [$paysys_id] is not available for this invoice");

                $this->getCart()->getInvoice()->setPaysystem($paysys_id);
                $this->getDi()->response->ajaxResponse(['ok'=>true]);
            }
        } catch (Exception $e) {
            $this->getDi()->response->ajaxResponse(['ok'=>false, 'error'=>$e->getMessage()]);
        }
    }

    public function setCouponAction()
    {
        if (!$this->getModule()->getConfig('use_coupons')) {
            return;
        }

        try {
            if ($coupon_error = $this->getCart()->setCouponCode($this->getParam('coupon'))) {
                throw new Am_Exception_InputError($coupon_error);
            }
            $this->getCart()->calculate();
            if ($this->getCart()->getInvoice()->isZero()) {
                $this->getCart()->getInvoice()->paysys_id = 'free';
            } elseif ($this->getCart()->getInvoice()->paysys_id == 'free') {
                $this->getCart()->getInvoice()->paysys_id = null;
            }

            $view = $this->getDi()->view;
            $view->cart = $this->getCart();

            $this->getDi()->response->ajaxResponse(
                [
                    'ok' => true,
                    'coupon' => $this->getCart()->getCouponCode(),
                    'html' => $view->render('cart/_basket.phtml'),
                    'paysys_id' => $this->getCart()->getInvoice()->paysys_id,
                ]
            );
        } catch (Exception $e) {
            $this->getCart()->calculate();

            if ($this->getCart()->getInvoice()->paysys_id == 'free') {
                $this->getCart()->getInvoice()->paysys_id = null;
            }

            $view = $this->getDi()->view;
            $view->cart = $this->getCart();

            $this->getDi()->response->ajaxResponse([
                'ok' => false,
                'error' => $e->getMessage(),
                'html' => $view->render('cart/_basket.phtml'),
                'paysys_id' => $this->getCart()->getInvoice()->paysys_id,
            ]);
        }
    }

    public function previewBasketAction()
    {
        echo $this->getModule()->renderCheckout($this->view);
    }

    public function choosePaysysAction()
    {
        $this->view->paysystems = $this->getModule()->getAvailablePaysystems($this->getCart()->getInvoice());

        if (count($this->view->paysystems) == 1) {
            $firstps = array_shift($this->view->paysystems);
            $this->getCart()->getInvoice()->setPaysystem($firstps->getId());
            return $this->doCheckout();
        }

        if ($this->getRequest()->isPost() && $this->getParam('choose-paysys')) {
            $this->view->error = ___('Please choose Payment Method');
        }
        $this->view->choosePaysys = true;
        $this->view->display('cart/checkout.phtml');
    }

    public function loginAction()
    {
        return $this->_response->redirectLocation($this->getDi()->url('login',
            ['saved_form'=>'cart','_amember_redirect_url' => base64_encode($this->view->overrideUrl())],false));
    }

    public function ajaxAddAction()
    {
        $this->addFromRequest();
        $this->view->display('blocks/basket.phtml');
    }

    public function ajaxAddOnlyAction()
    {
        $this->addFromRequest();
    }

    public function ajaxRemoveOnlyAction()
    {
        $data = json_decode($this->getParam('data'));
        try {
            if (!$data)
                throw new Am_Exception_InternalError(___('Shopping Cart Module. No data input'));

            foreach ($data as $item) {
                $item_id = intval($item->id);
                if (!$item_id)
                    throw new Am_Exception_InternalError(___('Shopping Cart Module. No product id input'));

                if ($i = $this->getCart()->getInvoice()->findItem(empty($item->type) ? 'product' : $item->type, intval($item_id)))
                {
                    if ($this->getCart()->isStick($i))
                        throw new Am_Exception_InputError("This item is stick and cannot be removed from shopping cart");
                    $this->getCart()->getInvoice()->deleteItem($i);
                }
            }

            if ($this->getDi()->auth->getUserId())
                $this->getCart()->setUser($this->getDi()->user);
            $this->getCart()->calculate();
        } catch (Exception $e) {
            $this->getDi()->logger->error("Error in cart/ajax-remove-only operation", ["exception" => $e]);
            $this->_response->ajaxResponse(
                [
                    'status' => 'error',
                    'message' => $e->getPublicError()
                ]);
            return;
        }
        $this->_response->ajaxResponse(['status' => 'ok']);
    }

    public function ajaxLoadOnlyAction()
    {
        $this->view->display('blocks/basket.phtml');
    }

    public function addFromRequest()
    {
        //data = [{id:id,qty:qty,plan:plan,type:type},{}...]
        $data = json_decode($this->getParam('data'), true);
        try {
            if (!$data)
                throw new Am_Exception_InternalError(___('Shopping Cart Module. No data input'));

            foreach ($data as $item) {
                $item_id = intval($item['id']);
                if (!$item_id)
                    throw new Am_Exception_InternalError(___('Shopping Cart Module. No product id input'));
                $qty = (!empty($item['qty']) && $q = intval($item['qty'])) ? $q : 1;

                $p = $this->getDi()->productTable->load($item_id);
                if ($p->is_disabled || $p->is_archived) continue;

                if (!empty($item['plan']) && ($bp = intval($item['plan']))) {
                    $p->setBillingPlan($bp);
                }
                $options = [];
                if (!empty($item['options']))
                {
                    $plan_id = $p->getBillingPlan()->pk();
                    parse_str($item['options'], $_);
                    $options = $_['productOption']["{$item_id}-{$plan_id}"][0];
                }
                $prOpt = $p->getOptions(true);
                foreach ($options as $k => $v)
                {
                    $options[$k] = [
                        'value' => $v, 'optionLabel' => $prOpt[$k]->title,
                        'valueLabel' => $prOpt[$k]->getOptionLabel($v)
                    ];
                }
                $this->getCart()->addItem($p, $qty, $options);
            }

            if ($this->getDi()->auth->getUserId())
                $this->getCart()->setUser($this->getDi()->user);

            $this->getCart()->calculate();
        } catch (Exception $e) {
            $this->getDi()->logger->error("Exception in cart/add-from-request operation", ["exception" => $e]);
            $this->_response->ajaxResponse(
                [
                    'status' => 'error',
                    'message' => $e->getPublicError()
                ]);
            return;
        }
        $this->_response->ajaxResponse(['status' => 'ok']);
    }

    public function signupRedirect($url)
    {
        return $this->_response->redirectLocation(
                $this->getDi()->url(
                    'signup/cart/checkout',
                    ['amember_redirect_url'=>$url],
                    false
                )
        );
    }

    function doLoginOrSignup()
    {
        $url = rtrim($this->view->overrideUrl(), '?');
        $url .= (strpos($url, '?') !== false) ? '&a=checkout' : '?a=checkout';
        $this->signupRedirect($url);
    }

    function doCheckout()
    {
        do {
            if (!$this->getCart()->getItems()) {
                return $this->view->display('cart/basket.phtml');
            }
            if (!$this->getDi()->auth->getUserId()) {
                return $this->doLoginOrSignup();
            } else {
                $this->getCart()->setUser($this->getDi()->user);
            }
            $invoice = $this->getCart()->getInvoice();

            if (empty($invoice->paysys_id)) {
                if($invoice->isZero()) {
                    $invoice->paysys_id = 'free';
                } else {
                    return $this->choosePaysysAction();
                }
            }
            elseif(($invoice->paysys_id == 'free') && (!$invoice->isZero()))
            {
                $invoice->paysys_id = null;
                return $this->choosePaysysAction();
            }

            $this->getDi()->hook->call(Am_Event::CART_INVOICE_CHECKOUT, [
                'invoice' => $invoice
            ]);
            $errors = $invoice->validate();
            if ($errors) {
                $this->view->assign('errors', $errors);
                return $this->view->display('cart/basket.phtml');
            }
            // display confirmation
            if (!$this->getInt('confirm') && $this->getDi()->config->get('shop.confirmation'))
                return $this->view->display('cart/confirm.phtml');
            ///

            $invoice = $this->getCart()->getInvoiceForCheckout();

            $payProcess = new Am_Paysystem_PayProcessMediator($this, $invoice);
            $result = $payProcess->process();
            if ($result->isFailure()) {
                $this->view->error = ___("Checkout error: ") . current($result->getErrorMessages());
                $this->view->paysystems = $this->getModule()->getAvailablePaysystems($this->getCart()->getInvoice());
                return $this->view->display('cart/checkout.phtml');
            }
        } while (false);
    }

    /**
     * @return Am_ShoppingCart
     */
    public function getCart()
    {
        return $this->getModule()->getCart();
    }
}