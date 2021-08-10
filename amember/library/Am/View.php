<?php

/**
 * @package Am_View
 */

/**
 * Class represents templates - derived from Zend_View_Abstract
 * @package Am_View
 */
class Am_View extends Zend_View_Abstract
{
    /** @var Am_Di */
    public $di;
    protected $layout = null;

    protected $_scriptVars = [];
    private $_scriptVarsPrinted = 0;

    /** @see _scriptVars() */

    function __construct(Am_Di $di = null)
    {
        parent::__construct();
        $this->headMeta()->setName('generator', 'aMember Pro');
        $this->di = $di ?: Am_Di::getInstance();
        if ($this->di->hasService('theme')) {
            $this->theme = $this->di->theme;
        } else {
            $this->theme = new Am_Theme($this->di, 'default', []);
        }
        $this->setHelperPath('Am/View/Helper', 'Am_View_Helper_');
        $this->setEncoding('UTF-8');
        foreach ($this->di->viewPath as $dir) {
            $this->addScriptPath($dir);
        }
        if (!$this->getScriptPaths()) {
            $this->addScriptPath(dirname(__FILE__) . '/../../application/default/views');
        }

        static $init = 0;
        if (!$init++) {
            $this->headScript()->prependScript(<<<CUT
window.rootUrl = {$this->j($this->url("",false))}; //kept for compatibilty only! use amUrl() instead
function amUrl(u, return_path) {
    var ret = {$this->j($this->url("",false))} + u;
    return (return_path || 0) ? [ret, []] : ret;
};
CUT
            );
        }
    }

    function j($_)
    {
        return json_encode($_);
    }

    /**
     * Return string of escaped HTML attributes from $attrs array
     * @param array $attrs
     */
    function attrs(array $attrs)
    {
        return Am_Html::attrs($attrs);
    }

    function display($name)
    {
        echo $this->render($name);
    }

    protected function _run()
    {
        $arg = func_get_arg(0);

        Am_Di::getInstance()->hook->call(Am_Event::BEFORE_RENDER,
            ['view' => $this, 'templateName' => $arg]);

        extract($this->getVars());
        $savedLayout = $this->layout;
        ob_start();
        include func_get_arg(0);
        $content = ob_get_contents();
        ob_end_clean();
        if ($this->layout && $savedLayout != $this->layout) // was switched in template
        {
            while ($layout = array_shift($this->layout))
            {
                ob_start();
                include $this->_script($layout);
                $content = ob_get_contents();
                ob_end_clean();
            }
        }

        // if we got scriptVars that were not rendered in printLayoutHead, we will print it
        // here. If possible - before </body>, if not - just at bottom
        if ($this->_scriptVars) {
            $sv = $this->_processScriptVars();
            $count = 0;
            $content = preg_replace_callback('#<\/body>#i', function($regs) use ($sv) {
                return $sv . $regs[0]; //
            }, $content, 1, $count);
            if (!$count) $content .= $sv;
        }

        $event = Am_Di::getInstance()->hook->call(new Am_Event_AfterRender(null,
            [
                'view' => $this,
                'templateName' => $arg,
                'output' => $content,
            ]));
        echo $event->getOutput();
    }

    public function setLayout($layout)
    {
        $this->layout[] = $layout;
    }

    public function formOptions($options, $selected = '')
    {
        return Am_Html::renderOptions($options, $selected);
    }

    public function formCheckboxes($name, $options, $selected)
    {
        $out = "";
        $name = Am_Html::escape($name);
        foreach ($options as $k => $v)
        {
            $k = Am_Html::escape($k);
            $sel = is_array($selected) ? in_array($k, $selected) : $k == $selected;
            $sel = $sel ? " checked='checked'" : "";
            $out .= "<label><input type='checkbox' name='{$name}[]' value='$k'$sel> $v</label><br />\n";
        }
        return $out;
    }

