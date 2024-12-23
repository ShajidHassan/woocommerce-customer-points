jQuery(document).ready(function($) {
    // Function to handle adding or subtracting points
    function handlePointsChange(addPoints, subtractPoints, addReason, subtractReason, pointsReason, orderId) {
        var data = {
            action: 'handle_points_change',
            security: ajax_object.security,
            points_to_add: addPoints,
            points_to_subtract: subtractPoints,
            points_to_add_reason: addReason, // Include the reason for adding points
            points_to_subtract_reason: subtractReason, // Include the reason for subtracting points
            points_reason: pointsReason,
            order_id: orderId
        };

        // Disable both buttons
        $('#add_points_button').prop('disabled', true);
        $('#subtract_points_button').prop('disabled', true);

        // Log the data object to the console for debugging
        console.log('Data:', data);

        $.ajax({
            type: 'POST',
            url: ajax_object.ajax_url,
            data: data,
            success: function(response) {
                // Handle success response
                console.log('Success:', response);
                // Reload the page or perform any other action after successful points change
                location.reload();
            },
            error: function(jqXHR, textStatus, errorThrown) {
                // Handle error
                console.error('AJAX request failed:', textStatus, errorThrown, jqXHR.responseText);
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
        var addReason = $('#points_to_add_reason').val(); // Get the reason for adding points
        var pointsReason = $('#points_reason').val();
        var orderId = parseInt($('#order_id').val());
        handlePointsChange(addPoints, subtractPoints, addReason, '', pointsReason, orderId);
    });

    // Example: On button click (Subtract Points)
    $('#subtract_points_button').on('click', function() {
        var addPoints = 0; // Default to 0 for addition
        var subtractPoints = parseInt($('#points_to_subtract').val());
        var subtractReason = $('#points_to_subtract_reason').val(); // Get the reason for subtracting points
        var pointsReason = $('#points_reason').val();
        var orderId = parseInt($('#order_id').val());
        handlePointsChange(addPoints, subtractPoints, '', subtractReason, pointsReason, orderId);
    });
});
