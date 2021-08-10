<?php

/**
 * Provides locale-based information and guess client locale
 * requires <i>locales.dat</i> file to be located in the same folder
 *
 * @package Am_Utils
 */
class Am_Locale
{
    protected $locale = '';
    protected static $cache = [];
    protected $storedLocale = null;
    protected $formatter = null;

    static protected $localeAliases = [
        'af' => [
             'af_NA',
             'af_ZA',
        ],
        'ar' =>  [
             'ar_AE',
             'ar_BH',
             'ar_DZ',
             'ar_EG',
             'ar_IQ',
             'ar_JO',
             'ar_KW',
             'ar_LB',
             'ar_LY',
             'ar_MA',
             'ar_OM',
             'ar_QA',
             'ar_SA',
             'ar_SD',
             'ar_SY',
             'ar_TN',
             'ar_YE',
        ],
        'cs' =>
        [
             'cs_CZ'
        ],
        'da' =>
        [
             'da_DK'
        ],
        'de' =>
        [
             'de_DE',
             'de_AT',
             'de_BE',
             'de_CH',
             'de_LI',
             'de_LU',
        ],
        'el' =>
        [
             'el_GR'
        ],
        'en' =>
        [
             'en_US',
             'en_AS',
             'en_AU',
             'en_BB',
             'en_BE',
             'en_BM',
             'en_BW',
             'en_BZ',
             'en_CA',
             'en_GB',
             'en_GU',
             'en_GY',
             'en_HK',
             'en_IE',
             'en_IN',
             'en_JM',
             'en_MH',
             'en_MP',
             'en_MT',
             'en_MU',
             'en_NA',
             'en_NZ',
             'en_PH',
             'en_PK',
             'en_SG',
             'en_TT',
             'en_UM',
             'en_VI',
             'en_ZA',
             'en_ZW',
        ],
        'es' =>
        [
             'es_ES',
             'es_419',
             'es_AR',
             'es_BO',
             'es_CL',
             'es_CO',
             'es_CR',
             'es_DO',
             'es_EC',
             'es_GQ',
             'es_GT',
             'es_HN',
             'es_MX',
             'es_NI',
             'es_PA',
             'es_PE',
             'es_PR',
             'es_PY',
             'es_SV',
             'es_US',
             'es_UY',
             'es_VE',
        ],
        'et' =>
        [
             'et_EE'
        ],
        'fi' =>
        [
            'fi_FI'
        ],
        'fr' =>
        [
             'fr_FR',
             'fr_BE',
             'fr_BF',
             'fr_BI',
             'fr_BJ',
             'fr_BL',
             'fr_CA',
             'fr_CD',
             'fr_CF',
             'fr_CG',
             'fr_CH',
             'fr_CI',
             'fr_CM',
             'fr_DJ',
             'fr_GA',
             'fr_GF',
             'fr_GN',
             'fr_GP',
             'fr_GQ',
             'fr_KM',
             'fr_LU',
             'fr_MC',
             'fr_MF',
             'fr_MG',
             'fr_ML',
             'fr_MQ',
             'fr_NE',
             'fr_RE',
             'fr_RW',
             'fr_SN',
             'fr_TD',
             'fr_TG',
             'fr_YT',
        ],
        'ja' =>
        [
             'ja_JP'
        ],
        'he' =>
        [
             'he_IL'
        ],
        'hu' =>
        [
            'hu_HU'
        ],
        'id' =>
        [
            'id_ID'
        ],
        'ko' =>
        [
             'ko_KR'
        ],
        'nb' =>
        [
             'nb_NO'
        ],

        'pt' =>
        [
             'pt_PT',
             'pt_AO',
             'pt_BR',
             'pt_GW',
             'pt_MZ',
             'pt_ST',
        ],
        'sv' =>
        [
             'sv_SE'
        ],
        'sq' =>
        [
             'sq_AL'
        ],

        'vi' =>
        [
             'vi_VN'
        ],
        'zh' =>
        [
             'zh_Hans',
             'zh_Hans_CN',
             'zh_Hans_HK',
             'zh_Hans_MO',
             'zh_Hans_SG',
             'zh_Hant',
             'zh_Hant_HK',
             'zh_Hant_MO',
             'zh_Hant_TW',
        ],
        'cs' =>
        [
            'cs_CZ'
        ],
    ];
    public $dateFormat, $timeFormat;

