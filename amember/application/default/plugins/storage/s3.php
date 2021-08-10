<?php

/**
 * @title Amazon S3
 * @desc Object storage built to store and retrieve any amount of data from anywhere
 * @logo_url amazon-aws.png
 */
class Am_Storage_S3 extends Am_Storage
{
    protected $_connector;
    /** last used bucket */
    protected $_bucket;
    protected $cacheLifetime = 300; // 5 minutes
    protected $_endpoints = [
        'us-east-1' => 's3.amazonaws.com',
        'us-east-2' => 's3-us-east-2.amazonaws.com',
        'us-west-1' => 's3-us-west-1.amazonaws.com',
        'us-west-2' => 's3-us-west-2.amazonaws.com',
        'ap-south-1' => 's3-ap-south-1.amazonaws.com',
        'ap-northeast-3' => 's3-ap-northeast-3.amazonaws.com',
        'ap-northeast-2' => 's3-ap-northeast-2.amazonaws.com',
        'ap-southeast-1' => 's3-ap-southeast-1.amazonaws.com',
        'ap-southeast-2' => 's3-ap-southeast-2.amazonaws.com',
        'ap-northeast-1' => 's3-ap-northeast-1.amazonaws.com',
        'ca-central-1' => 's3-ca-central-1.amazonaws.com',
        'cn-north-1' => 's3.cn-north-1.amazonaws.com.cn',
        'cn-northwest-1' => 's3.cn-northwest-1.amazonaws.com.cn',
        'eu-central-1' => 's3.eu-central-1.amazonaws.com',
        'eu-west-1' => 's3-eu-west-1.amazonaws.com',
        'eu-west-2' => 's3-eu-west-2.amazonaws.com',
        'eu-west-3' => 's3-eu-west-3.amazonaws.com',
        'sa-east-1' => 's3-sa-east-1.amazonaws.com'
    ];
    protected $_regions = [
        'us-east-1' => 'US East (N. Virginia)',
        'us-east-2' => 'US East (Ohio)',
        'us-west-1' => 'US West (N. California)',
        'us-west-2' => 'US West (Oregon)',
        'ap-south-1' => 'Asia Pacific (Mumbai)',
        'ap-northeast-3' => 'Asia Pacific (Osaka-Local)',
        'ap-northeast-2' => 'Asia Pacific (Seoul)',
        'ap-southeast-1' => 'Asia Pacific (Singapore)',
        'ap-southeast-2' => 'Asia Pacific (Sydney)',
        'ap-northeast-1' => 'Asia Pacific (Tokyo)',
        'ca-central-1' => 'Canada (Central)',
        'cn-north-1' => 'China (Beijing)',
        'cn-northwest-1' => 'China (Ningxia)',
        'eu-central-1'  =>  'EU (Frankfurt)',
        'eu-west-1' => 'EU (Ireland)',
        'eu-west-2' => 'EU (London)',
        'eu-west-3' => 'EU (Paris)',
        'sa-east-1' => 'South America (Sao Paulo)'
    ];

    public function isConfigured()
    {
        return $this->getConfig('secret_key') && $this->getConfig('access_key');
    }