    public function formRadio($name, $options, $selected)
    {
        $out = "";
        $name = Am_Html::escape($name);
        foreach ($options as $k => $v)
        {
            $k = Am_Html::escape($k);
            $sel = $k == $selected;
            $sel = $sel ? " checked='checked'" : "";
            $out .= "<input type='radio' name='{$name}' value='$k'$sel>\n$v\n<br />\n";
        }
        return $out;
    }

    function strLimit($str, $limit, $end="&hellip;")
    {
        return htmlentities(mb_substr($str, 0, $limit), null, 'UTF-8') .
            (mb_strlen($str) > $limit ? $end : '');
    }

    function getI18n()
    {
        $am_i18n = json_encode([
            'toggle_password_visibility' => ___('Toggle Password Visibility'),
            'password_strength' => ___('Password Strength'),

            'upload_browse' => ___('browse'),
            'upload_upload' => ___('upload'),
            'upload_files' => ___('Uploaded Files'),
            'upload_uploading' => ___('Uploading...'),
            'ms_please_select' => ___('-- Please Select --'),
            'ms_select_all' => ___('Select All'),
            'please_wait' => ___('Please Wait...'),

            'file_style_browse' => ___('Browse…'),
        ]);
        return "am_i18n = $am_i18n;";
    }

    function initI18n()
    {
        $this->headScript()->prependScript($this->getI18n());
        $this->_scriptVars([
            'msg_select_state' => ___('[Select state]'),
        ]);
    }

    /**
     * Output all code necessary for aMember, this must be included before
     * closing </head> into layout.phtml
     * @param $safe_jquery_load  - Load jQuery only if it was not leaded before(true|false). Default is false.
     *          safe_query_load now implemented in JS source, here it is ignored
     */
    function printLayoutHead($need_reset=true, $safe_jquery_load = false)
    {
        $this->initI18n();

        list($lang, ) = explode('_', $this->di->locale->getId());
        $jLang = json_encode($lang);

        $t = "?" . AM_VERSION_HASH;
        if($need_reset) {
            $this->headLink()
                 ->appendStylesheet($this->_scriptCss('reset.css').$t);
        }
        $this->headLink()
            ->appendStylesheet($this->_scriptCss('amember.css').$t);
        if (!defined('AM_USE_NEW_CSS')) // support old .grid .row .error and so on
            $this->headLink()
                ->appendStylesheet($this->_scriptCss('compat.css').$t);

        $this->headLink()
            ->appendStylesheet(['href' => "https://use.fontawesome.com/releases/v5.15.1/css/all.css",
                'rel' => 'stylesheet', 'media' => 'screen',
                'extras' => ['crossorigin' => 'anonymous'] ]);

        $this->theme->printLayoutHead($this);

        if ($siteCss = $this->_scriptCss('site.css')) {
            $_ = str_replace(parse_url(ROOT_URL, PHP_URL_PATH), '', $siteCss);
            $st = "?" . crc32(filemtime(ROOT_DIR . $_));
            $this->headLink()->appendStylesheet($siteCss . $st);
        }

        $this->headLink()->appendStylesheet($this->_scriptJs('jquery/jquery.ui.css').$t);

        $hs = $this->headScript();
        $locale = $this->di->locale;


        $script = "";
        try {
            $script .= "window.uiDateFormat = " . json_encode($this->convertDateFormat($locale->getDateFormat())) . ";\n";
            $script .= "window.momentDateFormat = " . json_encode($this->convertDateFormat($locale->getDateFormat(), 'moment')) . ";\n";
            $script .= sprintf("window.uiDefaultDate = new Date(%d,%d,%d);\n", date('Y'), date('n')-1, date('d'));
        } catch (Exception $e) {
            // we can live without it if Am_Locale is not yet registered, we will just skip this line
        }

        $hs->prependFile($this->_scriptJs('user.js').$t);
        $hs->prependFile($this->_scriptJs('vendors-user.js').$t);
        $hs->prependFile($this->_scriptJs('vendors-admin-user.js').$t);

        if ($safe_jquery_load) {
            $hs->prependScript('if (typeof jQuery == \'undefined\') {document.write(\'<scri\' + \'pt type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js" crossorigin="anonymous"></scr\'+\'ipt>\');} else {$=jQuery;}');
        } else {
            $hs->prependFile("https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js", 'text/javascript',
                ['integrity'=>"sha512-bLT0Qm9VnAYZDflyKcBaQ2gg0hSYNQrJ8RilYldYQ1FxQYoCLtUjuuRuZo+fjqhx/qtq/1itJ0C2ejDxltZVFg==", 'crossorigin'=>"anonymous"]);
        }

        // Fetch polyfill for IE11 and below
        if (
            preg_match('~MSIE|Internet Explorer~i', $_SERVER['HTTP_USER_AGENT']) ||
            preg_match('~Trident/7.0(; Touch)?; rv:11.0~',$_SERVER['HTTP_USER_AGENT']) ||
            preg_match('/ip(hone|ad|od)/i', $_SERVER['HTTP_USER_AGENT'])
        ) {
            $hs->prependFile('https://polyfill.io/v3/polyfill.min.js?features=fetch');
        }

        $hs->prependScript($script); // it is important to have amVars set before the user.js included


        if (file_exists(AM_CONFIGS_PATH . '/site.js'))
            $hs->appendFile($this->di->url('application/configs/site.js',false));

        echo "<!-- userLayoutHead() start -->\n";
        echo $this->placeholder("head-start") . "\n";
        echo $this->placeholder("head-start-user") . "\n";
        echo $this->headMeta() . "\n";
        echo $this->headLink() . "\n";
        echo $this->headStyle() . "\n";
        echo $this->_processScriptVars(true) . "\n";
        echo $this->headScript() . "\n";
        echo $this->placeholder('head-finish') . "\n";
        echo $this->placeholder("head-finish-user") . "\n";
        echo "<!-- userLayoutHead() finish -->\n";
    }