    public function __construct($locale = null)
    {
        if ($locale === null)
            $locale = key(Zend_Locale::getDefault());
        elseif (empty($locale))
            $locale = 'en_US';
        $this->locale = $locale;

        list($lang) = explode('_', $this->locale);
        foreach ([
            "Am_Locale_Formatter_{$locale}",
            "Am_Locale_Formatter_{$lang}",
            "Am_Locale_Formatter_Default"
                 ] as $classname) {

            if (class_exists($classname, false)) {
                $this->formatter = new $classname;
                break;
            }
        }
    }

    function __call($name, $arguments)
    {
        return call_user_func_array([$this->formatter, $name], $arguments);
    }

    function getId()
    {
        return $this->locale;
    }

    public function getTerritoryNames()
    {
        $data = $this->getData();
        return (array)@$data['territories'];
    }

    public function getDateFormat()
    {
        $data = $this->getData();
        if ($this->dateFormat) return $this->dateFormat;
        return empty($data['dateFormats']['php']) ? 'M d, Y' : $data['dateFormats']['php'];
    }

    public function getTimeFormat()
    {
        $data = $this->getData();
        if ($this->timeFormat) return $this->timeFormat;
        return empty($data['timeFormats']['php']) ? 'H:i:s' : $data['timeFormats']['php'];
    }

    public function getDateTimeFormat()
    {
        $data = $this->getData();
        return strtr($data['dateTimeFormat'], [
            '{1}' => $this->getDateFormat(),
            '{0}' => $this->getTimeFormat(),
        ]);
    }

    protected function getData()
    {
        if (isset(self::$cache[$this->locale]))
            return self::$cache[$this->locale];
        $data = Am_Di::getInstance()->cacheFunction->call(['Am_Locale','_readData'], [$this->locale]);
        return self::$cache[$this->locale] = $data;
    }

    public function getMonthNames($type = 'wide', $standalone = true)
    {
        $data = $this->getData();
        $primary = $standalone ? 'monthNamesSA' : 'monthNames';
        $secondary = $standalone ? 'monthNames' : 'monthNamesSA';
        return isset($data[$primary][$type]) ? $data[$primary][$type] : $data[$secondary][$type];
    }

    public function getWeekdayNames($type = 'wide')
    {
        $data = $this->getData();
        return (array)$data['weekDayNames'][$type];
    }

    /**
     * @param string $locale
     * @return array data
     */
    static function _readData($locale)
    {
        $readLocales = [];
        $arr = explode('_', $locale);
        do {
            $readLocales[] = implode('_', $arr);
            array_pop($arr);
        } while ($arr);
        $readLocales = array_reverse($readLocales);
        if ($locale != 'selfNames')
            array_unshift($readLocales, 'root');

        $f = fopen(dirname(__FILE__).'/locale.dat', 'r');
        if (!$f)
            throw new Am_Exception_InternalError("Could not open locale data file: locales.dat");
        /*
         * stream_get_line does not return correct result in php 5.2.6
         */
        if(version_compare(PHP_VERSION, '5.2.7', '<'))
            list($line, ) = explode(chr(5), file_get_contents(dirname(__FILE__).'/locale.dat', false, NULL, -1, 32000));
        else
            $line = stream_get_line($f, 64000, chr(5));

        $header = unserialize(substr($line, strlen('LOCALES:')));
        // now read
        $data = [];
        foreach ($readLocales as $locale)
        {
            $start = $header[$locale][0];
            $len   = $header[$locale][1] - $header[$locale][0];
            fseek($f, strlen($line) + $start + 1);
            $string = fread($f, $len);
            $data = self::mergeLocaleData($data, (array)unserialize($string));
        }
        return $data;
    }

    static function mergeLocaleData($d1, $d2)
    {
        foreach ($d2 as $k => $v)
        {
            if (isset($d1[$k]) && is_array($d1[$k])) {
                $d1[$k] = self::mergeLocaleData($d1[$k], $d2[$k]);
            } else {
                $d1[$k] = $d2[$k];
            }
        }
        return $d1;
    }

    static function getSelfNames()
    {
        return self::_readData('selfNames');
    }

    static function getLocales()
    {
        $f = fopen(dirname(__FILE__).'/locale.dat', 'r');
        if (!$f)
            throw new Am_Exception_InternalError("Could not open locale data file: locales.dat");
        $line = stream_get_line($f, 64000, chr(5));
        $header = unserialize(substr($line, strlen('LOCALES:')));
        unset($header['root']);
        return array_keys($header);
    }

