<?php

/**
 * Base class for step-by-step controller
 * Working in admin area for admin
 * And doing some amember-file-related operations
 *
 */
abstract class Am_Mvc_Controller_Upgrade_Base extends Am_Mvc_Controller
{
    protected $steps = [];

    // do not display default layout with content
    protected $noDisplay = false;

    public function __construct(Zend_Controller_Request_Abstract $request, Zend_Controller_Response_Abstract $response, array $invokeArgs = []
    )
    {
        check_demo();
        parent::__construct($request, $response, $invokeArgs);
        $this->steps = $this->getSteps();
    }

    abstract protected function getSteps();
    abstract protected function getSessionNamespace();
    abstract protected function getStrings();

    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->isSuper();
    }


    protected function resetSession()
    {
        $this->getSession()->unsetAll();
    }

    public function indexAction()
    {
        if (@$_GET['reset'])
            $this->resetSession();
        $upgradeProcess = new Am_BatchProcessor([$this, 'doUpgrade']);
        $status = null;
        $strings = $this->getStrings();
        if ($upgradeProcess->run($status))
        {
            $this->view->title = $strings['finished_title'];
            $this->view->content .=
                '<h2>' .  $strings['finished_title'] . '</h2>' .
                "<input type='button' value='".___('Back')."' onclick='window.location=amUrl(\"/admin\")'/>";
        } else {
            $this->view->title = $strings['title'];
            $path = json_encode(REL_ROOT_URL . '/'. $this->getRequest()->getModuleName() . '/' . $this->getRequest()->getControllerName());
            $this->view->content .=
                "<br /><input type='button' onclick='window.location=$path' value='".___('Continue')."' />";
        }
        if (!$this->noDisplay)
            $this->view->display('admin/layout.phtml');
    }


    public function doUpgrade(& $context, Am_BatchProcessor $batch)
    {
        $session = $this->getSession();
        if (empty($session->step)) $this->getSession()->step = 0;
        do {
            $currentOperation = $this->steps[$session->step][0];
            $start = (int)@$session->start;
            $this->outStepHeader();
            $ret = call_user_func_array([$this, $currentOperation], [$batch, & $start]);
            $session->start = $start;
            if (!$ret)
            {
                $batch->stop();
                return false;
            }
            $this->outText(___('Done') . "<br />\n");
            $session->step = $session->step + 1;
            if ($session->step >= count($this->steps))
            {
                $session->unsetAll();
                return true;
            }
        } while ($batch->checkLimits());
    }

    protected function outStepHeader()
    {
        $step = $this->getSession()->step;
        $title = $this->steps[$step][1];
        $out = sprintf(___('Step %d of %d', $step+1, count($this->steps)));
        $out .= ' - ' . $title;
        $this->view->content .= "<h2>".$out."</h2>\n";
    }

    protected function outText($text)
    {
        $this->view->content .= $text;
    }

    public function getSession()
    {
        static $session;
        if (empty($session))
        {
            $session = $this->getDi()->session->ns($this->getSessionNamespace());
            $session->setExpirationSeconds(3600);
        }
        return $session;
    }

}