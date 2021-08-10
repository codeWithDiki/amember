<?php

abstract class Am_TranslationDataSource_Abstract
{
    const FETCH_MODE_ALL = 'all';
    const FETCH_MODE_REWRITTEN = 'rewritten';
    const FETCH_MODE_UNTRANSLATED = 'untranslated';
    const LOCALE_KEY = '_default_locale';

    final public function getTranslationData($locale, $fetchMode = Am_TranslationDataSource_Abstract::FETCH_MODE_ALL)
    {
        list($lang, ) = explode('_', $locale);

        switch ($fetchMode) {
            case self::FETCH_MODE_ALL :
                $result = $this->getBaseTranslationData($lang);
                $result = $this->mergeWithCustomTranslation($result, $locale);
                break;
            case self::FETCH_MODE_REWRITTEN :
                $result = Am_Di::getInstance()->translationTable->getTranslationData($locale);
                $base = $this->getBaseTranslationData($lang);
                foreach ($result as $k=>$v) {
                    if (!key_exists($k, $base)) {
                        unset($result[$k]);
                    }
                }
                break;
            case self::FETCH_MODE_UNTRANSLATED :
                $result = $this->getBaseTranslationData($lang);
                $result = $this->mergeWithCustomTranslation($result, $locale);
                $flip = array_flip($result);
                $result = array_filter($result, function($v) use ($flip) {
                    return (bool)(!$v || $v == $flip[$v]);
                });
                break;
            default:
                throw new Am_Exception_InternalError('Unknown fetch mode : ' . $fetchMode);
                break;
        }

        if (isset($result[self::LOCALE_KEY])) {
            unset($result[self::LOCALE_KEY]);
        }
        return $result;
    }

    public function createTranslation($language)
    {
        $filename = $this->getFileName($language);
        $path = Am_Di::getInstance()->root_dir . "/application/default/language/user/{$filename}";

        if ($error = $this->validatePath($path)) {
            return $error;
        }

        $content = $this->getFileContent($language);
        file_put_contents($path, $content);
        return '';
    }

    protected function mergeWithCustomTranslation($translationData, $locale)
    {
        list($lang, ) = explode('_', $locale);

        foreach (array_unique([$lang, $locale]) as $l) {
            if ($custom = Am_Di::getInstance()->translationTable->getTranslationData($l)) {
                foreach ($translationData as $k => $v) {
                    if (isset($custom[$k])) {
                        $translationData[$k] = $custom[$k];
                    }
                }
                foreach ($custom as $k => $v) {
                    if (!isset($translationData[$k])) {
                        $translationData[$k] = $custom[$k];
                    }
                }
            }
        }
        return $translationData;
    }

    protected function getBaseTranslationData($language)
    {
        return $this->_getBaseTranslationData($language);
    }

    protected function validatePath($path)
    {
        if (file_exists($path)) {
            return ___('File %s is already exist. You can not create already existing translation.', $path);
        }

        $dir = dirname($path);
        if (!is_writeable($dir)) {
            return ___('Folder %s is not a writable for the PHP script. Please <br />
            chmod this file using webhosting control panel file manager or using your<br />
            favorite FTP client to 777 (write and read for all)<br />
            Please, don\'t forget to chmod it back to 755 after creation of translation', $dir);
        }

        return '';
    }

    abstract protected function _getBaseTranslationData($language);
    abstract function getFileName($language);
    abstract function getFileContent($language, $translationData = []);
}

class Am_TranslationDataSource_PHP extends Am_TranslationDataSource_Abstract
{
    function getFileName($language)
    {
        return $language . '.php';
    }

