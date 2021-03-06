<?php

/**
 * Class represents records from table helpdesk_category
 * {autogenerated}
 * @property int $category_id
 * @property int $owner_id
 * @property string $title
 * @property int $is_disabled
 * @see Am_Table
 */
class HelpdeskCategory extends Am_Record
{
    const ACCESS_TYPE = 'helpdesk-category';

    public function insert($reload = true)
    {
        $table_name = $this->getTable()->getName();
        $max = $this->getAdapter()->selectCell("SELECT MAX(sort_order) FROM {$table_name}");
        $this->sort_order = $max + 1;
        return parent::insert($reload);
    }

    public function delete()
    {
        $ret = parent::delete();
        $table_name = $this->getTable()->getName();
        $this->getAdapter()->query("UPDATE {$table_name}
            SET sort_order=sort_order-1
            WHERE sort_order>?", $this->sort_order);
        return $ret;
    }
}

class HelpdeskCategoryTable extends Am_Table
{
    protected $_key = 'category_id';
    protected $_table = '?_helpdesk_category';
    protected $_recordClass = 'HelpdeskCategory';

    function getOptions($include_disabled=false)
    {
        return $this->getAdapter()->selectCol("SELECT category_id AS ARRAY_KEY, title
            FROM ?_helpdesk_category
            WHERE 1 {AND is_disabled=?}
            ORDER BY sort_order",
            $include_disabled ? DBSIMPLE_SKIP : 0);
    }
}