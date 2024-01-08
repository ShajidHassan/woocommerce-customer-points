jQuery(document).ready(function($) {
    function updateCouponLabels() {
        $('.cart-discount').each(function() {
            var couponLabel = $(this).find('th').text().trim();
            if (couponLabel.startsWith('Coupon: point-')) {
                $(this).find('th').text('Coupon: Point Coupon');
            }
        });
        $('.xoo-wsc-remove-coupon').each(function() {
            var couponLabel = $(this).text().trim();
            if (couponLabel.startsWith('Coupon: point-')) {
                $(this).text('Coupon: Point Coupon');
            }
        });
        $('.xoo-wsc-sl-applied .xoo-wsc-slc-remove').each(function() {
            var couponText = $(this).text().trim();
            var couponCode = couponText.split(' ')[0]; // Extract the coupon code text
        
            if (couponCode.startsWith('point-')) {
                var removeLink = $(this).find('.xoo-wsc-remove-coupon').detach(); 
                $(this).empty().append('Coupon: Point Coupon ').append(removeLink);
            }
        });
        
        
    }

    // Call the function initially
    updateCouponLabels();

    // Use setInterval to continuously check for changes
    setInterval(updateCouponLabels, 500); // Adjust the interval as needed
});
