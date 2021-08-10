<?php

trait Am_Grid_Action_SimpleSort
{
    protected function _simpleSort(Am_Table $table, $item, $after = null, $before = null , $cluster = null)
    {
        $after = $after ? $after['id'] : null;
        $before = $before ? $before['id'] : null;
        $id = $item['id'];

        $table_name = $table->getName();
        $pk = $table->getKeyField();

        $db = Am_Di::getInstance()->db;
        $item = $table->load($id);
        if ($before) {
            $beforeItem = $table->load($before);

            $sign = $beforeItem->sort_order > $item->sort_order ?
                '-':
                '+';

            $newSortOrder = $beforeItem->sort_order > $item->sort_order ?
                $beforeItem->sort_order-1:
                $beforeItem->sort_order;

            $db->query("UPDATE $table_name
                SET sort_order=sort_order{$sign}1 WHERE
                sort_order BETWEEN ? AND ? AND $pk<>?{ AND ?#=?}",
                min($newSortOrder, $item->sort_order),
                max($newSortOrder, $item->sort_order),
                $id, ($cluster ?: DBSIMPLE_SKIP),
                ($cluster ? $item->$cluster : DBSIMPLE_SKIP));

            $db->query("UPDATE $table_name SET sort_order=? WHERE $pk=?", $newSortOrder, $id);

        } elseif ($after) {
            $afterItem = $table->load($after);

            $sign = $afterItem->sort_order > $item->sort_order ?
                '-':
                '+';

            $newSortOrder = $afterItem->sort_order > $item->sort_order ?
                $afterItem->sort_order:
                $afterItem->sort_order+1;

            $db->query("UPDATE $table_name
                SET sort_order=sort_order{$sign}1 WHERE
                sort_order BETWEEN ? AND ? AND $pk<>?{ AND ?#=?}",
                min($newSortOrder, $item->sort_order),
                max($newSortOrder, $item->sort_order),
                $id, ($cluster ?: DBSIMPLE_SKIP),
                ($cluster ? $item->$cluster : DBSIMPLE_SKIP));

            $db->query("UPDATE $table_name SET sort_order=? WHERE $pk=?", $newSortOrder, $id);
        }
    }
}