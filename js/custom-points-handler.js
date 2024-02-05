jQuery(document).ready(function($) {
    // Function to handle adding or subtracting points
    function handlePointsChange(addPoints, subtractPoints, orderId) {
        var data = {
            action: 'handle_points_change',
            security: ajax_object.security,
            points_to_add: addPoints,
            points_to_subtract: subtractPoints,
            order_id: orderId
        };

        // Disable both buttons
        $('#add_points_button').prop('disabled', true);
        $('#subtract_points_button').prop('disabled', true);

        $.ajax({
            type: 'POST',
            url: ajax_object.ajax_url,
            data: data,
            success: function(response) {
                // Handle success response
                console.log(response.data);
                // Reload the page or perform any other action after successful points change
                location.reload();
            },
            error: function(errorThrown) {
                // Handle error
                console.error('Points change failed: ' + errorThrown);
                alert('Points change failed: ' + errorThrown);
                // Re-enable both buttons
                $('#add_points_button').prop('disabled', false);
                $('#subtract_points_button').prop('disabled', false);
            }
        });
    }

    // Example: On button click (Add Points)
    $('#add_points_button').on('click', function() {
        var addPoints = parseInt($('#points_to_add').val());
        var subtractPoints = 0; // Default to 0 for subtraction
        var orderId = parseInt($('#order_id').val());
        handlePointsChange(addPoints, subtractPoints, orderId);
    });

    // Example: On button click (Subtract Points)
    $('#subtract_points_button').on('click', function() {
        var addPoints = 0; // Default to 0 for addition
        var subtractPoints = parseInt($('#points_to_subtract').val());
        var orderId = parseInt($('#order_id').val());
        handlePointsChange(addPoints, subtractPoints, orderId);
    });
});
