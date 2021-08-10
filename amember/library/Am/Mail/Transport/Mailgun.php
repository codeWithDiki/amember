<?php

class Am_Mail_Transport_Mailgun extends Am_Mail_Transport_Base
{
    const API_ENDPOINT = 'https://api.mailgun.net/v3';
    const API_ENDPOINT_EU = 'https://api.eu.mailgun.net/v3';

    protected $token;

    public function __construct($config)
    {
        $this->base_url = $config['account'] == 'eu' ? self::API_ENDPOINT_EU : self::API_ENDPOINT;
        $this->token = $config['token'];
        $this->domain = $config['domain'];
    }

    protected function _sendMail()
    {
        $request = new Am_HttpRequest("{$this->base_url}/{$this->domain}/messages.mime", Am_HttpRequest::METHOD_POST);
        $request->setHeader("Content-Type: multipart/form-data");
        $request->setAuth('api', $this->token);

        $request->addPostParameter('to', $this->recipients);

        $f = tmpfile();
        fwrite($f, $this->header.Zend_Mime::LINEEND.$this->body);

        $request->addUpload('message', $f);
        $response = $request->send();

        fclose($f);

        if ($response->getStatus() != 200) {
            throw new Zend_Mail_Transport_Exception(
                "Mailgun API: unexpected response: {$response->getStatus()} {$response->getBody()}"
            );
        }
    }
}