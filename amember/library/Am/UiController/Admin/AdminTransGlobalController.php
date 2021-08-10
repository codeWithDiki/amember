<?php

class Am_Grid_DataSource_Array_Trans extends Am_Grid_DataSource_Array
{
    /* @var TranslationDataProvider_Abstract */
    protected $tDataSource = null,
        $locale = null,
        $_order_f = null,
        $_order_d = null;

    public function __construct($locale)
    {
        $this->tDataSource = $this->createTDataSource();
        $this->locale = $locale;

        $translationData = $this->tDataSource
            ->getTranslationData($this->locale, Am_TranslationDataSource_Abstract::FETCH_MODE_ALL);
        return parent::__construct(self::prepareArray($translationData));
    }

    public static function prepareArray($translationData)
    {
        $records = [];
        foreach ($translationData as $base => $trans) {
            $record = new stdClass();
            $record->base = $base;
            $record->trans = $trans;
            $records[] = $record;
        }
        return $records;
    }

    /**
     * @return Am_TranslationDataSource_Abstract
     */
    public function getTDataSource()
    {
        return $this->tDataSource;
    }

    protected function createTDataSource()
    {
        return new Am_TranslationDataSource_PHP();
    }
}

class Am_Grid_Action_NewTrans extends Am_Grid_Action_Abstract
{
    protected $title = "Add Language";
    protected $type = self::NORECORD;

    public function run()
    {
        $form = $this->getForm();
        if ($form->isSubmitted() && $form->validate()) {

            $error = $this->grid->getDataSource()
                    ->getTDataSource()
                    ->createTranslation($this->grid->getCompleteRequest()->getParam('new_language'));
            if ($error) {
                $form->setError($error);
            } else {
                Zend_Locale::hasCache() && Zend_Locale::clearCache();
                Zend_Translate::hasCache() && Zend_Translate::clearCache();

                $this->grid->getDi()->cache->clean();
                $this->grid->redirectBack();
            }
        }
        echo $this->renderTitle();
        echo $form;
    }

    public function getForm()
    {
        $languageTranslation = Am_Locale::getSelfNames();

        $avalableLocaleList = Zend_Locale::getLocaleList();
        $existingLanguages = $this->grid->getDi()->languagesListUser;
        $languageOptions = [];

        foreach ($avalableLocaleList as $k=>$v) {
            $locale = new Zend_Locale($k);
            if (!array_key_exists($locale->getLanguage(), $existingLanguages) &&
                    isset($languageTranslation[$locale->getLanguage()])) {

                $languageOptions[$locale->getLanguage()] = "($k) {$languageTranslation[$locale->getLanguage()]}";
            }
        }

        asort($languageOptions);

        $form = new Am_Form_Admin();
        $form->setAction($this->grid->makeUrl(null));

        $form->addSelect('new_language', ['class'=>'am-combobox-fixed'])
                ->setLabel(___('Language'))
                ->loadOptions($languageOptions)
                ->setId('languageSelect');
        $form->addHidden('a')
                ->setValue('new');

        $form->addSaveButton();

        foreach ($this->grid->getVariablesList() as $k) {
            if ($val = $this->grid->getRequest()->get($k)) {
                $form->addHidden($this->grid->getId() .'_'. $k)->setValue($val);
            }
        }

        return $form;
    }
}

class Am_Grid_Action_ExportTrans extends Am_Grid_Action_Abstract
{
    protected $title = "Export";
    protected $type = self::NORECORD;

    public function run()
    {
        if (!$language = $this->grid->getCompleteRequest()->get('language')) {
            $language = Am_Di::getInstance()->locale->getLanguage();
        }

        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest'; //return response without layout
        $outputDataSource = new Am_TranslationDataSource_PO();
        $inputDataSource = $this->grid->getDataSource()->getTDataSource();

        $filename = $outputDataSource->getFileName($language);

        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");

        header('Content-type: text/plain');
        header("Content-Disposition: attachment; filename=$filename");
        echo $outputDataSource->getFileContent($language, $inputDataSource->getTranslationData($language, Am_TranslationDataSource_Abstract::FETCH_MODE_REWRITTEN));
    }
}

class Am_Grid_Filter_Trans extends Am_Grid_Filter_Abstract
{
    protected $locale = null,
        $varList = ['filter', 'mode'];

    public function  __construct($locale)
    {
        $this->title = ' ';
        $this->locale = $locale;
    }

    protected function applyFilter()
    {
        $tDataSource = $this->grid->getDataSource()->getTDataSource();

        $tData = $tDataSource
            ->getTranslationData($this->locale, $this->getParam('mode', Am_TranslationDataSource_Abstract::FETCH_MODE_ALL));
        $tData = $this->filter($tData, $this->getParam('filter'));
        $this->grid->getDataSource()->_friendSetArray(
                Am_Grid_DataSource_Array_Trans::prepareArray($tData)
        );

        list($fieldname, $desc) = $this->grid->getDataSource()->_friendGetOrder();
        if ($fieldname) {
            $this->grid->getDataSource()->setOrder($fieldname, $desc);
        }
    }