    function adminHeadInit()
    {
        $this->initI18n();

        $js_v = AM_VERSION_HASH;
        $t = "?" . AM_VERSION_HASH;

        $this->headLink()->appendStylesheet([
            'href' => "https://fonts.googleapis.com/css?family=Roboto:400,700",
            'rel' => 'stylesheet', 'media' => 'screen',
            'extras' => ['crossorigin' => 'anonymous'],
        ]);
        $this->headLink()->appendStylesheet($this->_scriptJs("jquery/jquery.ui.css").$t);
        $this->headLink()->appendStylesheet($this->_scriptCss('admin.css').$t);
        $this->headLink()->appendStylesheet([
            'href' => "https://use.fontawesome.com/releases/v5.15.1/css/all.css",
            'rel' => 'stylesheet', 'media' => 'screen',
            'extras' => ['crossorigin' => 'anonymous'],
        ]);

        if (!defined('AM_USE_NEW_CSS')) // support old .grid .row .error and so on
            $this->headLink()
                ->appendStylesheet($this->_scriptCss('compat.css').$t);

        list($lang, ) = explode('_', $this->di->locale->getId());

        $this->js___('login, email or name');
        $this->js___('Find an user…');
        $this->js___('Import From CSV');
        $this->js___('Export To CSV');

        if ($theme = $this->_scriptCss('admin-theme.css'))
            $this->headLink()->appendStylesheet($theme);

        $jsPublicUrl = $this->di->url("application/default/views/public/js/", false);

        $locale = $this->di->locale;
        $this->headScript()
            ->prependFile($jsPublicUrl . 'admin.js'.$t)
            ->prependFile($jsPublicUrl . 'vendors-admin.js'.$t)
            ->prependFile($jsPublicUrl . 'vendors-admin-user.js'.$t)
            ->prependFile("https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js", 'text/javascript',
                ['integrity'=>"sha512-bLT0Qm9VnAYZDflyKcBaQ2gg0hSYNQrJ8RilYldYQ1FxQYoCLtUjuuRuZo+fjqhx/qtq/1itJ0C2ejDxltZVFg==", 'crossorigin'=>"anonymous"]);

        $this->placeholder("head-start-admin")->append($this->_processScriptVars(true));

        $_ = json_encode([
            'uiDateFormat' => $this->convertDateFormat($locale->getDateFormat()),
            'momentDateFormat' => $this->convertDateFormat($locale->getDateFormat(), 'moment'),
            'lang' => $lang,
            'configDisable_rte' => $this->di->config->get('disable_rte', 0),
        ]);

        $date = [date('Y'), date('n')-1, date('d')];
        $this->headScript()->prependScript(<<<CUT
            { // set aMember admin UI settings
                const _amConfig = $_; 
                for (const _ in _amConfig) window[_] = _amConfig[_];
                window.uiDefaultDate = new Date({$date[0]},{$date[1]},{$date[2]});
            }  
CUT
        );
        $this->headScript()
            ->appendFile("https://cdn.ckeditor.com/4.10.0/full-all/ckeditor.js", 'text/javascript', [
                'crossorigin'=>'anonymous',
            ]); // will keep as external deps - too many files!

        if (!empty($this->USE_VUE))
        {
            $this->headStyle()->captureStart();
            echo '[v-cloak] {display:none;}';
            $this->headStyle()->captureEnd();
            if (AM_APPLICATION_ENV == 'debug')
                $this->headScript()->prependFile('https://cdnjs.cloudflare.com/ajax/libs/vue/2.6.12/vue.js', 'text/javascript', [
                    'crossorigin'=>'anonymous',
                ]);
            else
                $this->headScript()->prependFile('https://cdnjs.cloudflare.com/ajax/libs/vue/2.6.12/vue.min.js', 'text/javascript', [
                    'integrity'=>"sha512-BKbSR+cfyxLdMAsE0naLReFSLg8/pjbgfxHh/k/kUC82Hy7r6HtR5hLhobaln2gcTvzkyyehrdREdjpsQwy2Jw==", 'crossorigin'=>"anonymous",
                ]);
        }
    }

