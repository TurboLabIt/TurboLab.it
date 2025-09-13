//import $ from 'jquery';

$('#tli-youtube-video-player iframe').on('load', function(){

    let videoPlayer = $(this);

    let noAutoplay = videoPlayer.data('tli-autoplay');
    if( noAutoplay == 0 ) {
        return false;
    }

    let playCommandTarget = videoPlayer[0].contentWindow;
    playCommandTarget.postMessage('{"event":"command","func":"playVideo","args":""}', '*');
});


$(document).on('click', '.tli-video-thumb', function(){

    $('#tli-youtube-video-player').get(0).scrollIntoView({behavior: 'smooth'});

    let clickedVideoEmbedUrl    = $(this).data('embed-url');
    let clickedVideoTitle       = $(this).find('.title').html();

    let videoPlayerContainer    = $('#tli-youtube-video-player');
    let videoPlayer             = videoPlayerContainer.find('iframe');

    videoPlayer.data('tli-autoplay', 1);
    videoPlayer.attr('src', clickedVideoEmbedUrl);
    videoPlayerContainer.find('.title').html(clickedVideoTitle);
});
