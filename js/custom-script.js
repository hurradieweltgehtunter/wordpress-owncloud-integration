$(document).ready(function() {
    $('.runner').on('click', function() {
        
        $('.result').html('')

        $.ajax({
            url: ajaxurl, // defined in admin header -> admin-ajax.php
            dataType: 'JSON',
            method: 'POST',
            data: {
                'action': 'get_files',
            },
            success: function(response) {
                $('.result').html(response)
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
})