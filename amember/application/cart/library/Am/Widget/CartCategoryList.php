<?php

class Am_Widget_CartCategoryList extends Am_Widget
{
    protected $path = 'category-list.phtml';
    protected $id = 'cart-categories-list';

    public function getTitle()
    {
        return ___('Cart: Category List');
    }

    public function getCategoryCode()
    {
        return $this->getDi()->request->getFiltered('c',
            $this->getDi()->request->getQuery('c'));
    }
    public function prepare(Am_View $view)
    {
        $module = $this->getDi()->modules->get('cart');

        $view->productCategories = [];
        foreach ($this->getDi()->productCategoryTable->findBy() as $cat) {
            $view->productCategories[$cat->pk()] = $cat;
        }
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