    function renderInputs()
    {
        $options = [
            Am_TranslationDataSource_Abstract::FETCH_MODE_ALL => 'All',
            Am_TranslationDataSource_Abstract::FETCH_MODE_REWRITTEN => 'Customized Only',
            Am_TranslationDataSource_Abstract::FETCH_MODE_UNTRANSLATED => 'Untranslated Only'
        ];

        $filter = ___('Display Mode') . ' ';

        $filter .= $this->renderInputSelect('mode', $options, ['id'=>'trans-mode']);
        $filter .= ' ';
        $filter .= $this->renderInputText([
            'name' => 'filter',
            'placeholder' => ___('Filter by String')
        ]);
        $filter .= sprintf('<input type="hidden" name="language" value="%s">', $this->locale);

        return $filter;
    }

    protected function filter($array, $filter)
    {
        if (!$filter) return $array;
        foreach ($array as $k=>$v) {
            if (false === stripos($k, $filter) &&
                    false === stripos($v, $filter)) {

                unset($array[$k]);
            }
        }
        return $array;
    }
}

class AdminTransGlobalController extends Am_Mvc_Controller_Grid
{
    protected $language = null;

    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->hasPermission(Am_Auth_Admin::PERM_TRANSLATION);
    }

    public function init()
    {
        $this->getView()->headScript()->appendScript($this->getJs());

        $enabled = $this->getDi()->getLangEnabled();
        $locale = $this->getDi()->locale->getId();
        $lang = $this->getDi()->locale->getLanguage();
        $this->language = $this->getParam('language') ?: (in_array($locale, $enabled) ? $locale : $lang);
        parent::init();
    }

    public function createGrid()
    {
        $grid = $this->_createGrid(___('Translations'));
        //$grid->actionAdd(new Am_Grid_Action_NewTrans);
        $grid->actionAdd(new Am_Grid_Action_ExportTrans())->setTarget('_top');
        return $grid;
    }

    protected function _createGrid($title)
    {
        $ds = $this->createDS($this->getLocale());
        $ds->setOrder('base');
        $grid = new Am_Grid_Editable('_trans', $title, $ds, $this->_request, $this->view);
        $grid->setPermissionId(Am_Auth_Admin::PERM_TRANSLATION);
        $grid->addField('base', ___('Key/Default'), true, '', null, '50%')
            ->setRenderFunction(function($obj, $f, $grid, $field){
                return $grid->renderTd(nl2br($grid->escape($obj->$f)), false);
            });
        $grid->addField('trans', ___('Current'), true, '', [$this, 'renderTrans'], '50%');
        $grid->setFilter(new Am_Grid_Filter_Trans($this->getLocale()));
        $grid->actionsClear();
        $grid->setRecordTitle(___('Translation'));
        $grid->addCallback(Am_Grid_ReadOnly::CB_RENDER_CONTENT, [$this, 'wrapContent']);
        $grid->addCallback(Am_Grid_ReadOnly::CB_RENDER_TABLE, [$this, 'wrapTable']);
        return $grid;
    }

    protected function createDS($locale)
    {
        return new Am_Grid_DataSource_Array_Trans($locale);
    }

    function wrapTable(& $out, $grid)
    {
        $out = '<form method="post" target="_top" name="translations" action="'
                . $this->getUrl(null, 'save', null, ['language'=>$this->getLocale()])
                . '">'
                . $out
                . sprintf('<input type="hidden" name="language" value="%s">', $this->getLocale());

        $vars = $this->grid->getVariablesList();
        $vars[] = 'p'; //stay on current page
        foreach ($vars as $var) {
            if ($val = $this->grid->getRequest()->getParam($var)) {
                $out .= sprintf('<input type="hidden" name="%s" value="%s">', $this->grid->getId() . '_' . $var, $val);
            }
        }
        $out .= '<div class="am-group-wrap"><input type="submit" name="submit" value="Save"></div>'
                . '</form>';
    }

    function wrapContent(& $out, $grid)
    {
        $out = $this->renderLanguageSelection() . $out;
    }

    function renderTrans($record)
    {
        //htmlentities - we need double encode here, html entity can be part of base string
        return sprintf(<<<CUT
<td class="text-edit-wrapper">
        <input type="hidden" name="trans_base[%s]" value="%s"/>
        <textarea class="text-edit" name="trans[%s]">%s</textarea>
</td>
CUT
            , md5($record->base), htmlentities($record->base, ENT_QUOTES, 'UTF-8')
            , md5($record->base), htmlentities($record->trans, ENT_QUOTES, 'UTF-8')
        );
    }

    public function getLocale()
    {
        return $this->language;
    }

    function saveAction()
    {
        $trans = array_map(function($el) {return preg_replace('/\r?\n/', "\n", $el);},
            $this->getRequest()->getParam('trans', []));
        $trans_base = array_map(function($el) {return preg_replace('/\r?\n/', "\n", $el);},
            $this->getRequest()->getParam('trans_base', []));

        $tData = $this->grid->getDataSource()
                ->getTDataSource()
                ->getTranslationData($this->getLocale(), Am_TranslationDataSource_Abstract::FETCH_MODE_ALL);

        $toReplace = [];
        foreach ($trans as $k=>$v) {
            if ( $v != $tData[$trans_base[$k]] ) {
                $toReplace[$trans_base[$k]] = $v;
            }
        }

        if (count($toReplace)) {
            $this->getDi()->translationTable->replaceTranslation($toReplace, $this->getLocale());
            Zend_Translate::hasCache() && Zend_Translate::clearCache();
        }

        $_POST['trans'] = $_GET['trans'] = $_POST['trans_base'] = $_GET['trans_base'] = null;
        $this->grid->getRequest()->setParam('trans', null);
        $this->grid->getCompleteRequest()->setParam('trans', null);
        $this->getRequest()->setParam('trans', null);
        $this->grid->getRequest()->setParam('trans_base', null);
        $this->grid->getCompleteRequest()->setParam('trans_base', null);
        $this->getRequest()->setParam('trans_base', null);

        $url = $this->getDi()->url('admin-trans-global/index', $this->getRequest()->toArray(), false);
        $this->_response->redirectLocation($url);
    }

    protected function renderLanguageSelection()
    {
        $form = new Am_Form_Admin();

        $form->addSelect('language')
                ->setLabel(___('Language'))
                ->setValue($this->getLocale())
                ->loadOptions($this->getLanguageOptions());

        $renderer = HTML_QuickForm2_Renderer::factory('array');

        $form->render($renderer);

        $form = $renderer->toArray();
        $filter = '';
        foreach ($form['elements'] as $el) {
            $filter .= ' ' . $el['label'] . ' ' . $el['html'];
        }
        $url = $this->getDi()->url('admin-setup/language');
        $icon = $this->view->icon('plus');
        return sprintf("<div class='am-filter-wrap'><form class='filter' method='get' action='%s'>\n",
                $this->escape($this->getUrl(null, 'index'))) .
                $filter .
                " <a href=\"$url\" target=\"_top\" style=\"display:inline-block; vertical-align:middle\">$icon</a></form></div>\n" ;
    }

    protected function getLanguageOptions()
    {
        $op =  $this->getDi()->languagesListUser;
        $enabled = $this->getDi()->getLangEnabled();
        $_ = [];
        foreach ($enabled as $k) {
            $_[$k] = $op[$k];
        }
        return $_;
    }

    protected function getJs()
    {
        $revertIcon = $this->getView()->icon('revert');

        $cancel_title = ___('Cancel All Changes in Translations on Current Page');
        $jsScript = <<<CUT
(function($){
    jQuery(function() {
        jQuery(document).on('change', 'form.filter select#trans-mode', function() {
            jQuery(this).parents('form').get(0).submit();
        })
    })

    var changedNum = 0;
    jQuery(document).on('focus', ".text-edit", function(event) {
        if (!jQuery(this).data('valueSaved')) {
            jQuery(this).data('valueSaved', true);
            jQuery(this).data('value', jQuery(this).prop('value'));
        }
    })

    jQuery(document).on('change', "select[name=language]", function(){
        this.form.submit();
    });

    jQuery(document).on('change', ".text-edit", function(event) {
        if (!jQuery(this).hasClass('changed')) {
            jQuery(this).addClass('changed');
            var aRevert = jQuery('<a href="#" class="text-edit-revert">{$revertIcon}</a>').attr('title', jQuery(this).data('value')).click(function(){
                input = jQuery(this).closest('.text-edit-wrapper').find('.text-edit');
                input.prop('value', input.data('value'));
                jQuery(this).remove();
                input.removeClass('changed');
                changedNum--;
                if (!changedNum && jQuery(".am-pagination").hasClass('hidden')) {
                    jQuery(".am-pagination").next().remove();
                    jQuery(".am-pagination").removeClass('hidden');
                    jQuery(".am-pagination").show();
                }
                return false;
            })
            changedNum++;
            jQuery(this).after(aRevert);
        }
        var aCancel = jQuery('<a href="javascript:;" class="local">$cancel_title</a>').click(function(){
            jQuery(".text-edit").filter(".changed").each(function(){
                 input = jQuery(this);
                 input.prop('value', input.data('value'));
                 input.next().remove();
                 input.removeClass('changed');
             })
             if (jQuery(".am-pagination").hasClass('hidden')) {
                 jQuery(".am-pagination").next().remove();
                 jQuery(".am-pagination").removeClass('hidden');
                 jQuery(".am-pagination").show();
             }
             changedNum = 0;
             return false;
        })

        aCancel = aCancel.wrap('<div class="trans-cancel"></div>').parents('div');

        if (jQuery(".am-pagination").css('display')!='none') {
            jQuery(".am-pagination").addClass('hidden')
            jQuery(".am-pagination").after(aCancel);
            jQuery(".am-pagination").hide();
        }
    })
})(jQuery)
CUT;
        return $jsScript;
    }
}