    function getFileContent($language, $translationData = [])
    {
        $expectedLocaleName = $language;
        $locale = new Zend_Locale($expectedLocaleName);
        //prepend local to start of array
        $translationData = array_reverse($translationData);
        $translationData[self::LOCALE_KEY] = $locale;
        $translationData = array_reverse($translationData);

        $out = '';
        $out .= "<?php"
            . PHP_EOL
            . "return array ("
            . PHP_EOL;

        foreach ($translationData as $msgid => $msgstr) {
            $out .= "\t";
            $out .= sprintf("'%s'=>'%s',",
                str_replace("'", "\'", $msgid),
                str_replace("'", "\'", $msgstr)
            );
            $out .= PHP_EOL;
        }
        $out .= "\t''=>''" . PHP_EOL;
        $out .= ");";
        return $out;
    }

    protected function _getBaseTranslationData($language)
    {
        $result = include(AM_APPLICATION_PATH . "/default/language/user/default.php");
        $result = array_merge($result, (array)@include(AM_APPLICATION_PATH . "/default/language/user/{$language}.php"));
        $result = array_merge($result, (array)@include(AM_APPLICATION_PATH . "/default/language/admin/default.php"));
        $result = array_merge($result, (array)@include(AM_APPLICATION_PATH . "/default/language/admin/{$language}.php"));
        return $result;
    }
}

class Am_TranslationDataSource_PO extends Am_TranslationDataSource_Abstract
{
    function getFileName($language)
    {
        return $language . '.po';
    }

    function getFileContent($language, $translationData= [])
    {
        $expectedLocaleName = $language;
        $locale = new Zend_Locale($expectedLocaleName);
        //prepend local to start of array
        $translationData = array_reverse($translationData);
        $translationData[self::LOCALE_KEY] = $locale;
        $translationData = array_reverse($translationData);

        $out = '';

        foreach ($translationData as $msgid => $msgstr) {
            $out .= sprintf('msgid "%s"', $this->prepare($msgid, true));
            $out .= PHP_EOL;
            $out .= sprintf('msgstr "%s"', $this->prepare($msgstr, true));
            $out .= PHP_EOL;
            $out .= PHP_EOL;
        }

        return $out;
    }

    protected function _getBaseTranslationData($language)
    {
        $result= [];
        $result = $this->getTranslationArray(AM_APPLICATION_PATH . "/default/language/user/default.pot");
        $result = array_merge($result, $this->getTranslationArray(AM_APPLICATION_PATH . "/default/language/user/{$language}.po"));
        return $result;
    }

    protected function getTranslationArray($file)
    {
        $result = [];

        $fPointer = fopen($file, 'r');

        $part = '';
        while (!feof($fPointer)) {
            $line = fgets($fPointer);
            $part .= $line;
            if (!trim($line)) { //entity divided with empty line in file
                $result = array_merge($result, $this->getTranslationEntity($part));
                $part = '';
            }
        }

        fclose($fPointer);

        unset($result['']);//unset meta
        return $result;
    }

    protected function getTranslationEntity($contents)
    {
        $result = [];
        $matches = [];

        $matched = preg_match(
            '/(msgid\s+("([^"]|\\\\")*?"\s*)+)\s+' .
            '(msgstr\s+("([^"]|\\\\")*?"\s*)+)/u',
            $contents, $matches
        );

        if ($matched) {
            $msgid = $matches[1];
            $msgid = preg_replace(
                '/\s*msgid\s*"(.*)"\s*/s', '\\1', $matches[1]);
            $msgstr = $matches[4];
            $msgstr = preg_replace(
                '/\s*msgstr\s*"(.*)"\s*/s', '\\1', $matches[4]);
            $result[$this->prepare($msgid)] = $this->prepare($msgstr);
        }

        return $result;
    }

    protected function prepare($string, $reverse = false)
    {
        if ($reverse) {
            $smap = ['"', "\n", "\t", "\r"];
            $rmap = ['\\"', '\\n"' . "\n" . '"', '\\t', '\\r'];
            return (string) str_replace($smap, $rmap, $string);
        } else {
            $smap = ['/"\s+"/', '/\\\\n/', '/\\\\r/', '/\\\\t/', '/\\\\"/'];
            $rmap = ['', "\n", "\r", "\t", '"'];
            return (string) preg_replace($smap, $rmap, $string);
        }
    }
}

