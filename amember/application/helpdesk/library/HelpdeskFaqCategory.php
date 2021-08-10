<?php

class HelpdeskFaqCategory extends Am_Record
{
    protected $_childNodes = [];

    function getChildNodes()
    {
        return $this->_childNodes;
    }

    function createChildNode()
    {
        $c = new self($this->getTable());
        $c->parent_id = $this->pk();
        if (!$c->parent_id)
            throw new Am_Exception_InternalError("Could not add child node to not-saved object in ".__METHOD__);
        $this->_childNodes[] = $c;
        return $c;
    }

    public function fromRow(array $vars)
    {
        if (isset($vars['childNodes']))
        {
            foreach ($vars['childNodes'] as $row)
            {
                $r = new self($this->getTable());
                $r->fromRow($row);
                $this->_childNodes[] = $r;
            }
            unset($vars['childNodes']);
        }
        return parent::fromRow($vars);
    }

    public function save()
    {
        if (!empty($this->parent_id))
            if ($this->pk() == $this->parent_id) {
                $this->parent_id = 0;
            }
        parent::save();
    }
}

class HelpdeskFaqCategoryTable extends Am_Table
{
    protected $_key = 'faq_category_id';
    protected $_table = '?_helpdesk_faq_category';
    protected $_recordClass = 'HelpdeskFaqCategory';

    function getTree($cast_objects = true)
    {
        $ret = [];
        foreach ($this->_db->select("SELECT
            faq_category_id AS ARRAY_KEY,
            parent_id AS PARENT_KEY, pc.*
            FROM ?_helpdesk_faq_category AS pc
            ORDER BY 0+sort_order, title") as $r)
        {
            $ret[] = $cast_objects ? $this->createRecord($r) : $r;
        }
        return $ret;
    }

    function getOptions()
    {
        $ret = [];
        $sql = <<<CUT
                SELECT faq_category_id AS ARRAY_KEY,
                parent_id AS PARENT_KEY,
                faq_category_id, title
                FROM ?_helpdesk_faq_category
                ORDER BY parent_id, 0+sort_order, title
CUT;
        $rows = $this->_db->select($sql);
        foreach ($rows as $id => $r){
            $this->renderNode($r, $id, '', $ret);
        }

        return $ret;
    }

    protected function renderNode($r, $id, $title, &$ret)
    {
        $title .= ($title ? '/' : '') . $r['title'];
        $ctitle = $title;

        $ret[$id] = $title;
        foreach($r['childNodes'] as $cid => $c) {
            $this->renderNode($c, $cid, $ctitle, $ret);
        }
    }

    function moveNodes($fromId, $toId)
    {
        $this->_db->query("UPDATE {$this->_table} SET parent_id=?d WHERE parent_id=?d",
            $toId, $fromId);
    }

    public function delete($key)
    {
        parent::delete($key);
        $this->_db->query("UPDATE ?_helpdesk_faq SET faq_category_id=NULL  WHERE faq_category_id=?d", $key);
    }
}