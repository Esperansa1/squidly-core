<?php
/**
 * Autoload Class Map
 *
 * Pre-computed mapping of class names to file paths for fast O(1) lookups.
 * This eliminates the need for multiple file_exists() checks during autoloading.
 *
 * Generated for Squidly Core plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

return [
    // Shared - Interfaces
    'RepositoryInterface' => 'includes/shared/interfaces/RepositoryInterface.php',
    'PostTypeInterface' => 'includes/shared/interfaces/PostTypeInterface.php',

    // Shared - Abstracts
    'BasePostType' => 'includes/shared/abstracts/BasePostType.php',

    // Shared - Exceptions
    'ResourceInUseException' => 'includes/shared/exceptions/ResourceInUseException.php',

    // Shared - Models
    'Address' => 'includes/shared/models/Address.php',

    // Core
    'PostTypeRegistry' => 'includes/core/PostTypeRegistry.php',

    // Admin
    'AdminMenuManager' => 'includes/admin/AdminMenuManager.php',
    'AdminPageHandler' => 'includes/admin/AdminPageHandler.php',
    'CustomerPageHandler' => 'includes/admin/CustomerPageHandler.php',

    // Domains - Stores
    'StoreBranch' => 'includes/domains/stores/models/StoreBranch.php',
    'StoreBranchRepository' => 'includes/domains/stores/repositories/StoreBranchRepository.php',
    'StoreBranchPostType' => 'includes/domains/stores/post-types/StoreBranchPostType.php',
    'StoreBranchRestController' => 'includes/domains/stores/rest/StoreBranchRestController.php',
    'PublicBranchRestController' => 'includes/domains/stores/rest/PublicBranchRestController.php',

    // Domains - Products
    'Product' => 'includes/domains/products/models/Product.php',
    'ProductGroup' => 'includes/domains/products/models/ProductGroup.php',
    'GroupItem' => 'includes/domains/products/models/GroupItem.php',
    'Ingredient' => 'includes/domains/products/models/Ingredient.php',
    'IngredientGroup' => 'includes/domains/products/models/IngredientGroup.php',
    'ProductRepository' => 'includes/domains/products/repositories/ProductRepository.php',
    'ProductGroupRepository' => 'includes/domains/products/repositories/ProductGroupRepository.php',
    'GroupItemRepository' => 'includes/domains/products/repositories/GroupItemRepository.php',
    'IngredientRepository' => 'includes/domains/products/repositories/IngredientRepository.php',
    'IngredientGroupRepository' => 'includes/domains/products/repositories/IngredientGroupRepository.php',
    'ProductPostType' => 'includes/domains/products/post-types/ProductPostType.php',
    'ProductGroupPostType' => 'includes/domains/products/post-types/ProductGroupPostType.php',
    'GroupItemPostType' => 'includes/domains/products/post-types/GroupItemPostType.php',
    'IngredientPostType' => 'includes/domains/products/post-types/IngredientPostType.php',
    'IngredientGroupPostType' => 'includes/domains/products/post-types/IngredientGroupPostType.php',
    'ProductGroupRestController' => 'includes/domains/products/rest/ProductGroupRestController.php',
    'IngredientRestController' => 'includes/domains/products/rest/IngredientRestController.php',
    'IngredientGroupRestController' => 'includes/domains/products/rest/IngredientGroupRestController.php',
    'ProductRestController' => 'includes/domains/products/rest/ProductRestController.php',
    'PublicProductRestController' => 'includes/domains/products/rest/PublicProductRestController.php',

    // Domains - Customers
    'Customer' => 'includes/domains/customers/models/Customer.php',
    'CustomerRepository' => 'includes/domains/customers/repositories/CustomerRepository.php',
    'CustomerPostType' => 'includes/domains/customers/post-types/CustomerPostType.php',
    'CustomerRestController' => 'includes/domains/customers/rest/CustomerRestController.php',
    'GuestCustomerRestController' => 'includes/domains/customers/rest/GuestCustomerRestController.php',
    'GuestCleanupCron' => 'includes/domains/customers/services/GuestCleanupCron.php',

    // Domains - Orders
    'Order' => 'includes/domains/orders/models/Order.php',
    'OrderItem' => 'includes/domains/orders/models/OrderItem.php',
    'OrderRepository' => 'includes/domains/orders/repositories/OrderRepository.php',
    'OrderPostType' => 'includes/domains/orders/post-types/OrderPostType.php',
    'OrderRestController' => 'includes/domains/orders/rest/OrderRestController.php',
    'PublicOrderRestController' => 'includes/domains/orders/rest/PublicOrderRestController.php',
    'OrderStatisticsRestController' => 'includes/domains/orders/rest/OrderStatisticsRestController.php',

    // Domains - Payments
    'PaymentBootstrap' => 'includes/domains/payments/PaymentBootstrap.php',
    'PaymentProvider' => 'includes/domains/payments/interfaces/PaymentProvider.php',
    'WooProvider' => 'includes/domains/payments/providers/WooProvider.php',
    'PaymentService' => 'includes/domains/payments/services/PaymentService.php',
    'PaymentStatusSync' => 'includes/domains/payments/services/PaymentStatusSync.php',
    'PaymentRestController' => 'includes/domains/payments/rest/PaymentRestController.php',

    // API
    'AdminApiBootstrap' => 'includes/api/AdminApiBootstrap.php',
    'ConfigRestController' => 'includes/api/ConfigRestController.php',
];
