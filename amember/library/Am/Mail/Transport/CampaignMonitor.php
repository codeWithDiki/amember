<?php
class Am_Mail_Transport_CampaignMonitor extends Am_Mail_Transport_Base
{
    const API_ENDPOINT = 'https://api.createsend.com/api/v3.1/transactional/classicemail/send?clientID=';

    protected $_api_key = null,
        $_client_id = null;

    public function __construct($config)
    {
        $this->_api_key = $config['api_key'];
        $this->_client_id = $config['client_id'];
    }

    protected function _extractHeaderToParams(Zend_Mail_Part $part, $header_name, &$params, $is_array = true)
    {
        try {
            $header_content = $part->getHeader($header_name, 'string');
            $param_name = str_replace('-', '', $header_name);
            foreach (array_filter(array_map('trim', explode(',', $header_content))) as $header_content_line) {
                if ($is_array) {
                    $params[$param_name][] = $header_content_line;
                } else {
                    $params[$param_name] = $header_content_line;
                }
            }
        } catch (Exception $e) {
        }
    }

    protected function _sendMail()
    {
        $request = new Am_HttpRequest(self::API_ENDPOINT.$this->_client_id, Am_HttpRequest::METHOD_POST);
        $request->setAuth($this->_api_key, 'none');
        $request->setHeader('Content-type: application/json; charset=utf-8');

        $params = [
            'TrackOpens' => true,
            'TrackClicks' => true,
            'InlineCSS' => true,
        ];

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
        $params['Group'] = $subject;

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
                $params['Attachments'] = [];
                for ($i = 2; $i <= $part->countParts(); $i++) {
                    $attPart = $part->getPart($i);

                    $encoding = $attPart->getHeader('content-transfer-encoding');
                    $disposition = $attPart->getHeader('content-disposition');
                    preg_match('/filename="(.*)"/', $disposition, $m);
                    $filename = $m[1];

                    $content = $attPart->getContent();
                    if ($encoding && $encoding == 'quoted-printable') {
                        $content = quoted_printable_decode($content);
                        $content = base64_encode($content);
                    }

                    $params['Attachments'][] = [
                        'Name' => $filename,
                        'Type' => $attPart->{'content-type'},
                        'Content' => $content,
                    ];

                }
            }
        }

        $request->setBody(json_encode($params));
        $response = $request->send();

        if (!($body = json_decode(
                $response->getBody(),
                true
            )) || !isset($body[0]['Status']) || ($body[0]['Status'] != 'Accepted')) {
            throw new Zend_Mail_Transport_Exception("CampaignMonitor API: unexpected response: ".$response->getBody());
        }
    }
}