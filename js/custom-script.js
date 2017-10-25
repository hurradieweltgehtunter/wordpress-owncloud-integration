$(document).ready(function() {
    $('.runner').on('click', function() {
        
        $('.result').html('')
        $('.loadanimation').fadeIn(100);

        $.ajax({
            url: ajaxurl, // defined in admin header -> admin-ajax.php
            dataType: 'JSON',
            method: 'POST',
            data: {
                'action': 'get_files',
            },
            success: function(response) {
                $.each(response.log, function(k, message) {
                    $('.result').append('<div>' + message + '</div>');
                })
                
                $('.loadanimation').fadeOut(100);
            }
        });
    })

    $('.empty').on('click', function() {
        
        $('.result').html('')

        $.ajax({
            url: ajaxurl, // defined in admin header -> admin-ajax.php
            dataType: 'JSON',
            method: 'POST',
            data: {
                'action': 'empty_media_pool',
            },
            success: function(response) {
                $('.result').html(JSON.stringify(response))
            }
        });
    })

    $('.test-connection').on('click', function(e) {
        e.preventDefault();
        $('.test-result').html('Testing connection...')
        $.ajax({
            url: ajaxurl, // defined in admin header -> admin-ajax.php
            dataType: 'JSON',
            method: 'POST',
            data: {
                'action': 'test_connection',
                credentials: {
                    baseUri: $('#ocBaseUri').val(),
                    userName: $('#ocUserName').val(),
                    password: $('#ocPassword').val()
                }
            },
            success: function(response) {
                $('.test-result').html(response.message)
            }
        });

        return false;
    })
})