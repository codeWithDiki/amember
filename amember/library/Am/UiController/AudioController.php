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

class AudioController extends MediaController
{
    protected $type = 'audio';

    function getHtmlCode($media, $width, $height)
    {
        $scriptId = "am-{$this->type}-" . filterId($this->id);
        $mediaId = filterId($this->id);
        $divId = "div-{$scriptId}";

        $url = $this->getSignedLink($media);

        return <<<CUT
<div id="{$divId}" class="am-audio-wrapper">
    <audio id="player-{$mediaId}" controls>
        <source type="{$media->mime}" />
    </audio>
</div>
CUT;
    }
}