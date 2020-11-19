//
// * Javascript for "rooms" feature extension
// *
// * Developer: 2020 Florian Metzger-Noel (github.com/flocko-motion)
//

require(['core/first', 'jquery', 'jqueryui', 'core/ajax', 'core/notification'], function(core, $, bootstrap, ajax, notification) {
    window.notification = notification;

    $(document).ready(function() {

        $('#attendance_message_show').click(function() {
            $('#attendance_message_form').show();
        });

        $('#attendance_message_cancel').click(function() {
            $('#attendance_message_form').hide();
        });

        $('#attendance_message_send').click(function() {
            alert("TODO: send message");
        });
    });
});