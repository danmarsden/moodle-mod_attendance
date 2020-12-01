//
// * Javascript mod_presence
// *
// * Developer: 2020 Florian Metzger-Noel (github.com/flocko-motion)
//

require(['core/first', 'jquery', 'jqueryui', 'core/ajax', 'core/notification'], function(core, $, bootstrap, ajax, notification) {
    window.notification = notification;

    $(document).ready(function() {

        $('#presence_message_show').click(function(event) {
            event.preventDefault();
            $('#presence_message_form').show();
        });

        $('#presence_message_cancel').click(function() {
            $('#presence_message_form').hide();
        });

        $('#presence_message_send').click(function() {
            var message = $('#presence_message_text').val().trim();
            if (!message) {
                notification.alert(window.get_string("error"), window.get_string("messageempty"));
                return;
            }
            var userid = Number($('#mod_presence_user_profile').attr('data-presence-userid'));

            ajax.call([{
                methodname: 'mod_presence_send_message',
                args: {
                    'userid': userid,
                    'message': message,
                },
            }])[0].done(function(result) {
                $('#presence_message_text').val('');
                $('#presence_message_form').hide();
                notification.alert(window.get_string("success"), window.get_string("messagesent"));
            }).fail(function() {
                notification.exception(new Error('Failed to load data'));
            });
        });

        $('#presence_status_save').click(function() {
            updateUser(0);
        });

        $('#presence_strengths_save').click(function() {
            updateUser(0);
        });


        $('#presence_sws_edit').click(function(event) {
            event.preventDefault();

            var sws = $('#presence_sws_bar').attr('aria-valuenow');
            $("input[name=presence_sws_options][value=" + sws + "]").prop('checked', true);

            $('#presence_sws_display').hide();
            $('#presence_sws_editor').show();
        });

        $('#presence_sws_cancel').click(function() {
            $('#presence_sws_display').show();
            $('#presence_sws_editor').hide();
        });

        $('#presence_sws_save').click(function() {
            var sws = Number($("input[name=presence_sws_options]:checked").val());
            updateUser(sws);
        });

        function updateUser(sws) {
            var userid = Number($('#mod_presence_user_profile').attr('data-presence-userid'));
            ajax.call([{
                methodname: 'mod_presence_update_user',
                args: {
                    'userid': userid,
                    'courseid': window.get_var("courseid"),
                    'status': $('#presence_status_input').val(),
                    'strengths': $('#presence_strengths_input').val(),
                    'sws': sws,
                },
            }])[0].done(function(result) {

                if (result.sws) {
                    $('#presence_sws_text').text(result.swstext);
                    $('#presence_sws_textshort').text(result.swstextshort);
                    $('#presence_sws_bar').css('width', result.swspercent + '%');
                    $('#presence_sws_bar').attr('aria-valuenow', result.sws);
                    $('#presence_sws_display').show();
                    $('#presence_sws_editor').hide();
                }
            }).fail(function() {
                notification.exception(new Error('Failed to load data'));
            });
        }
    });
});