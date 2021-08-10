<?php

class Am_Widget_AffGeneralLink extends Am_Widget
{
    protected $id = 'aff-general-link';
    protected $path = 'aff-general-link.phtml';

    public function getTitle()
    {
        return ___('Your General Affiliate Link');
    }

    public function prepare(Am_View $view)
    {
        if (!$this->getDi()->auth->getUser())
            return false;

        $module = $this->getDi()->modules->get('aff');

        $this->view->assign('canUseCustomRedirect', $module->canUseCustomRedirect($this->getDi()->user));
        $this->view->assign('generalLink', $module->getGeneralAffLink($this->getDi()->auth->getUser()));
    }
}