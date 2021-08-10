<?php

/**
 * Simple text template engine
 * @package Am_Utils
 */
class Am_SimpleTemplate
{
    protected $vars = [];
    static $modifiers = [
        'date' => 'amDate',
        'time' => 'amDateTime',
        'escape' => ['Am_Html', 'escape'],
        'urlencode' => 'urlencode',
        'ucfirst' => 'ucfirst',
        'strtolower' => 'strtolower',
        'strtoupper' => 'strtoupper',
        'intval' => 'intval',
        'number_format' => 'number_format',
        'currency' => ['Am_Currency', 'render'],
        'chunk_split' => 'chunk_split',
        'json_encode' => 'json_encode',
        'country' => ['Am_SimpleTemplate', 'country'],
        'state' => ['Am_SimpleTemplate', 'state'],
        'idn' => ['Am_SimpleTemplate', 'idn'],
        'default' => ['Am_SimpleTemplate', '_default'],
        'label' => ['Am_SimpleTemplate', '_label'],
        'nl2br' => 'nl2br',
    ];

    const NOT_FOUND = 'placeholder-not-found';

    static function _default($v, $d)
    {
        return $v ?: $d;
    }

    static function _label($v, $fn, $_)
    {
        if (!$f = Am_Di::getInstance()->userTable->customFields()->get($fn)) {
            return $v;
        }

        if (in_array($f->type, ['select', 'radio', 'multi_select', 'checkbox', 'single_checkbox'])) {
            if ($f->isArray()) {
                if ($v == 'Array') {
                    $v = $_;
                } else {
                    $v = unserialize($v);
                }
            } else {
                $v = [$v];
            }

            return implode(', ', array_map(function($_) use ($f) {return isset($f->options[$_]) ? $f->options[$_] : $_;}, $v));
        }
        return $v;
    }

    static function country($code)
    {
        $country = Am_Di::getInstance()->countryTable->findFirstByAlpha2($code);
        return $country ? $country->title : $code;
    }

    static function state($code)
    {
        $state = Am_Di::getInstance()->stateTable->findFirstByState($code);
        return $state ? $state->title : $code;
    }

    static function idn($url)
    {
        $parts = parse_url($url);
        if (isset($parts['host'])) {
            $parts['host'] = idn_to_utf8($parts['host']);
        }
        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host = isset($parts['host']) ? $parts['host'] : '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $user = isset($parts['user']) ? $parts['user'] : '';
        $pass = isset($parts['pass']) ? ':' . $parts['pass']  : '';
        $pass = ($user || $pass) ? "$pass@" : '';
        $path = isset($parts['path']) ? $parts['path'] : '';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
        return "$scheme$user$pass$host$port$path$query$fragment";
    }

    function assignStdVars()
    {
        $this->assign('site_title', Am_Di::getInstance()->config->get('site_title', 'aMember Pro'));
        $this->assign('root_url', ROOT_URL);
        $this->assign('root_surl', ROOT_SURL);
        $this->assign('cur_date', amDate('now'));
        $this->assign('cur_datetime', amDatetime('now'));
        return $this;
    }

    function __get($k)
    {
        return array_key_exists($k, $this->vars) ? $this->vars[$k] : self::NOT_FOUND;
    }

    function __isset($k)
    {
        return array_key_exists($k, $this->vars);
    }

    function __set($k, $v)
    {
        $this->vars[$k] = $v;
    }

    public function __call($name, $arguments)
    {
        if (strpos($name, 'set')===0) {
            $var = lcfirst(substr($name, 3));
            $this->vars[$var] = $arguments[0];
            return $this;
        }
        trigger_error("Method [$name] does not exists in " . __CLASS__, E_USER_ERROR);
    }

    function assign($k, $v = null)
    {
        if (is_array($k) && ($v === null)) {
            $this->vars = array_merge($this->vars, $k);
        } else {
            $this->vars[$k] = $v;
        }
    }

    function render($text)
    {
        $text = preg_replace_callback('/{%(.*?)%}/s', [$this, '_replaceLoop'], $text);
        $text = preg_replace_callback('/{{(.*?)}}/', [$this, '_replaceSpin'], $text);
        return preg_replace_callback("/%('?[\/a-zA-Z+-][\/a-zA-Z0-9_+-]*'?)(?:\.([a-zA-Z0-9_]+))?(\|[a-zA-Z0-9_: |]+)?%/", [$this, '_replace'], $text);
    }

    /**
     * {%invoice_items
     *    <strong>%item_title%</strong>
     * %}
     */
    public function _replaceLoop(array $matches)
    {
        preg_match('/^([^%\s]*)/i', $matches[1], $m);
        if (!isset($this->vars[$m[1]])) return $matches[0];
        $body = preg_replace('/^([^%\s]*)/i', '', $matches[1]);
        $out = '';
        $t = clone $this;
        foreach ((array)$this->vars[$m[1]] as $item) {
            foreach ($item as $k => $v) {
                $t->assign($k, $v);
            }
            $out .= $t->render($body);
        }
        return $out;
    }

    /**
     * {{Variant 1|Variant 2|Variant 3}}
     */
    public function _replaceSpin(array $matches)
    {
        $alt = explode('|', $matches[1]);
        return $alt[rand(0, count($alt)-1)];
    }

    /**
     * %varname%
     * %varname.propname%
     * %varname.propname|date%
     * %varname.propname|currency%
     * %varname.propname|default:defaultVal%
     * %'literal'%
     * %'now'|date%
     * %'amember/login'|urlencode%
     */
    public function _replace(array $matches)
    {
        $v = (substr($matches[1], 0, 1) == "'" && substr($matches[1], -1) == "'") ?
            substr($matches[1], 1, strlen($matches[1])-2) : //literal
            $this->__get($matches[1]);

        if (isset($matches[2]) && strlen($matches[2])) {
            $k = $matches[2];
            if (is_object($v)) {
                $v = (property_exists($v, $k) || isset($v->{$k})) ? $v->{$k} : self::NOT_FOUND;
            } elseif (is_array($v)) {
                $v = array_key_exists($k, $v) ? $v[$k] : self::NOT_FOUND;
            } else {
                $v = self::NOT_FOUND;
            }
        }

        if($v === self::NOT_FOUND) return $matches[0];

        $_v = $v;
        if (is_array($v)) $v = 'Array';
        if (is_object($v)) $v = 'Object';

        if (isset($matches[3]) && strlen($matches[3])) {
            $modifiers = array_filter(explode('|', $matches[3]));
            foreach ($modifiers as $m) {
                $_ = explode(':', $m);
                $m = array_shift($_);
                if (empty(self::$modifiers[$m])) continue;
                array_unshift($_, $v);
                if ($m == 'label') {
                    array_push($_, $matches[2], $_v);
                }
                $v = call_user_func_array(self::$modifiers[$m], $_);
            }
        }
        return $v;
    }
}