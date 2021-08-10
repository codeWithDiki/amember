<?php

class Am_Form_Element_UsersAdvancedSearch extends HTML_QuickForm2_Element
{
    protected $value;

    public function __toString()
    {
        $di = Am_Di::getInstance();

        $name = Am_Html::escape($this->getName());

        if ($this->value)
        {
            $q = new Am_Query_User;
            $q->unserialize($this->value);
            $label = Am_Html::escape($q->getDescription(false));
            $users_found = $q->getFoundRows();
            $text = ___('%s user(s) matches your search', number_format($users_found));
            $url = $di->nonce->url($di->surl('admin-advanced-search/conditions', ['_serialized' => $q->serialize()]), AdminAdvancedSearchController::NONCE_KEY);
        } else {
            $label = ___('Define');
            $text = '';
            $url = $di->nonce->url($di->surl('admin-advanced-search/conditions'), AdminAdvancedSearchController::NONCE_KEY);
        }
        $url = Am_Html::escape($url);
        $value = Am_Html::escape($this->getRawValue());
        return <<<CUT
        <span class="am-open-advanced-search-popup" data-url='$url'>
            <input type="hidden" name="$name" value="$value" id="advanced-search-hidden-input" />
            <a href='javascript:' class="am-open-advanced-search-popup-open">$label</a>
            <div class='am-advanced-search-text'>$text</div>
        </span>
CUT;
    }

    public function getType()
    {
        return 'users-advanced-search';
    }

    public function getRawValue()
    {
        return $this->value;
    }

    public function setValue($value)
    {
        $this->value = $value;
    }
}


class AdminAdvancedSearchController extends Am_Mvc_Controller
{
    const NONCE_KEY = 'admin-advanced-search';

    function checkAdminPermissions(Admin $admin)
    {
        $this->getDi()->nonce->check(self::NONCE_KEY);
        return $admin->hasPermission('grid_users');
    }

    function indexAction()
    {
        $this->view->content = $html . $grid->render();
        $this->view->display('admin/layout-blank.phtml');
    }

    function conditionsAction()
    {
        $di = $this->getDi();
        $q = new Am_Query_User();
        if ($this->_request->get('_apply_query'))
            $q->setFromRequest($this->_request);
        elseif ($_ = $this->_request->get('_serialized'))
        {
            $q->unserialize($_);
        }
        $hidden = $this->getDi()->nonce->hidden(self::NONCE_KEY);
        $hidden .= "<input type='hidden' name='_apply_query' value='1'>";
        $hidden .= "&nbsp;<input type='submit' name='_save_query' value='".___('Save')."' class='am-advanced-search-save'>";
        $html = "<div class='am-advanced-search-inline am-advanced-search'>". $q->renderForm($hidden, false) . "</div>\n";
        $users_found = null;
        $users_text = null;
        if ($q->getConditions())
        {
            $users_found = $q->getFoundRows();
            $users_text = ___('%s user(s) matches your search', number_format($users_found));
            $burl = Am_Html::escape($di->nonce->url($di->surl('admin-advanced-search/browse'), AdminAdvancedSearchController::NONCE_KEY));
            $browse = $users_found ? ("<a class='am-advanced-search-browse-open' href='javascript:' data-url='$burl'>".___('browse')."</a>") : "";
            $html .= "<div style='padding-left: 5em;' class='am-advanced-search-count'>$users_text.$browse</div>";
            $html .= "<span id='am-advanced-search-browse-users'></span>";
        }
        if ($this->_request->get('_save_query'))
        {
            $save = [
                'conditions' => $q->serialize(),
                'description' => $q->getDescription(false),
                'text' => $users_text,
            ];
            $html .= "<span id='_am_save_advanced_search' data-vars='" . json_encode($save) . "' />";
        }
        $this->view->title = "";
        $this->view->content = $html;
        $this->view->display('admin/layout-blank.phtml');
    }

    function renderUserUrl(User $user)
    {
        $url = $this->getView()->userUrl($user->user_id);
        return sprintf('<td><a href="%s" target="_blank">%s</a></td>',
            $this->escape($url), $this->escape($user->login));
    }

    function browseAction()
    {
        $withWrap = (bool) $this->_request->get('_u_wrap');
        unset($_GET['_u_wrap']);

        $searchUi = new Am_Query_Ui_Advanced();
        $searchUi->setFromRequest($this->_request);
        $ds = $searchUi->getQuery();
        $grid = new Am_Grid_ReadOnly('_u', ___('Found Users'), $ds,
            $this->_request, $this->view);
        if ($withWrap)
            $grid->isAjax(false);
        $grid->setCountPerPage(5);
        $grid->addField('login', ___('Username'))->setRenderFunction([$this, 'renderUserUrl']);
        $grid->addField('name_f', ___('First Name'));
        $grid->addField('name_l', ___('Last Name'));
        $grid->addField('email', ___('E-Mail Address'));

        $response = $this->getResponse();
        $grid->run($response);

        if (!($this->_request->isXmlHttpRequest() || $response->isRedirect()))
        {
            $view = $this->getView();
            $view->layoutNoTitle = true;
            $view->title = "";
            $view->content = $response->getBody();
            $response->clearBody();
            $view->display('admin/layout-blank.phtml');
        }
    }
}
