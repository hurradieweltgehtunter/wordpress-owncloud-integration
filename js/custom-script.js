function getFolderList() {
    $.ajax({
        url: ajaxurl, // defined in admin header -> admin-ajax.php
        dataType: 'JSON',
        method: 'POST',
        data: {
            action: 'get_folder_list'
        },
        success: function(res) {
            $('.folder-list')
                .on('changed.jstree', function(e, data) {
                    $('#ocsyncPath').val(data.instance.get_node(data.selected).data);
                })
                .jstree(
                    { 'core' : {
                        'data' : res
                    }
                });
        }
    });
}

$(document).ready(function() {
    getFolderList();

    $('.runner').on('click', function(e) {
        e.preventDefault();
        $('.result').html('');
        $('.oc').addClass('animated');
        $('.loadanimation').fadeIn(100);

        $.ajax({
            url: ajaxurl, // defined in admin header -> admin-ajax.php
            dataType: 'JSON',
            method: 'POST',
            data: {
                action: 'get_files'
            },
            success: function(response) {
                $.each(response.log, function(k, message) {
                    $('.result').append('<div>' + message + '</div>');
                });

                $('.loadanimation').fadeOut(100);
                $('.oc').removeClass('animated');
            }
        });
    });

    $('.empty').on('click', function(e) {
        e.preventDefault();
        $('.result').html('');

        $.ajax({
            url: ajaxurl, // defined in admin header -> admin-ajax.php
            dataType: 'JSON',
            method: 'POST',
            data: {
                action: 'empty_media_pool'
            },
            success: function(response) {
                $('.result').html(JSON.stringify(response));
            }
        });
    });

    $('.test-connection').on('click', function(e) {
        e.preventDefault();
        var elem = $('.test-result');
        elem.html('');
        $('<i/>').addClass('fa fa-spin fa-cog connection-icon').appendTo(elem);
        $.ajax({
            url: ajaxurl, // defined in admin header -> admin-ajax.php
            dataType: 'JSON',
            method: 'POST',
            data: {
                action: 'test_connection',
                credentials: {
                    baseUri: $('#ocBaseUri').val(),
                    userName: $('#ocUserName').val(),
                    password: $('#ocPassword').val()
                }
            },
            success: function(response) {
                $('.test-result').html(response.message);

                var icon = $('<i/>').addClass('fa connection-icon');

                if (response.status === 'success') {
                    icon.addClass('fa-check icon-green');
                }
                else {
                    icon.addClass('fa-exclamation-circle icon-red');
                }

                icon.prependTo($('.test-result'));
            }
        });

        return false;
    });
});

function printFolder(folder, parent) {
    var folder_li = $('<li data-path="' + folder.path + '"/>');
    folder_li.html(folder.name);
    folder_li.addClass('set-root-folder');
    folder_li.appendTo(parent);

    if (folder.subs.length > 0) {
        var ul = $('<ul/>');

        folder.subs.forEach(function(sub) {
            printFolder(sub, ul);
        });

        ul.appendTo(folder_li);
    }
}
