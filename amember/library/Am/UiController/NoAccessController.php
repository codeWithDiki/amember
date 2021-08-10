<?php

class NoAccessController extends Am_Mvc_Controller
{
    function liteAction()
    {
        $this->noAccess($this->getParam('title', ___('protected area')));
    }

    function folderAction()
    {
        if (!$id = $this->getInt('id')) {
            throw new Am_Exception_InputError("Empty folder#");
        }
        $folder = $this->getDi()->folderTable->load($id);

        // Check if login cookie exists. If not, user is not logged in and should be redirected to login page.
        $pl = $this->getDi()->plugins_protect->loadGet('new-rewrite');

        /**
         * handle case with remember me cookie
         */
        if (($user = $this->getDi()->auth->getUser()) && $folder->hasAccess($user)) {
            Am_Mvc_Response::redirectLocation($this->url('protect/new-rewrite', [
                    'f' => $id,
                    'url' => $this->getParam('url', $folder->getUrl()),
                    'host' => $this->getParam('host'),
                    'ssl' => $this->getParam('ssl')
            ], false));
        }

        // User will be there only if file related to folder doesn't exists.
        // So if main file exists, this means that user is logged in but don't have an access.
        // If main file doesn't exists, redirect user to new-rewrite in order to recreate it.
        // Main file will be created even if user is not active.

        if (is_file($pl->getFilePath($pl->getEscapedCookie())))
        {
            if ($folder->no_access_url) {
                Am_Mvc_Response::redirectLocation($folder->no_access_url);
            } else {
                $this->noAccess(___("Folder %s (%s)", $folder->title, $folder->url));
            }
        } else {
            Am_Mvc_Response::redirectLocation($this->url('protect/new-rewrite', [
                    'f' => $id,
                    'url' => $this->getParam('url', $folder->getUrl()),
                    'host' => $this->getParam('host'),
                    'ssl' => $this->getParam('ssl')
            ], false));
        }
    }

    function contentAction()
    {
        if (!$id = $this->getInt('id')) {
            throw new Am_Exception_InputError("Empty folder#");
        }
        $type = filterId($this->getParam('type'));

        $registry = $this->getDi()->resourceAccessTable->getAccessTables();
        if (isset($registry[$type]) && ($r = $registry[$type]->load($id, false))) {
            $title = ___($r->getLinkTitle());
        } else {
            $title = ___("Protected Content [%s-%d]", $type, $id);
        }
        $this->noAccess($title);
    }

    protected function noAccess($title)
    {
        if (
            ($pid = $this->getDi()->config->get('403_page'))
            && ($p = $this->getDi()->pageTable->load($pid, false))
        ) {
            echo $p->render($this->getDi()->view, $this->getDi()->auth->getUserId() ? $this->getDi()->auth->getUser() : null);
        } else {
            $this->view->accessObjectTitle = $title;
            $this->view->orderUrl = $this->url('signup', false);
            $this->view->useLayout = !$this->getRequest()->isXmlHttpRequest();
            $this->view->display('no-access.phtml');
        }
    }
}
