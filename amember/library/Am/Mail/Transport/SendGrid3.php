<?php

class Am_Mail_Transport_SendGrid3 extends Am_Mail_Transport_Base
{
    const API_ENDPOINT = 'https://api.sendgrid.com/v3/mail/send';

    protected $_api_key = null;

    public function __construct($config)
    {
        $this->_api_key = $config['api_key'];
    }

    protected function _extractHeaderToParams(
        Zend_Mail_Part $part,
        $header_name,
        &$params,
        $is_array = true,
        &$skip = []
    ) {
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
                if (in_array($email, $skip)) {
                    return;
                }
                $skip[] = $email;

                if ($is_array) {
                    $params['personalizations'][0][$param_name][] = [
                        'email' => $email,
                        'name' => $name,
                    ];
                } else {
                    $params[$param_name] = [
                        'email' => $email,
                        'name' => $name,
                    ];
                }
            }
        } catch (Exception $e) {
        }
    }

    protected function getHeader($part, $header)
    {
        $_ = $part->headerExists($header) ? $part->getHeader($header, 'string') : null;
        if (strpos($_, '=?') === 0) {
            $_ = mb_decode_mimeheader($_);
        }
        return $_;
    }

    protected function _sendMail()
    {
        $request = new Am_HttpRequest(self::API_ENDPOINT, Am_HttpRequest::METHOD_POST);
        $request->setHeader("Authorization: Bearer {$this->_api_key}");
        $request->setHeader("Content-Type: application/json");

        $params = ['personalizations' => [0 => []]];

        $part = new Zend_Mail_Part(
            [
                'raw' => $this->header.Zend_Mime::LINEEND.$this->body,
            ]
        );

        $skip = [];
        $this->_extractHeaderToParams($part, 'to', $params, true, $skip);
        $this->_extractHeaderToParams($part, 'cc', $params, true, $skip);
        $this->_extractHeaderToParams($part, 'bcc', $params, true, $skip);
        $this->_extractHeaderToParams($part, 'from', $params, false);
        $this->_extractHeaderToParams($part, 'reply-to', $params, false);

        $params['subject'] = $this->getHeader($part, 'subject');
        $params['headers'] = [];

        if ($in_reply_to = $this->getHeader($part, 'in-reply-to')) {
            $params['headers']['In-Reply-To'] = $in_reply_to;
        }

        if ($references = $this->getHeader($part, 'references')) {
            $params['headers']['References'] = $references;
        }

        if ($message_id = $this->getHeader($part, 'message-id')) {
            $params['headers']['Message-ID'] = $message_id;
        }

        if(empty($params['headers'])) {
            unset($params['headers']);
        }

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

        $params['content'] = [
            [
                'type' => $type,
                'value' => $content,
            ],
        ];

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

                    $params['attachments'][] = [
                        'content' => base64_encode($content),
                        'type' => Upload::getMimeType($filename),
                        'filename' => $filename,
                        'disposition' => 'attachment',
                    ];
                }
            }
        }

        $request->setBody(json_encode($params));
        $response = $request->send();

        if ($response->getStatus() != 202) {
            throw new Zend_Mail_Transport_Exception(
                "SendGrid API: unexpected response: {$response->getStatus()} {$response->getBody()}"
            );
        }
    }
}