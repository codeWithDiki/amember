<?php

/**
 * Special controller to handle API action
 * IS NOT subclassed from Am_Mvc_Controller
 */
class Am_ApiController_Base
{
    /** @var Am_Di */
    protected $_di;

    function __construct(Am_Di $di)
    {
        $this->_di = $di;
    }

    /** @return Am_Di */
    function getDi()
    {
        return $this->_di;
    }
}
