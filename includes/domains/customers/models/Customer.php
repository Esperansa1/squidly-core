<?php
declare(strict_types=1);

/**
 * Customer Model
 * 
 * Represents both registered customers and guest customers.
 * Handles authentication via Google or phone verification.
 * Supports loyalty points system and staff management labels.
 */
class Customer
{
    public int $id;
    
    // Personal Information
    public string $email;
    public string $first_name;
    public string $last_name;
    public string $phone;
    
    // Authentication
    public string $auth_provider;    // 'google', 'phone'
    public ?string $google_id;       // Google account ID if auth_provider is 'google'
    public ?DateTime $phone_verified_at;  // When phone was verified
    
    // Addresses
    public array $addresses;         // Address[] objects
    
    // Notifications
    public bool $allow_sms_notifications;
    public bool $allow_email_notifications;
    
    // Order History
    public array $order_ids;         // int[] - list of order IDs for this customer
    public int $total_orders;        // Calculated field for quick access
    public float $total_spent;       // Calculated field for quick access
    public ?DateTime $last_order_date;
    
    // Loyalty System
    public float $loyalty_points_balance;    // Current available points
    public float $lifetime_points_earned;    // Total points ever earned
    
    // Customer Management (for staff)
    public string $staff_labels;     // Free text for staff notes about customer behavior/complaints
    
    // Account Status
    public bool $is_active;
    public DateTime $registration_date;
    
    // Guest Handling
    public bool $is_guest;           // true for guest customers, false for registered

    public function __construct(array $data)
    {
        // Validate required fields first
        $this->validateRequiredFields($data);
        
        $this->id = (int) $data['id'];
        
        // Personal Information (all required)
        $this->first_name = trim((string) $data['first_name']);
        $this->last_name = trim((string) $data['last_name']);
        $this->phone = $this->validateAndFormatPhone((string) $data['phone']);
        $this->email = isset($data['email']) ? $this->validateEmail((string) $data['email']) : '';
        
        // Authentication (required)
        $this->auth_provider = (string) $data['auth_provider'];
        $this->google_id = isset($data['google_id']) ? trim((string) $data['google_id']) : null;
        $this->phone_verified_at = isset($data['phone_verified_at']) 
            ? $this->parseDateTime($data['phone_verified_at']) 
            : null;
        
        // Addresses - convert array data to Address objects
        $this->addresses = [];
        if (isset($data['addresses']) && is_array($data['addresses'])) {
            foreach ($data['addresses'] as $addressData) {
                if ($addressData instanceof Address) {
                    $this->addresses[] = $addressData;
                } else {
                    $this->addresses[] = new Address($addressData);
                }
            }
        }
        
        // Notifications (with validation)
        $this->allow_sms_notifications = $this->validateBoolean($data['allow_sms_notifications'] ?? false, 'allow_sms_notifications');
        $this->allow_email_notifications = $this->validateBoolean($data['allow_email_notifications'] ?? false, 'allow_email_notifications');
        
        // Order History (with validation)
        $this->order_ids = $this->validateOrderIds($data['order_ids'] ?? []);
        $this->total_orders = $this->validateNonNegativeInt($data['total_orders'] ?? 0, 'total_orders');
        $this->total_spent = $this->validateNonNegativeFloat($data['total_spent'] ?? 0.0, 'total_spent');
        $this->last_order_date = isset($data['last_order_date']) 
            ? $this->parseDateTime($data['last_order_date']) 
            : null;
        
        // Loyalty System (with validation)
        $this->loyalty_points_balance = $this->validateNonNegativeFloat($data['loyalty_points_balance'] ?? 0.0, 'loyalty_points_balance');
        $this->lifetime_points_earned = $this->validateNonNegativeFloat($data['lifetime_points_earned'] ?? 0.0, 'lifetime_points_earned');
        
        // Customer Management
        $this->staff_labels = (string) ($data['staff_labels'] ?? '');
        
        // Account Status
        $this->is_active = $this->validateBoolean($data['is_active'] ?? true, 'is_active');
        $this->registration_date = isset($data['registration_date']) 
            ? $this->parseDateTime($data['registration_date']) 
            : new DateTime();
        
        // Guest Handling
        $this->is_guest = $this->validateBoolean($data['is_guest'] ?? false, 'is_guest');
        
        // Cross-field validation
        $this->validateAuthProvider();
        $this->validateBusinessRules();
    }