    static function getLanguagesList($context = 'user')
    {
        $options = [];
        $selfNames = (array)self::getSelfNames();
        foreach (am_glob(AM_APPLICATION_PATH.'/default/language/'.$context.'/*.php') as $fn)
        {
            if (!preg_match('|\b([a-z]{2,3}(_[A-Za-z0-9]+)?)\.php$|', $fn, $regs)) continue;
            $lang = $regs[1];
            $langs = self::getLocaleAliases($lang);
            array_unshift($langs, $lang);
            foreach ($langs as $lang)
            {
                $title = mb_convert_case(@$selfNames[$lang], MB_CASE_TITLE, 'UTF-8');
                $options[$lang] = $title ? $title : $lang;
            }
        }
        return $options;
    }

    /**
     * Return locales list of same language but with different locale settings
     * That way we do not have to create additional files in languages/ folder
     * @return array of string
     */
    static function getLocaleAliases($locale)
    {
        return array_key_exists($locale, self::$localeAliases) ? self::$localeAliases[$locale] : [];
    }

    /**
     * Find out locale from the request, settings or session
     * if language choice enabled, try the following:
     *      - REQUEST parameter "lang"
     *      - SESSION parameter "lang"
     *      - Am_App::getUser->lang
     *      - default in App
     *      - en_US
     * else use latter 2
     */
    static function initLocale(Am_Di $di)
    {
        if (preg_match('/\badmin\b/', @$_SERVER['REQUEST_URI'])) {
            $possibleLang = [];
            $auth = $di->authAdmin;
            $user = $auth->getUserId() ? $auth->getUser() : null;
            if (!empty($_REQUEST['__lang'])) {
                $possibleLang[] = filterId($_REQUEST['__lang']);
            } elseif (!empty($di->session->langAdmin)) {
                $possibleLang[] = $di->session->langAdmin;
            } elseif ($user && !empty($user->lang)) {
                $possibleLang[] = $user->lang;
            }
            $br = Zend_Locale::getBrowser();
            arsort($br);
            $possibleLang = array_merge($possibleLang, array_keys($br));

            $possibleLang[] = $di->config->get('lang.default', 'en_US');
            $possibleLang[] = 'en_US'; // last but not least
            // now choose the best candidate
            $enabledLangs = $di->getLangEnabled(false);
            $checked = [];
            foreach ($possibleLang as $lc)
            {
                list($lang) = explode('_', $lc, 2);

                if (!in_array($lc, $enabledLangs) && !in_array($lang, $enabledLangs)) continue;

                if ($lc == $lang)
                    $lc = self::guessLocaleByLang($lang);

                if(!$lc)
                    continue;

                if (isset($checked[$lc])) continue;
                $checked[$lc] = true;
                // check if locale file is exists
                $lc = preg_replace('/[^A-Za-z0-9_]/', '', $lc);
                if (!Zend_Locale::isLocale($lc)) continue;
                Zend_Locale::setDefault($lc);
                // then update user if it was request
                // and set to session
                break;
            }
            if(!empty($_REQUEST['__lang']))
            {
                if ((($_REQUEST['__lang'] == $lang) || ($_REQUEST['__lang'] == $lc)) && $user && $user->lang != $lang)
                    $user->updateQuick('lang', $lc);
                // set to session
                $di->session->langAdmin = $lc;
            }
        } else {
            $possibleLang = [];
            if ($di->config->get('lang.display_choice'))
            {
                $auth = $di->auth;
                $user = $auth->getUserId() ? $auth->getUser() : null;
                if (!empty($_REQUEST['_lang'])) {
                    $possibleLang[] = filterId($_REQUEST['_lang']);
                } elseif (!empty($di->session->lang)) {
                    $possibleLang[] = $di->session->lang;
                } elseif ($user && $user->lang) {
                    $possibleLang[] = $user->lang;
                }

                $br = Zend_Locale::getBrowser();
                arsort($br);
                $possibleLang = array_merge($possibleLang, array_keys($br));
            }

            $possibleLang[] = $di->config->get('lang.default', 'en_US');
            $possibleLang[] = 'en_US'; // last but not least
            // now choose the best candidate
            $enabledLangs = $di->getLangEnabled(false);
            $checked = [];
            foreach ($possibleLang as $lc)
            {
                list($lang) = explode('_', $lc, 2);

                if (!in_array($lc, $enabledLangs) && !in_array($lang, $enabledLangs)) continue;

                if ($lc == $lang)
                    $lc = self::guessLocaleByLang($lang);

                if(!$lc)
                    continue;

                if (isset($checked[$lc])) continue;
                $checked[$lc] = true;
                // check if locale file is exists
                $lc = preg_replace('/[^A-Za-z0-9_]/', '', $lc);
                if (!Zend_Locale::isLocale($lc)) continue;
                Zend_Locale::setDefault($lc);
                // then update user if it was request
                // and set to session
                break;
            }
            if($di->config->get('lang.display_choice') && !empty($_REQUEST['_lang']))
            {
                if ((($_REQUEST['_lang'] == $lang) || ($_REQUEST['_lang'] == $lc)) && $user && $user->lang != $lang)
                    $user->updateQuick('lang', $lc);
                // set to session
                $di->session->lang = $lc;
            }
        }
        Zend_Registry::set('Zend_Locale', new Zend_Locale());
        $amLocale = new Am_Locale();
        $amLocale->dateFormat = $di->config->get('date_format');
        $amLocale->timeFormat = $di->config->get('time_format');
        Zend_Registry::set('Am_Locale', $amLocale);
        $di->locale = $amLocale;
        Zend_Locale_Format::setOptions([
            'date_format' => $amLocale->getDateFormat(),
        ]);
    }

