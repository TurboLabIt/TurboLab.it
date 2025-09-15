//import $ from 'jquery';

$(document).on('click', '.tli-video-thumb', function(){

    let videoPlayerContainer = $('#tli-youtube-video-player');
    videoPlayerContainer.get(0).scrollIntoView({behavior: 'smooth'});

    let videoIframe = videoPlayerContainer.find('iframe');
    let allows = videoIframe.attr('allow');

    // Add autoplay if not already present
    if( !allows.includes('autoplay') ) {
        videoIframe.attr('allow', 'autoplay; ' + allows);
    }

    let clickedVideoEmbedUrl    = $(this).data('embed-url');
    let clickedVideoTitle       = $(this).find('.tli-video-thumb-title').html();

    videoIframe.attr('src', clickedVideoEmbedUrl);
    videoPlayerContainer.find('.title').html(clickedVideoTitle);
});
