<?php

class SavedSearch extends Am_Record
{
    public function insert($reload = true)
    {
        $table_name = $this->getTable()->getName();
        $max = $this->getAdapter()->selectCell("SELECT MAX(sort_order) FROM {$table_name}");
        $this->sort_order = $max + 1;
        return parent::insert($reload);
    }
}

class SavedSearchTable extends Am_Table
{
    protected $_key = 'saved_search_id';
    protected $_table = '?_saved_search';
    protected $_recordClass = 'SavedSearch';

    public function getOptions($class = null)
    {
        return $this->_db->selectCol(
            "SELECT saved_search_id as ARRAY_KEY, `name` FROM {$this->_table} {WHERE class=?} ORDER BY sort_order",
            $class ?: DBSIMPLE_SKIP
        );
    }
}