# Tools Directory

This directory contains administrative and development tools for the Squidly Core plugin.

## Directory Structure

### `/admin/`
Administrative tools and management interfaces:
- `manage-test-data.php` - Central hub for managing test data and accessing debug tools

### `/test-data/`
Test data management scripts:
- `create-full-store-test.php` - Creates comprehensive test data including complex hamburger restaurant products, customers, and orders
- `cleanup-all-test-data.php` - Removes all test data from the system (with safety confirmation)

## Usage

### Test Data Management
1. **Access Hub:** Navigate to `/tools/admin/manage-test-data.php`
2. **Create Test Data:** Use the interface to generate comprehensive test data
3. **Clean Data:** Remove all test data when needed

### Direct Access
Scripts can also be accessed directly:
- Test data creation: `/tools/test-data/create-full-store-test.php`
- Data cleanup: `/tools/test-data/cleanup-all-test-data.php`

## Debug Scripts
Debug utilities are located in `/debug-scripts/` at the plugin root and can be accessed through the management hub.

## Requirements
- WordPress admin privileges
- WooCommerce plugin (for payment-related features)
- Development environment (cleanup scripts delete ALL data)

## Safety Notes
- All scripts require admin privileges
- Cleanup scripts include safety confirmations
- Only use in development environments
- Test data scripts create realistic complex data for thorough testing