//
// * Javascript
// *
// * @package    ajaxdemo
// * Developer: 2020 Ricoshae Pty Ltd (http://ricoshae.com.au)
//

require(['core/first', 'jquery', 'jqueryui', 'core/ajax'], function(core, $, bootstrap, ajax) {

    // -----------------------------
    $(document).ready(function() {

        $('#id_roomid').change(function() {
            var selectedroomid = $('#id_roomid').val();
            ajax.call([{
                methodname: 'mod_attendance_get_room_capacity',
                args: {
                    'roomid': selectedroomid
                },
            }])[0].done(function(capacity) {
                var oldCapacity = $('#id_roomattendants').val();
                $('#id_roomattendants option').each(function() {
                    if ( $(this).val() > 0) {
                        $(this).remove();
                    }
                });
                for (var i = 1; i <= capacity; i++) {
                    $('<option/>').val(i).html(i).appendTo('#id_roomattendants');
                }
                $('#id_roomattendants').val(Math.min(oldCapacity, capacity));
                return;
            }).fail(function(err) {
                // notification.exception(new Error('Failed to load data'));
                return;
            });

        });

    });
});