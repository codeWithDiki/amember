<?php

class UserNote extends Am_Record_WithData
{
    public function insert($reload = true)
    {
        parent::insert($reload);
        $this->getDi()->hook->call(new Am_Event_UserNoteAfterInsert($this));
        return $this;
    }
}

class UserNoteTable extends Am_Table_WithData
{
    protected $_key = 'note_id';
    protected $_table = '?_user_note';
    protected $_recordClass = 'UserNote';

    function selectLast($num, $dateThreshold = null)
    {
        return $this->selectObjects("SELECT n.*, u.name_f, u.name_l, u.login, u.email,
            a.login AS a_login, a.name_f AS a_name_f, a.name_l AS a_name_l
            FROM ?_user_note n
            LEFT JOIN ?_user u ON n.user_id = u.user_id
            LEFT JOIN ?_admin a ON n.admin_id = a.admin_id
            {WHERE n.dattm > ?}
            ORDER BY n.dattm DESC LIMIT ?d",
            $dateThreshold ?: DBSIMPLE_SKIP, $num);
    }
}