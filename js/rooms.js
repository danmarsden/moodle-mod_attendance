//
// * Javascript for "rooms" feature extension
// *
// * Developer: 2020 Florian Metzger-Noel (github.com/flocko-motion)
//

require(['core/first', 'jquery', 'jqueryui', 'core/ajax', 'core/notification'], function(core, $, bootstrap, ajax, notification) {
    window.notification = notification;

    $(document).ready(function() {

        console.log("rooms");

        function modAttendanceGetRoomCapacity() {
            var selectedroomid = $('#id_roomid').val();
            ajax.call([{
                methodname: 'mod_attendance_get_room_capacity',
                args: {
                    'roomid': selectedroomid
                },
            }])[0].done(function(capacity) {
                var minVal = Number($('[name=bookings]').val());
                var currentVal = $('#id_maxattendants').val();
                var currentValAllowed = false;
                $('#id_maxattendants option').each(function() {
                    var n = $(this).val();
                    if (n > capacity || ( n > 0 && n < minVal)) {
                        $(this).hide();
                    }
                    else {
                        $(this).show();
                        if (currentVal == $(this).val()) {
                            currentValAllowed = true;
                        }
                    }
                });
                if (!currentValAllowed) {
                    $('#id_maxattendants').val(0);
                }
                return;
            }).fail(function(err) {
                return;
            });
        }
        modAttendanceGetRoomCapacity();

        $('#id_roomid').change(modAttendanceGetRoomCapacity);



        $('button[data-att-book-session]').click(function() {
            ajax.call([{
                methodname: 'mod_attendance_book_session',
                args: {
                    'sessionid': $(this).attr('data-att-book-session'),
                    'book': $(this).attr('data-att-book-action'),
                },
            }])[0].done(function(result) {
                var show = result.bookingstatus * -2 + 1;
                var hide = result.bookingstatus *  2 - 1;
                $(`button[data-att-book-session=${result.sessionid}][data-att-book-action=${show}]`).show();
                $(`button[data-att-book-session=${result.sessionid}][data-att-book-action=${hide}]`).hide();
                $(`span[data-att-book-session=${result.sessionid}]`)
                    .text(result.bookedspots);
                if (result.errormessage) {
                    notification.alert(result.errortitle, result.errormessage, result.errorconfirm);
                }
                return;
            }).fail(function(err) {
                // notification.exception(new Error('Failed to load data'));
                return;
            });
        });

    });
});