    /**
     * That is an utility function to inform JS core
     * that a template has been replaced with new version
     *
     * Call it from template and it will output
     * necessary JSON in the printLayoutHead()
     */
    function _scriptReplaced($tplName, $version = "1")
    {
        $this->_scriptVars[ 'script-replaced-' . $tplName ] = $version;
        return $this;
    }

    /**
     * That is an utility function to pass a variable to JS core
     *
     * It will be passed to amVars array
     * @param array|string values|key
     * @param mixed value (not used if array passed)
     */
    function _scriptVars($vars, $val = null)
    {
        if (func_num_args()==1)
            $this->_scriptVars = array_merge($this->_scriptVars, $vars);
        else
            $this->_scriptVars[$vars] = $val;
        return $this;
    }

    /**
     * Used to pass translation into JavaScript
     * Use this function instead of anything else, because it may be
     * used by translation tools to find strings!
     * @param $msg
     * @return Am_View
     */
    function js___($msg) {
        $this->_scriptVars['msg_' . $msg] = ___($msg);
        return $this;
    }

    /**
     * Array of vars prepared for assignment to window.amVars
     * it will be rendered to <head> and optionally to bottom of <body>
     */
    function _getScriptVars($addDefaults = true)
    {
        $ret = $this->_scriptVars;
        if ($addDefaults)
            $ret = array_merge([
                'public-path' =>  $_ = preg_replace('#application$#', '', $this->di->url("application", false)),
                'api-url' => $_, // the same but who knows
                'datepickerDefaults' => [
                    'closeText' => ___('Done'),
                    'prevText' => ___('Prev'),
                    'nextText' => ___('Next'),
                    'currentText' => ___('Today'),
                    'monthNames' => array_values($this->di->locale->getMonthNames('wide', true)),
                    'monthNamesShort' => array_values($this->di->locale->getMonthNames('abbreviated', true))
                ],
                'langCount' => count($this->di->getLangEnabled(false)),
            ], $ret);
        return $ret;
    }

