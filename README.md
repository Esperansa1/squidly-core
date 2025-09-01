# Squidly Core WordPress Plugin

A comprehensive restaurant management system with advanced product customization, payment integration, and order management.

## Project Structure

### Core Architecture
- **Ingredient and Products** are the main structures
- **GroupItem** is an abstraction of Ingredient/Product
- **ProductGroup** is a collection of GroupItems (Ingredient/Product)
- **Product** can have multiple ProductGroups
- **Price overrides** can be set on GroupItem level
- **Repository files** handle all database access for their corresponding models
- **RepositoryInterface** sets the baseline for all repositories

### Directory Structure

```
squidly-core/
├── includes/           # Core plugin functionality
│   ├── domains/        # Domain-driven design structure
│   │   ├── customers/  # Customer management
│   │   ├── orders/     # Order processing and management
│   │   ├── payments/   # Payment gateway integration
│   │   ├── products/   # Product and ingredient management
│   │   └── stores/     # Store branch management
│   └── shared/         # Shared utilities and interfaces
├── tools/              # Administrative and development tools
│   ├── admin/          # Management interfaces
│   └── test-data/      # Test data generation and cleanup
├── debug-scripts/      # Development debugging utilities
├── tests/              # Automated test suite
└── assets/             # Static assets (CSS, JS, images)
```

## Features

### 🍔 Complex Product Management
- Multi-level product customization with Product Groups
- Ingredient management with price overrides
- Hamburger restaurant-style product configuration
- Support for modifications and special instructions

### 💳 Payment Integration
- WooCommerce gateway integration
- Bi-directional order synchronization
- Multiple payment methods (cash, card, online)
- Automatic payment status updates

### 📦 Order Management
- Complete order lifecycle tracking
- Customer preferences and dietary requirements
- Delivery and pickup options
- Kitchen workflow integration

### 🏪 Multi-Store Support
- Store branch management
- Location-specific product availability
- Individual branch settings and hours

## Tools and Administration

### Management Hub
Access the central management interface at:
`/wp-content/plugins/squidly-core/tools/admin/manage-test-data.php`

### Test Data Management
- **Create Test Data**: Generate comprehensive restaurant data with complex products
- **Cleanup Data**: Remove all test data with safety confirmations
- **Debug Tools**: Access various debugging utilities

### Debug Scripts
Available debugging tools:
- Payment system debugging and API testing
- Order data inspection
- Payment product creation and validation

## Development

### Testing
- Comprehensive test suite in `/tests/`
- Integration tests for payment flows
- Unit tests for all major components

### Requirements
- WordPress 5.0+
- PHP 7.4+
- WooCommerce (for payment features)
- MySQL 5.7+ or MariaDB 10.2+

### Getting Started
1. Install and activate the plugin
2. Ensure WooCommerce is installed and activated
3. Use the management hub to create test data
4. Configure payment settings as needed

## Production Notes
- All debug and test tools require admin privileges
- Test data scripts should only be used in development
- Payment integration requires proper WooCommerce configuration
- Regular backups recommended before using cleanup scripts

## Architecture Principles
- Domain-driven design with clear separation of concerns
- Repository pattern for data access abstraction
- Interface-based design for extensibility
- WordPress best practices and security standards