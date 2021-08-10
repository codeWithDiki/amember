<?php

/**
 * Amazon Simple Email Service mail transport
 *
 * Integration between Zend Framework and Amazon Simple Email Service
 *
 * @package Am_Mail
 * @license http://framework.zend.com/license/new-bsd New BSD License
 * @author Alex Scott <alex@cgi-central.net>
 *
 * Class is based on Chistopher Valles work Christopher Valles <info@christophervalles.com>
 * https://github.com/christophervalles/Amazon-SES-Zend-Mail-Transport
 * main change is usage of Am_HttpClient instead of Zend_Http_Client
 */
class Am_Mail_Transport_Ses extends Am_Mail_Transport_Base
{
    
    const REGION_US_EAST_1 = 'us-east-1';
    const REGION_US_EAST_2 = 'us-east-2';
    const REGION_US_WEST_2 = 'us-west-2';
    const REGION_AP_SOUTH_1 = 'ap-south-1';
    const REGION_AP_SOUTHEAST_1 = 'ap-southeast-2';
    const REGION_CA_CENTRAL_1 = 'ca-central-1';
    const REGION_EU_CENTRAL_1 = 'eu-central-1';
    const REGION_EU_WEST_1 = 'eu-west-1';
    const REGION_EU_WEST_2 = 'eu-west-2';
    const REGION_SA_EAST_1 = 'sa-east-1';
    const REGION_US_GOV_WEST_1 = 'us-gov-west-1';
    
    /**
     * Template of the webservice body request
     *
     * @var string
     */
    protected $_bodyRequestTemplate = 'Action=SendRawEmail&Source=%s&%s&RawMessage.Data=%s';
    
    /**
     * Remote smtp hostname or i.p.
     *
     * @var string
     */
    protected $_host;
    
    /**
     * Amazon Access Key
     *
     * @var string|null
     */
    protected $_accessKey;
    
    /**
     * Amazon private key
     *
     * @var string|null
     */
    protected $_privateKey;
    
    protected $region;
    
    /**
     * Constructor.
     *
     * @param string $endpoint (Default: https://email.us-east-1.amazonaws.com)
     * @param array|null $config (Default: null)
     * @return void
     * @throws Zend_Mail_Transport_Exception if accessKey is not present in the config
     * @throws Zend_Mail_Transport_Exception if privateKey is not present in the config
     */
    public function __construct(array $config = [], $host = 'https://email.us-east-1.amazonaws.com')
    {
        if (!array_key_exists('accessKey', $config)) {
            throw new Zend_Mail_Transport_Exception('This transport requires the Amazon access key');
        }
        
        if (!array_key_exists('privateKey', $config)) {
            throw new Zend_Mail_Transport_Exception('This transport requires the Amazon private key');
        }
        
        $this->_accessKey = $config['accessKey'];
        $this->_privateKey = $config['privateKey'];
        $this->region = $config['region'];
        
        $this->_host = $this->getEndpoint($config['region'], $host);
    }
    
    function getEndpoint($region, $default)
    {
        if (!empty($region)) {
            return "https://email.{$region}.amazonaws.com";
        } else {
            return $default;
        }
    }
    
    /**
     * Send an email using the amazon webservice api
     *
     * @return void
     */
    public function _sendMail()
    {
        $date = gmdate('D, d M Y H:i:s O');
        
        //Send the request
        $client = new Am_HttpRequest($this->_host, Am_HttpRequest::METHOD_POST);
        $client->setHeader(
            [
                'Date' => $date,
                'Host' => "email.{$this->region}.amazonaws.com"
            ]
        );
        
        
        //Build the parameters
        $params = [
            'Action' => 'SendRawEmail',
            'Source' => $this->_mail->getFrom(),
            'RawMessage.Data' => base64_encode(sprintf("%s\n%s\n", $this->header, $this->body)),
        ];
        
        $client->addPostParameter($params);
        
        $this->signRequest($client);
        $response = $client->send();
        
        if ($response->getStatus() != 200) {
            throw new Zend_Mail_Transport_Exception("Amazon SES: unexpected response: " . $response->getBody());
        }
    }
    
