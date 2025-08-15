<?php
declare(strict_types=1);

/**
 * Payment System Integration Tests
 * 
 * Tests the complete payment flow including PaymentManager, PaymentService, and gateway interactions
 */
class PaymentSystemIntegrationTest extends WP_UnitTestCase
{
    private PaymentManager $manager;
    private OrderRepository $orderRepo;
    private CustomerRepository $customerRepo;
    private int $customerId;
    private int $orderId;

    public function setUp(): void
    {
        parent::setUp();
        
        $this->orderRepo = new OrderRepository();
        $this->customerRepo = new CustomerRepository();
        $this->manager = new PaymentManager($this->orderRepo);
        
        // Create test customer
        $this->customerId = $this->customerRepo->create([
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => 'test@example.com',
            'phone' => '+972501234567',
            'auth_provider' => 'phone'
        ]);
        
        // Create test order
        $this->orderId = $this->orderRepo->create([
            'customer_id' => $this->customerId,
            'status' => Order::STATUS_PENDING,
            'total_amount' => 100.50,
            'subtotal' => 90.00,
            'tax_amount' => 8.10,
            'delivery_fee' => 2.40,
            'payment_status' => Order::PAYMENT_PENDING,
            'payment_method' => Order::PAYMENT_ONLINE,
            'notes' => 'Test order for payment integration',
            'order_items' => [
                [
                    'product_id' => 1,
                    'product_name' => 'Test Product',
                    'quantity' => 1,
                    'unit_price' => 90.00,
                    'total_price' => 90.00,
                    'modifications' => []
                ]
            ]
        ]);
    }

    public function test_gateway_registration_and_retrieval(): void
    {
        $mockGateway = $this->createMockGateway('test_gateway', 'Test Gateway');
        
        // Register gateway
        $this->manager->registerGateway($mockGateway, true);
        
        // Verify registration
        $this->assertTrue($this->manager->hasGateway('test_gateway'));
        $this->assertEquals(1, $this->manager->getGatewayCount());
        $this->assertEquals('test_gateway', $this->manager->getDefaultGatewayId());
        
        // Retrieve gateway
        $retrievedGateway = $this->manager->getGateway('test_gateway');
        $this->assertEquals('test_gateway', $retrievedGateway->getGatewayId());
        $this->assertEquals('Test Gateway', $retrievedGateway->getDisplayName());
    }

    public function test_multiple_gateways_management(): void
    {
        $gateway1 = $this->createMockGateway('gateway1', 'Gateway 1');
        $gateway2 = $this->createMockGateway('gateway2', 'Gateway 2');
        
        $this->manager->registerGateway($gateway1, true);
        $this->manager->registerGateway($gateway2, false);
        
        $this->assertEquals(2, $this->manager->getGatewayCount());
        $this->assertEquals('gateway1', $this->manager->getDefaultGatewayId());
        
        // Change default
        $this->manager->setDefaultGateway('gateway2');
        $this->assertEquals('gateway2', $this->manager->getDefaultGatewayId());
        
        // Get available gateways
        $available = $this->manager->getAvailableGateways();
        $this->assertCount(2, $available);
        $this->assertArrayHasKey('gateway1', $available);
        $this->assertArrayHasKey('gateway2', $available);
        $this->assertTrue($available['gateway2']['is_default']);
        $this->assertFalse($available['gateway1']['is_default']);
    }

    public function test_gateway_selection_by_currency(): void
    {
        $usdGateway = $this->createMockGateway('usd_gateway', 'USD Gateway', ['USD']);
        $ilsGateway = $this->createMockGateway('ils_gateway', 'ILS Gateway', ['ILS', 'EUR']);
        
        $this->manager->registerGateway($usdGateway, true);
        $this->manager->registerGateway($ilsGateway, false);
        
        // Test currency-based selection
        $selectedGateway = $this->manager->getBestGatewayForCurrency('ILS');
        $this->assertEquals('ils_gateway', $selectedGateway->getGatewayId());
        
        $selectedGateway = $this->manager->getBestGatewayForCurrency('USD');
        $this->assertEquals('usd_gateway', $selectedGateway->getGatewayId());
        
        // Test fallback to default for unsupported currency
        $selectedGateway = $this->manager->getBestGatewayForCurrency('JPY');
        $this->assertEquals('usd_gateway', $selectedGateway->getGatewayId()); // Should use default
    }

