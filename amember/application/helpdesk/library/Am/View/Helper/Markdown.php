<?php

class Am_View_Helper_Markdown extends Zend_View_Helper_Abstract
{
    public function markdown($string)
    {
        $arr = array_map('trim', explode("\n", $string));
        $uls = [];
        $isUl = false;
        foreach ($arr as $k => & $line) {
            $line = preg_replace('/\*\*(.*?)\*\*/', '<strong>\1</strong>', $line);
            $line = preg_replace('/([^*])\*(.*?)\*([^*])/', '\1<em>\2</em>\3', $line);
            if (substr($line, 0, 2) == '* ') {
                if ($isUl) {
                    $uls[count($uls) - 1][] = substr($line, 2);
                    unset($arr[$k]);
                } else {
                    $isUl = true;
                    $uls[] = [];
                    $uls[count($uls) - 1][] = substr($line, 2);
                    $line = '<UL__' . (count($uls) - 1);
                }
            } else {
                $isUl = false;
            }
            if (substr($line, 0, 5) == '#### ') {
                $line = "<h4>" . substr($line, 4) . "</h4>";
            }
            if (substr($line, 0, 4) == '### ') {
                $line = "<h3>" . substr($line, 4) . "</h3>";
            }
            if (substr($line, 0, 3) == '## ') {
                $line = "<h2>" . substr($line, 3) . "</h2>";
            }
            if (substr($line, 0, 2) == '# ') {
                $line = "<h1>" . substr($line, 2) . "</h1>";
            }
            if ($line == '---' || $line == '***') {
                $line = "<hr />";
            }

            if (substr($line, 0, 1) != '<' || substr($line, 0, 2) == '<a') {
                $line .= "\n";
            }
        }

        $replace = [];
        foreach ($uls as $k => $items) {
            $replace["<UL__{$k}"] = sprintf("<ul>%s</ul>", implode("", array_map(function($_) {return "<li>{$_}</li>";}, $items)));
        }
        $string = implode("", $arr);
        return str_replace(array_keys($replace), array_values($replace), $string);
    }
}