    /**
     * Get customer's full name
     */
    public function getFullName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Get customer's primary/default address
     */
    public function getPrimaryAddress(): ?Address
    {
        foreach ($this->addresses as $address) {
            if ($address->is_default) {
                return $address;
            }
        }
        
        // If no default set, return first address
        return $this->addresses[0] ?? null;
    }

    /**
     * Add a new address to customer
     */
    public function addAddress(Address $address): void
    {
        // If this is the first address, make it default
        if (empty($this->addresses)) {
            $address->is_default = true;
        }
        
        // If new address is set as default, unset others
        if ($address->is_default) {
            foreach ($this->addresses as $existingAddress) {
                $existingAddress->is_default = false;
            }
        }
        
        $this->addresses[] = $address;
    }

    /**
     * Check if customer can earn loyalty points
     */
    public function canEarnLoyaltyPoints(): bool
    {
        return !$this->is_guest && $this->is_active;
    }

    /**
     * Add loyalty points to customer balance
     */
    public function addLoyaltyPoints(float $points): void
    {
        if ($points <= 0) {
            throw new InvalidArgumentException('Points to add must be positive');
        }
        
        if (!$this->canEarnLoyaltyPoints()) {
            throw new InvalidArgumentException('Customer cannot earn loyalty points (guest or inactive)');
        }
        
        $this->loyalty_points_balance += $points;
        $this->lifetime_points_earned += $points;
    }

    /**
     * Use loyalty points (deduct from balance)
     */
    public function useLoyaltyPoints(float $points): bool
    {
        if ($points <= 0) {
            throw new InvalidArgumentException('Points to use must be positive');
        }
        
        if ($points > $this->loyalty_points_balance) {
            throw new InvalidArgumentException('Insufficient loyalty points balance');
        }
        
        $this->loyalty_points_balance -= $points;
        return true;
    }

    /**
     * Check if customer is authenticated properly
     */
    public function isAuthenticated(): bool
    {
        switch ($this->auth_provider) {
            case 'google':
                return !empty($this->google_id);
            case 'phone':
                return $this->phone_verified_at !== null;
            default:
                return false;
        }
    }

    /**
     * Check if customer has verified contact information
     */
    public function hasVerifiedContact(): bool
    {
        return $this->isAuthenticated() && !empty($this->phone);
    }

    /**
     * Add staff label/note about customer
     */
    public function addStaffLabel(string $label): void
    {
        $label = trim($label);
        
        if (empty($label)) {
            throw new InvalidArgumentException('Staff label cannot be empty');
        }
        
        if (strlen($label) > 500) {
            throw new InvalidArgumentException('Staff label cannot exceed 500 characters');
        }
        
        if (!empty($this->staff_labels)) {
            $this->staff_labels .= "\n";
        }
        $this->staff_labels .= date('Y-m-d H:i:s') . ': ' . $label;
    }

    /**
     * Update order statistics after a new order
     */
    public function updateOrderStats(int $orderId, float $orderTotal): void
    {
        if ($orderId <= 0) {
            throw new InvalidArgumentException('Order ID must be positive');
        }
        
        if ($orderTotal < 0) {
            throw new InvalidArgumentException('Order total cannot be negative');
        }
        
        if (in_array($orderId, $this->order_ids, true)) {
            throw new InvalidArgumentException('Order ID already exists in customer history');
        }
        
        $this->order_ids[] = $orderId;
        $this->total_orders++;
        $this->total_spent += $orderTotal;
        $this->last_order_date = new DateTime();
    }

