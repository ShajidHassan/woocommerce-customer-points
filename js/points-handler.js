jQuery(document).ready(function ($) {
    $('#apply_points').on('click', function () {
        var button = $(this);
        var pointsToUse = $('#points_to_use').val();

         // Disable the button to prevent multiple clicks
         button.prop('disabled', true);

        $.ajax({
            type: 'POST',
            url: wc_checkout_params.ajax_url,
            data: {
                'action': 'apply_points_as_coupon',
                'points_to_use': pointsToUse
            },
            success: function (response) {
                // Reload or update the checkout page
                location.reload();
            },
            error: function () {
                // Re-enable the button in case of an error
                button.prop('disabled', false);
                alert('Failed to apply points. Please try again.');
            }
        });
    });

    //Use all button functionality
    $('#use_all_points').on('click', function () {
        var maxPoints = parseInt($('#points_to_use').attr('max'));
        $('#points_to_use').val(maxPoints);
    });

    $('#apply_points').on('click', function () {
        var pointsToUse = $('#points_to_use').val();

        $.ajax({
            type: 'POST',
            url: wc_checkout_params.ajax_url,
            data: {
                'action': 'apply_points_as_coupon',
                'points_to_use': pointsToUse
            },
            success: function (response) {
                // Reload or update the checkout page
                location.reload();
            }
        });
    });
});