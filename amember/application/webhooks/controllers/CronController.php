<?php

class Webhooks_CronController extends Am_Mvc_Controller
{
    public function indexAction()
    {
        set_time_limit(0);
        ignore_user_abort(true);
        ini_set('memory_limit', AM_HEAVY_MEMORY_LIMIT);
        
        $this->getModule()->runCron();
    }
}