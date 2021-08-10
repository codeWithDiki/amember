<?php

/**
 * if link does not start with "http" it will treated as relative to REL_ROOT_URL
 * you can also use {THIS_URL} to refer to current url
 */
class Am_Grid_Field_Decorator_Link extends Am_Grid_Field_Decorator_Tpl
{
    protected $attr = [
        'class' => 'link'
    ];

    public function __construct($tpl, $target = '_top')
    {
        parent::__construct($tpl);
        $this->setAttribute('target', $target);
    }

    protected function parseTpl($record)
    {
        $ret = parent::parseTpl($record);
        if ((strpos($ret, 'http')!==0) && ($ret[0] != '/')) {
            $ret = Am_Di::getInstance()->url($ret, false);
        }
        return $ret;
    }

    function setTarget($target)
    {
        $this->setAttribute('target', $target);
    }

    function setAttribute($name, $value)
    {
        $this->attr[$name] = $value;
    }

    public function render(&$out, $obj, $controller)
    {
        $this->attr['href'] = $this->parseTpl($obj);
        $start = sprintf('<a %s>', Am_Html::attrs($this->attr));
        $stop = '</a>';
        $out = preg_replace('|(<td.*?>)(.+)(</td>)|', '\1'.$start.'\2'.$stop.'\3', $out);
    }
}