    /**
     * Convert guest customer to registered customer
     */
    public function convertToRegistered(string $email, string $authProvider, ?string $googleId = null): void
    {
        if (!$this->is_guest) {
            throw new InvalidArgumentException('Customer is already registered');
        }
        
        $email = $this->validateEmail($email);
        if (empty($email)) {
            throw new InvalidArgumentException('Valid email is required for registration');
        }
        
        if (!in_array($authProvider, ['google', 'phone'], true)) {
            throw new InvalidArgumentException('Invalid auth provider for registration');
        }
        
        if ($authProvider === 'google' && empty($googleId)) {
            throw new InvalidArgumentException('Google ID is required for Google registration');
        }
        
        $this->is_guest = false;
        $this->email = $email;
        $this->auth_provider = $authProvider;
        $this->google_id = $googleId;
        
        if ($authProvider === 'phone') {
            $this->phone_verified_at = new DateTime();
        }
        
        $this->validateAuthProvider();
    }

    /**
     * Convert to array for storage/API responses
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'auth_provider' => $this->auth_provider,
            'google_id' => $this->google_id,
            'phone_verified_at' => $this->phone_verified_at?->format('Y-m-d H:i:s'),
            'addresses' => array_map(fn(Address $addr) => $addr->toArray(), $this->addresses),
            'allow_sms_notifications' => $this->allow_sms_notifications,
            'allow_email_notifications' => $this->allow_email_notifications,
            'order_ids' => $this->order_ids,
            'total_orders' => $this->total_orders,
            'total_spent' => $this->total_spent,
            'last_order_date' => $this->last_order_date?->format('Y-m-d H:i:s'),
            'loyalty_points_balance' => $this->loyalty_points_balance,
            'lifetime_points_earned' => $this->lifetime_points_earned,
            'staff_labels' => $this->staff_labels,
            'is_active' => $this->is_active,
            'registration_date' => $this->registration_date->format('Y-m-d H:i:s'),
            'is_guest' => $this->is_guest,
        ];
    }

    /**
     * Create a customer snapshot for order storage
     * This captures customer info at time of order for data integrity
     */
    public function createOrderSnapshot(): array
    {
        return [
            'customer_id' => $this->id,
            'customer_name' => $this->getFullName(),
            'customer_email' => $this->email,
            'customer_phone' => $this->phone,
            'is_guest' => $this->is_guest,
            'snapshot_created_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Validate required fields are present
     */
    private function validateRequiredFields(array $data): void
    {
        $requiredFields = ['first_name', 'last_name', 'auth_provider']; // Phone Validation is under validateAndFormatPhone
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                throw new InvalidArgumentException("Required field '{$field}' is missing or empty");
            }
        }
        
        // ID is required and must be non-negative integer
        if (!isset($data['id']) || !is_numeric($data['id']) || (int) $data['id'] < 0) {
            throw new InvalidArgumentException('ID is required and must be a non-negative integer');
        }
    }

    /**
     * Validate and format phone number
     */
    private function validateAndFormatPhone(string $phone): string
    {
        $phone = trim($phone);
        
        if (empty($phone)) {
            throw new InvalidArgumentException('Phone number cannot be empty');
        }
        
        // Remove all non-digit characters except +
        $cleanPhone = preg_replace('/[^\d+]/', '', $phone);
        
        if (empty($cleanPhone)) {
            throw new InvalidArgumentException('Phone number must contain digits');
        }
        
        // Israeli phone number validation
        if (str_starts_with($cleanPhone, '+972')) {
            // Full international format
            if (strlen($cleanPhone) !== 13) {
                throw new InvalidArgumentException('Israeli phone number with +972 must be 13 digits total');
            }
        } elseif (str_starts_with($cleanPhone, '0')) {
            // Local format starting with 0
            if (strlen($cleanPhone) !== 10) {
                throw new InvalidArgumentException('Israeli phone number starting with 0 must be 10 digits');
            }
            // Convert to international format
            $cleanPhone = '+972' . substr($cleanPhone, 1);
        } else {
            throw new InvalidArgumentException('Phone number must start with +972 or 0 for Israeli numbers');
        }
        
        return $cleanPhone;
    }

