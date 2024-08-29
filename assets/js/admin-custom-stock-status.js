(function ($) {
    $(document).ready(function () {
        $('.accordion-toggle').on('click', function (e) {
            e.preventDefault();
            var $content = $(this).next('.accordion-content');
            if ($content.is(':visible')) {
                $content.slideUp();
                $(this).text('+');
            } else {
                $content.slideDown();
                $(this).text('-');
            }
        });

        $('.variation-stock-status-dropdown').on('change', function () {
            var variationId = $(this).data('variation-id');
            var newStatus = $(this).val();
            var security = admin_custom_stock_status_script.security;

            $.ajax({
                url: admin_custom_stock_status_script.ajaxurl,
                method: 'POST',
                data: {
                    action: 'update_variation_stock_status',
                    variation_id: variationId,
                    new_status: newStatus,
                    security: security
                },
                success: function (response) {
                    if (response.success) {
                        showToast('Success', response.data.message);
                    } else {
                        showToast('Error', response.data.message);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error: ', status, error);
                    alert('There was an error updating the stock status.');
                }
            });
        });
    });

    function showToast(title, message) {
        // Implementation of a toast notification
        alert(title + ": " + message);
    }
})(jQuery);
