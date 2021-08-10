<?php
class Am_Mail_Transport_Postmark extends Am_Mail_Transport_Base
{
    const API_ENDPOINT = 'https://api.postmarkapp.com/email';

    protected $token;

    public function __construct($config)
    {
        $this->token = $config['token'];
    }

    protected function _sendMail()
    {
        $request = new Am_HttpRequest(self::API_ENDPOINT, Am_HttpRequest::METHOD_POST);
        $request->setHeader("Content-Type: application/json");
        $request->setHeader("Accept: application/json");
        $request->setHeader("X-Postmark-Server-Token: {$this->token}");

        $params = [];

        $part = new Zend_Mail_Part(
            [
                'raw' => $this->header.Zend_Mime::LINEEND.$this->body,
            ]
        );

        foreach (['To', 'Cc', 'Bcc', 'From'] as $token) {
            try {
                if ($v = $part->getHeader(strtolower($token), 'string')) {
                    $params[$token] = $v;
                }
            } catch (Exception $e) {
            }
        }

        try {
            if ($v = $part->getHeader('reply-to', 'string')) {
                $params['ReplyTo'] = $v;
            }
        } catch (Exception $e) {
        }

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
                $params['TextBody'] = $content;
                break;
            case 'text/html':
                $params['HtmlBody'] = $content;
                break;
            default:
                throw new Zend_Mail_Transport_Exception("Postmark API: unknown content-type: ".$type);
        }

        //attachments
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
                        'ContentType' => Upload::getMimeType($filename),
                        'Name' => $filename,
                        'Content' => base64_encode($content),
                    ];
                }
            }
        }

        $request->setBody(json_encode($params));
        $response = $request->send();

        if ($response->getStatus() != 200) {
            throw new Zend_Mail_Transport_Exception(
                "Postmark API: unexpected response: {$response->getStatus()} {$response->getBody()}"
            );
        }
    }
}