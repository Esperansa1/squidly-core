# Debug Scripts

This folder contains various debug and testing scripts used during development.

## Scripts Overview

### Payment System Debug Scripts
- `debug-payment.php` - Payment system debugging utilities
- `debug-payment-api.php` - API endpoint testing for payments
- `debug-pay-button.php` - Pay button functionality testing
- `test-payment-setup.php` - Payment setup validation

### Data Creation & Testing
- `create-payment-product.php` - Creates WooCommerce payment product
- `check-orders.php` - Order data inspection utility

### System Testing
- `test-payment-setup.php` - Complete payment system validation

## Usage

These scripts are for development and debugging purposes only. They should not be used in production environments.

Most scripts require admin privileges and should be accessed directly via browser:
```
/wp-content/plugins/squidly-core/debug-scripts/[script-name].php
```

## Main Test Scripts

For comprehensive testing, use the main scripts in the plugin root:
- `create-full-store-test.php` - Creates complete test data with complex products
- `cleanup-all-test-data.php` - Removes all test data from the system