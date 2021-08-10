<?php

// for autoloading
class Am_Utils { }

/* * * Helper Functions * */

function memUsage($op)
{

}

function tmUsage($op, $init=false, $start_anyway=false)
{

}

/* * ************* GLOBAL FUNCTIONS
/**
 * Function displays nice-looking error message without
 * using of fatal_error function and template
 */

function amDie($string, $return=false, $last_error = '')
{
    header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);

    if (defined('DATA_DIR'))
        $path = DATA_DIR . '/last_error';
    else
        $path = realpath(dirname(dirname(dirname(__FILE__)))) . '/data/last_error';
    @file_put_contents($path, ($last_error ? $last_error : $string));
    error_log($last_error ? $last_error : $string);
    $out = <<<CUT
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
    <head>
        <title>Fatal Error</title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <style type="text/css">
        body {
                background: #eee;
                font: 80%/100% verdana, arial, helvetica, sans-serif;
                text-align: center;
        }
        #container {
            display: inline-block;
            margin: 50px auto 0;
            text-align: left;
            border: 2px solid #f00;
            background-color: #fdd;
            padding: 10px 10px 10px 10px;
            width: 60%;
        }
        h1 {
            font-size: 12pt;
            font-weight: bold;
        }
        </style>
    </head>
    <body>
        <div id="container">
            <h1>Script Error</h1>
            $string
        </div>
    </body>
</html>
CUT;
    if (!$return) {
        while(@ob_end_clean());
    }
    return $return ? $out : exit($out);
}

/**
 * Function displays nice-looking maintenance message without
 * using template
 */
function amMaintenance($string, $return=false)
{
    if (!$return) {
        header('HTTP/1.1 503 Service Unavailable', true, 503);
    }

    if($_SERVER['HTTP_ACCEPT'] == 'application/json')
    {
        header("Content-type: application/json; charset=UTF-8");
        $out = json_encode(['status' => 'maintenance', 'message' => $string]);
    }else
    {
        $out = <<<CUT
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
    <head>
        <title>Maintenance Mode</title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <style type="text/css">
        body {
            background: #eee;
            font: 80%/100% verdana, arial, helvetica, sans-serif;
            text-align: center;
        }
        #container {
            display: inline-block;
            margin: 50px auto 0;
            text-align: left;
            border: 2px solid #CCDDEB;
            background-color: #DFE8F0;
            padding: 10px;
            width: 60%;
        }
        h1 {
            font-size: 12pt;
            font-weight: bold;
        }
        </style>
    </head>
    <body>
        <div id="container">
            <h1>Maintenance Mode</h1>
            $string
        </div>
    </body>
</html>
CUT;
    }

    return $return ? $out : exit($out);
}

/**
 * @return <type> Return formatted date string
 */
function amDate($string)
{
    if ($string == null)
        return '';
    return date(Am_Di::getInstance()->locale->getDateFormat(), amstrtotime($string));
}

function amDatetime($string)
{
    if ($string == null)
        return '';
    return date(Am_Di::getInstance()->locale->getDateTimeFormat(), amstrtotime($string));
}

function amTime($string)
{
    if ($string == null)
        return '';
    return date(Am_Di::getInstance()->locale->getTimeFormat(), amstrtotime($string));
}

//https://tools.ietf.org/html/rfc4180
function amEscapeCsv($value, $delim)
{
    if(strpos($value, $delim) !== false || strpos($value, '"') !== false || strpos($value, "\r\n") !== false) {
        $value= '"' . str_replace('"', '""', $value) . '"';
    }
    return $value;
}

function check_demo($msg="Sorry, this function disabled in the demo")
{
    if (AM_APPLICATION_ENV == 'demo')
        throw new Am_Exception_InputError($msg);
}

/**
 * Dump any number of variables, last veriable if exists becomes title
 */
function print_rr($vars, $title="==DEBUG==")
{
    $args = func_get_args();
    $html = !empty($_SERVER['HTTP_CONNECTION']);
    if ($args == 1)
        $title = array_pop($args);
    else
        $title = '==DEBUG==';
    echo $html ? "\n<table><tr><td><pre><b>$title</b>\n" : "\n$title\n";
    foreach ($args as $vars) {
        $out = print_r($vars, true);
        echo $html ? print_rrs($out) : $out;
        print $html ? "<br />\n" : "\n\n";
    }
    if ($html)
        print "</pre></td></tr></table><br/>\n";
}

function print_rre($vars, $title="==DEBUG==")
{
    print_rr($vars, $title);
    print("\n==<i>exit() called from print_rre</i>==\n");
    print_rr(get_backtrace_callers(0), 'print_rre called from ');
    exit();
}

