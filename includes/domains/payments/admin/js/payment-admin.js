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
        
        console.log('Starting payment request for order:', orderId, 'amount:', amount);
        console.log('AJAX URL:', squidly_payment.ajax_url);
        console.log('Nonce:', squidly_payment.nonce);
        
        $.ajax({
            url: squidly_payment.ajax_url,
            type: 'POST',
            data: {
                action: 'squidly_start_payment',
                order_id: parseInt(orderId),
                amount: amount,
                nonce: squidly_payment.nonce
            },
            success: function(response) {
                console.log('Payment request successful:', response);
                if (response.success && response.data.checkout_url) {
                    console.log('Opening checkout URL:', response.data.checkout_url);
                    window.open(response.data.checkout_url, '_blank');
                    location.reload();
                } else if (!response.success && response.data) {
                    console.log('Payment error from server:', response.data);
                    alert('Payment failed: ' + response.data);
                } else {
                    console.log('Unexpected response format:', response);
                    alert('Unexpected response format');
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX Error Details:');
                console.log('- Status:', xhr.status);
                console.log('- Status Text:', xhr.statusText);
                console.log('- Response Text:', xhr.responseText);
                console.log('- Response JSON:', xhr.responseJSON);
                console.log('- jQuery Status:', status);
                console.log('- jQuery Error:', error);
                
                const response = xhr.responseJSON;
                let errorMsg = 'Unknown error';
                
                if (response && response.error) {
                    errorMsg = response.error;
                } else if (xhr.status === 403) {
                    errorMsg = 'Permission denied - check nonce';
                } else if (xhr.status === 404) {
                    errorMsg = 'API endpoint not found';
                } else if (xhr.status === 500) {
                    errorMsg = 'Server error';
                } else if (xhr.responseText) {
                    errorMsg = 'Server response: ' + xhr.responseText.substring(0, 100);
                }
                
                alert('Payment failed: ' + errorMsg);
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
            url: squidly_payment.ajax_url,
            type: 'POST',
            data: {
                action: 'squidly_refund_payment',
                order_id: parseInt(orderId),
                amount: amount,
                reason: reason,
                nonce: squidly_payment.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Refund processed successfully: ' + response.data.message);
                    location.reload();
                } else if (!response.success && response.data) {
                    alert('Refund failed: ' + response.data);
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                alert('Refund failed: ' + (response?.error || 'Unknown error'));
            }
        });
    });
});