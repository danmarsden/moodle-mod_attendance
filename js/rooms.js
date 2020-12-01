//
// * Javascript for "rooms" feature extension
// *
// * Developer: 2020 Florian Metzger-Noel (github.com/flocko-motion)
//

require(['core/first', 'jquery', 'jqueryui', 'core/ajax', 'core/notification'], function(core, $, bootstrap, ajax, notification) {
    window.notification = notification;

    $(document).ready(function() {

        function modpresenceGetRoomCapacity() {
            var selectedroomid = $('#id_roomid').val();
            if(!selectedroomid) return;
            ajax.call([{
                methodname: 'mod_presence_get_room_capacity',
                args: {
                    'roomid': selectedroomid
                },
            }])[0].done(function(capacity) {
                // var minVal = Number($('[name=bookings]').val());
                var currentVal = $('#id_maxattendants').val();
                var currentValAllowed = false;
                $('#id_maxattendants option').each(function() {
                    var n = $(this).val();
                    if (n > capacity /*|| ( n > 0 && n < minVal)*/) {
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
                notification.exception(new Error('Failed to load data'));
                return;
            });
        }
        modpresenceGetRoomCapacity();

        $('#id_roomid').change(modpresenceGetRoomCapacity);



        $('button[data-presence-book-session]').click(function() {
            ajax.call([{
                methodname: 'mod_presence_book_session',
                args: {
                    'sessionid': $(this).attr('data-presence-book-session'),
                    'book': $(this).attr('data-presence-book-action'),
                },
            }])[0].done(function(result) {
                var show = result.bookingstatus * -2 + 1;
                var hide = result.bookingstatus *  2 - 1;
                $(`button[data-presence-book-session=${result.sessionid}][data-presence-book-action=${show}]`).show();
                $(`button[data-presence-book-session=${result.sessionid}][data-presence-book-action=${hide}]`).hide();
                $(`span[data-presence-book-session=${result.sessionid}]`)
                    .text(result.bookedspots);
                if (result.errormessage) {
                    notification.alert(result.errortitle, result.errormessage, result.errorconfirm);
                }
                return;
            }).fail(function(err) {
                notification.exception(new Error('Failed to load data'));
                return;
            });
        });

        function checkDoubleBookings() {


            console.log('check double bookings');

            var sessionid = $(this).attr('data-presence-book-session');
            if (!sessionid) sessionid = 0;

            var roomid = Number($('#id_roomid').val());


            var days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            var daysChecked = [];
            for (var i in days) {
                daysChecked[i] = Number($('#id_sdays_' + days[i]).prop('checked'));
            }

            var from = new Date(
                $('#id_sessiondate_year').val(),
                $('#id_sessiondate_month').val() - 1,
                $('#id_sessiondate_day').val(),
                $('#id_sestime_starthour').val(),
                $('#id_sestime_startminute').val()
            ).getTime() / 1000;



            var to = new Date(
                $('#id_sessiondate_year').val(),
                $('#id_sessiondate_month').val() - 1,
                $('#id_sessiondate_day').val(),
                $('#id_sestime_endhour').val(),
                $('#id_sestime_endminute').val()
            ).getTime() / 1000;

            var repeatUntil = new Date(
                $('#id_sessionenddate_year').val(),
                $('#id_sessionenddate_month').val() - 1,
                $('#id_sessionenddate_day').val()
            ).getTime() / 1000;

            ajax.call([{
                methodname: 'mod_presence_check_doublebooking',
                args: {
                    'sessionid': sessionid,
                    'roomid': roomid,
                    'from': from,
                    'to': to,
                    'repeat': Number($('#id_addmultiply').prop('checked')),
                    'repeatdays': daysChecked.join(','),
                    'repeatperiod': Number($('#id_period').val()),
                    'repeatuntil': repeatUntil,
                },
            }])[0].done(function(result) {
                console.log(result);
                $('#presence_collisions').html(result.result).show();
            }).fail(function(err) {
                notification.exception(new Error('Failed to load data'));
            });
        }
        $('#id_sessiondate_day').change(checkDoubleBookings);
        $('#id_sessiondate_month').change(checkDoubleBookings);
        $('#id_sessiondate_year').change(checkDoubleBookings);
        $('#id_sestime_starthour').change(checkDoubleBookings);
        $('#id_sestime_startminute').change(checkDoubleBookings);
        $('#id_sestime_endhour').change(checkDoubleBookings);
        $('#id_sestime_endminute').change(checkDoubleBookings);
        $('#id_addmultiply').change(checkDoubleBookings);
        $('#id_sdays_Mon').change(checkDoubleBookings);
        $('#id_sdays_Tue').change(checkDoubleBookings);
        $('#id_sdays_Wed').change(checkDoubleBookings);
        $('#id_sdays_Thu').change(checkDoubleBookings);
        $('#id_sdays_Fri').change(checkDoubleBookings);
        $('#id_sdays_Sat').change(checkDoubleBookings);
        $('#id_sdays_Sun').change(checkDoubleBookings);
        $('#id_sdays_Mon').change(checkDoubleBookings);
        $('#id_period').change(checkDoubleBookings);
        $('#id_sessionenddate_day').change(checkDoubleBookings);
        $('#id_sessionenddate_month').change(checkDoubleBookings);
        $('#id_sessionenddate_year').change(checkDoubleBookings);
        $('#id_roomid').change(checkDoubleBookings);

    });
});