    static function guessLocaleByLang($lang)
    {
        if(strpos($lang, '_')!== false) {
            return $lang;
        }
        if(isset(self::$localeAliases[$lang]) && !empty(self::$localeAliases[$lang][0])) {
            return self::$localeAliases[$lang][0];
        } else {
            return Zend_Locale::getLocaleToTerritory($lang);
        }
    }

    /**
     * Returns the language part of the locale
     * @return string
     */
    public function getLanguage()
    {
        $locale = explode('_', $this->locale);
        return $locale[0];
    }

    /**
     * Possible unit values;
     *  day,day-future,day-past,hour,hour-future,hour-past,minute,minute-future,minute-past,month,month-future,month-past,second,second-future,second-past,week,week-future,week-past,year,year-future,year-past
     * @param string $unit
     * @param unit $count number of units to format
     * @return string
     */
    public function formatUnits($unit, $count)
    {
        $data = $this->getData();
        $alternatives = $data['units']['day'];
        $pl = $this->findPlural($count);
        return str_replace('{0}', $count, $alternatives[$pl]);
    }

    public function findPlural($count)
    {
        $data = $this->getData();
        foreach ($data['pluralRules'] as $k => $expr)
        {
            $x = $expr = preg_replace('/\bn\b/', $count, $expr);
            $ret = eval($x = 'return ' . $expr . ';');
            if ($ret) return $k;
        }
        return 'other';
    }

    function changeLanguageTo($lang = null)
    {
        if (!empty($lang) && Zend_Locale::isLocale($locale = self::guessLocaleByLang($lang)))
        {
            $currentLocale = key(Zend_Locale::getDefault());

            if($currentLocale == $locale)
                return;

            if (empty($this->storedLocale))
                $this->storedLocale = key(Zend_Locale::getDefault());

            Am_Di::getInstance()->app->loadTranslations(Zend_Registry::get('Zend_Translate'), $locale);
            Zend_Registry::get('Zend_Locale')->setDefault($locale);
        }
    }

    function restoreLanguage()
    {
        if (!empty($this->storedLocale) && Zend_Locale::isLocale($this->storedLocale))
        {
            $this->changeLanguageTo($this->storedLocale);
        }
    }
}

interface Am_Locale_Formatter
{
    public function formatTermsText(Am_TermsText $terms);
    public function formatOptionTermsText(Am_TermsText $terms);
    public function formatPeriod(Am_Period $period, $format="%s", $skip_one_c = false);
}

abstract class Am_Locale_Formatter_Base implements Am_Locale_Formatter
{
    protected $f = [
        'period_day' => null,
        'period_month' => null,
        'period_year' => null,
        'period_lifetime' => null,
        'period_up_to' => null,
        'period_one' => null,

        'bp_Free' => null,
        'bp_free' => null,
        'bp_for' => null,
        'bp_for_first' => null,
        'bp_for_each' => null,
        'bp_then' => null,
        'bp_installments' => null,
    ];

