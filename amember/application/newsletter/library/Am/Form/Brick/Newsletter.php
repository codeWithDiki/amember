<?php

class Am_Form_Brick_Newsletter extends Am_Form_Brick
{
    protected $labels = [
        'Subscribe to Site Newsletters' => 'Subscribe to Site Newsletters',
    ];

    protected $hideIfLoggedInPossible = self::HIDE_DESIRED;

    public function __construct($id = null, $config = null)
    {
        $this->name = ___('Newsletter');
        parent::__construct($id, $config);
    }

    public function isAcceptableForForm(Am_Form_Bricked $form)
    {
        return $form instanceof Am_Form_Signup;
    }

    public function insertBrick(HTML_QuickForm2_Container $form)
    {
        if ($this->getConfig('type') == 'checkboxes')
        {
            $options = Am_Di::getInstance()->newsletterListTable->getUserOptions();
            if ($enabled = $this->getConfig('lists')) {
                $_ = $options;
                $options = [];
                foreach ($enabled as $id) {
                    $options[$id] = $_[$id];
                }
            }
            $user = Am_Di::getInstance()->auth->getUser();
            if ($this->getConfig('hide_if_subscribed') && $user) {
                /** @var NewsletterUserSubscriptionTable $table */
                $subscribed_ids = Am_Di::getInstance()->newsletterUserSubscriptionTable->getSubscribedIds($user->pk());
                $options = array_filter($options, function($id) use ($subscribed_ids) { return !in_array($id, $subscribed_ids);}, ARRAY_FILTER_USE_KEY);
            }
            if (!$options) return; // no lists enabled
            $group = $form->addGroup('_newsletter')->setLabel($this->___('Subscribe to Site Newsletters'));
            if ($this->getConfig('required')) {
                $group->addClass('am-row-required');
            }
            $group->setSeparator("<br />\n");
            foreach ($options as $list_id => $title)
            {
                $c = $group->addAdvCheckbox($list_id)->setContent($title);
                if (!$this->getConfig('unchecked')) {
                    $c->setAttribute('checked');
                }
                if ($this->getConfig('required')) {
                    $c->addRule('required');
                }
            }
        } else {
            $data = [];
            if ($this->getConfig('no_label')) {
                $data['content'] = $this->___('Subscribe to Site Newsletters');
            }
            $c = $form->addAdvCheckbox('_newsletter', [], $data);
            if (!$this->getConfig('no_label')) {
                $c->setLabel($this->___('Subscribe to Site Newsletters'));
            }
            if (!$this->getConfig('unchecked')) {
                $c->setAttribute('checked');
            }
            if ($this->getConfig('required')) {
                $c->addRule('required');
            }
        }
    }

    public function initConfigForm(Am_Form $form)
    {
        $el = $form->addSelect('type', ['id'=>'newsletter-type-select'])->setLabel(___('Type'));
        $el->addOption(___('Single Checkbox'), 'checkbox');
        $el->addOption(___('Checkboxes for Selected Lists'), 'checkboxes');

        $form->addAdvCheckbox('no_label', ['id' => 'newsletter-am-no-label'])
            ->setLabel(___("Hide Label"));
        $form->addScript()
            ->setScript(<<<CUT
jQuery(function(){
    jQuery('#newsletter-type-select').change(function(){
        jQuery('#newsletter-am-no-label').closest('.am-row').toggle(jQuery(this).val() == 'checkbox')
    }).change();
})
CUT
            );

        $lists = $form->addSortableMagicSelect('lists', ['id'=>'newsletter-lists-select'])
            ->setLabel(___("Lists\n" .
                'All List will be displayed if none selected'));
        $lists->loadOptions(Am_Di::getInstance()->newsletterListTable->getAdminOptions());
        $form->addScript()->setScript(<<<CUT
jQuery(document).ready(function($) {
    jQuery("#newsletter-type-select").change(function(){
        var val = jQuery(this).val();
        jQuery("#row-newsletter-lists-select").toggle(val == 'checkboxes');
        jQuery("#row-hide-if-subscribed").toggle(val == 'checkboxes');
    }).change();
});
CUT
            );
        $form->addAdvCheckbox('hide_if_subscribed', ['id' => 'hide-if-subscribed'])
            ->setLabel('Hide List if User is already subscribed');

        $form->addAdvCheckbox('unchecked')
            ->setLabel(___("Default unchecked\n" .
                'Leave unchecked if you want newsletter default to be checked'));

        $form->addAdvCheckbox('required')
            ->setLabel(___("Subscription is required?"));
    }
}