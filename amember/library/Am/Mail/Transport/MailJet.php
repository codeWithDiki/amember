<?php
class Am_Mail_Transport_MailJet extends Am_Mail_Transport_Base
{
    const API_ENDPOINT = 'https://api.mailjet.com/v3/send';

    protected $apikey_public, $apikey_private;

    public function __construct($config)
    {
        $this->apikey_public = $config['apikey_public'];
        $this->apikey_private = $config['apikey_private'];
    }

    protected function _extractHeaderToParams(Zend_Mail_Part $part, $header_name, &$params)
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

                $params[ucfirst($param_name).'Email'] = $email;
                $params[ucfirst($param_name).'Name'] = $name;
            }
        } catch (Exception $e) {
        }
    }

    protected function _sendMail()
    {
        $request = new Am_HttpRequest(self::API_ENDPOINT, Am_HttpRequest::METHOD_POST);
        $request->setAuth($this->apikey_public, $this->apikey_private);
        $request->setHeader("Content-Type: application/json");

        $params = [];

        $part = new Zend_Mail_Part(
            [
                'raw' => $this->header.Zend_Mime::LINEEND.$this->body,
            ]
        );

        foreach (['To', 'Cc', 'Bcc'] as $token) {
            try {
                if ($v = $part->getHeader(strtolower($token), 'string')) {
                    $params[$token] = $v;
                }
            } catch (Exception $e) {
            }
        }

        try {
            if ($v = $part->getHeader('reply-to', 'string')) {
                $params['Headers']['Reply-To'] = $v;
            }
        } catch (Exception $e) {
        }

        $this->_extractHeaderToParams($part, 'from', $params);

        $subject = $part->getHeader('subject');
        if (strpos($subject, '=?') === 0) {
            $subject = mb_decode_mimeheader($subject);
        }

        $params['Subject'] = $subject;

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
                $params['Text-part'] = $content;
                break;
            case 'text/html':
                $params['Html-part'] = $content;
                break;
            default:
                throw new Zend_Mail_Transport_Exception("MailJet API: unknown content-type: ".$type);
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

                    $params['Attachments'][] = [
                        'Content-type' => Upload::getMimeType($filename),
                        'Filename' => $filename,
                        'content' => base64_encode($content),
                    ];
                }
            }
        }

        $request->setBody(json_encode($params));
        $response = $request->send();

        if ($response->getStatus() != 200) {
            throw new Zend_Mail_Transport_Exception(
                "MailJet API: unexpected response: {$response->getStatus()} {$response->getBody()}"
            );
        }
    }
}