//import $ from 'jquery';

$(document).on('click', '.tli-video-thumb', function(){

    let videoPlayerContainer = $('#tli-youtube-video-player');
    videoPlayerContainer.get(0).scrollIntoView({behavior: 'smooth'});

    let videoIframe = videoPlayerContainer.find('iframe');
    let allows = videoIframe.attr('allow');

    // the autoplay permission must be in place before the iframe (re)loads
    if( !allows.includes('autoplay') ) {
        videoIframe.attr('allow', 'autoplay; ' + allows);
    }

    // legacy embedUrls (pre-built, cached server side) may still carry autoplay=1
    let clickedVideoEmbedUrl    = String($(this).data('embed-url')).replace('&autoplay=1', '');
    let clickedVideoTitle       = $(this).find('.tli-video-thumb-title').html();

    videoIframe.off('load.tli-play').on('load.tli-play', function() {
        playWhenReady(this.contentWindow);
    });

    videoIframe.get(0).contentWindow.location.replace(clickedVideoEmbedUrl);
    videoPlayerContainer.find('.title').html(clickedVideoTitle);
});


// the player inside the iframe boots asynchronously: repeat the command until it reports "playing", then stop
function playWhenReady(playerWindow)
{
    let attempts = 0;

    let timer = setInterval(function() {

        if( ++attempts > 20 ) {
            return stop();
        }

        playerWindow.postMessage(JSON.stringify({event: 'listening', id: 'tli-player', channel: 'widget'}), '*');
        playerWindow.postMessage(JSON.stringify({event: 'command', func: 'playVideo', args: [], id: 'tli-player', channel: 'widget'}), '*');

    }, 250);

    function onPlayerMessage(message) {
        if( String(message.data).includes('"playerState":1') ) {
            stop();
        }
    }

    function stop() {
        clearInterval(timer);
        window.removeEventListener('message', onPlayerMessage);
    }

    window.addEventListener('message', onPlayerMessage);
}
