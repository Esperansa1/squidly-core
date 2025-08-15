<?php
declare(strict_types=1);

/**
 * Payment Manager
 * 
 * Manages multiple payment gateways and provides centralized payment operations
 */
class PaymentManager
{
    private array $gateways = [];
    private ?string $defaultGateway = null;
    private OrderRepository $orderRepo;

    public function __construct(OrderRepository $orderRepo)
    {
        $this->orderRepo = $orderRepo;
    }

    /**
     * Register a payment gateway
     */
    public function registerGateway(PaymentGatewayInterface $gateway, bool $isDefault = false): void
    {
        $gatewayId = $gateway->getGatewayId();
        $this->gateways[$gatewayId] = $gateway;

        if ($isDefault || $this->defaultGateway === null) {
            $this->defaultGateway = $gatewayId;
        }
    }

    /**
     * Get gateway by ID
     */
    public function getGateway(?string $gatewayId = null): PaymentGatewayInterface
    {
        $gatewayId = $gatewayId ?: $this->defaultGateway;

        if (!$gatewayId || !isset($this->gateways[$gatewayId])) {
            throw PaymentException::gatewayNotConfigured($gatewayId ?: 'unknown');
        }

        return $this->gateways[$gatewayId];
    }

    /**
     * Get all registered gateways
     */
    public function getAvailableGateways(): array
    {
        $gateways = [];
        foreach ($this->gateways as $gatewayId => $gateway) {
            $gateways[$gatewayId] = [
                'id' => $gatewayId,
                'display_name' => $gateway->getDisplayName(),
                'supported_currencies' => $gateway->getSupportedCurrencies(),
                'capabilities' => [
                    'authorize' => $gateway->supportsAuthorization(),
                    'capture' => $gateway->supportsCapture(),
                    'refund' => $gateway->supportsRefunds(),
                    'void' => $gateway->supportsVoid(),
                ],
                'is_default' => $gatewayId === $this->defaultGateway,
            ];
        }
        return $gateways;
    }

    /**
     * Get best gateway for currency
     */
    public function getBestGatewayForCurrency(string $currency): PaymentGatewayInterface
    {
        foreach ($this->gateways as $gateway) {
            if (in_array($currency, $gateway->getSupportedCurrencies())) {
                return $gateway;
            }
        }

        // Fallback to default gateway
        return $this->getGateway();
    }

    /**
     * Create payment service for specific gateway
     */
    public function createPaymentService(?string $gatewayId = null): PaymentService
    {
        $gateway = $this->getGateway($gatewayId);
        return new PaymentService($gateway, $this->orderRepo);
    }

    /**
     * Process payment with automatic gateway selection
     */
    public function processPayment(PaymentRequest $request, array $paymentData, ?string $preferredGateway = null): PaymentResult
    {
        $gateway = $preferredGateway ? $this->getGateway($preferredGateway) : $this->getBestGatewayForCurrency($request->currency);
        $service = new PaymentService($gateway, $this->orderRepo);
        
        return $service->processPayment($request, $paymentData);
    }

    /**
     * Handle webhook from any gateway
     */
    public function handleWebhook(string $gatewayId, array $webhookData): PaymentWebhookResult
    {
        $gateway = $this->getGateway($gatewayId);
        $service = new PaymentService($gateway, $this->orderRepo);
        
        return $service->handleWebhook($webhookData);
    }

    /**
     * Get payment status from any gateway by transaction ID
     */
    public function getPaymentStatus(string $transactionId): PaymentStatus
    {
        // Extract gateway from transaction ID
        $gatewayId = $this->extractGatewayFromTransactionId($transactionId);
        $gateway = $this->getGateway($gatewayId);
        $service = new PaymentService($gateway, $this->orderRepo);
        
        return $service->getPaymentStatus($transactionId);
    }

    /**
     * Capture payment from any gateway
     */
    public function capturePayment(string $transactionId, ?float $amount = null): PaymentResult
    {
        $gatewayId = $this->extractGatewayFromTransactionId($transactionId);
        $gateway = $this->getGateway($gatewayId);
        $service = new PaymentService($gateway, $this->orderRepo);
        
        return $service->capturePayment($transactionId, $amount);
    }

