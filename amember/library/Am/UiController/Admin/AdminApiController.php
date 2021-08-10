<?php

class AdminApiController extends Am_Mvc_Controller
{
    public function checkAdminPermissions(Admin $admin)
    {
        return true;
    }

    function __call($methodName, $args)
    {
        if (preg_match('#^(.+)Action$#', $methodName, $regs))
        {
            return $this->callAction($regs[1]);
        } else {
            throw new Am_Exception_InternalError("Invalid method called {$methodName} in " . __METHOD__);
        }
    }

    function callAction($action)
    {
        $this->_response->setHeader('Content-Type', 'application/json');

        $ret = [];

        $pr = $this->getDi()->productTable;
        foreach ($pr->findBy([]) as $pr)
        {
            $x = array_merge(['_id' => $pr->pk(), ], $pr->toArray());
            $ret[ ] = $x;
        }
        $this->_response->setBody(json_encode($ret, JSON_PRETTY_PRINT));

    }
}