    function _processScriptVars($addDefaults = false)
    {
        if (!$this->_scriptVars)
            if (!$addDefaults)
                return;

        $_ = $this->_renderScriptVars($this->_getScriptVars($addDefaults));
        $this->_scriptVars = [];
        $this->_scriptVarsPrinted++;
        return $_;
    }

    function _renderScriptVars(array $scriptVars) {
        return sprintf('<script type="text/am-vars">%s</script>'."\n",
            json_encode($scriptVars));
    }

    /**
     * Convert date format from PHP date() to Jquery UI
     * @param string $dateFormat
     * @return string
     */
    public function convertDateFormat($dateFormat, $type = 'ui')
    {
        $map = [
            'ui' => [
                'j' => 'd', //day of month (no leading zero)
                'd' => 'dd', //day of month (two digit)
                'z' => 'oo', //day of the year (three digit)
                'D' => 'D', //day name short
                'l' => 'DD', //day name long
                'm' => 'mm', //month of year (two digit)
                'M' => 'M', //month name short
                'F' => 'MM', //month name long
                'y' => 'y', //year (two digit)
                'Y' => 'yy', //year (four digit)
            ],
            'moment' => [
                'j' => 'D', //day of month (no leading zero)
                'd' => 'DD', //day of month (two digit)
                'z' => 'DDDD', //day of the year (three digit)
                'D' => 'ddd', //day name short
                'l' => 'dddd', //day name long
                'm' => 'MM', //month of year (two digit)
                'M' => 'MMM', //month name short
                'F' => 'MMMM', //month name long
                'y' => 'YY', //year (two digit)
                'Y' => 'YYYY', //year (four digit)
            ]
        ];
        return strtr($dateFormat, $map[$type]);
    }

    function getThemes($themeType = 'user')
    {
        $td = AM_THEMES_PATH ;
        $entries = scandir($td);
        $ret = ['default' => "Default Theme"];
        foreach ($entries as $d)
        {
            if ($d[0] == '.')
                continue;
            $p = "$td/$d";
            if (is_dir($p) && is_readable($p))
                $ret[$d] = ucwords(str_replace ('-', ' ', $d));
        }
        return $ret;
    }

    /**
     * Converts $path to a file located inside aMember folder (!)
     * to an URL (if possible, relative, if impossible, using ROOT_SURL)
     * @throws Am_Exception_InternalError
     * @return string
     */
    function pathToUrl($path)
    {
        if (AM_PHAR && (strpos($path, 'phar://')===0))
        {
            $rel = preg_replace('#^.+\.phar#', '', $path);
        } else {
            $r = realpath(Am_Di::getInstance()->root_dir);
            $p = realpath($path);
            if (strpos($p, $r) !== 0)
                if (AM_APPLICATION_ENV != 'demo')
                    throw new Am_Exception_InternalError("File [$p] is not inside application path [$r]");
            $rel = substr($p, strlen($r));
        }
        return REL_ROOT_URL . str_replace('\\', '/', $rel);
    }

    /** Find location of the CSS (respecting the current theme)
     * @return string|null path including REL_ROOT_URL, or null
     */
    function _scriptCss($name, $escape = true)
    {
        try {
            $ret = $this->pathToUrl($this->_script('public/css/' . $name));
        } catch (Zend_View_Exception $e) {
            return;
        }
        return $escape ? $this->escape($ret) : $ret;
    }

    /** Find location of the CSS (respecting the current theme)
     * @return string|null path including REL_ROOT_URL, or null
     */
    function _scriptJs($name, $escape = true)
    {
        try {
            $ret = $this->pathToUrl($this->_script('public/js/' . $name));
        } catch (Zend_View_Exception $e) {
            return;
        }
        return $escape ? $this->escape($ret) : $ret;
    }

