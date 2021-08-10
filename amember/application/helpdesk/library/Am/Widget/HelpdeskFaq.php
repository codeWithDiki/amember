<?php

class Am_Widget_HelpdeskFaq extends Am_Widget
{
    protected $path = 'helpdesk/faq.phtml';
    protected $id = 'helpdesk-faq';
    protected $bpath = [];

    public function getTitle()
    {
        return ___('FAQ');
    }

    public function prepare(Am_View $view)
    {
        $this->view = $view;
        $this->bpath = [];

        if (!empty($view->title)) // display the item
        {
            $faq = $this->getDi()->helpdeskFaqTable->findFirstByTitle($view->title);
            $this->bpath = [$this->getDi()->url('helpdesk/faq', false) => ___('FAQ')];
            $cats = [];
            $faq_category_id = $faq->faq_category_id;
            while ($faq_category_id) {
                $cat = $this->getDi()->helpdeskFaqCategoryTable->load($faq_category_id);
                $faq_category_id = $cat->parent_id;
                $cats[] = $cat->title;
            }
            foreach (array_reverse($cats) as $title) {
                $this->bpath[$this->getDi()->url('helpdesk/faq/c/' . urldecode($title),
                    false)] = $title;
            }
            $view->faq = $faq;
            $this->path = 'helpdesk/faq-item.phtml'; // use other template
        } else { // display index or category
            $this->view->catActive = $catActive = null;
            if ($view->cat) {
                if (!$catActive = $this->getDi()->helpdeskFaqCategoryTable->findFirstByTitle($view->cat)) {
                    throw new Am_Exception_NotFound;
                }
                $this->view->catActive = $catActive;
                $cats = [];
                $faq_category_id = $catActive->parent_id;
                while ($faq_category_id) {
                    $cat = $this->getDi()->helpdeskFaqCategoryTable->load($faq_category_id);
                    $faq_category_id = $cat->parent_id;
                    $cats[] = $cat->title;
                }
                $this->view->categories = $this->getDi()->db->selectCol("SELECT title FROM ?_helpdesk_faq_category WHERE parent_id=?", $catActive->pk());
            } else {
                $this->view->categories = $this->getDi()->db->selectCol("SELECT title FROM ?_helpdesk_faq_category WHERE parent_id IS NULL");
            }

            $this->view->faq = $this->getDi()->helpdeskFaqTable->findBy([
                    'faq_category_id' => $catActive ? $catActive->pk() : null], null, null, 'sort_order');
            if ($view->cat) {
                $this->bpath = [$this->getDi()->url('helpdesk/faq', false) => ___('FAQ')];
                foreach (array_reverse($cats) as $title) {
                    $this->bpath[$this->getDi()->url('helpdesk/faq/c/' . urldecode($title),
                        false)] = $title;
                }
            }
        }
    }

    /**
     * Function specific for aMember layout - returns path for breadcrump display
     */
    function getBreadcrumpsPath()
    {
        return $this->bpath;
    }
}