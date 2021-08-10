<?php

class Am_Widget_CartCategorySelect extends Am_Widget
{
    protected $id = 'cart-category-select';
    protected $path = 'category-select.phtml';

    public function getTitle()
    {
        return ___('Category');
    }

    public function getCategoryCode()
    {
        return $this->getDi()->request->getFiltered('c',
            $this->getDi()->request->getQuery('c'));
    }

    public function prepare(Am_View $view)
    {
        $module = $this->getDi()->modules->get('cart');

        $q = $module->getProductsQuery();
        $pids = array_map(function($_) {return $_->pk();}, $q->selectAllRecords());

        $this->view->productCategorySelected = $this->getCategoryCode();
        $this->view->productCategoryOptions = [null => ___('-- Home --')] +
            array_map([$this, '___'], $this->getDi()->productCategoryTable->getUserSelectOptions([
                ProductCategoryTable::EXCLUDE_EMPTY => true,
                ProductCategoryTable::COUNT => true,
                ProductCategoryTable::EXCLUDE_HIDDEN => true,
                ProductCategoryTable::INCLUDE_HIDDEN => $module->getHiddenCatCodes(),
                ProductCategoryTable::ROOT => $module->getConfig('category_id', null),
                ProductCategoryTable::SCOPE => $pids
            ]));

        if (count($this->view->productCategoryOptions) == 1) return false;
    }

    function ___($title)
    {
        $_ = array_map('___', explode('/', $title));
        $last = array_pop($_);
        if (preg_match('/^(.*) \((\d+)\)$/', $last, $m)) {
            $last = sprintf('%s (%d)',___($m[1]), $m[2]);
        }
        array_push($_, $last);
        return implode('/', $_);
    }
}