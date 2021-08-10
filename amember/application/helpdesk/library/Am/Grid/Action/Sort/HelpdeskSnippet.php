<?php

class Am_Grid_Action_Sort_HelpdeskSnippet extends Am_Grid_Action_Sort_Abstract
{
    protected function setSortBetween($item, $after, $before)
    {
        $this->_simpleSort(Am_Di::getInstance()->helpdeskSnippetTable, $item, $after, $before);
    }
}