    public function formatTermsText(Am_TermsText $terms)
    {
        if (is_null($terms->first_price) || !strlen($terms->first_period)) {
            return "";
        }

        $price1 = $terms->first_price;
        $price2 = $terms->second_price;
        if ($price1 instanceof Am_Currency) $price1 = $price1->getValue();
        if ($price2 instanceof Am_Currency) $price2 = $price2->getValue();

        $c1 = ($price1 > 0) ? $terms->getCurrency($terms->first_price) : $this->f['bp_Free'];
        $c2 = ($price2 > 0) ? $terms->getCurrency($terms->second_price) : $this->f['bp_free'];

        $p1 = new Am_Period($terms->first_period);
        $p2 = new Am_Period($terms->second_period);
        $ret = (string)$c1;
        $equal = 0;
        if (!$p1->isLifetime()) {
            if ($terms->rebill_times) {
                $ret .= $p1->getText($this->f['bp_for_first'], true);
            } else {
                $ret .= $p1->getText($this->f['bp_for']);
            }
        }
        if ($terms->rebill_times)
        {
            if (!$p1->equalsTo($p2) || ($price1 != $price2)) {
                $ret .= $this->f['bp_then'];
            } else {
                $ret = "";
                $equal = 1;
            }
            if($terms->rebill_times == 1 && !$equal) {
                $ret .= (string)$c2 . $p2->getText($this->f['bp_for']);
            } else {
                $ret .= (string)$c2 . $p2->getText($this->f['bp_for_each'], true);
            }
            if (($terms->rebill_times < IProduct::RECURRING_REBILLS) && !($p2->isLifetime() || ($terms->rebill_times == 1 && !$equal))) {
                $n = $terms->rebill_times + $equal;
                $ret .= sprintf($this->plural($n, $this->f['bp_installments']), $n);
            }
        }
        return preg_replace('/[ ]+/', ' ', $ret);
    }

    public function formatOptionTermsText(Am_TermsText $terms)
    {
        if (is_null($terms->first_price) || !strlen($terms->first_period)) {
            return "";
        }

        $price1 = $terms->first_price;
        $price2 = $terms->second_price;
        if ($price1 instanceof Am_Currency) $price1 = $price1->getValue();
        if ($price2 instanceof Am_Currency) $price2 = $price2->getValue();

        $c1 = ($price1 > 0) ? $terms->getCurrency($terms->first_price) : $this->f['bp_Free'];
        $c2 = ($price2 > 0) ? $terms->getCurrency($terms->second_price) : $this->f['bp_free'];

        $p1 = new Am_Period($terms->first_period);
        $p2 = new Am_Period($terms->second_period);
        $ret = (string) $c1;
        $equal = 0;
        if (!$p1->isLifetime() && $price2) {
            if ($terms->rebill_times) {
                $ret .= $p1->getText($this->f['bp_for_first'], true);
            } else {
                $ret .= $p1->getText($this->f['bp_for']);
            }
        }
        if (!$price1 && (!$price2 || !$terms->rebill_times)) $ret = '';
        if ($terms->rebill_times && $price2)
        {
            if ($ret && (!$p1->equalsTo($p2) || ($price1 != $price2))) {
                $ret .= $this->f['bp_then'];
            } else {
                $ret = "";
                $equal = 1;
            }
            if ($terms->rebill_times == 1 && !$equal) {
                $ret .= (string) $c2 . $p2->getText($this->f['bp_for']);
            } else {
                $ret .= (string) $c2 . $p2->getText($this->f['bp_for_each'], true);
            }
            if (($terms->rebill_times < IProduct::RECURRING_REBILLS) && !($p2->isLifetime() || ($terms->rebill_times == 1 && !$equal))) {
                $n = $terms->rebill_times + $equal;
                $ret .= sprintf($this->plural($n, $this->f['bp_installments']), $n);
            }
        }
        return preg_replace('/[ ]+/', ' ', $ret);
    }

