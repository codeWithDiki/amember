<?php

class Am_Grid_Filter_HelpdeskSnippet extends Am_Grid_Filter_Abstract
{
    protected $cOptions;

    public function __construct($cOptions)
    {
        $this->cOptions = $cOptions;
    }

    protected function applyFilter()
    {
        if ($cat = $this->getParam('filter')) {
            $this->grid->getDataSource()->getDataSourceQuery()
                ->addWhere('category=?', $cat);
        }
    }

    public function renderInputs()
    {
        return $this->renderInputSelect('filter', array_merge_recursive(['' => ___('-- Category')], $this->cOptions));
    }
}