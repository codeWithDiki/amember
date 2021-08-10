<?php

class AdminSavedSearchController extends Am_Mvc_Controller_Grid
{
    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->hasPermission('grid_u');
    }

    public function createGrid()
    {
        $ds = new Am_Query(new Am_Table(null, '?_saved_search', 'saved_search_id'));
        $ds->addWhere('class=?', 'Am_Query_User')
            ->setOrder('sort_order');

        $grid = new Am_Grid_Editable('_ss', ___('Saved Search'), $ds, $this->_request, $this->view);
        $grid->setPermissionId(Am_Auth_Admin::PERM_LOGGED_IN);
        $grid->addField(new Am_Grid_Field('name', ___('Title')));
        $grid->actionsClear();
        $grid->actionAdd(new Am_Grid_Action_Url('search', ___('Search'), 'admin-users?_u_search_load={saved_search_id}'))
            ->setTarget('_top');
        $grid->actionAdd(new Am_Grid_Action_Delete());
        $grid->actionAdd(new Am_Grid_Action_LiveEdit('name'));
        $grid->actionAdd(new Am_Grid_Action_Sort_SavedSearch());

        return $grid;
    }
}

class Am_Grid_Action_Sort_SavedSearch extends Am_Grid_Action_Sort_Abstract
{
    protected function setSortBetween($item, $after, $before)
    {
        $this->_simpleSort(Am_Di::getInstance()->savedSearchTable, $item, $after, $before, 'class');
    }
}