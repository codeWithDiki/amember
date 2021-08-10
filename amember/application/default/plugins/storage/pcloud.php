<?php

/**
 * @title pCloud
 * @desc pCloud is the secure cloud storage, where you can store, share and work on all your files
 * @logo_url pcloud.png
 */
class Am_Storage_Pcloud extends Am_Storage
{
    protected $cacheLifetime = 300; // 5 minutes
    protected $_isDebug = false;

    const API_URL = 'https://api.pcloud.com';

    public function isConfigured()
    {
        return $this->getConfig('username') && $this->getConfig('password');
    }

    function _initSetupForm(Am_Form_Setup $form)
    {
        $form->setTitle('pCloud');

        $form->addText('username')
            ->setLabel('Username (Email)')
            ->addRule('required');
        $form->addSecretText('password')
            ->setLabel('Password')
            ->addRule('required');
    }

    public function getDescription()
    {
        return $this->isConfigured() ?
            ___("Files located on pCloud storage. (Warning: Your buckets should not contain letters in uppercase in its name)") :
            ___("pCloud storage is not configured");
    }

    function directAction($request, $response, $invokeArgs)
    {
        //link is working only for requester IP so we can not do server side call
        $url = json_encode(self::API_URL . '/getpublinkdownload?' . http_build_query([
                'forcedownload' => 1,
                'code' => $request->getParam('code'),
            ]));

        echo <<<CUT
<html>
<body>
<div style="text-align: center; padding: 5em 0; font-size: 2rem">Downloading. Please Wait...</div>
<script type="text/javascript">
    fetch({$url}, {referrerPolicy: 'no-referrer'})
        .then(r => r.json())
        .then(function(r){
            window.location = 'https://' + r.hosts[0] + r.path;
        })
        .catch(err => console.log(err));
</script>
</body>
</html>
CUT;

    }

    public function getItems($path, array & $actions)
    {
        $items = [];

        $ret = $this->getDi()->cacheFunction->call([$this, 'listfolder'], [$path ?: '/'], [], $this->cacheLifetime);


        if ($path) {
            $path = array_filter(explode('/', $path));
            $parent = implode('/', array_slice($path, 0, count($path)-1));
            $items[] = new Am_Storage_Folder($this, '..', $parent);
        }

        foreach ($ret['contents'] as $r)
        {
            if ($r['isfolder']) {
                $items[] = $item = new Am_Storage_Folder($this, $r['name'], $r['path']);
            } else {
                $items[] = $item = new Am_Storage_File($this, $r['name'], $r['size'], $r['path'], $r['contenttype'], null);
            }
        }

        $actions[] = new Am_Storage_Action_Refresh($this, $path);

        return $items;
    }

    public function isLocal()
    {
        return false;
    }

    public function get($path)
    {
        $ret = $this->getDi()->cacheFunction->call([$this, 'checksumfile'], [$path], [], $this->cacheLifetime);
        return new Am_Storage_File($this, $ret['name'], $ret['size'], $path, $ret['contenttype'], null);
    }

    public function getUrl(Am_Storage_File $file, $expTime, $force_download = true)
    {
        //link is working only for requester IP so we can not do server side call
        if (!$code = $this->getfilepublink($file->getPath(), $expTime)) {
            throw new Am_Exception_InternalError;
        }
        return $this->getDi()->surl("storage/{$this->getId()}", ['code' => $code], false);

//        we can not use it because link is binded to server IP and user can not use it in his browser
//        return $this->getfilelink($file->getPath(), $expTime, $force_download);
//        if (!$code = $this->getfilepublink($file->getPath(), $expTime)) {
//            throw new Am_Exception_InternalError;
//        }
//        return $this->getpublinkdownload($code, $force_download);
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

    function listfolder($path)
    {
        $req = new Am_HttpRequest(self::API_URL . "/listfolder?" . http_build_query([
                'username' => $this->getConfig('username'),
                'password' => $this->getConfig('password'),
                'path' => $path,
            ]));
        $resp = $req->send();
        $this->log([$req, $resp], 'listfolder');
        if (
            $resp->getStatus() == '200'
            && ($body = json_decode($resp->getBody(), true))
            && !$body['result']
        ) {
            return $body['metadata'];
        }

        return [];
    }

    function getfilepublink($path, $expire)
    {
        $req = new Am_HttpRequest(self::API_URL . "/getfilepublink?" . http_build_query([
                'username' => $this->getConfig('username'),
                'password' => $this->getConfig('password'),
                'path' => $path,
                'expire' => time() + $expire, //it is premium feature
                'maxdownloads' => 1,
            ]));
        $resp = $req->send();
        $this->log([$req, $resp], 'getfilepublink');
        if (
            $resp->getStatus() == '200'
            && ($body = json_decode($resp->getBody(), true))
            && !$body['result']
        ) {
            return $body['code'];
        }
    }

    function getpublinkdownload($code, $force_download = true)
    {
        $req = new Am_HttpRequest(self::API_URL . "/getpublinkdownload?" . http_build_query([
                'code' => $code,
                'forcedownload' => $force_download,
            ]));
        $resp = $req->send();
        $this->log([$req, $resp], 'getpublinkdownload');
        if (
            $resp->getStatus() == '200'
            && ($body = json_decode($resp->getBody(), true))
            && !$body['result']
        ) {
            return "https://{$body['hosts'][0]}{$body['path']}";
        }
    }

    function getfilelink($path, $expire, $force_download = true)
    {
        $req = new Am_HttpRequest(self::API_URL . "/getfilelink?" . http_build_query([
                'username' => $this->getConfig('username'),
                'password' => $this->getConfig('password'),
                'path' => $path,
                'forcedownload' => $force_download,
                //'expire' => time() + $expire, //it is premium feature
            ]));
        $resp = $req->send();
        $this->log([$req, $resp], 'getfilelink');
        if (
            $resp->getStatus() == '200'
            && ($body = json_decode($resp->getBody(), true))
            && !$body['result']
        ) {
            return "https://{$body['hosts'][0]}{$body['path']}";
        }
    }

    function checksumfile($path)
    {
        $req = new Am_HttpRequest(self::API_URL . "/checksumfile?" . http_build_query([
                'username' => $this->getConfig('username'),
                'password' => $this->getConfig('password'),
                'path' => $path,
            ]));
        $resp = $req->send();
        $this->log([$req, $resp], 'checksumfile');
        if (
            $resp->getStatus() == '200'
            && ($body = json_decode($resp->getBody(), true))
            && !$body['result']
        ) {
            return $body['metadata'];
        }

        return [];
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