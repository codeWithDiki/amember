<?php

interface Am_Mvc_Request_Interface {
    
    public function getParam($key, $default = null);
    
}