<?php

/**
 * @title Backblaze
 * @desc Cloud storage that's astonishingly easy and low-cost
 * @logo_url backblaze.png
 */
class Am_Storage_B2 extends Am_Storage
{
    protected $cacheLifetime = 300; // 5 minutes
    protected $_isDebug = false;

    const AUTH_URL = 'https://api.backblazeb2.com/b2api/v2/b2_authorize_account';

    public function isConfigured()
    {
        return $this->getConfig('app_id') && $this->getConfig('app_key');
    }

    public function getDescription()
    {
        return $this->isConfigured() ?
            ___("Files located on Backblaze storage") :
            ___("Backblaze storage is not configured");
    }

    function _initSetupForm(Am_Form_Setup $form)
    {
        $form->setTitle('Backblaze');

        $form->addText('app_id')
            ->setLabel('Key ID')
            ->addRule('required');

        $form->addSecretText('app_key')
            ->setLabel('Application Key')
            ->addRule('required')
            ->addRule('callback2', null, [$this, '_check']);

        $msg = ___('Your content on Backblaze should be private.
            Please use only Private Buckets on Backblaze to store content.
            aMember use Key ID and Application Key to generate links with
            authentication token for users to provide access them to your
            content on Backblaze.');

        $form->addProlog(<<<CUT
<div class="info"><strong>$msg</strong></div>
CUT
            );
    }

    function _check($val, $owner)
    {
        $v = $owner->getContainer()->getValue();
        return $this->auth($v["storage.{$this->getId()}.app_id"], $v["storage.{$this->getId()}.app_key"]) ?
            null : 'Invalid Credentials';
    }

    function auth($app_id, $app_key)
    {
        $req = new Am_HttpRequest(self::AUTH_URL);
        $req->setAuth($app_id, $app_key);

        $resp = $req->send();
        $this->log([$req, $resp], 'b2_authorize_account');

        if ($resp->getStatus() != 200) {
            return false;
        } else {
            $r = json_decode($resp->getBody(), true);
            $this->getDi()->store->set("{$this->getId()}.accountId", $r['accountId'], '+23 hours');
            $this->getDi()->store->set("{$this->getId()}.authorizationToken", $r['authorizationToken'], '+23 hours');
            $this->getDi()->store->set("{$this->getId()}.apiUrl", $r['apiUrl'], '+23 hours');
            $this->getDi()->store->set("{$this->getId()}.downloadUrl", $r['downloadUrl'], '+23 hours');
            return true;
        }
    }

    function getAuthData()
    {
        if (!$this->getDi()->store->get("{$this->getId()}.accountId")) {
            $this->auth($this->getConfig('app_id'), $this->getConfig('app_key'));
        }

        return [
            $this->getDi()->store->get("{$this->getId()}.accountId"),
            $this->getDi()->store->get("{$this->getId()}.authorizationToken"),
            $this->getDi()->store->get("{$this->getId()}.apiUrl"),
            $this->getDi()->store->get("{$this->getId()}.downloadUrl"),
        ];
    }

    public function getItems($path, array & $actions)
    {
        $items = [];
        if ($path == '')
        {
            $buckets = $this->getDi()->cacheFunction->call(
                [$this, 'listBuckets'],
                [],
                [],
                $this->cacheLifetime
            );
            foreach ($buckets as $id => $name)
                $items[] = new Am_Storage_Folder($this, $name, $id);

            $actions[] = new Am_Storage_Action_Refresh($this, '');

        } else {
            @list($bucket, $bpath) = explode('/', $path, 2);
            $ret = $this->getDi()->cacheFunction->call(
                [$this, 'getFiles'],
                [$bucket, $bpath],
                [],
                $this->cacheLifetime
            );

            $_bpath = array_filter(explode('/', $bpath));
            $ppath = implode('/', array_slice($_bpath, 0, count($_bpath)-1));
            $parent = $bpath ? rtrim("$bucket/$ppath", "/") : '';
            $items[] = new Am_Storage_Folder($this, '..', $parent);

            foreach ($ret as $file)
            {
                $name = substr($file['fileName'], strlen($bpath));
                $name = rtrim($name, '/');
                if ($file['action'] == 'folder') {
                    $items[] = new Am_Storage_Folder($this, $name, $bucket . '/' . $file['fileName']);
                } else {
                    $items[] = new Am_Storage_File($this, $name, $file['contentLength'], $file['fileId'], $file['contentType'], null);
                }
            }

            $actions[] = new Am_Storage_Action_Refresh($this, $path);
        }
        return $items;
    }

    function getFiles($bucket, $bpath)
    {
        list($accountId, $authorizationToken, $apiUrl, $downloadUrl) = $this->getAuthData();

        $req = new Am_HttpRequest("{$apiUrl}/b2api/v2/b2_list_file_names", Am_HttpRequest::METHOD_POST);
        $req->setHeader('Authorization', $authorizationToken);
        $req->setBody(json_encode([
            'bucketId' => $bucket,
            'prefix' => $bpath,
            'delimiter' => '/',
        ]));

        $resp = $req->send();
        $this->log([$req, $resp], 'b2_list_file_names');

        if ($resp->getStatus() == 200) {
            $r = json_decode($resp->getBody(), true);
            return $r['files'];
        } else {
            return [];
        }
    }

    function listBuckets()
    {
        list($accountId, $authorizationToken, $apiUrl, $downloadUrl) = $this->getAuthData();

        $req = new Am_HttpRequest("{$apiUrl}/b2api/v2/b2_list_buckets", Am_HttpRequest::METHOD_POST);
        $req->setHeader('Authorization', $authorizationToken);
        $req->setBody(json_encode(['accountId' => $accountId]));

        $resp = $req->send();
        $this->log([$req, $resp], 'b2_list_buckets');

        if ($resp->getStatus() == 200) {
            $r = json_decode($resp->getBody(), true);
            $_ = [];
            foreach ($r['buckets'] as $b) {
                $_[$b['bucketId']] = $b['bucketName'];
            }
            return $_;
        } else {
            return [];
        }
    }

    function getFile($id)
    {
        list($accountId, $authorizationToken, $apiUrl, $downloadUrl) = $this->getAuthData();

        $req = new Am_HttpRequest("{$apiUrl}/b2api/v2/b2_get_file_info", Am_HttpRequest::METHOD_POST);
        $req->setHeader('Authorization', $authorizationToken);
        $req->setBody(json_encode([
            'fileId' => $id,
        ]));

        $resp = $req->send();
        $this->log([$req, $resp], 'b2_get_file_info');

        if ($resp->getStatus() == 200) {
            return json_decode($resp->getBody(), true);
        }
    }

    function getBucket($id)
    {
        list($accountId, $authorizationToken, $apiUrl, $downloadUrl) = $this->getAuthData();

        $req = new Am_HttpRequest("{$apiUrl}/b2api/v2/b2_list_buckets", Am_HttpRequest::METHOD_POST);
        $req->setHeader('Authorization', $authorizationToken);
        $req->setBody(json_encode(['accountId' => $accountId, 'bucketId' => $id]));

        $resp = $req->send();
        $this->log([$req, $resp], 'b2_list_buckets');

        if ($resp->getStatus() == 200) {
            $r = json_decode($resp->getBody(), true);
            foreach ($r['buckets'] as $b) {
                return $b;
            }
        }
    }

    function authenticate($path, $expTime, $force_download)
    {
        $file = $this->getFile($path);
        $bucket = $this->getBucket($file['bucketId']);

        $p = preg_split('|[\\\/]|', $file['fileName']); // get name
        $name = array_pop($p);
        $disposition = 'attachment; filename="' . $name . '"';

        list($accountId, $authorizationToken, $apiUrl, $downloadUrl) = $this->getAuthData();

        $req = new Am_HttpRequest("{$apiUrl}/b2api/v2/b2_get_download_authorization", Am_HttpRequest::METHOD_POST);
        $req->setHeader('Authorization', $authorizationToken);

        $body = [
            'bucketId' => $file['bucketId'],
            'fileNamePrefix' => $file['fileName'],
            'validDurationInSeconds' => $expTime,
        ];

        if ($force_download) {
            $body['b2ContentDisposition'] = $disposition;
        }

        $req->setBody(json_encode($body));

        $resp = $req->send();
        $this->log([$req, $resp], 'b2_get_download_authorization');

        if ($resp->getStatus() == 200) {
            $_ = json_decode($resp->getBody(), true);
            $query = ['Authorization' => $_['authorizationToken']];
            if ($force_download) {
                $query['b2ContentDisposition'] = $disposition;
            }
            return "{$downloadUrl}/file/{$bucket['bucketName']}/{$file['fileName']}?" . http_build_query($query);
        } else {
            throw new Am_Exception_FatalError();
        }
    }

    public function isLocal()
    {
        return false;
    }

    public function get($path)
    {
        $file = $this->getDi()->cacheFunction->call(
                [$this, 'getFile'],
                [$path],
                [],
                $this->cacheLifetime
        );

        if (!$file) {
            throw new Am_Exception_InputError();
        }

        $p = preg_split('|[\\\/]|', $file['fileName']); // get name
        $name = array_pop($p);
        return new Am_Storage_File($this, $name, $file['contentLength'], $path, $file['contentType'], null);
    }

    public function getUrl(Am_Storage_File $file, $expTime, $force_download = true)
    {
        return $this->authenticate($file->getPath(), $expTime, $force_download);
    }

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

    function log($events, $title)
    {
        if (!$this->_isDebug) {
            return;
        }
        $l = $this->getDi()->invoiceLogRecord;
        $l->paysys_id = $this->getId();
        $l->title = $title;
        foreach ($events as $event) {
            $l->add($event);
        }
    }
}