class Am_TranslationDataSource_DB extends Am_TranslationDataSource_Abstract
{
    public function createTranslation($language)
    {
        throw new Am_Exception_InputError('Local translations can not be created');
    }

    function getFileName($language)
    {
        throw new Am_Exception_InputError('Local translations can not be exported');
    }

    function getFileContent($language, $translationData = [])
    {
        throw new Am_Exception_InputError('Local translations can not be exported');
    }

    protected function _getBaseTranslationData($language)
    {
        $result = [];
        foreach(Am_Di::getInstance()->plugins_payment->loadEnabled()->getAllEnabled() as $pl)
        {
            $result[$pl->getConfig('title')] = "";
            $result[$pl->getConfig('description')] = "";
        }
        foreach (Am_Di::getInstance()->db->select("SELECT title, description FROM ?_product") as $r) {
            $result[ $r['title'] ] = "";
            $result[ $r['description'] ] = "";
        }
        foreach (Am_Di::getInstance()->db->select("SELECT terms FROM ?_billing_plan WHERE terms<>''") as $r) {
            $result[ $r['terms'] ] = "";
        }
        foreach ((array)Am_Di::getInstance()->config->get('member_fields') as $field) {
            $result[ $field['title'] ] = "";
            $result[ $field['description'] ] = "";
        }
        foreach ((array)Am_Di::getInstance()->config->get('helpdesk_ticket_fields') as $field) {
            $result[ $field['title'] ] = "";
            $result[ $field['description'] ] = "";
        }
        foreach (Am_Di::getInstance()->db->select("SELECT title, `desc` FROM ?_folder") as $r) {
            $result[ $r['title'] ] = "";
            $result[ $r['desc'] ] = "";
        }
        foreach (Am_Di::getInstance()->db->select("SELECT title, `desc` FROM ?_file") as $r) {
            $result[ $r['title'] ] = "";
            $result[ $r['desc'] ] = "";
        }
        foreach (Am_Di::getInstance()->db->select("SELECT title, `desc` FROM ?_page") as $r) {
            $result[ $r['title'] ] = "";
            $result[ $r['desc'] ] = "";
        }
        foreach (Am_Di::getInstance()->db->select("SELECT title, `desc` FROM ?_link") as $r) {
            $result[ $r['title'] ] = "";
            $result[ $r['desc'] ] = "";
        }
        foreach (Am_Di::getInstance()->db->select("SELECT title, `desc` FROM ?_video") as $r) {
            $result[ $r['title'] ] = "";
            $result[ $r['desc'] ] = "";
        }
        foreach (Am_Di::getInstance()->db->select("SELECT title, description FROM ?_resource_category") as $r) {
            $result[ $r['title'] ] = "";
            $result[ $r['description'] ] = "";
        }
        foreach (Am_Di::getInstance()->db->select("SELECT title FROM ?_saved_form") as $r) {
            $result[ $r['title'] ] = "";
        }
        foreach (Am_Di::getInstance()->db->select("SELECT title FROM ?_user_group") as $r) {
            $result[ $r['title'] ] = "";
        }
        $savedFormTbl = Am_Di::getInstance()->savedFormTable->getName();
        foreach (Am_Di::getInstance()->db->select("SELECT fields FROM {$savedFormTbl}") as $r) {
            $fields = json_decode($r['fields'], true);
            foreach ($fields as $field){
                if (isset($field['labels'])) {
                    foreach ($field['labels'] as $k => $v) {
                        $result[ $v ] = "";
                    }
                }
            }
        }
        return Am_Di::getInstance()->hook->filter($result, Am_Event::GET_BASE_TRANSLATION_DATA);
    }
}
