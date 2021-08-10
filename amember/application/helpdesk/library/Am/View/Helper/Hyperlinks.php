<?php

class Am_View_Helper_Hyperlinks extends Zend_View_Helper_Abstract
{
    public function hyperlinks($string)
    {
        $pattern = '#((https?://|www\.)(.*?))(([>".?,)]{0,1}(\s|$))|(&(quot|gt);))#i';
        return preg_replace_callback($pattern, function($m) {
            $label = Am_Html::escape($m[1]);
            $url = Am_Html::escape(substr($m[1], 0, 4) == 'www.' ? "http://{$m[1]}" : $m[1]);
            return '<a href="' . $url . '" target="_blank" rel="noreferrer">' . $label . '</a>' . $m[4];
        }, $string);
    }
}