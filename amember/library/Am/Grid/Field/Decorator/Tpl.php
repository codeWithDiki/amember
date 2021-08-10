<?php

class Am_Grid_Field_Decorator_Tpl extends Am_Grid_Field_Decorator_Abstract
{
    protected $tpl;

    public function __construct($tpl)
    {
        $this->setTpl($tpl);
    }

    /**
     * You can use variables like
     * {user_id} and {getInvoiceId()} in the template
     * it will be automatically fetched from record, escaped and substituted
     */
    function setTpl($tpl)
    {
        $this->tpl = $tpl;
    }

    protected function parseTpl($record)
    {
        $this->_record = $record;
        $ret = preg_replace_callback('|{(.+?)}|', [$this, '_pregReplace'], $this->tpl);
        unset($this->_record);
        return $ret;
    }

    public function _pregReplace($matches)
    {
        $var = $matches[1];
        if ($var == 'THIS_CONTENT') {
            $ret = 'THIS_CONTENT';
        } elseif ($var == 'THIS_URL') {
            $ret = Am_Di::getInstance()->request->getRequestUri();
        } elseif (preg_match('|^(.+)\(\)$|', $var, $regs)) {
            $ret = call_user_func([$this->_record, $regs[1]]);
        } else {
            $ret = $this->_record->{$var};
        }
        return htmlentities(urlencode($ret), ENT_QUOTES, 'UTF-8');
    }

    public function render(&$out, $obj, $controller)
    {
        $content = $this->parseTpl($obj);
        $out = preg_replace('|(<td.*?>)(.+)(</td>)|s', '\1' . str_replace('THIS_CONTENT', '\2', $content) . '\3', $out);
    }
}