    /**
     * Validate email format
     */
    private function validateEmail(string $email): string
    {
        $email = trim($email);
        
        if (empty($email)) {
            return ''; // Email is optional for some cases
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }
        
        return strtolower($email);
    }

    /**
     * Validate boolean values
     */
    private function validateBoolean($value, string $fieldName): bool
    {
        if (!is_bool($value) && !in_array($value, [0, 1, '0', '1', 'true', 'false'], true)) {
            throw new InvalidArgumentException("Field '{$fieldName}' must be a boolean value");
        }
        
        return (bool) $value;
    }

    /**
     * Validate non-negative integer
     */
    private function validateNonNegativeInt($value, string $fieldName): int
    {
        if (!is_numeric($value) || (int) $value < 0) {
            throw new InvalidArgumentException("Field '{$fieldName}' must be a non-negative integer");
        }
        
        return (int) $value;
    }

    /**
     * Validate non-negative float
     */
    private function validateNonNegativeFloat($value, string $fieldName): float
    {
        if (!is_numeric($value) || (float) $value < 0) {
            throw new InvalidArgumentException("Field '{$fieldName}' must be a non-negative number");
        }
        
        return (float) $value;
    }

    /**
     * Validate order IDs array
     */
    private function validateOrderIds(array $orderIds): array
    {
        if (!is_array($orderIds)) {
            throw new InvalidArgumentException('order_ids must be an array');
        }
        
        foreach ($orderIds as $orderId) {
            if (!is_numeric($orderId) || (int) $orderId <= 0) {
                throw new InvalidArgumentException('All order IDs must be positive integers');
            }
        }
        
        return array_map('intval', $orderIds);
    }

    /**
     * Parse datetime from various formats
     */
    private function parseDateTime($value): DateTime
    {
        if ($value instanceof DateTime) {
            return $value;
        }
        
        try {
            return new DateTime((string) $value);
        } catch (Exception $e) {
            throw new InvalidArgumentException("Invalid datetime format: {$value}");
        }
    }

    /**
     * Validate authentication provider and related fields
     */
    private function validateAuthProvider(): void
    {
        $validProviders = ['google', 'phone'];
        
        if (!in_array($this->auth_provider, $validProviders, true)) {
            throw new InvalidArgumentException('auth_provider must be one of: ' . implode(', ', $validProviders));
        }
    }

    /**
     * Validate business rules and data consistency
     */
    private function validateBusinessRules(): void
    {
        // Guest customers validation
        if ($this->is_guest) {
            if ($this->loyalty_points_balance > 0) {
                throw new InvalidArgumentException('Guest customers cannot have loyalty points balance');
            }
            
            if ($this->lifetime_points_earned > 0) {
                throw new InvalidArgumentException('Guest customers cannot have earned loyalty points');
            }
            
            if (!empty($this->order_ids)) {
                throw new InvalidArgumentException('Guest customers cannot have persistent order history');
            }
        }
        
        // Loyalty points validation
        if ($this->loyalty_points_balance > $this->lifetime_points_earned) {
            throw new InvalidArgumentException('Current points balance cannot exceed lifetime points earned');
        }
        
        // Order statistics consistency
        if ($this->total_orders !== count($this->order_ids)) {
            throw new InvalidArgumentException('total_orders must match the count of order_ids array');
        }
        
        if ($this->total_orders > 0 && $this->total_spent <= 0) {
            throw new InvalidArgumentException('total_spent must be positive when total_orders > 0');
        }
        
        if ($this->total_orders > 0 && $this->last_order_date === null) {
            throw new InvalidArgumentException('last_order_date is required when customer has orders');
        }
        
        // Name validation
        if (strlen($this->first_name) < 1 || strlen($this->first_name) > 50) {
            throw new InvalidArgumentException('First name must be between 1 and 50 characters');
        }
        
        if (strlen($this->last_name) < 1 || strlen($this->last_name) > 50) {
            throw new InvalidArgumentException('Last name must be between 1 and 50 characters');
        }
        
        // Registration date validation
        if ($this->registration_date > new DateTime()) {
            throw new InvalidArgumentException('Registration date cannot be in the future');
        }
    }
}