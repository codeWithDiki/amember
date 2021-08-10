<?php

class AgreementController extends Am_Mvc_Controller
{
    function indexAction()
    {
        $type = $this->getParam('type');
        $doc = $this->getDi()->agreementTable->getCurrentByType($type);

        if (!$doc) {
            throw new Am_Exception_NotFound;
        }

        if (isset($_GET['text'])) {
            echo $doc->body;
        } else {
            $this->view->headStyle()->appendStyle(".am-common pre {overflow: auto;}");
            $this->view->title = ___($doc->title);
            $this->view->content = $doc->body;
            $this->view->meta_title = ___($doc->meta_title ? $doc->meta_title : $doc->title);
            if ($doc->meta_keywords)
                $this->view->headMeta()->setName('keywords', $doc->meta_keywords);
            if ($doc->meta_description)
                $this->view->headMeta()->setName('description', $doc->meta_description);
            if ($doc->meta_robots)
                $this->view->headMeta()->setName('robots', $doc->meta_robots);
            $this->view->display('layout.phtml');
        }
    }
}