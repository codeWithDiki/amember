<?php

class Am_View_Helper_Skip extends Zend_View_Helper_Abstract
{
    public function skip($string)
    {
        $arr = explode("\n", $string);
        $s_arr = [];

        //find line for skip
        //store this info to array
        foreach ($arr as $k => $v) {
            if (preg_match('/^\s*>\s*>/', trim($v))) {
                $s_arr[$k] = 1;
            } else {
                $s_arr[$k] = 0;
            }
        }

        //add one none skiped line at the end
        //in order to resolve problem if last line should be skipped too
        $arr[] = '';
        $arr = array_map(['Am_Html', 'escape'], $arr);
        $s_arr[] = 0;

        //remove empty lines between skipped lines
        $prev = 0;
        $empty_lines = [];
        foreach ($s_arr as $k => $v) {
            if (trim($arr[$k]) == '') {
                $empty_lines[] = $k;
            } elseif ($v && $prev) {
                foreach ($empty_lines as $key) {
                    $s_arr[$key] = 1;
                }
                $empty_lines = [];
                $prev = 1;
            } elseif ($v) {
                $empty_lines = [];
                $prev = 1;
            } else {
                $prev = 0;
            }
        }

        //skip
        $skipped = 0;
        $skipped_lines = [];
        $label_lines_skipped = ___('lines skipped');
        foreach ($s_arr as $k => $v) {
            if ($v) {
                $skipped_lines[] = $arr[$k];
                if (!$skipped) {
                    $first_skipped_line = $k;
                } else {
                    unset($arr[$k]);
                }
                $skipped++;
            } elseif ($skipped == 1) { //show single line as is
                $arr[$first_skipped_line] = $skipped_lines[0];
                $skipped = 0;
                $skipped_lines = [];
            } elseif ($skipped) {
                $arr[$first_skipped_line] = '<div style="color:#F44336; cursor:pointer; display:inline" onclick="elem = this.nextSibling; elem.style.display = (elem.style.display == \'block\') ? \'none\' : \'block\';">...' . $skipped . ' ' . $label_lines_skipped . '...</div><div style="display:none; border-left:1px solid red; padding-left:0.5em"><pre>' . "\n" . implode("\n", $skipped_lines) . '</pre></div>';
                $skipped = 0;
                $skipped_lines = [];
            }
        }

        $string = implode("\n", $arr);
        return $string;
    }
}