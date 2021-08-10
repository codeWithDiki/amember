<?php

/**
 * Fake form - just display href in tabs list
 */
class Am_Form_Setup_Link extends Am_Form_Setup
{
    function setUrl($url)
    {
        $this->_url = $url;
        return $this;
    }
    function getUrl()
    {
        return $this->_url;
    }
    function renderTitle()
    {
        return $this->title;
    }

    public function initElements()
    {
        Am_Mvc_Response::redirectLocation($this->getUrl());
    }
}