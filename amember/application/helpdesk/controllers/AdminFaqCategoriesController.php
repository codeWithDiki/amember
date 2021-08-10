<?php

class Helpdesk_AdminFaqCategoriesController extends Am_Mvc_Controller_AdminCategory
{
    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->hasPermission(Bootstrap_Helpdesk::ADMIN_PERM_FAQ);
    }

    protected function getTable()
    {
        return $this->getDi()->helpdeskFaqCategoryTable;
    }

    protected function getNote()
    {
        return ___('aMember does not respect category hierarchy. Each category is absolutely independent. You can use hierarchy only to organize your categories.');
    }

    protected function getTitle()
    {
        return ___('FAQ Categories');
    }

    protected function getAddLabel()
    {
        return ___('Add FAQ Category');
    }
}