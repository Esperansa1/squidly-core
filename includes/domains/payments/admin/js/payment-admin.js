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
        console.log('REST URL:', squidly_payment.rest_url + 'start');
        console.log('Nonce:', squidly_payment.nonce);
        
        $.ajax({
            url: squidly_payment.rest_url + 'start',
            type: 'POST',
            data: {
                order_id: parseInt(orderId),
                amount: amount
            },
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', squidly_payment.nonce);
                console.log('Request headers set, sending request...');
            },
            success: function(response) {
                console.log('Payment request successful:', response);
                if (response.checkout_url) {
                    console.log('Opening checkout URL:', response.checkout_url);
                    window.open(response.checkout_url, '_blank');
                    location.reload();
                } else if (response.error) {
                    console.log('Payment error from server:', response.error);
                    alert('Payment failed: ' + response.error);
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
            url: squidly_payment.rest_url + 'refund',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                order_id: parseInt(orderId),
                amount: amount,
                reason: reason
            }),
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