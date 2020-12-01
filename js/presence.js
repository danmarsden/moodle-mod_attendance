//
// * Javascript for mod_presence
// *
// * Developer: 2020 Florian Metzger-Noel (github.com/flocko-motion)
//

require(['core/first', 'jquery', 'jqueryui', 'core/ajax', 'core/notification'], function(core, $, bootstrap, ajax, notification) {
    window.notification = notification;

    $(document).ready(function () {

        $('[data-presence-action=back]').click(function () {
            window.history.back();
        });

        // eslint-disable-next-line no-unused-vars
        window.get_string = function(str) {
            return $('data[data-type=presence_str][data-key='+str+']').attr('data-value');
        };

    });
});
