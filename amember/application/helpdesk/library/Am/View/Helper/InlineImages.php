<?php

class Am_View_Helper_InlineImages extends Zend_View_Helper_Abstract
{
    public function inlineImages($string, $message, $strategy)
    {
        $replace = [];
        foreach ($message->loadGetAttachments() as $_) {
            if (strpos($_->mime, 'image/') === 0) {
                $replace["!{$_->name}!"] = sprintf('<img src="%s" style="max-width:100%%" /><br />', $this->view->escape($this->url($_, $message, $strategy)));
            }
        }

        return $replace ? str_replace(array_keys($replace), array_values($replace), $string) : $string;
    }

    protected function url($upload, $message, $strategy)
    {
        return $strategy->assembleUrl([
            'page_id' => 'view',
            'action' => 'file',
            'message_id' => $this->view->obfuscate($message->message_id),
            'id' => $this->view->obfuscate($upload->upload_id)
        ], 'inside-pages', false);
    }
}