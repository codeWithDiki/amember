<?php

class Am_Widget_AffIntro extends Am_Widget
{
    protected $id = 'aff-intro';
    protected $path = 'aff-intro.phtml';

    public function getTitle()
    {
        return '';
    }

    public function prepare(Am_View $view)
    {
        if (!$this->getDi()->auth->getUser())
            return false;

        $module = $this->getDi()->modules->get('aff');

        $this->view->assign('intro', $module->getConfig('intro'));
    }
}