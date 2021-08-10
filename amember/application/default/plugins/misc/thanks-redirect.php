<?php

/**
 * @am_plugin_api 6.0
*/
class Am_Plugin_ThanksRedirect extends Am_Plugin
{
    protected $_configPrefix = 'misc.';

    function getTitle()
    {
        return ___('Thanks Redirect');
    }

    function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addText('url', ['class' => 'am-el-wide'])
            ->setLabel(___("After Purchase Redirect User to this URL\ninstead of thanks page\n" .
                'You can use %root_url%, %root_surl%, %invoice.%, %product.% and %user.% variables in url eg: %user.login%, %user.email%, %invoice.public_id% etc.'));
    }

    function onGridProductInitForm(Am_Event_Grid $e)
    {
        $e->getGrid()->getForm()->getAdditionalFieldSet()
            ->addText('_thanks_redirect_url', ['class' => 'am-el-wide'])
            ->setLabel(___("After Purchase Redirect User to this URL\ninstead of thanks page\n" .
                'You can use %root_url%, %root_surl%, %invoice.%, %product.% and %user.% variables in url eg: %user.login%, %user.email%, %invoice.public_id% etc.'));
    }

    function onGridProductValuesFromForm(Am_Event_Grid $e)
    {
        $args = $e->getArgs();
        $product = $args[1];
        $product->data()->set('thanks_redirect_url', @$args[0]['_thanks_redirect_url']);
    }

    function onGridProductValuesToForm(Am_Event_Grid $e)
    {
        $args = $e->getArgs();
        $product = $args[1];
        $args[0]['_thanks_redirect_url'] = $product->data()->get('thanks_redirect_url');
    }

    function onThanksPage(Am_Event $e)
    {
        if ($e->getController()->getRequest()->getParam('skip_thanks_redirect')) return;

        /** @var Invoice $invoice */
        if(!$invoice = $e->getInvoice()) return;

        $url = $this->getConfig('url');
        foreach ($invoice->getProducts() as $pr) {
            if ($_ = $pr->data()->get('thanks_redirect_url')) {
                $url = $_;
                break;
            }
        }

        $t = new Am_SimpleTemplate();
        $t->assignStdVars();
        $t->assign('invoice', $invoice);
        $t->assign('user', $invoice->getUser());
        $t->assign('id', $invoice->getSecureId('THANKS'));
        if ($product = $invoice->getItem(0)->tryLoadProduct()) {
            $t->assign('product', $product);
        }

        if ($url = $t->render($url)) {
            Am_Mvc_Response::redirectLocation($url);
        }
    }
}