function print_rrs($origstr) {
    $str = preg_replace('/^(\s*\(\s*)$/m', '<span style="display:none">$1', $origstr);
    $str = preg_replace('/^(\s*\)\s*)$/m', '$1</span>', $str);

    $a = explode("\n", $str);
    if (count($a)<40) return $origstr;
    foreach($a as $k => $line) {
        if (strpos($line, '<span') === 0) {
            $a[$k-1] = sprintf('<a style="font-weight:bold; text-decoration:none; color:#3f7fb0" href="javascript:;" onclick="var e = this; while(e.nodeName.toLowerCase()!= \'span\') {e = e.nextSibling;} e.style.display = (e.style.display == \'block\') ? \'none\' :  \'block\'; this.style.fontWeight = (this.style.fontWeight == \'bold\') ? \'\' : \'bold\';">%s</a>', $a[$k-1]);
        }
    }
    return implode("\n", $a);
}

function formatSimpleXml(SimpleXMLElement $xml)
{
    $dom = dom_import_simplexml($xml)->ownerDocument;
    $dom->formatOutput = true;
    return $dom->saveXML();
}

function print_xml($xml)
{
    if ($xml instanceof SimpleXMLElement)
        $xml = formatSimpleXml($xml);
    elseif ($xml instanceof DOMElement) {
        $xml->formatOutput = true;
        $xml = $xml->saveXML();
    }
    echo highlight_string($xml, true);
}

function print_xmle($xml)
{
    print_xml($xml);
    print("\n==<i>exit() called from print_rre</i>==\n");
    print_rr(get_backtrace_callers(0), 'print_xmle called from ');
    exit();
}

function moneyRound($v)
{
    // round() return comma as decimal separator in some locales.
    return floatval(number_format((float)$v, 2, '.', ''));
}

function print_bt($title="==BACKTRACE==")
{ /** print backtrace * */
    print_rr(get_backtrace_callers(1), $title);
}

/** @return mixed first not-empty argument */
function get_first($arg1, $arg2)
{
    $args = func_get_args();
    foreach ($args as $a)
        if ($a != '')
            return $a;
}

if (!function_exists("lcfirst")):

    function lcfirst($str)
    {
        $str[0] = strtolower($str[0]);
        return $str;
    }

endif;

if (!function_exists("random_bytes")):

    function random_bytes($length)
    {
        return Am_Di::getInstance()->security->randomString($length);
    }

endif;

/**
 * Remove from string all chars except the [a-zA-Z0-9_-]
 * @param string|null input
 * @return string|null filtered
 */
function filterId($string)
{
    if ($string === null)
        return null;
    return preg_replace('/[^a-zA-Z0-9_-]/', '', $string);
}

/**
 * Transform any date to SQL format yyyy-mm-dd
 */
function amstrtotime($tm)
{
    if ($tm instanceof DateTime)
        return $tm->format('U');
    if (strlen($tm) == 14 && preg_match('/^\d{14}$/', $tm))
        return mktime(substr($tm, 8, 2), substr($tm, 10, 2), substr($tm, 12, 2),
            substr($tm, 4, 2), substr($tm, 6, 2), substr($tm, 0, 4));
    elseif (is_numeric($tm))
        return (int) $tm;
    else {
        $res = strtotime($tm, Am_Di::getInstance()->time);
        if ($res == -1)
            trigger_error("Problem with parcing timestamp [" . htmlentities($tm) . "]", E_USER_NOTICE);
        return $res;
    }
}

/**
 * Return string representation of unsigned 32bit int
 * workaroud for 32bit platforms
 *
 * used to get integer id from string to work with database
 *
 * @param string $str
 * @return string
 */
function amstrtoint($str)
{
    return sprintf('%u', crc32($str));
}

function sqlDate($d)
{
    if (!($d instanceof DateTime) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d))
        return $d;
    else
        return date('Y-m-d', amstrtotime($d));
}

function sqlTime($tm)
{
    if (!($tm instanceof DateTime) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $tm))
        return $tm;
    else
        return date('Y-m-d H:i:s', amstrtotime($tm));
}

/**
 * Convert StringOfCamelCase to string_of_camel_case
 */
function fromCamelCase($string, $separator="_")
{
    return strtolower(preg_replace('/([A-Z])/', $separator . '\1', lcfirst($string)));
}

/**
 * Convert string_of_camel_case to stringOfCamelCase
 * @param <type> $string
 */
function toCamelCase($string)
{
    return lcfirst(str_replace(' ', '', ucwords(preg_replace('/[_-]+/', ' ', $string))));
}

/**
 * Find all defined not abstract successors of given $className
 * @param string $className
 */
function amFindSuccessors($className)
{
    $ret = [];
    foreach (get_declared_classes () as $c) {
        if (is_subclass_of($c, $className)) {
            $r = new ReflectionClass($c);
            if ($r->isAbstract())
                continue;
            $ret[] = $c;
        }
    }
    return $ret;
}

