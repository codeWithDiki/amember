<?php

/**
 * @title aMember built-in Newsletters
 * @desc store subscribers internally and send emails within aMember Admin Control Panel
 * @setup_url default/admin-content/p/newsletter
 */

class Am_Newsletter_Plugin_Standard extends Am_Newsletter_Plugin
{
    public function changeSubscription(User $user, array $addLists, array $deleteLists)
    {
        return true;
    }
}