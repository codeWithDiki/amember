<?php

abstract class MediaController extends Am_Mvc_Controller
{
    protected $id;
    protected $media;
    protected $type;

    abstract function getHtmlCode($media, $width, $height);

    function getPlayerParams(ResourceAbstractFile $media)
    {
        $config = $this->getMediaConfig($media);

        return [
            'autoplay' => $config['autoPlay'],
            'disableContextMenu' => true,
            'iconUrl' => $this->getDi()->surl('application/default/views/public/js/plyr/plyr.svg', false),
        ];
    }

    function getMediaConfig(ResourceAbstractFile $media)
    {
        $localConfig = [];

        if (!$media->config) {

        } elseif (substr($media->config, 0,6) == 'preset') {
            $presets = unserialize($this->getDi()->store->getBlob('flowplayer-presets'));
            $localConfig = $presets[$media->config]['config'];
        } else {
            $localConfig = unserialize($media->config);
        }

        $config = array_merge($this->getDi()->config->get('flowplayer', []), $localConfig);

        if (!empty($config['logo_id'])) {
            $logo = $this->getDi()->uploadTable->load($config['logo_id'], false);
            $logo_url = $logo ? $this->getDi()->surl('upload/get/' . preg_replace('/^\./', '', $logo->path), false) : '';
            $config['logo'] = $logo_url;
            $config['logo_position'] =  !empty($config['logo_position']) ? $config['logo_position'] : 'top-right';
        }

        $config['autoPlay'] = isset($config['autoPlay']) && $config['autoPlay'];

        return $config;
    }

    function getMedia()
    {
        if (!$this->media) {
            $this->id = $this->_request->getInt('id');
            if (!$this->id)
                throw new Am_Exception_InputError("Wrong URL - no media id passed");
            $this->media = $this->getDi()->videoTable->load($this->id, false);
            if (!$this->media)
                throw new Am_Exception_InputError("This media has been removed");
        }
        return $this->media;
    }

    function dAction()
    {
        $id = $this->_request->get('id');
        $this->validateSignedLink($id);
        $id = intval($id);
        $media = $this->getDi()->videoTable->load($id);
        set_time_limit(600);

        while (@ob_end_clean());
        $this->getDi()->session->writeClose();

        if ($path = $media->getFullPath()) {
            $this->_helper->sendFile($path, $media->getMime());
        } else {
            Am_Mvc_Response::redirectLocation(
                $media->getProtectedUrl($this->getDi()->config->get('storage.s3.expire',15) * 60)
            );
        }
    }

    function pAction()
    {
        $media = $this->getMedia();
        $view = $this->view;

        $view->meta_title = $media->meta_title ?: $media->title;
        if ($media->meta_keywords)
            $view->headMeta()->setName('keywords', $media->meta_keywords);
        if ($media->meta_description)
            $view->headMeta()->setName('description', $media->meta_description);
        if ($media->meta_robots)
            $view->headMeta()->setName('robots', $media->meta_robots);

        $view->title = $media->title;
        $view->media_item = $media;
        $view->content = <<<CUT
<script type="text/javascript" id="am-{$this->type}-{$this->id}">
    {$this->renderJs()}
</script>
CUT;
        $view->display($media->tpl ?: 'layout.phtml');
    }

    function getSignedLink(ResourceAbstract $media)
    {
        $rel = $media->pk() . '-' . ($this->getDi()->time + 3600 * 24);
        return $this->getDi()->surl(
            "{$this->type}/d/id/{$rel}-{$this->getDi()->security->siteHash('am-' . $this->type . '-' . $rel, 10)}",
            false
        );
    }

    function validateSignedLink($id)
    {
        @list($rec_id, $time, $hash) = explode('-', $id, 3);
        if ($rec_id <= 0)
            throw new Am_Exception_InputError('Wrong media id#');
        if ($time < Am_Di::getInstance()->time)
            throw new Am_Exception_InputError('Media Link Expired');
        if ($hash != $this->getDi()->security->siteHash("am-" . $this->type . "-$rec_id-$time", 10))
            throw new Am_Exception_InputError('Media Link Error - Wrong Sign');
    }

    function renderJs()
    {
        if(!$this->getDi()->auth->getUserId())
            $this->getDi()->auth->checkExternalLogin($this->getRequest());

        $media = $this->getMedia();
        $config = $this->getMediaConfig($media);
        $params = $this->getPlayerParams($media);

        $this->view->id = $this->id;
        $this->view->type = $this->type;
        $width = $this->_request->getInt('width') ?: (!empty($config['width']) ? $config['width'] : 'unset');
        if (is_numeric($width)) {
            $width .= 'px';
        }
        $height = $this->_request->getInt('height') ?: (!empty($config['height']) ? $config['height'] : 'unset');
        if (is_numeric($height)) {
            $height .= 'px';
        }

        $guestAccess = $media->hasAccess(null);
        if (!$this->getDi()->auth->getUserId() && !$guestAccess) {
            try {
                if (in_array($media->mime, ['audio/mpeg', 'audio/mp3'])) throw new Exception; //skip it for audio files
                $m = $this->getDi()->videoTable->load($this->getDi()->config->get('video_non_member'));
                $media = $m;
                $this->view->media = $this->getSignedLink($m);
                $this->view->mime = $m->mime;
            } catch (Exception $e) {
                $this->view->error = ___("You must be logged-in to open this media");
                $this->view->link = $this->getDi()->url("login", false);
            }
        } elseif (!$guestAccess && !$media->hasAccess($this->getDi()->user)) {
            try {
                if (in_array($media->mime, ['audio/mpeg', 'audio/mp3'])) throw new Exception; //skip it for audio files
                $m = $this->getDi()->videoTable->load($this->getDi()->config->get('video_not_proper_level'));
                $media = $m;
                $this->view->media = $this->getSignedLink($m);
                $this->view->mime = $m->mime;
            } catch (Exception $e) {
                $this->view->error = ___("Your subscription does not allow access to this media");
                if(!empty($media->no_access_url)) {
                    $this->view->link = $media->no_access_url;
                } else {
                    $this->view->link = $this->getDi()->url('no-access/content',
                        ['id' => $media->pk(), 'type' => $media->getTable()->getName(true)]);
                }
            }
        } else {
            $this->view->media = $this->getSignedLink($media);
            $this->view->mime = $media->mime;
        }

        $this->view->playerParams = $params;
        $this->view->isSecure = $this->getRequest()->isSecure();
        $this->view->code = $this->getHtmlCode($media, $width, $height);
        return $this->view->render('_media.phtml');
    }

    function jsAction()
    {
        $this->_response->setHeader('Content-type', 'text/javascript');
        echo $this->renderJs();
    }
}
