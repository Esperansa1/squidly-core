jQuery(document).ready(function($) {
    
    $('.squidly-pay-action').on('click', function(e) {
        e.preventDefault();
        
        const orderId = $(this).data('order-id');
        const nonce = $(this).data('nonce');
        
        const amount = prompt('Enter payment amount:');
        if (!amount || isNaN(amount) || parseFloat(amount) <= 0) {
            alert('Please enter a valid amount');
            return;
        }
        
        $.ajax({
            url: squidly_payment.rest_url + 'start',
            type: 'POST',
            data: {
                order_id: orderId,
                amount: amount
            },
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', squidly_payment.nonce);
            },
            success: function(response) {
                if (response.checkout_url) {
                    window.open(response.checkout_url, '_blank');
                    location.reload();
                } else if (response.error) {
                    alert('Payment failed: ' + response.error);
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                alert('Payment failed: ' + (response?.error || 'Unknown error'));
            }
        });
    });
    
    $('.squidly-refund-action').on('click', function(e) {
        e.preventDefault();
        
        const orderId = $(this).data('order-id');
        const nonce = $(this).data('nonce');
        
        const amount = prompt('Enter refund amount:');
        if (!amount || isNaN(amount) || parseFloat(amount) <= 0) {
            alert('Please enter a valid amount');
            return;
        }
        
        const reason = prompt('Enter refund reason (optional):') || '';
        
        if (!confirm('Are you sure you want to process this refund?')) {
            return;
        }
        
        $.ajax({
            url: squidly_payment.rest_url + 'refund',
            type: 'POST',
            data: {
                order_id: orderId,
                amount: amount,
                reason: reason
            },
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', squidly_payment.nonce);
            },
            success: function(response) {
                if (response.success) {
                    alert('Refund processed successfully: ' + response.message);
                    location.reload();
                } else if (response.error) {
                    alert('Refund failed: ' + response.error);
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                alert('Refund failed: ' + (response?.error || 'Unknown error'));
            }
        });
    });
});