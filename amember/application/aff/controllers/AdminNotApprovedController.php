<?php

class Aff_AdminNotApprovedController extends Am_Mvc_Controller_Grid
{
    function checkAdminPermissions(Admin $admin)
    {
        return true;
    }

    function createGrid()
    {
        $ds = new Am_Query_User();
        $ds->leftJoin('?_data', 'd', "d.`key`='aff_await_approval' AND d.`table`='user' AND d.`id`=u.user_id")
            ->addWhere('d.`value`=?', 1)
            ->addField("CONCAT(name_f, ' ', name_l)", '_name');

        $grid = new Am_Grid_Editable('_ana', 'Affiliate Applications', $ds, $this->getRequest(), $this->view);
        $grid->addField('login', ___('Username'))
            ->addDecorator(new Am_Grid_Field_Decorator_Link($this->getDi()->view->userUrl('{user_id}'), '_top'));
        $grid->addField('_name', ___('Name'));
        $grid->addField('email', ___('E-Mail Address'));

        $grid->actionsClear();
        $grid->actionAdd(new Am_Grid_Action_AffiliateApprove('affiliate-approve', ___('Approve')));
        $grid->actionAdd(new Am_Grid_Action_AffiliateDeny('affiliate-deny', ___('Deny')));

        return $grid;
    }
}

class Am_Grid_Action_AffiliateApprove extends Am_Grid_Action_Abstract
{
    protected $attributes = ['class' => 'aff-approve'];

    function run()
    {
        $r = $this->grid->getRecord();
        $r->data()->set('aff_await_approval', null);
        $r->is_affiliate = 1;
        $r->save();

        $this->grid->redirectBack();
    }
}

class Am_Grid_Action_AffiliateDeny extends Am_Grid_Action_Abstract
{
    function run()
    {
        if (!$_ = $this->grid->getRequest()->get('deny_reason')) {
            echo $this->renderTitle();
            echo $this->renderDeny();
        } else {
            $this->_do($_);
            $this->grid->redirectBack();
        }
    }

    function _do($reason)
    {
        $r = $this->grid->getRecord();
        $r->data()->set('aff_await_approval', null);
        $r->save();

        if ($et = Am_Mail_Template::load('aff.manually_approve_denied', $r->lang)) {
            $et->setReason($reason);
            $et->setAffiliate($r);
            $et->setUser($r);
            $et->send($r);
        }
    }

    public function renderDeny()
    {
        $vars = $this->grid->getCompleteRequest()->toArray();

        $hidden = Am_Html::renderArrayAsInputHiddens($vars);
        $url_yes = $this->grid->makeUrl(null);
        $id = $this->grid->getId();
        $back = $this->renderBackButton(___('No, cancel'));
        $deny = Am_Html::escape(___('Deny'));
        return <<<CUT
<div class="info">
<form method="post" action="$url_yes" style="display: inline;">
    <p><span class="required" aria-required="true">*</span> What is denied reason?</p>
    <textarea name="{$id}_deny_reason" rows="8" cols="50"></textarea><br />
    $hidden
    <br/>
    <div class="buttons">
    <input type="submit" value="{$deny}" id='deny-action-continue' />
    $back
    </div>
</form>
</div>
<script type="text/javascript">
  jQuery('#deny-action-continue').click(function(){
    jQuery(this).closest('.am-grid-wrap').
        find('input[type=submit], input[type=button]').
        attr('disabled', 'disabled');
    jQuery(this).closest('form').submit();
    return false;
  })
</script>
CUT;
    }
}