    function signRequest(Am_HttpRequest $request)
    {
        $headers = $request->getHeaders();
        unset($headers['date']);
        
        $secretKey = $this->_privateKey;
        $timestamp = time();
        $longDate = gmdate('Ymd\THis\Z', $timestamp);
        $headers['x-amz-date'] = $longDate;
        $shortDate = substr($longDate, 0, 8);
        
        $region = $this->region;
        $service = 'email';
        
        
        $credentialScope = $this->createScope($shortDate, $region, $service);
        $payload_sha256 = hash('sha256', $request->getBody());
        $signingContext = $this->createSigningContext($request, $headers, $payload_sha256);
        
        $signingContext['string_to_sign'] = $this->createStringToSign(
            $longDate,
            $credentialScope,
            $signingContext['canonical_request']
        );
        
        $signingKey = $this->getSigningKey($shortDate, $region, $service, $secretKey);
        $signature = hash_hmac('sha256', $signingContext['string_to_sign'], $signingKey);
        
        $headers['Authorization'] = "AWS4-HMAC-SHA256 "
            . "Credential={$this->_accessKey}/{$credentialScope}, "
            . "SignedHeaders={$signingContext['signed_headers']}, Signature={$signature}";
        
        $headers['x-amz-content-sha256'] = $payload_sha256;
        foreach ($headers as $k => $v) {
            $request->setHeader($k, $v, true);
        }
    }
    
    private function getSigningKey($shortDate, $region, $service, $secretKey)
    {
        // Retrieve the hash form the cache or create it and add it to the cache
        $dateKey = hash_hmac('sha256', $shortDate, 'AWS4' . $secretKey, true);
        $regionKey = hash_hmac('sha256', $region, $dateKey, true);
        $serviceKey = hash_hmac('sha256', $service, $regionKey, true);
        $signingKey = hash_hmac('sha256', 'aws4_request', $serviceKey, true);
        
        return $signingKey;
    }
    
    private function createStringToSign($longDate, $credentialScope, $creq)
    {
        return "AWS4-HMAC-SHA256\n{$longDate}\n{$credentialScope}\n"
            . hash('sha256', $creq);
    }
    
    private function createScope($shortDate, $region, $service)
    {
        return $shortDate
            . '/' . $region
            . '/' . $service
            . '/aws4_request';
    }
    
    private function createSigningContext(Am_HttpRequest $request, array $headers, $payload)
    {
        $signable = [
            'host' => true,
            'date' => true,
            'content-md5' => true
        ];
        
        // Normalize the path as required by SigV4 and ensure it's absolute
        $canon = 'POST' . "\n"
            . $this->createCanonicalizedPath($request->getUrl()->getNormalizedURL()) . "\n"
            . $this->getCanonicalizedQueryString($request->getUrl()->getNormalizedURL()) . "\n";
        
        $canonHeaders = [];
        
        foreach ($headers as $key => $values) {
            $key = strtolower($key);
            if (isset($signable[$key]) || substr($key, 0, 6) === 'x-amz-') {
                $canonHeaders[$key] = $key . ':' . preg_replace('/\s+/', ' ', $values);
            }
        }
        
        ksort($canonHeaders);
        $signedHeadersString = implode(';', array_keys($canonHeaders));
        $canon .= implode("\n", $canonHeaders) . "\n\n"
            . $signedHeadersString . "\n"
            . $payload;
        
        return [
            'canonical_request' => $canon,
            'signed_headers' => $signedHeadersString
        ];
    }
    
    protected function createCanonicalizedPath($uri)
    {
        $url = rawurldecode(parse_url($uri, PHP_URL_PATH));
        $doubleEncoded = rawurlencode(ltrim($url, '/'));
        
        return '/' . str_replace('%2F', '/', $doubleEncoded);
    }
    
    private function getCanonicalizedQueryString($uri)
    {
        $query = parse_url($uri, PHP_URL_QUERY);
        parse_str($query, $queryParams);
        unset($queryParams['X-Amz-Signature']);
        if (empty($queryParams)) {
            return '';
        }
        
        $qs = '';
        ksort($queryParams);
        foreach ($queryParams as $key => $values) {
            if (is_array($values)) {
                sort($values);
            } elseif ($values === 0) {
                $values = ['0'];
            } elseif (!$values) {
                $values = [''];
            }
            
            foreach ((array)$values as $value) {
                $qs .= rawurlencode($key) . '=' . rawurlencode($value) . '&';
            }
        }
        return substr($qs, 0, -1);
    }
    
    
}