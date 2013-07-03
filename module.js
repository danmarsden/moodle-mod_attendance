M.mod_attendance = {}

M.mod_attendance.init_manage = function(Y) {

    Y.on('click', function(e) {
        if (e.target.get('checked')) {
            checkall();
        } else {
            checknone();
        }
    }, '#cb_selector' );
};

M.mod_attendance.set_preferences_action = function(action) {
    var item = document.getElementById('preferencesaction');
    if (item) {
        item.setAttribute('value', action);
    } else {
        item = document.getElementById('preferencesform');
        var input = document.createElement("input");
        input.setAttribute("type", "hidden");
        input.setAttribute("name", "action");
        input.setAttribute("value", action);
        item.appendChild(input);
    }
};