/** translate, sprintf if requested and return string */
function ___($msg)
{
    try {
        $tr = Zend_Registry::get('Zend_Translate');
    } catch (Zend_Exception $e) {
        //trigger_error("Zend_Translate is not available from registry", E_USER_NOTICE);
        return $msg;
    }
    $args = func_get_args();
    $msg = $tr->_(array_shift($args));
    return $args ? vsprintf($msg, $args) : $msg;
}

/** translate and printf format string */
function __e($msg)
{
    $args = func_get_args();
    echo call_user_func_array('___', $args);
}

function is_trial()
{
    return '=-=TRIAL=-=' != ('=-=' . 'TRIAL=-=');
}

function check_trial($errmsg="Sorry, this function is available in aMember Pro not-trial version only")
{
    if (is_trial ()) {
        throw new Am_Exception_FatalError($errmsg);
    }
}

function get_backtrace_callers($skipLevels = 1, $bt=null)
{
    if ($bt === null)
        $bt = debug_backtrace();
    $bt = array_slice($bt, $skipLevels + 1);
    $ret = [];
    foreach ($bt as $b) {
        $b['line'] = intval(@$b['line']);
        if (!isset($b['file']))
            $b['file'] = null;
        if (@$b['object'] && $className = (get_class($b['object']))) {
            $ret[] = $className . "->" . $b['function'] . " in line $b[line] ($b[file])";
        } elseif (@$b['class'])
            $ret[] = "$b[class]:$b[function] in line $b[line] ($b[file])";
        else
            $ret[] = "$b[function] in line $b[line] ($b[file])";
    }
    return $ret;
}

function array_remove_value(& $array, $value)
{
    foreach ($array as $k => $v)
        if ($v === $value)
            unset($array[$k]);
}

/**
 * Replacement for @see glob() function with support for PHAR archives
 * It DOES NOT have full support of glob features, just for basic listing folders and files
 * @param $path
 * @return array found pathnames
 */
function am_glob($path)
{
    if ((strpos($path, 'phar://') !== 0) && (strpos($path, 'vfs://') !== 0)) return glob($path);
    $dir = [];
    $pattern = [];

    if (substr(PHP_OS, 0, 3) == 'WIN')
        $path = str_replace(DIRECTORY_SEPARATOR, '/', $path);

    $path = explode('://', $path, 2);

    if (count($path)>1) {
        $dir[] = $path[0] . ':/'; // slash will be added by implode
        $path = $path[1];
    }

    foreach (explode('/', $path) as $p)
    {
        if ($pattern || strchr($p, '*')!==false || strchr($p, '?') !== false)
            $pattern[] = $p;
        else
            $dir[] = $p;
    }
    $dir = implode('/', $dir);
    $pattern = implode('/', $pattern);

    if (!file_exists($dir)) return [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    $ret = [];

    // convert file pattern to regex
    $pattern = preg_replace('#\.([^*])#', '\\.$1', $pattern);
    $rplCallback = function($match) {
        $regexMatch = [
            '.*' => '[a-zA-Z0-9._-]+',
            '*' => '[a-zA-Z0-9_-]+[a-zA-Z0-9_.,-]*',
            '?' => '[a-zA-Z0-9_-]',
        ];
        return $regexMatch[$match[0]];
    };
    $regex = preg_replace_callback('#\.\*|\*|\?#', $rplCallback, $pattern);
    $regex = '#^'.$regex.'$#';
    foreach ($it as $item)
    {
        $itemPath = $item->getPathname();
        if (substr(PHP_OS, 0, 3) == 'WIN')
            $itemPath = str_replace(DIRECTORY_SEPARATOR, '/', $itemPath);
        if (strpos($itemPath, $dir)!== 0)
            throw new \Exception("Found item path [$itemPath] does not start from [$dir] - [$pattern]");
        $pathPart = substr($itemPath, strlen($dir)+1);
        $pathPart = preg_replace('#\/.$#', '', $pathPart); // remove leading /. to return folders
        if (preg_match($regex, $pathPart))
            $ret[] = $dir . '/' . $pathPart;
    }
    return $ret;
}

/**
 * Replace last segment of IP address with .xx for better privacy
 * @param string $ip
 * @return string
 */
function filterIp($ip)
{
    return (defined('AM_SHOW_FULL_IP') && AM_SHOW_FULL_IP) ? $ip : preg_replace('#\.\d+\s*$#', '.**', $ip);
}

/**
 * Return true if we got a XHR or Fetch request
 * @return bool
 */
function amIsXmlHttpRequest() {
    $x = strtolower(empty($_SERVER['HTTP_X_REQUESTED_WITH']) ? "" : $_SERVER['HTTP_X_REQUESTED_WITH']);
    $a = strtolower(empty($_SERVER['HTTP_ACCEPT']) ? "" : $_SERVER['HTTP_ACCEPT']);
    return ($x == 'xmlhttprequest') ||
        ((0 === strpos($a, 'application/')) && ($a != 'application/javascript') && ($a != 'application/xml') );
}