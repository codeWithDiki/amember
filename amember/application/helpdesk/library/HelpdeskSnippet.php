<?php

/**
 * Class represents records from table helpdesk_snippet
 * {autogenerated}
 * @property int $snippet_id
 * @property string $title
 * @property string $content
 * @see Am_Table
 */
class HelpdeskSnippet extends Am_Record
{
    public function insert($reload = true)
    {
        $table_name = $this->getTable()->getName();
        $max = $this->getAdapter()->selectCell("SELECT MAX(sort_order) FROM {$table_name}");
        $this->sort_order = $max + 1;
        return parent::insert($reload);
    }
}

class HelpdeskSnippetTable extends Am_Table
{
    protected $_key = 'snippet_id';
    protected $_table = '?_helpdesk_snippet';
    protected $_recordClass = 'HelpdeskSnippet';

    function getCategories()
    {
        return $this->_db->selectCol(<<<CUT
            SELECT DISTINCT category, category AS ?
                FROM ?_helpdesk_snippet
                WHERE category IS NOT NULL
                ORDER BY category
CUT
            , DBSIMPLE_ARRAY_KEY);
    }
}