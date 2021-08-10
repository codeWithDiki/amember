<?php

class Am_View_Helper_Breadcrumbs extends Zend_View_Helper_Abstract
{
    protected $path = [];

    public function breadcrumbs()
    {
        return $this->path ? $this->view->partial('_breadcrumbs.phtml', [
            'path' => $this->path
        ]) : '';
    }

    public function setPath($path)
    {
        $this->path = $path;
    }
}