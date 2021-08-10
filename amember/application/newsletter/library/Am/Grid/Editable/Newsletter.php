<?php

class Am_Grid_Editable_Newsletter extends Am_Grid_Editable_Content
{
    public function __construct(Am_Mvc_Request $request, Am_View $view)
    {
        parent::__construct($request, $view);
        $a = new Am_Grid_Action_Callback('_refresh', ___('Refresh 3-rd party lists'), [$this, 'doRefreshLists'], Am_Grid_Action_Abstract::NORECORD);
        $this->actionAdd($a);
        $this->actionAdd(new Am_Grid_Action_NewsletterSubscribeAll());
        $this->actionGet('delete')->setIsAvailableCallback([$this, 'canDelete']);
        $this->refreshLists(false); // refresh if expired
        foreach ($this->getActions() as $action) {
            $action->setTarget('_top');
        }
        $this->setFilter(new Am_Grid_Filter_Content_Common);
        $this->setEventId('gridNewsletter');
    }

    function canDelete(NewsletterList $record)
    {
        try {
            $pl = $this->getDi()->plugins_newsletter->loadGet($record->plugin_id);
            return !$pl->canGetLists();
        }
        catch (Exception $e)
        {
            return true;
        }
    }

    public function init()
    {
        parent::init();
        $this->addCallback(self::CB_VALUES_FROM_FORM, [$this, '_valuesFromForm']);
    }

    public function _valuesFromForm(array & $vars)
    {
        if (!empty($vars['_vars'][$vars['plugin_id']]))
            $this->getRecord()->setVars($vars['_vars'][$vars['plugin_id']]);
    }

    public function valuesToForm()
    {
        $ret = parent::valuesToForm();
        if (!empty($ret['plugin_id']))
            $ret['_vars'][$ret['plugin_id']] = $this->getRecord()->getVars();
        return $ret;
    }

    public function doRefreshLists(Am_Grid_Action_Callback $action)
    {
        $this->refreshLists(true);
        echo ___('Done');
        echo "<br /><br />";
        echo $action->renderBackButton(___('Continue'));
    }

    public function refreshLists($force=true)
    {
        $this->getDi()->newsletterListTable->disableDisabledPlugins(
            $this->getDi()->plugins_newsletter->getEnabled());
        foreach ($this->getDi()->plugins_newsletter->loadEnabled()->getAllEnabled() as $pl)
        {
            if (!$pl->canGetLists()) continue;
            $k = 'newsletter_plugins_' . $pl->getId() . '_lists';
            if (!$force && $this->getDi()->store->get($k))
                continue; // it is stored
            $lists = $pl->getLists();
            $this->getDi()->newsletterListTable->syncLists($pl, $lists);
            $this->getDi()->store->set($k, serialize($lists), '+1 hour');
        }
    }

    public function getLists()
    {
        $ret = [];
        foreach ($this->getDi()->plugins_newsletter->loadEnabled()->getAllEnabled() as $pl)
        {
            if (!$pl->canGetLists()) continue;
            $k = 'newsletter_plugins_' . $pl->getId() . '_lists';
            $s = $this->getDi()->store->get($k);
            if ($s)
                $ret[$pl->getId()] = (array)unserialize($s);
        }
        return $ret;
    }

    protected function initGridFields()
    {
        $this->addField('title', ___('Title'))->setRenderFunction([$this, 'renderAccessTitle']);
        $this->addField('plugin_id', ___('Plugin'));
        $this->addField('plugin_list_id', ___('Plugin List Id'));
        $this->addField('auto_subscribe', ___('Auto'));
        $this->addField('subscribed_users', ___('Subscribers'))
            ->addDecorator(new Am_Grid_Field_Decorator_Link('admin-users/index?_u_search[-newsletters][val][]={list_id}'));
        $this->actionAdd(new Am_Grid_Action_LiveCheckbox('auto_subscribe'));
        parent::initGridFields();
        $this->removeField('_link');
    }

    protected function createAdapter()
    {
        $q = new Am_Query(Am_Di::getInstance()->newsletterListTable);
        $q->addWhere('IFNULL(disabled,0)=0');
        $q->leftJoin('?_newsletter_user_subscription', 's', 's.list_id = t.list_id AND s.is_active > 0');
        $q->leftJoin('?_user', 'u', 's.user_id=u.user_id');
        $q->addField('COUNT(IF(u.unsubscribed=0, s.list_id, NULL))', 'subscribed_users');
        return $q;
    }

    function createForm()
    {
        $r = $this->getRecord();
        $form = new Am_Form_Admin($this->getContentGridId() . '-' . ($r->isLoaded() ? $r->pk() : 'new'));

        $plugins = $this->getDi()->plugins_newsletter->loadEnabled()->getAllEnabled();
        if ($r->isLoaded())
        {
            if ($r->plugin_id)
            {
                $group = $form->addFieldset();
                $group->setLabel(ucfirst($r->plugin_id));
                if ($r->plugin_list_id) {
                    $form->addStatic()->setLabel(___('Plugin List Id'))->setContent(Am_Html::escape($r->plugin_list_id));
                }
                $form->addHidden('plugin_id', ['value' => $r->plugin_id]);
            }
        } else {
            $sel = $form->addSelect('plugin_id')->setLabel(___('Plugin'));
            foreach ($plugins as $pl)
            {
                if (!$pl->canGetLists())
                    $sel->addOption($pl->getTitle(), $pl->getId());
            }
            $form->addText('plugin_list_id')->setLabel(___("Plugin List Id\nvalue required"));

            $form->addScript()->setScript(<<<END
jQuery(function(){
    function showHidePlugins(el, skip)
    {
        var txt = jQuery("input[name='plugin_list_id']");
        var enabled = el.val() != 'standard';
        txt.closest(".am-row").toggle(enabled);
        if (enabled) {
            txt.rules("add", { required : true});
        } else if(skip) {
            txt.rules("remove", "required");
        }
    }
    jQuery("select[name='plugin_id']").change(function(){
        showHidePlugins(jQuery(this), true);
    });
    showHidePlugins(jQuery("select[name='plugin_id']"), false);
});
END
);
        }

        $form->addText('title', ['class' => 'am-el-wide translate'])->setLabel(___('Title'))->addRule('required');
        $form->addText('desc', ['class' => 'am-el-wide'])->setLabel(___('Description'));
        $form->addAdvCheckbox('hide')->setLabel(___("Hide\n" . "do not display this item in members area"));

        $form->addAdvCheckbox('auto_subscribe')->setLabel(___("Auto-Subscribe users to list\n".
            "once it becomes accessible for them.\n"
            . "Disable that setting if you want to get user consent about being added to list"));
        foreach ($plugins as $pl)
        {
            $group = $form->addElement(new Am_Form_Container_PrefixFieldset('_vars'))->setId('headrow-' . $pl->getId() .'-pluginoptions');
            $gr = $group->addElement(new Am_Form_Container_PrefixFieldset($pl->getId()));
            $pl->getIntegrationFormElements($gr);
        }

        $group = $form->addFieldset('access')->setLabel(___('Access'));
        $group->addElement(new Am_Form_Element_ResourceAccess)->setName('_access')
            ->setLabel(___('Access Permissions'))
            ->setAttribute('without_free_without_login', 'true')
            ->setAttribute('without_period', 'true')
            ->setAttribute('with_stop', 'true');

        return $form;
    }
}