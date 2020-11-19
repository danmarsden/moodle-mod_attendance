//
// * Javascript for "rooms" feature extension
// *
// * Developer: 2020 Florian Metzger-Noel (github.com/flocko-motion)
//

require(['core/first', 'jquery', 'jqueryui', 'core/ajax', 'core/notification'], function(core, $, bootstrap, ajax, notification) {
    window.notification = notification;

    $(document).ready(function() {

        $('[data-module=mod_attendance]').change(function() {
            var sessionid = $('[data-module=mod_attendance][data-sessionid]').val();
            if (!sessionid) {
                return;
            }

            var userid = Number($(this).attr("data-userid"));
            var userids = [];
            if (userid) {
                userids.push(userid);
                if($(this).attr('type') == 'checkbox') {
                    $(`[data-module=mod_attendance][data-field=duration][data-userid=${userid}]`).prop('disabled', !$(this).prop('checked'));
                }
            } else {
                // Set all..
                if($(this).attr('type') == 'checkbox') {
                    $('[data-module=mod_attendance][data-field=attendance]').prop('checked', $(this).prop('checked'));
                    $('[data-module=mod_attendance][data-field=duration]')
                        .filter(function() { return $(this).attr("data-userid") > 0; })
                        .prop('disabled', !$(this).prop('checked'));
                } else {
                    var duration = $(this).val();
                    $('[data-module=mod_attendance][data-field=duration]').val(duration);
                }
                // Collect user ids to send..
                $('[data-module=mod_attendance][data-field=attendance]').each(function(k, v) {
                    var userid = Number($(v).attr('data-userid'));
                    if (userid) {
                        userids.push(userid);
                    }
                });
            }

            var updates = [];

            for (var k in userids) {
                userid = userids[k];
                updates.push({
                    'userid': userid,
                    'attendance': Number(
                        $(`[data-module=mod_attendance][data-userid=${userid}][data-field=attendance]`).prop("checked")
                    ),
                    'duration': Number(
                        $(`[data-module=mod_attendance][data-userid=${userid}][data-field=duration]`).val()
                    ),
                    'remarks_course': $(`[data-module=mod_attendance][data-userid=${userid}][data-field=remarks_course]`).val(),
                    'remarks_personality': $(`[data-module=mod_attendance][data-userid=${userid}][data-field=remarks_personality]`).val(),
                });
            }

            ajax.call([{
                methodname: 'mod_attendance_update_evaluation',
                args: {
                    'sessionid': sessionid,
                    'updates': updates,
                },
            }])[0].done(function () {
                return;
            }).fail(function () {
                // notification.exception(new Error('Failed to load data'));
                return;
            });
        });

        $('#mod_attendance_evaluation_list').show();
        $('#mod_attendance_evaluation_loading').hide();

    });
});