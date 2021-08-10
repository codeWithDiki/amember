<?php

class Am_Api_ProductProductCategory extends Am_ApiController_Table
{
    function index($request, $response, $args)
    {
        return $response->withJson($this->getDi()->productCategoryTable->getCategoryProducts());
    }
}