    public function formatPeriod(Am_Period $period, $format="%s", $skip_one_c = false)
    {
        switch ($period->getUnit()){
            case 'd':
                $uu = $this->plural($period->getCount(), $this->f['period_day']);
                break;
            case 'm':
                $uu = $this->plural($period->getCount(), $this->f['period_month']);
                break;
            case 'y':
                $uu = $this->plural($period->getCount(), $this->f['period_year']);
                break;
            case Am_Period::FIXED:
                if ($period->getCount() == Am_Period::MAX_SQL_DATE)
                    return  $this->f['period_lifetime'];
                return sprintf($this->f['period_up_to'], amDate($period->getCount()));
        }
        $cc = $period->getCount();
        if ($period->getCount() == 1) $cc = $skip_one_c ? '' : $this->f['period_one'];

        $f = $this->plural($period->getCount(), $format);
        return sprintf($f, preg_replace('/[ ]+/', ' ', "$cc $uu"));
    }

    protected function plural($n, $forms)
    {
        if (!is_array($forms)) return $forms;
        $i = $this->findPluralForm($n);
        return isset($forms[$i]) ? $forms[$i] : $forms[0];
    }

    abstract protected function findPluralForm($n);
}

class Am_Locale_Formatter_Default extends Am_Locale_Formatter_Base
{
    protected $f = [
        'period_day' => ['day', 'days'],
        'period_month' => ['month', 'months'],
        'period_year' => ['year', 'years'],
        'period_lifetime' => ' for lifetime',
        'period_up_to' => ' up to %s',
        'period_one' => 'one',

        'bp_Free' => 'Free',
        'bp_free' => 'free',
        'bp_for' => ' for %s',
        'bp_for_first' => ' for the first %s',
        'bp_for_each' => ' for each %s',
        'bp_then' => ', then ',
        'bp_installments' => [', for %d installment', ', for %d installments'],
    ];

    protected function findPluralForm($n)
    {
        return $n == 1 ? 0 : 1;
    }
}

class Am_Locale_Formatter_ru extends Am_Locale_Formatter_Base
{
    protected $f = [
        'period_day' => ['день', 'дня', 'дней'],
        'period_month' => ['месяц', 'месяца', 'месяцев'],
        'period_year' => ['год', 'года', 'лет'],
        'period_lifetime' => ' навсегда',
        'period_up_to' => ' до %s',
        'period_one' => 'один',

        'bp_Free' => 'Бесплатно',
        'bp_free' => 'бесплатно',
        'bp_for' => ' за %s',
        'bp_for_first' => [' за первый %s', ' за первые %s', ' за первые %s'],
        'bp_for_each' => [' за каждый %s', ' за каждые %s', ' за каждые %s'],
        'bp_then' => ', далее ',
        'bp_installments' => [' %d раз', ' %d раза', ' %d раз'],
    ];

    protected function findPluralForm($n)
    {
        return ($n%10==1 && $n%100!=11) ? 0 : ($n%10>=2 && $n%10<=4 && ($n%100<10 || $n%100>=20) ? 1 : 2);
    }
}

class Am_Locale_Formatter_fr extends Am_Locale_Formatter_Base
{
    protected $f = [
        'period_day' => ['jour', 'jours'],
        'period_month' => ['mois', 'mois'],
        'period_year' => ['an', 'ans'],
        'period_lifetime' => ' à vie',
        'period_up_to' => ' jusqu`au %s',
        'period_one' => 'un',

        'bp_Free' => 'Gratuit',
        'bp_free' => 'gratuit',
        'bp_for' => ' pour %s',
        'bp_for_first' => [' pour le premier %s', ' pour les premiers %s'],
        'bp_for_each' => ' tous les %s',
        'bp_then' => ', ensuite ',
        'bp_installments' => [', en %d fois', ', en %d fois'],
    ];

    protected function findPluralForm($n)
    {
        return $n == 1 ? 0 : 1;
    }
}

class Am_Locale_Formatter_it extends Am_Locale_Formatter_Base
{
    protected $f = [
        'period_day' => ['giorno', 'giorni'],
        'period_month' => ['mese', 'mesi'],
        'period_year' => ['anno', 'anni'],
        'period_lifetime' => ' a vita',
        'period_up_to' => ' fino a %s',
        'period_one' => 'un',

        'bp_Free' => 'Gratis',
        'bp_free' => 'gratis',
        'bp_for' => ' per %s',
        'bp_for_first' => [' per il primo %s', ' per i primi %s'],
        'bp_for_each' => [' ogni %s', ' ogni %s'],
        'bp_then' => ', poi ',
        'bp_installments' => [', per %d rate', ', per %d rate'],
    ];