    public function test_payment_request_creation_from_order(): void
    {
        $order = $this->orderRepo->get($this->orderId);
        $customer = $this->customerRepo->get($this->customerId);
        
        $paymentRequest = PaymentRequest::fromOrder($order, $customer);
        
        $this->assertEquals($order->id, $paymentRequest->orderId);
        $this->assertEquals($customer->id, $paymentRequest->customerId);
        $this->assertEquals($order->total_amount, $paymentRequest->amount);
        $this->assertEquals('ILS', $paymentRequest->currency);
        $this->assertEquals($customer->email, $paymentRequest->customerEmail);
        $this->assertEquals($customer->phone, $paymentRequest->customerPhone);
        
        // Validate request
        $errors = $paymentRequest->validate();
        $this->assertEmpty($errors);
    }

    public function test_payment_request_validation(): void
    {
        // Test invalid amount
        $request = new PaymentRequest(
            orderId: 1,
            customerId: 1,
            amount: -10.0,
            currency: '',
            description: 'Test'
        );
        
        $errors = $request->validate();
        $this->assertContains('Payment amount must be greater than 0', $errors);
        $this->assertContains('Currency is required', $errors);
        
        // Test valid request
        $request = new PaymentRequest(
            orderId: 1,
            customerId: 1,
            amount: 50.0,
            currency: 'ILS'
        );
        
        $errors = $request->validate();
        $this->assertEmpty($errors);
    }

    public function test_payment_status_model(): void
    {
        $status = new PaymentStatus(
            transactionId: 'test_123',
            gatewayTransactionId: 'gw_123',
            status: PaymentStatus::STATUS_AUTHORIZED,
            amount: 100.0,
            amountCaptured: 0.0,
            amountRefunded: 0.0
        );
        
        $this->assertTrue($status->isAuthorized());
        $this->assertFalse($status->isCompleted());
        $this->assertFalse($status->hasFailed());
        $this->assertEquals(100.0, $status->getRemainingCaptureAmount());
        $this->assertEquals(0.0, $status->getRemainingRefundAmount());
        
        // Test captured status
        $capturedStatus = new PaymentStatus(
            transactionId: 'test_124',
            gatewayTransactionId: 'gw_124',
            status: PaymentStatus::STATUS_CAPTURED,
            amount: 100.0,
            amountCaptured: 100.0
        );
        
        $this->assertTrue($capturedStatus->isCompleted());
        $this->assertFalse($capturedStatus->isAuthorized());
        $this->assertEquals(0.0, $capturedStatus->getRemainingCaptureAmount());
        $this->assertEquals(100.0, $capturedStatus->getRemainingRefundAmount());
    }

    public function test_payment_webhook_result(): void
    {
        $result = PaymentWebhookResult::success(
            eventType: PaymentWebhookResult::EVENT_PAYMENT_SUCCEEDED,
            data: ['test' => 'data'],
            transactionId: 'test_123',
            gatewayTransactionId: 'gw_123'
        );
        
        $this->assertTrue($result->success);
        $this->assertTrue($result->isPaymentCompleted());
        $this->assertFalse($result->isPaymentFailed());
        
        $result->addAction('order_updated', ['order_id' => 123]);
        $this->assertCount(1, $result->actions);
        $this->assertEquals('order_updated', $result->actions[0]['action']);
        
        // Test failed result
        $failedResult = PaymentWebhookResult::failed(
            eventType: PaymentWebhookResult::EVENT_PAYMENT_FAILED,
            message: 'Payment failed'
        );
        
        $this->assertFalse($failedResult->success);
        $this->assertTrue($failedResult->isPaymentFailed());
        $this->assertFalse($failedResult->isPaymentCompleted());
    }

    public function test_payment_exception_handling(): void
    {
        // Test specific exception types
        $cardDeclined = PaymentException::cardDeclined('Card was declined by bank');
        $this->assertEquals(PaymentException::ERROR_CARD_DECLINED, $cardDeclined->errorCode);
        $this->assertStringContainsString('payment was declined', $cardDeclined->getUserMessage());
        
        $insufficientFunds = PaymentException::insufficientFunds();
        $this->assertEquals(PaymentException::ERROR_INSUFFICIENT_FUNDS, $insufficientFunds->errorCode);
        $this->assertStringContainsString('Insufficient funds', $insufficientFunds->getUserMessage());
        
        $invalidAmount = PaymentException::invalidAmount(-50.0);
        $this->assertEquals(PaymentException::ERROR_INVALID_AMOUNT, $invalidAmount->errorCode);
        
        // Test exception data
        $gatewayError = PaymentException::gatewayError('Gateway timeout', ['code' => 'TIMEOUT']);
        $array = $gatewayError->toArray();
        $this->assertArrayHasKey('error_code', $array);
        $this->assertArrayHasKey('gateway_data', $array);
        $this->assertEquals(['code' => 'TIMEOUT'], $array['gateway_data']);
    }