    function _initSetupForm(Am_Form_Setup $form)
    {
        $form->setTitle('Amazon S3');

        $form->addText('access_key', ['size' => 40])->setLabel('AWS Access Key')
            ->addRule('required')
            ->addRule('regex', 'must be alphanumeric', '/^[A-Z0-9]+$/');
        $form->addSecretText('secret_key', ['size' => 40])->setLabel('AWS Secret Key')
            ->addRule('required');

        $form->addSelect('region')->loadOptions($this->_regions)->setLabel('Amazon S3 Region');
        $form->addText('expire', ['size' => 5])->setLabel('Video link lifetime, min');
        $form->setDefault('expire', 15);
        $form->addAdvCheckbox('use_ssl')
          ->setLabel(___("Use SSL for Authenticated URLs\n" .
          "enable this option if you use https for your site"));

        $msg = ___('Your content on Amazon S3 should not be public.
            Please restrict public access to your files on Amazon S3 side
            and ensure you can not access it directly from Amazon S3.
            aMember use Access Key and Secret Key to generate links with
            authentication token for users to provide access them to your
            content on Amazon S3.');

        $form->addProlog(<<<CUT
<div class="info"><strong>$msg</strong></div>
CUT
            );
    }

    public function getRegion()
    {
        return $this->getConfig('region', 'us-east-1');
    }

    public function getEndpoint()
    {
        return $this->_endpoints[$this->getRegion()];
    }

    public function getDescription()
    {
        return $this->isConfigured() ?
            ___("Files located on Amazon S3 storage. (Warning: Your buckets should not contain letters in uppercase in its name)") :
            ___("Amazon S3 storage is not configured");
    }

    /** @return S3 */
    protected function getConnector()
    {
        if (!$this->_connector)
        {
            $this->_connector = new S3($this->getConfig('access_key'), $this->getConfig('secret_key'), false, $this->getEndpoint(), $this->getRegion());
            switch($this->getRegion()){
                case 'eu-central-1':
                case 'us-east-2':
                case 'eu-west-2':
                case 'eu-west-3':
                case 'ap-south-1':
                case 'ap-northeast-3':
                case 'ap-northeast-2':
                case 'ca-central-1':
                case 'cn-north-1':
                case 'cn-northwest-1':
                    $this->_connector->setRequestClass('S3Request_HttpRequest4');
                    break;
                default :
                    $this->_connector->setRequestClass('S3Request_HttpRequest2');
            }
        }
        return $this->_connector;
    }

    /** @access private testing */
    public function _setConnector($connector)
    {
        $this->_connector = $connector;
    }

    public function getItems($path, array & $actions)
    {
        $items = [];
        if ($path == '')
        {
            $buckets = $this->getDi()->cacheFunction->call(
                [$this->getConnector(), 'listBuckets'],
                [], [], $this->cacheLifetime);
            foreach ($buckets as $name)
                $items[] = new Am_Storage_Folder($this, $name, $name);

            $actions[] = new Am_Storage_Action_Refresh($this, '');

        } else {
            @list($bucket, $bpath) = explode('/', $path, 2);
            if(@$bpath)
                $bpath .= '/';
            else
                $bpath = null;
            $ret = $this->getDi()->cacheFunction->call(
                [$this->getConnector(), 'getBucket'],
                [$bucket, $bpath, null, null, $delimiter = '/', true],
                    [], $this->cacheLifetime);

            $this->_bucket = $bucket;
            $tree = [];
            foreach ($ret as $r)
            {
                if (!empty($r['prefix']) && substr($r['prefix'], -1) == '/') {
                    $part = rtrim($r['prefix'], '/');
                    $part = explode('/', $part);
                    $part = $part[count($part) -1];
                    $tree[$part] = $part;
                } else {
                    $part = rtrim($r['name'], '/');
                    $part = explode('/', $part);
                    $part = $part[count($part) -1];
                    $r['is_blob'] = true;
                    $tree[$part] = $r;
                }
            }

            $ctree = & $tree;
            $bpath = array_filter(explode('/', $bpath));
            $ppath = implode('/', array_slice($bpath, 0, count($bpath)-1));
            $parent = $bpath ? rtrim("$bucket/$ppath", "/") : '';
            $items[] = new Am_Storage_Folder($this, '..', $parent);

            foreach ($ctree as $name => $r)
            {
                if (isset($r['is_blob'])) {
                    $items[] = $item = new Am_Storage_File($this, $name, $r['size'],
                        $bucket . '/' . $r['name'], null, null);
                    $item->_hash = $r['hash'];
                } else {
                    $items[] = $item = new Am_Storage_Folder($this, $name, $path . '/' . $name);
                }
            }

            $actions[] = new Am_Storage_Action_Refresh($this, $path);
//            $actions[] = $x = new Am_Storage_Action_Upload($this, $this->getId() . '::' .$bucket,
//                $this->renderUpload($bucket));
        }
        return $items;
    }

    public function isLocal()
    {
        return false;
    }

    public function get($path)
    {
        list($bucket, $uri) = explode('/', $path, 2);
        $info = $this->getDi()->cacheFunction->call(
                [$this->getConnector(), 'getObjectInfo'],
                [$bucket, $uri], [], $this->cacheLifetime);

        $p = preg_split('|[\\\/]|', $path); // get name
        $name = array_pop($p);
        return new Am_Storage_File($this, $name, $info['size'], $path, $info['type'], null);
    }

    public function getUrl(Am_Storage_File $file, $expTime, $force_download = true)
    {
        list($bucket, $uri) = explode('/', $file->getPath(), 2);
        return $this->getConnector()->getAuthenticatedURL($bucket, $uri, $expTime, false, $this->getConfig('use_ssl'), $force_download);
    }

/*
 * <PostResponse>
 *   <Location>https://amember-com.s3.amazonaws.com/filename.jpg</Location>
 *   <Bucket>xxx-com</Bucket>
 *   <Key>fn.jpg</Key>
 *   <ETag>"123ad031affb55f5b5a1da5f12a42cbf"</ETag>
 * </PostResponse>
 */
    public function action(array $query, $path, $url, Am_Mvc_Request $request, Am_Mvc_Response $response)
    {
        switch ($query['action'])
        {
            case 'refresh':
                $this->getDi()->cacheFunction->clean();
                $response->setRedirect($url);
                break;
            default:
                throw new Am_Exception_InputError('unknown action!');
        }
    }


//    protected function renderUpload($bucket)
//    {
//        $output = "";
//        $output .= "<p>Upload file to Amazon S3</p>";
//        $bucket = Am_Html::escape($bucket);
//        $output .= "<form enctype='multipart/form-data' action='https://$bucket.s3.amazonaws.com/' method='post'>";
//        $output .= Am_Html::renderArrayAsInputHiddens( $x =
//            $this->getConnector()->getHttpUploadPostParams($bucket, '', S3::ACL_PRIVATE,
//                3600, 1024*1024*30)
//        );
//        $output .= "<input type='file' name='file' />";
//        $output .= "<input type='submit' value='Upload' /></form>";
//        return $output;
//    }
}