    protected function findPluralForm($n)
    {
        return $n == 1 ? 0 : 1;
    }
}

class Am_Locale_Formatter_de extends Am_Locale_Formatter_Base
{
    protected $f = [
        'period_day' => ['Tag', 'Tage'],
        'period_month' => ['Monat', 'Monate'],
        'period_year' => ['Jahr', 'Jahre'],
        'period_lifetime' => ' für das Leben',
        'period_up_to' => ' bis %s',
        'period_one' => 'ein',

        'bp_Free' => 'Kostenlos',
        'bp_free' => 'kostenlos',
        'bp_for' => ' für %s',
        'bp_for_first' => [' für den ersten %s', ' für die ersten %s'],
        'bp_for_each' => [' für jedes %s', ' für alle %s'],
        'bp_then' => ', dann ',
        'bp_installments' => [', für %d Ratenzahlung', ', für %d Raten'],
    ];

    protected function findPluralForm($n)
    {
        return $n == 1 ? 0 : 1;
    }
}

class Am_Locale_Formatter_es extends Am_Locale_Formatter_Base
{
    protected $f = [
        'period_day' => ['día', 'días'],
        'period_month' => ['mes', 'meses'],
        'period_year' => ['año', 'años'],
        'period_lifetime' => ' para toda la vida',
        'period_up_to' => ' hasta %s',
        'period_one' => 'uno',

        'bp_Free' => 'Gratis',
        'bp_free' => 'gratis',
        'bp_for' => ' por %s',
        'bp_for_first' => [' para el primer %s', ' durante los primeros %s'],
        'bp_for_each' => [' por cada %s', ' por cada %s'],
        'bp_then' => ', luego ',
        'bp_installments' => [', por %d cuota', ', por %d cuotas'],
    ];

    protected function findPluralForm($n)
    {
        return $n == 1 ? 0 : 1;
    }
}

class Am_Locale_Formatter_pt extends Am_Locale_Formatter_Base
{
    protected $f = [
        'period_day' => ['dia', 'dias'],
        'period_month' => ['mês', 'meses'],
        'period_year' => ['ano', 'anos'],
        'period_lifetime' => ' para sempre',
        'period_up_to' => ' até %s',
        'period_one' => 'um',

        'bp_Free' => 'Grátis',
        'bp_free' => 'grátis',
        'bp_for' => ' por %s',
        'bp_for_first' => ' pelos primeiros %s',
        'bp_for_each' => ' por cada %s',
        'bp_then' => ', então ',
        'bp_installments' => [', em %d parcela', ', em %d parcelas'],
    ];

    protected function findPluralForm($n)
    {
        return $n == 1 ? 0 : 1;
    }
}

class Am_Locale_Formatter_id extends Am_Locale_Formatter_Base
{
    protected $f = [
        'period_day' => 'hari',
        'period_month' => 'bulan',
        'period_year' => 'tahun',
        'period_lifetime' => ' untuk seumur hidup',
        'period_up_to' => ' sampai %s',
        'period_one' => 'satu',

        'bp_Free' => 'Gratis',
        'bp_free' => 'gratis',
        'bp_for' => ' untuk %s',
        'bp_for_first' => ' untuk %s pertama',
        'bp_for_each' => ' untuk setiap %s',
        'bp_then' => ', kemudian ',
        'bp_installments' => ', untuk %d kali cicilan',
    ];

    protected function findPluralForm($n)
    {
        return 0;
    }
}

class Am_Locale_Formatter_nl extends Am_Locale_Formatter_Base
{
    protected $f = [
        'period_day' => ['dag', 'dagen'],
        'period_month' => ['maand', 'maanden'],
        'period_year' => ['jaar', 'jaren'],
        'period_lifetime' => ' levenslang, zo lang als product bestaat',
        'period_up_to' => ' tot %s',
        'period_one' => 'een',

        'bp_Free' => 'Gratis',
        'bp_free' => 'gratis',
        'bp_for' => ' voor %s',
        'bp_for_first' => ' voor de eerste %s',
        'bp_for_each' => ' voor elke %s',
        'bp_then' => ', daarna ',

        'bp_installments' => [', voor %d betaling', ', voor %d termijnen'],
    ];

    protected function findPluralForm($n)
    {
        return $n == 1 ? 0 : 1;
    }
}