    /** Find location of the Image (respecting the current theme)
     * @return string|null path including REL_ROOT_URL, or null
     */
    function _scriptImg($name, $escape = true)
    {
        try {
            $ret = $this->pathToUrl($this->_script('public/img/' . $name));
        } catch (Zend_View_Exception $e) {
            return;
        }
        return $escape ? $this->escape($ret) : $ret;
    }

    /** Find path of the Image/CSS/JS (respecting the current theme)
     * @return string|null path
     */
    function _scriptPath($type/*img,js,css*/, $name, $escape = true)
    {
        try {
            $ret = $this->_script("public/$type/" . $name);
        } catch (Zend_View_Exception $e) {
            return;
        }
        return $escape ? $this->escape($ret) : $ret;
    }

    /**
     * Returns url of current page with given _REQUEST parameters overriden
     * @param array $parametersOverride
     */
    function overrideUrl(array $parametersOverride = [], $skipRequestParams = false)
    {
        $vars = $skipRequestParams ? $parametersOverride : array_merge($_REQUEST, $parametersOverride);
        return $this->di->request->assembleUrl(false,true) . '?' . http_build_query($vars, '', '&');
    }

    /**
     * print escaped current url without parameters
     */
    function pUrl($controller = null, $action = null, $module = null, $params = null)
    {
        $args = func_get_args();
        echo call_user_func_array([Am_Di::getInstance()->request, 'makeUrl'], $args);
    }

    function rurl($path, $params = null, $encode = true)
    {
        return call_user_func_array([$this->di, 'rurl'], func_get_args());
    }

    function surl($path, $params = null, $encode = true)
    {
        return call_user_func_array([$this->di, 'surl'], func_get_args());
    }

    /**
     * Add necessary html code to page to enable graphical reports
     */
    function enableReports()
    {
        static $reportsEnabled = false;
        if ($reportsEnabled)
            return;
        $url1 = "//cdnjs.cloudflare.com/ajax/libs/raphael/2.1.2/raphael-min.js";
        $url2 = "//cdnjs.cloudflare.com/ajax/libs/morris.js/0.5.1/morris.min.js";
        $this->placeholder('head-finish')->append(<<<CUT
<script language="javascript" type="text/javascript" src="$url1"></script>
<script language="javascript" type="text/javascript" src="$url2"></script>
CUT
        );
        $reportsEnabled = true;
    }
}

/**
 * @package Am_View
 * helper to display theme variables in human-readable format
 */
class Am_View_Helper_ThemeVar
{
    function themeVar($k, $default = null)
    {
        $k = sprintf('themes.%s.%s', Am_Di::getInstance()->config->get('theme', 'default'), $k);
        return Am_Di::getInstance()->config->get($k, $default);
    }
}

/**
 * @package Am_View
 * helpder to display time interval in human readable format
 */
class Am_View_Helper_GetElapsedTime
{
    public $view = null;

    function setView(Zend_View_Interface $view)
    {
        $this->view = $view;
    }

    function getElapsedTime($date, $compact = false) {
        $sdate = amstrtotime($date);
        $edate = $this->view->di->time;

        $time = $edate - $sdate;
        if ($time < 0 || ($time>=0 && $time<=59)) {
            // Seconds
            return ___('just now');
        } elseif($time>=60 && $time<=3599) {
            // Minutes
            $pmin = $time / 60;
            $premin = explode('.', $pmin);

            $timeshift = $premin[0] . ($compact ? '' : ' ') . ___('min');

        } elseif($time>=3600 && $time<=86399) {
            // Hours
            $phour = $time / 3600;
            $prehour = explode('.', $phour);

            $timeshift = $prehour[0]. ($compact ? '' : ' ') . ___('hrs');

        } elseif($time>=86400 && $time<86400*30) {
            // Days
            $pday = $time / 86400;
            $preday = explode('.', $pday);

            $timeshift = $preday[0] . ($compact ? 'd' : ' ' . ___('days'));

        } elseif ($time>=86400*30 && $time<86400*30*12) {
            // Month
            $pmonth = $time / (86400 * 30);
            $premonth = explode('.', $pmonth);

            $timeshift = ($compact ? '' : ___('more than')) . ' ' . $premonth[0] .
                ($compact ? 'm' : ' ' . ___('month')) . ($compact ? '+' : '');
        } else {
            // Year
            $pyear = $time / (86400 * 30 * 12);
            $preyear = explode('.', $pyear);

            $timeshift = ($compact ? '' : ___('more than')) . ' ' . $preyear[0] .
                ($compact ? 'y' : ' ' . ___('year')) . ($compact ? '+' : '');
        }
        return $timeshift . ' ' . ___('ago');
    }
}