    /**
     * Refund payment from any gateway
     */
    public function refundPayment(string $transactionId, float $amount, string $reason = ''): PaymentResult
    {
        $gatewayId = $this->extractGatewayFromTransactionId($transactionId);
        $gateway = $this->getGateway($gatewayId);
        $service = new PaymentService($gateway, $this->orderRepo);
        
        return $service->refundPayment($transactionId, $amount, $reason);
    }

    /**
     * Void payment from any gateway
     */
    public function voidPayment(string $transactionId): PaymentResult
    {
        $gatewayId = $this->extractGatewayFromTransactionId($transactionId);
        $gateway = $this->getGateway($gatewayId);
        $service = new PaymentService($gateway, $this->orderRepo);
        
        return $service->voidPayment($transactionId);
    }

    /**
     * Set default gateway
     */
    public function setDefaultGateway(string $gatewayId): void
    {
        if (!isset($this->gateways[$gatewayId])) {
            throw PaymentException::gatewayNotConfigured($gatewayId);
        }
        
        $this->defaultGateway = $gatewayId;
    }

    /**
     * Check if gateway exists
     */
    public function hasGateway(string $gatewayId): bool
    {
        return isset($this->gateways[$gatewayId]);
    }

    /**
     * Get gateway count
     */
    public function getGatewayCount(): int
    {
        return count($this->gateways);
    }

    /**
     * Extract gateway ID from transaction ID
     */
    private function extractGatewayFromTransactionId(string $transactionId): ?string
    {
        // Transaction IDs are formatted as "gateway_actualid"
        if (strpos($transactionId, '_') !== false) {
            return explode('_', $transactionId, 2)[0];
        }
        
        // Fallback to default gateway if format is unknown
        return $this->defaultGateway;
    }

    /**
     * Initialize with common gateways based on configuration
     */
    public static function createFromConfig(OrderRepository $orderRepo, array $config = []): self
    {
        $manager = new self($orderRepo);

        // Initialize Stripe if configured
        if (isset($config['stripe']) && !empty($config['stripe']['secret_key'])) {
            $stripeGateway = new StripePaymentGateway($config['stripe']);
            $manager->registerGateway($stripeGateway, $config['stripe']['is_default'] ?? false);
        }

        // Add more gateways here as they are implemented
        // Example:
        // if (isset($config['paypal']) && !empty($config['paypal']['client_id'])) {
        //     $paypalGateway = new PayPalPaymentGateway($config['paypal']);
        //     $manager->registerGateway($paypalGateway, $config['paypal']['is_default'] ?? false);
        // }

        return $manager;
    }

    /**
     * Get default gateway ID
     */
    public function getDefaultGatewayId(): ?string
    {
        return $this->defaultGateway;
    }

    /**
     * Remove gateway
     */
    public function removeGateway(string $gatewayId): void
    {
        if (!isset($this->gateways[$gatewayId])) {
            return;
        }

        unset($this->gateways[$gatewayId]);

        // If we removed the default gateway, set a new default
        if ($this->defaultGateway === $gatewayId) {
            $this->defaultGateway = !empty($this->gateways) ? array_key_first($this->gateways) : null;
        }
    }

    /**
     * Validate gateway configuration
     */
    public function validateGateway(string $gatewayId): array
    {
        $errors = [];
        
        if (!$this->hasGateway($gatewayId)) {
            $errors[] = "Gateway '{$gatewayId}' is not registered";
            return $errors;
        }

        try {
            $gateway = $this->getGateway($gatewayId);
            
            // Basic validation - check if gateway can report its capabilities
            $gateway->getSupportedCurrencies();
            $gateway->getDisplayName();
            
        } catch (PaymentException $e) {
            $errors[] = "Gateway '{$gatewayId}' configuration error: " . $e->getMessage();
        } catch (Exception $e) {
            $errors[] = "Gateway '{$gatewayId}' validation failed: " . $e->getMessage();
        }

        return $errors;
    }
}