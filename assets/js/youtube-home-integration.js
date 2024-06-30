//import $ from 'jquery';

jQuery('#tli-youtube-video-player iframe').on('load', function(){

    let videoPlayer = jQuery(this);

    let noAutoplay = videoPlayer.data('tli-autoplay');
    if( noAutoplay == 0 ) {
        return false;
    }

    let playCommandTarget = videoPlayer[0].contentWindow;
    playCommandTarget.postMessage('{"event":"command","func":"playVideo","args":""}', '*');
});


jQuery(document).on('click', '.tli-video-thumb', function(){

    let clickedVideoEmbedUrl    = jQuery(this).data('embed-url');
    let clickedVideoTitle       = jQuery(this).find('.title').html();

    let videoPlayerContainer    = jQuery('#tli-youtube-video-player');
    let videoPlayer             = videoPlayerContainer.find('iframe');

    videoPlayer.data('tli-autoplay', 1);
    videoPlayer.attr('src', clickedVideoEmbedUrl);
    videoPlayerContainer.find('.title').html(clickedVideoTitle);
});
