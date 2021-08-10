<?php
class Am_Mail_Transport_SendGrid extends Am_Mail_Transport_Base
{
    const API_ENDPOINT = 'https://api.sendgrid.com/api/mail.send.json';

    protected $_api_user = null,
        $_api_key = null;

    public function __construct($config)
    {
        $this->_api_user = $config['api_user'];
        $this->_api_key = $config['api_key'];
    }

    protected function _extractHeaderToParams(Zend_Mail_Part $part, $header_name, &$params, $is_array = true)
    {
        try {
            $header_content = $part->getHeader($header_name, 'string');
            $param_name = str_replace('-', '', $header_name);
            foreach (array_filter(array_map('trim', explode(',', $header_content))) as $header_content_line) {
                if (preg_match('/(.*)<(.*)>/', $header_content_line, $m)) {
                    $email = trim($m[2]);
                    $name = trim($m[1]);
                    $name = trim($name, '"');
                } else {
                    $email = trim($header_content_line);
                    $name = '';
                }

                if ($is_array) {
                    $params[$param_name][] = $email;
                    $params[$param_name.'name'][] = $name;
                } else {
                    $params[$param_name] = $email;
                    $params[$param_name.'name'] = $name;
                }
            }
        } catch (Exception $e) {
        }
    }

    protected function _sendMail()
    {
        $request = new Am_HttpRequest(self::API_ENDPOINT, Am_HttpRequest::METHOD_POST);

        $params = [];
        $params['api_user'] = $this->_api_user;
        $params['api_key'] = $this->_api_key;

        $part = new Zend_Mail_Part(
            [
                'raw' => $this->header.Zend_Mime::LINEEND.$this->body,
            ]
        );

        $this->_extractHeaderToParams($part, 'to', $params);
        $this->_extractHeaderToParams($part, 'cc', $params);
        $this->_extractHeaderToParams($part, 'bcc', $params);
        $this->_extractHeaderToParams($part, 'from', $params, false);
        $this->_extractHeaderToParams($part, 'reply-to', $params, false);

        $subject = $part->getHeader('subject');
        if (strpos($subject, '=?') === 0) {
            $subject = mb_decode_mimeheader($subject);
        }

        $params['subject'] = $subject;

        $canHasAttacments = false;

        //message
        list($type) = explode(";", $part->getHeader('content-type'));
        if ($type == 'multipart/alternative') {
            $msgPart = $part->getPart(2);
        } else {
            $msgPart = $part->isMultipart() ? $part->getPart(1) : $part;
            if ($msgPart->isMultipart()) {
                $msgPart = $msgPart->getPart(2); //html part
            }
            $canHasAttacments = true;
        }

        list($type) = explode(";", $msgPart->getHeader('content-type'));
        $encoding = $msgPart->getHeader('content-transfer-encoding');

        $content = $msgPart->getContent();
        if ($encoding && $encoding == 'quoted-printable') {
            $content = quoted_printable_decode($content);
        } else {
            $content = base64_decode($content);
        }

        switch ($type) {
            case 'text/plain':
                $params['text'] = $content;
                break;
            case 'text/html':
                $params['html'] = $content;
                break;
            default:
                throw new Zend_Mail_Transport_Exception("SendGrid API: unknown content-type: ".$type);
        }

        //attachments
        $handlers = [];
        if ($canHasAttacments) {
            if ($part->isMultipart()) {
                $params['files'] = [];
                for ($i = 2; $i <= $part->countParts(); $i++) {
                    $attPart = $part->getPart($i);

                    $encoding = $attPart->getHeader('content-transfer-encoding');
                    $disposition = $attPart->getHeader('content-disposition');
                    preg_match('/filename="(.*)"/', $disposition, $m);
                    $filename = $m[1];

                    $content = $attPart->getContent();
                    if ($encoding && $encoding == 'quoted-printable') {
                        $content = quoted_printable_decode($content);
                    } else {
                        $content = base64_decode($content);
                    }

                    $f = tmpfile();
                    array_push($handlers, $f);
                    fwrite($f, $content);

                    $request->addUpload("files[$filename]", $f, $filename);
                }
            }
        }

        $request->addPostParameter($params);
        $response = $request->send();

        foreach ($handlers as $f) {
            fclose($f);
        }

        if ($response->getStatus() != 200) {
            throw new Zend_Mail_Transport_Exception("SendGrid API: unexpected response: ".$response->getBody());
        }
    }
}