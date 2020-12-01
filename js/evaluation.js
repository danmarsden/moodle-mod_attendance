//
// * Javascript for mod_presence
// *
// * Developer: 2020 Florian Metzger-Noel (github.com/flocko-motion)
//

require(['core/first', 'jquery', 'jqueryui', 'core/ajax', 'core/notification', ],
    function(core, $, bootstrap, ajax, notification) {
        window.notification = notification;
        $(document).ready(function() {

            var queryTimer = null;
            var query = null;
            var queryId = 0;
            var usersToAdd = {};
            var newUserId = -1;

            function resetSearchBox() {
                $('input[data-module=mod_presence_add]').val('');
                $('div[data-module=mod_presence_add] > button[data-template=false]').remove();
            }

            function addStudent(o) {
                var row = $(this);
                row.hide();
                var userid = row.attr('data-userid');
                if (userid in usersToAdd) {
                    return;
                }
                if (userid < 0) {
                    $('#modPresenceNewName').text(row.attr('data-name'));
                    $('#modPresenceNewName').attr('data-id', userid);
                    $('#modPresenceNewName').attr('data-name', row.attr('data-name'));
                    $('#modPresenceNewName').attr('data-action', row.attr('data-action'));
                    $('#modPresenceNewName').attr('data-actiontext', row.attr('data-actiontext'));
                    $('#modPresenceNewEmail').val('');
                    $('#modPresenceNewPhone').val('');
                    $('[data-view=mod_presence_add_user]').hide();
                    $('[data-view=mod_presence_new_user]').show();
                } else {
                    usersToAdd[userid] = {
                        'id': userid,
                        'name': row.attr('data-name'),
                        'action': row.attr('data-action'),
                        'actiontext': row.attr('data-actiontext'),
                    };
                    updateList();
                    resetSearchBox();
                }
            }

            $('#mod_presence_new_user_submit').click(function newStudentSubmit() {
                var userid =  $('#modPresenceNewName').attr('data-id');
                usersToAdd[userid] = {
                    'id': userid,
                    'name': $('#modPresenceNewName').attr('data-name'),
                    'action': $('#modPresenceNewName').attr('data-action'),
                    'actiontext': $('#modPresenceNewName').attr('data-actiontext'),
                    'email': $('#modPresenceNewEmail').val(),
                    'phone': $('#modPresenceNewPhone').val(),
                };
                updateList();
                resetSearchBox();
                $('[data-view=mod_presence_add_user]').show();
                $('[data-view=mod_presence_new_user]').hide();
            });

            $('#mod_presence_new_user_cancel').click(function newStudentCancel() {
                $('[data-view=mod_presence_add_user]').show();
                $('[data-view=mod_presence_new_user]').hide();
            });

            $('#mod_presence_add_save').click(function() {
                console.log('magic useradd');
                var sessionid = Number($('[data-module=mod_presence][data-sessionid]').val());
                var userdata = [];
                Object.values(usersToAdd).forEach(function(user) {
                    userdata.push({
                        'id' : user.id,
                        'action': user.action,
                        'name': user.name,
                        'email': user.email ? user.email : null,
                        'phone': user.phone ? user.phone : null,
                    });
                });
                ajax.call([{
                    methodname: 'mod_presence_magic_useradd',
                    args: {
                        'sessionid': sessionid,
                        'userdata': userdata,
                    },
                }])[0].done(function(res) {
                    window.location.reload();
                }).fail(function() {
                    notification.exception(new Error('Failed to magically add users'));
                    return;
                });
            });

            function updateList() {
                var users = Object.values(usersToAdd);
                users.sort(function(a, b) {
                    if (a.action > b.action) {
                        return 1;
                    }
                    if (a.action < b.action) {
                        return -1;
                    }
                    if (a.name > b.name) {
                        return 1;
                    }
                    if (a.name < b.name) {
                        return -1;
                    }
                    return 0;
                });
                $('div[data-module=mod_presence_add_list] > div[data-template=false]').remove();
                users.forEach(function(user) {
                    var element = $('div[data-module=mod_presence_add_list] > div[data-template=true]')
                        .clone()
                        .attr('data-template', false)
                        .attr('data-userid', user.id)
                        .appendTo('div[data-module=mod_presence_add_list]');
                    $(element.children()[0]).html(user.actiontext + ": <b>" + user.name + '</b>');
                    $(element.children()[1]).attr('data-userid', user.id).click(removeStudent);
                    element.show();
                });
                if (users.length) {
                    $('div[data-module=mod_presence_add_list]').show();
                } else {
                    $('div[data-module=mod_presence_add_list]').hide();
                }
            }

            function removeStudent(o) {
                o.preventDefault();
                var element = $(this);
                var userid = element.attr('data-userid');
                delete usersToAdd[userid];
                updateList();
            }

            function querySend() {
                queryId++;
                var sessionid = Number($('[data-module=mod_presence][data-sessionid]').val());

                ajax.call([{
                    methodname: 'mod_presence_autocomplete_addstudent',
                    args: {
                        'sessionid': sessionid,
                        'queryid': queryId,
                        'query': query,
                    },
                }])[0].done(function(res) {
                    // results from previous query? ignore.
                    if (res.queryid != queryId) {
                        return;
                    }
                    $('div[data-module=mod_presence_add] > button[data-template=false]').remove();
                    res.results.forEach(function(row) {
                        if (row.userid == 0) {
                            row.userid = newUserId--;
                        }
                        if (row.userid in usersToAdd) {
                            return;
                        }
                        var pos = row.name.toLowerCase().indexOf(res.query);
                        if (pos !== -1) {
                            row.nameHtml = row.name.substr(0, pos)
                            + '<b>'
                            + row.name.substr(pos, res.query.length)
                            + '</b>'
                            + row.name.substr(pos + res.query.length);
                        } else {
                            row.nameHtml = row.name;
                        }
                        var element = $('div[data-module=mod_presence_add] > button[data-template=true]')
                            .clone()
                            .attr('data-template', false)
                            .attr('data-userid', row.userid)
                            .attr('data-name', row.name)
                            .attr('data-action', row.action)
                            .attr('data-actiontext', row.actiontext)
                            .appendTo('div[data-module=mod_presence_add]');
                        $(element.children()[0]).html(row.nameHtml);
                        if (row.tag) {
                            $(element.children()[1]).html(row.tag);
                        } else {
                            $(element.children()[1]).remove();
                        }
                        element.click(addStudent);
                        element.show();
                    });
                }).fail(function() {
                    notification.exception(new Error('Failed to load data'));
                    return;
                });
            }

            // autocomplete for add student field
            $('[data-module=mod_presence_add]').on('input',function() {
                query = String($(this).val()).trim().toLowerCase();
                if (queryTimer) {
                    clearTimeout(queryTimer);
                }
                queryTimer = setTimeout(querySend, 250);
            });


            // Take evaluation.
            $('[data-module=mod_presence_evaluate]').change(function() {
                var sessionid = $('[data-module=mod_presence][data-sessionid]').val();
                if (!sessionid) {
                    return;
                }

                var userid = Number($(this).attr("data-userid"));
                var userids = [];
                if (userid) {
                    userids.push(userid);
                    if ($(this).attr('type') == 'checkbox') {
                        $("[data-module=mod_presence_evaluate][data-field=duration][data-userid=" + userid + "]")
                            .prop('disabled', !$(this).prop('checked'));
                    }
                } else {
                    // Set all..
                    if ($(this).attr('type') == 'checkbox') {
                        $('[data-module=mod_presence_evaluate][data-field=presence]').prop('checked', $(this).prop('checked'));
                        $('[data-module=mod_presence_evaluate][data-field=duration]')
                            .filter(function() {
                                return $(this).attr("data-userid") > 0;
                            })
                            .prop('disabled', !$(this).prop('checked'));
                    } else {
                        var duration = $(this).val();
                        $('[data-module=mod_presence_evaluate][data-field=duration]').val(duration);
                    }
                    // Collect user ids to send..
                    $('[data-module=mod_presence_evaluate][data-field=presence]').each(function(k, v) {
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
                        'presence': Number(
                            $("[data-module=mod_presence_evaluate][data-userid=" + userid + "][data-field=presence]")
                                .prop("checked")
                        ),
                        'duration': Number(
                            $("[data-module=mod_presence_evaluate][data-userid=" + userid + "][data-field=duration]").val()
                        ),
                        'remarks_course': $("[data-module=mod_presence_evaluate][data-userid=" + userid + "][data-field=remarks_course]")
                            .val(),
                        'remarks_personality':
                            $("[data-module=mod_presence_evaluate][data-userid=" + userid + "][data-field=remarks_personality]")
                            .val(),
                    });
                }

                ajax.call([{
                    methodname: 'mod_presence_update_evaluation',
                    args: {
                        'sessionid': sessionid,
                        'updates': updates,
                    },
                }])[0].done(function() {
                    return;
                }).fail(function() {
                    notification.exception(new Error('Failed to load data'));
                    return;
                });
            });

            $('#mod_presence_evaluation_list').show();
            $('#mod_presence_evaluation_loading').hide();

        });
});

