<?php
/*
*   Members page. Used to renew subscription.
*
*
*     Author: Alex Scott
*      Email: alex@cgi-central.net
*        Web: http://www.cgi-central.net
*    Details: Member display page
*    FileName $RCSfile$
*    Release: 6.3.6 ($Revision: 5371 $)
*
* Please direct bug reports,suggestions or feedback to the cgi-central forums.
* http://www.cgi-central.net/forum/
*
* aMember PRO is a commercial software. Any distribution is strictly prohibited.
*
*/

class VideoController extends MediaController
{
    protected $type = 'video';

    function getHtmlCode($media, $width, $height)
    {
        $scriptId = "am-{$this->type}-" . filterId($this->id);
        $mediaId = filterId($this->id);
        $divId = "div-".$scriptId;

        $url = $this->getSignedLink($media);
        $config = $this->getMediaConfig($media);

        //Poster
        $poster = '';
        if ($poster_id = $media->poster_id ?: (isset($config['poster_id']) ? $config['poster_id'] : "")) {
            $file = $this->getDi()->plugins_storage->getFile($poster_id);
            $poster = $file ? $file->getUrl(100, false) : '';
        }

        //Captions
        $captions = '';
        if ($media->cc_vtt_id) {
            $cc_vtt = $this->getDi()->uploadTable->load($media->cc_vtt_id, false);
            $cc_vtt_url = $this->getDi()->surl('upload/get/' . preg_replace('/^\./', '', $cc_vtt->path), false);
            $captions = $cc_vtt_url;
        }

        $track = '';
        if ($captions) {
            $track = <<<CUT
<track kind="captions" src="{$captions}" />
CUT;

        }

        //Branding
        $logo_css = '';
        $position_map = [
            'top-right' => 'top: 20px;right: 20px;',
            'top-left' => 'top: 20px;left: 20px;',
            'bottom-right' => 'bottom: 50px;right: 20px;',
            'bottom-left' => 'bottom: 50px;left: 20px;',
        ];


        if (!empty($config['logo'])) {
$logo_css = <<<CUT
<style type="text/css">
    #{$divId} .plyr__video-wrapper::before {
        {$position_map[$config['logo_position']]}   
        position: absolute;
        z-index: 10;
        content: url('{$config['logo']}');
    }
    .plyr--stopped.plyr__poster-enabled .plyr__video-wrapper::before {
        display: none;
    }
</style>
CUT;
        }

        return <<<CUT
{$logo_css}
<div id="{$divId}" class="am-video-wrapper" style="max-width:{$width}; height:{$height}">
    <video id="player-{$mediaId}" controls data-poster="{$poster}">
        <source type="{$media->mime}" />
        {$track}
    </video>
</div>
CUT;

    }
}