class Am_Locale_Formatter_da extends Am_Locale_Formatter_Base
{
    protected $f = [
        'period_day' => ['dag', 'dage'],
        'period_month' => ['måned', 'måneder'],
        'period_year' => ['år', 'år'],
        'period_lifetime' => ' for livet',
        'period_up_to' => ' frem til %s',
        'period_one' => 'en',

        'bp_Free' => 'Gratis',
        'bp_free' => 'gratis',
        'bp_for' => ' i %s',
        'bp_for_first' => ' for den første %s',
        'bp_for_each' => ' for hver %s',
        'bp_then' => ' og derefter ',
        'bp_installments' => [', for %d rate', ', for %d rater'],
    ];

    protected function findPluralForm($n)
    {
        return $n == 1 ? 0 : 1;
    }
}

class Am_Locale_Formatter_fi extends Am_Locale_Formatter_Base
{
    protected $f = [
        'period_day' => ['päivä', 'päivää'],
        'period_month' => ['kuukausi', 'kuukautta'],
        'period_year' => ['vuosi', 'vuotta'],
        'period_lifetime' => ' voimassa ikuisesti',
        'period_up_to' => ' %s asti',
        'period_one' => 'yksi',

        'bp_Free' => 'Maksuton',
        'bp_free' => 'maksuton',
        'bp_for' => ' / %s',
        'bp_for_first' => ' per ensimmäinen %s',
        'bp_for_each' => ' per %s',
        'bp_then' => ', sitten ',
        'bp_installments' => [', %d maksuerä', ', yhteensä %d maksuerää'],
    ];

    protected function findPluralForm($n)
    {
        return $n == 1 ? 0 : 1;
    }
}

class Am_Locale_Formatter_lv extends Am_Locale_Formatter_Base
{
    protected $f = [
        'period_day' => ['diena', 'dienas', 'dienas'],
        'period_month' => ['mēnesis', 'mēneši', 'mēneši'],
        'period_year' => ['gads', 'gadi', 'gadi'],
        'period_lifetime' => ' uz visiem laikiem',
        'period_up_to' => ' līdz %s',
        'period_one' => 'vienu',

        'bp_Free' => 'Bezmaksas',
        'bp_free' => 'par brīvu',
        'bp_for' => ' par %s',
        'bp_for_first' => [' pirmajai %s', ' pirmajai %s', ' pirmajai %s'],
        'bp_for_each' => [' par katru %s', ' par katru %s', ' par katru %s'],
        'bp_then' => ', tālāk ',
        'bp_installments' => [' %d reizi', ' %d reizes', ' %d reizes'],
    ];

    protected function findPluralForm($n)
    {
        return (($n%10==1 && $n%100!=11) ? 0 : ($n!=0 ? 1 : 2));
    }
}

class Am_Locale_Formatter_et extends Am_Locale_Formatter_Base
{
    protected $f = [
        'period_day' => ['päev', 'päeva'],
        'period_month' => ['kuus', 'kuud'],
        'period_year' => ['aastas', 'aastaid'],
        'period_lifetime' => ' kogu elu',
        'period_up_to' => ' kuni %s',
        'period_one' => 'üks',

        'bp_Free' => 'Tasuta',
        'bp_free' => 'tasuta',
        'bp_for' => ' %s eest',
        'bp_for_first' => ' esimesel %s',
        'bp_for_each' => ' iga %s kohta',
        'bp_then' => ', siis ',
        'bp_installments' => [', %d osamakse eest', ', %d osamakse eest'],
    ];

    protected function findPluralForm($n)
    {
        return $n == 1 ? 0 : 1;
    }
}

class Am_Locale_Formatter_ja extends Am_Locale_Formatter_Base
{
    protected $f = [
        'period_day' => ['日'],
        'period_month' => ['月'],
        'period_year' => ['年'],
        'period_lifetime' => ' 生涯',
        'period_up_to' => ' まで %s',
        'period_one' => '1',

        'bp_Free' => '無料',
        'bp_free' => '無料',
        'bp_for' => ' にとって %s',
        'bp_for_first' => ' 最初の %s',
        'bp_for_each' => ' それぞれについて %s',
        'bp_then' => ', それから ',
        'bp_installments' => [', %d 回分'],
    ];

    protected function findPluralForm($n)
    {
        return 0;
    }
}