    public function test_gateway_validation(): void
    {
        $validGateway = $this->createMockGateway('valid_gateway', 'Valid Gateway');
        $this->manager->registerGateway($validGateway);
        
        // Test valid gateway
        $errors = $this->manager->validateGateway('valid_gateway');
        $this->assertEmpty($errors);
        
        // Test non-existent gateway
        $errors = $this->manager->validateGateway('non_existent');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('not registered', $errors[0]);
    }

    public function test_payment_manager_creation_from_config(): void
    {
        $config = [
            'stripe' => [
                'secret_key' => 'sk_test_123',
                'publishable_key' => 'pk_test_123',
                'test_mode' => true,
                'is_default' => true
            ]
        ];
        
        $manager = PaymentManager::createFromConfig($this->orderRepo, $config);
        
        $this->assertEquals(1, $manager->getGatewayCount());
        $this->assertTrue($manager->hasGateway('stripe'));
        $this->assertEquals('stripe', $manager->getDefaultGatewayId());
    }

    /**
     * Create a mock payment gateway for testing
     */
    private function createMockGateway(string $id, string $name, array $currencies = ['ILS', 'USD']): PaymentGatewayInterface
    {
        return new class($id, $name, $currencies) implements PaymentGatewayInterface {
            private string $id;
            private string $name;
            private array $currencies;
            
            public function __construct(string $id, string $name, array $currencies)
            {
                $this->id = $id;
                $this->name = $name;
                $this->currencies = $currencies;
            }
            
            public function getGatewayId(): string { return $this->id; }
            public function getDisplayName(): string { return $this->name; }
            public function getSupportedCurrencies(): array { return $this->currencies; }
            public function supportsAuthorization(): bool { return true; }
            public function supportsCapture(): bool { return true; }
            public function supportsRefunds(): bool { return true; }
            public function supportsVoid(): bool { return true; }
            
            public function createPaymentIntent(PaymentRequest $request): PaymentIntent
            {
                return new PaymentIntent(
                    gatewayId: $this->id,
                    gatewayIntentId: 'intent_' . uniqid(),
                    clientSecret: 'secret_' . uniqid(),
                    amount: $request->amount,
                    currency: $request->currency,
                    status: PaymentStatus::STATUS_CREATED,
                    metadata: $request->metadata
                );
            }
            
            public function processPayment(PaymentIntent $intent, array $paymentData): PaymentResult
            {
                return new PaymentResult(
                    success: true,
                    gatewayId: $this->id,
                    transactionId: $this->id . '_' . $intent->gatewayIntentId,
                    gatewayTransactionId: $intent->gatewayIntentId,
                    amount: $intent->amount,
                    currency: $intent->currency,
                    status: PaymentStatus::STATUS_CAPTURED,
                    message: 'Payment successful'
                );
            }
            
            public function capturePayment(string $transactionId, ?float $amount = null): PaymentResult
            {
                return new PaymentResult(
                    success: true,
                    gatewayId: $this->id,
                    transactionId: $transactionId,
                    gatewayTransactionId: str_replace($this->id . '_', '', $transactionId),
                    amount: $amount ?? 100.0,
                    currency: 'ILS',
                    status: PaymentStatus::STATUS_CAPTURED,
                    message: 'Payment captured'
                );
            }
            
            public function refundPayment(string $transactionId, float $amount, string $reason = ''): PaymentResult
            {
                return new PaymentResult(
                    success: true,
                    gatewayId: $this->id,
                    transactionId: $transactionId,
                    gatewayTransactionId: str_replace($this->id . '_', '', $transactionId),
                    amount: $amount,
                    currency: 'ILS',
                    status: PaymentStatus::STATUS_REFUNDED,
                    message: 'Payment refunded'
                );
            }
            
            public function voidPayment(string $transactionId): PaymentResult
            {
                return new PaymentResult(
                    success: true,
                    gatewayId: $this->id,
                    transactionId: $transactionId,
                    gatewayTransactionId: str_replace($this->id . '_', '', $transactionId),
                    amount: 100.0,
                    currency: 'ILS',
                    status: PaymentStatus::STATUS_VOIDED,
                    message: 'Payment voided'
                );
            }
            
            public function getPaymentStatus(string $transactionId): PaymentStatus
            {
                return new PaymentStatus(
                    transactionId: $transactionId,
                    gatewayTransactionId: str_replace($this->id . '_', '', $transactionId),
                    status: PaymentStatus::STATUS_CAPTURED,
                    amount: 100.0,
                    amountCaptured: 100.0,
                    currency: 'ILS'
                );
            }
            
            public function handleWebhook(array $webhookData): PaymentWebhookResult
            {
                return PaymentWebhookResult::success(
                    eventType: PaymentWebhookResult::EVENT_PAYMENT_SUCCEEDED,
                    data: $webhookData,
                    transactionId: $this->id . '_test_transaction',
                    gatewayTransactionId: 'test_transaction'
                );
            }
        };
    }
}