/**
 * helper to display blocks
 * @package Am_View
 * @link Am_Blocks
 * @link Am_Block
 */
class Am_View_Helper_Blocks extends Zend_View_Helper_Abstract
{
    /** @var Am_Blocks */
    protected $blocks;

    /** @return Am_Blocks */
    function getContainer()
    {
        if (!$this->blocks)
            $this->blocks = $this->view->di->blocks;
        return $this->blocks;
    }

    function setContainer(Am_Blocks $blocks)
    {
        $this->blocks = $blocks;
    }

    /**
     * Render blocks by $path pattern
     * Each block will be outlined by envelope
     * $vars array will be passed to $view into the block render()
     * @param string $path
     * @param string $envelope
     * @param array $vars
     * @return string
     */
    function render($path, $envelope = "%s", array $vars = [])
    {
        $out = "";
        foreach ($this->getContainer()->get($path) as $block)
        {
            $view = new Am_View;
            if ($vars) $view->assign($vars);
            $view->blocks()->setContainer($this->getContainer());
            $out .= $block->render($view, $envelope);
        }
        return $out;
    }

    /** if called as blocks() returns itself, if called as block('path') calls render('path') */
    function blocks($path = null, $envelope = "%s", $vars = [])
    {
        return $path === null ? $this : $this->render($path, $envelope, $vars);
    }

    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->getContainer(), $name], $arguments);
    }

    public function requireJs($path)
    {
        if (!preg_match('#^(/|http(s?):)#i', $path))
            $path = $this->view->_scriptJs($path);
        $this->view->headScript()->appendFile($path . "?" . AM_VERSION_HASH);
        return $this;
    }

    public function requireCss($path)
    {
        if (!preg_match('#^(/|http(s?):)#i', $path))
            $path = $this->view->_scriptCss($path);
        $this->view->headLink()->appendStylesheet($path);
        return $this;
    }
}

/**
 * View helper to return translagted text (between start() and stop() calls)
 * @package Am_View
 * @deprecated
 */
class Am_View_Helper_Tr extends Zend_View_Helper_Abstract
{
    protected $text;
    protected $args;

    /**
     * Return translated text if argument found, or itself for usage of start/stop
     * @param string|null $text
     * @return Am_View_Helper_Tr|string
     */
    function tr($text = null)
    {
        if ($text === null)
            return $this;
        $this->args = func_get_args();
        $this->text = array_shift($this->args);
    }

    function start($arg1=null, $arg2=null)
    {
        $this->args = func_get_args();
        ob_start();
    }

    function stop()
    {
        $this->text = ob_get_clean();
        $this->doPrint();
    }

    protected function doPrint()
    {
        $tr = Zend_Registry::get('Zend_Translate');
        if (!$tr)
        {
            trigger_error("No Zend_Translate instance found", E_USER_WARNING);
            echo $this->text;
        }
        /* @var $tr Zend_Translate_Adapter */
        $this->text = $tr->_(trim($this->text));
        vprintf($this->text, $this->args);
    }
}

/**
 * For usage in templates
 * echo escaped variable
 */
function p($var)
{
    echo htmlentities($var, ENT_QUOTES, 'UTF-8', false);
}

/** echo variable escaped for javascript
 */
function j($var)
{
    echo strtr($var, ["'" => "\\'", '\\' => '\\\\', '"' => '\\"', "\r" => '\\r', '</' => '<\/', "\n" => '\\n']);
}