//import $ from 'jquery';

document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        locale: 'it',
        firstDay: 1,
        initialView: 'dayGridMonth',
        validRange: {
            start: jQuery('#calendar').data('min-date'),
            end: jQuery('#calendar').data('max-date'),
        },
        events: {
            url: jQuery('#calendar').data('events-loading-url')
        }
});
    calendar.render();
});
