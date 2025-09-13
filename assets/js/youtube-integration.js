//import $ from 'jquery';

$(document).on('click', '.tli-video-thumb', function(){

    let videoPlayer = $('#tli-youtube-video-player');
    videoPlayer.get(0).scrollIntoView({behavior: 'smooth'});

    let clickedVideoEmbedUrl    = $(this).data('embed-url');
    let clickedVideoTitle       = $(this).find('.tli-video-thumb-title').html();

    videoPlayer.find('iframe').attr('src', clickedVideoEmbedUrl);
    videoPlayer.find('.title').html(clickedVideoTitle);
});
