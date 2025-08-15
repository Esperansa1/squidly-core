<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Payment Service Unit Tests
 * 
 * Tests PaymentService business logic in isolation using mocked dependencies
 */
class PaymentServiceTest extends TestCase
{
    private PaymentService $service;
    private MockObject $mockGateway;
    private MockObject $mockOrderRepo;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockGateway = $this->createMock(PaymentGatewayInterface::class);
        $this->mockOrderRepo = $this->createMock(OrderRepository::class);
        
        $this->service = new PaymentService($this->mockGateway, $this->mockOrderRepo);
    }

    public function test_successful_payment_processing(): void
    {
        $request = new PaymentRequest(
            orderId: 123,
            customerId: 456,
            amount: 100.50,
            currency: 'ILS'
        );
        
        $paymentData = ['card_token' => 'tok_123'];
        
        $mockIntent = new PaymentIntent(
            gatewayId: 'test_gateway',
            gatewayIntentId: 'intent_123',
            clientSecret: 'secret_123',
            amount: 100.50,
            currency: 'ILS',
            status: PaymentStatus::STATUS_CREATED,
            metadata: []
        );
        
        $mockResult = new PaymentResult(
            success: true,
            gatewayId: 'test_gateway',
            transactionId: 'txn_123',
            gatewayTransactionId: 'intent_123',
            amount: 100.50,
            currency: 'ILS',
            status: PaymentStatus::STATUS_CAPTURED,
            message: 'Payment successful'
        );
        
        $mockOrder = new Order();
        $mockOrder->id = 123;
        $mockOrder->customer_id = 456;
        $mockOrder->status = Order::STATUS_PENDING;
        $mockOrder->order_date = date('Y-m-d H:i:s');
        $mockOrder->total_amount = 100.50;
        $mockOrder->subtotal = 90.00;
        $mockOrder->tax_amount = 8.10;
        $mockOrder->delivery_fee = 2.40;
        $mockOrder->payment_status = Order::PAYMENT_PENDING;
        $mockOrder->payment_method = '';
        $mockOrder->gateway_transaction_id = null;
        $mockOrder->notes = '';
        $mockOrder->order_items = [];
        $mockOrder->delivery_address = null;
        $mockOrder->pickup_time = null;
        $mockOrder->special_instructions = null;
        
        // Configure mock expectations
        $this->mockGateway->expects($this->once())
            ->method('createPaymentIntent')
            ->with($request)
            ->willReturn($mockIntent);
            
        $this->mockGateway->expects($this->once())
            ->method('processPayment')
            ->with($mockIntent, $paymentData)
            ->willReturn($mockResult);
            
        $this->mockOrderRepo->expects($this->once())
            ->method('get')
            ->with(123)
            ->willReturn($mockOrder);
            
        $this->mockOrderRepo->expects($this->once())
            ->method('update')
            ->with(123, $this->callback(function($data) {
                return $data['status'] === Order::STATUS_CONFIRMED &&
                       $data['payment_status'] === Order::PAYMENT_PAID &&
                       $data['payment_method'] === 'test_gateway' &&
                       $data['gateway_transaction_id'] === 'intent_123';
            }));
        
        // Execute
        $result = $this->service->processPayment($request, $paymentData);
        
        // Assert
        $this->assertTrue($result->success);
        $this->assertEquals('txn_123', $result->transactionId);
        $this->assertEquals(PaymentStatus::STATUS_CAPTURED, $result->status);
    }

    public function test_payment_processing_with_authorization_only(): void
    {
        $request = new PaymentRequest(
            orderId: 123,
            customerId: 456,
            amount: 100.50,
            currency: 'ILS',
            captureImmediately: false
        );
        
        $mockIntent = new PaymentIntent(
            gatewayId: 'test_gateway',
            gatewayIntentId: 'intent_123',
            clientSecret: 'secret_123',
            amount: 100.50,
            currency: 'ILS',
            status: PaymentStatus::STATUS_CREATED,
            metadata: []
        );
        
        $mockResult = new PaymentResult(
            success: true,
            gatewayId: 'test_gateway',
            transactionId: 'txn_123',
            gatewayTransactionId: 'intent_123',
            amount: 100.50,
            currency: 'ILS',
            status: PaymentStatus::STATUS_AUTHORIZED,
            message: 'Payment authorized'
        );
        
        $mockOrder = new Order();
        $mockOrder->id = 123;
        $mockOrder->customer_id = 456;
        $mockOrder->status = Order::STATUS_PENDING;
        $mockOrder->order_date = date('Y-m-d H:i:s');
        $mockOrder->total_amount = 100.50;
        $mockOrder->subtotal = 90.00;
        $mockOrder->tax_amount = 8.10;
        $mockOrder->delivery_fee = 2.40;
        $mockOrder->payment_status = Order::PAYMENT_PENDING;
        $mockOrder->payment_method = '';
        $mockOrder->gateway_transaction_id = null;
        $mockOrder->notes = '';
        $mockOrder->order_items = [];
        $mockOrder->delivery_address = null;
        $mockOrder->pickup_time = null;
        $mockOrder->special_instructions = null;
        
        $this->mockGateway->method('createPaymentIntent')->willReturn($mockIntent);
        $this->mockGateway->method('processPayment')->willReturn($mockResult);
        $this->mockOrderRepo->method('get')->willReturn($mockOrder);
        
        $this->mockOrderRepo->expects($this->once())
            ->method('update')
            ->with(123, $this->callback(function($data) {
                return $data['status'] === Order::STATUS_PENDING &&
                       $data['payment_status'] === Order::PAYMENT_AUTHORIZED;
            }));
        
        $result = $this->service->processPayment($request, []);
        
        $this->assertTrue($result->success);
        $this->assertEquals(PaymentStatus::STATUS_AUTHORIZED, $result->status);
    }

    public function test_failed_payment_processing(): void
    {
        $request = new PaymentRequest(
            orderId: 123,
            customerId: 456,
            amount: 100.50,
            currency: 'ILS'
        );
        
        $mockIntent = new PaymentIntent(
            gatewayId: 'test_gateway',
            gatewayIntentId: 'intent_123',
            clientSecret: 'secret_123',
            amount: 100.50,
            currency: 'ILS',
            status: PaymentStatus::STATUS_CREATED,
            metadata: []
        );
        
        $mockResult = new PaymentResult(
            success: false,
            gatewayId: 'test_gateway',
            transactionId: 'txn_123',
            gatewayTransactionId: 'intent_123',
            amount: 100.50,
            currency: 'ILS',
            status: PaymentStatus::STATUS_FAILED,
            message: 'Payment failed'
        );
        
        $mockOrder = new Order();
        $mockOrder->id = 123;
        $mockOrder->customer_id = 456;
        $mockOrder->status = Order::STATUS_PENDING;
        $mockOrder->order_date = date('Y-m-d H:i:s');
        $mockOrder->total_amount = 100.50;
        $mockOrder->subtotal = 90.00;
        $mockOrder->tax_amount = 8.10;
        $mockOrder->delivery_fee = 2.40;
        $mockOrder->payment_status = Order::PAYMENT_PENDING;
        $mockOrder->payment_method = '';
        $mockOrder->gateway_transaction_id = null;
        $mockOrder->notes = '';
        $mockOrder->order_items = [];
        $mockOrder->delivery_address = null;
        $mockOrder->pickup_time = null;
        $mockOrder->special_instructions = null;
        
        $this->mockGateway->method('createPaymentIntent')->willReturn($mockIntent);
        $this->mockGateway->method('processPayment')->willReturn($mockResult);
        $this->mockOrderRepo->method('get')->willReturn($mockOrder);
        
        $this->mockOrderRepo->expects($this->once())
            ->method('update')
            ->with(123, $this->callback(function($data) {
                return $data['status'] === Order::STATUS_CANCELLED &&
                       $data['payment_status'] === Order::PAYMENT_FAILED;
            }));
        
        $result = $this->service->processPayment($request, []);
        
        $this->assertFalse($result->success);
        $this->assertEquals(PaymentStatus::STATUS_FAILED, $result->status);
    }

    public function test_payment_request_validation_failure(): void
    {
        $invalidRequest = new PaymentRequest(
            orderId: -1,
            customerId: 0,
            amount: -50.0,
            currency: ''
        );
        
        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Valid order ID is required');
        
        $this->service->processPayment($invalidRequest, []);
    }

    public function test_capture_payment(): void
    {
        $transactionId = 'txn_123';
        $amount = 75.25;
        
        $mockResult = new PaymentResult(
            success: true,
            gatewayId: 'test_gateway',
            transactionId: $transactionId,
            gatewayTransactionId: 'intent_123',
            amount: $amount,
            currency: 'ILS',
            status: PaymentStatus::STATUS_CAPTURED,
            message: 'Payment captured'
        );
        
        $this->mockGateway->expects($this->once())
            ->method('capturePayment')
            ->with($transactionId, $amount)
            ->willReturn($mockResult);
        
        $result = $this->service->capturePayment($transactionId, $amount);
        
        $this->assertTrue($result->success);
        $this->assertEquals(PaymentStatus::STATUS_CAPTURED, $result->status);
        $this->assertEquals($amount, $result->amount);
    }

    public function test_refund_payment(): void
    {
        $transactionId = 'txn_123';
        $amount = 50.00;
        $reason = 'Customer request';
        
        $mockResult = new PaymentResult(
            success: true,
            gatewayId: 'test_gateway',
            transactionId: $transactionId,
            gatewayTransactionId: 'intent_123',
            amount: $amount,
            currency: 'ILS',
            status: PaymentStatus::STATUS_REFUNDED,
            message: 'Payment refunded'
        );
        
        $this->mockGateway->expects($this->once())
            ->method('refundPayment')
            ->with($transactionId, $amount, $reason)
            ->willReturn($mockResult);
        
        $result = $this->service->refundPayment($transactionId, $amount, $reason);
        
        $this->assertTrue($result->success);
        $this->assertEquals(PaymentStatus::STATUS_REFUNDED, $result->status);
    }

    public function test_refund_payment_invalid_amount(): void
    {
        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Invalid payment amount');
        
        $this->service->refundPayment('txn_123', -10.0);
    }

    public function test_void_payment(): void
    {
        $transactionId = 'txn_123';
        
        $mockResult = new PaymentResult(
            success: true,
            gatewayId: 'test_gateway',
            transactionId: $transactionId,
            gatewayTransactionId: 'intent_123',
            amount: 100.0,
            currency: 'ILS',
            status: PaymentStatus::STATUS_VOIDED,
            message: 'Payment voided'
        );
        
        $this->mockGateway->expects($this->once())
            ->method('voidPayment')
            ->with($transactionId)
            ->willReturn($mockResult);
        
        $result = $this->service->voidPayment($transactionId);
        
        $this->assertTrue($result->success);
        $this->assertEquals(PaymentStatus::STATUS_VOIDED, $result->status);
    }

    public function test_get_payment_status(): void
    {
        $transactionId = 'txn_123';
        
        $mockStatus = new PaymentStatus(
            transactionId: $transactionId,
            gatewayTransactionId: 'intent_123',
            status: PaymentStatus::STATUS_CAPTURED,
            amount: 100.0,
            amountCaptured: 100.0,
            currency: 'ILS'
        );
        
        $this->mockGateway->expects($this->once())
            ->method('getPaymentStatus')
            ->with($transactionId)
            ->willReturn($mockStatus);
        
        $status = $this->service->getPaymentStatus($transactionId);
        
        $this->assertEquals($transactionId, $status->transactionId);
        $this->assertEquals(PaymentStatus::STATUS_CAPTURED, $status->status);
        $this->assertTrue($status->isCompleted());
    }

    public function test_webhook_processing_payment_succeeded(): void
    {
        $webhookData = [
            'event_type' => 'payment.succeeded',
            'transaction_id' => 'txn_123'
        ];
        
        $mockResult = PaymentWebhookResult::success(
            eventType: PaymentWebhookResult::EVENT_PAYMENT_SUCCEEDED,
            data: $webhookData,
            transactionId: 'txn_123',
            gatewayTransactionId: 'intent_123'
        );
        
        $mockOrder = new Order();
        $mockOrder->id = 123;
        $mockOrder->customer_id = 456;
        $mockOrder->status = Order::STATUS_PENDING;
        $mockOrder->order_date = date('Y-m-d H:i:s');
        $mockOrder->total_amount = 100.50;
        $mockOrder->subtotal = 90.00;
        $mockOrder->tax_amount = 8.10;
        $mockOrder->delivery_fee = 2.40;
        $mockOrder->payment_status = Order::PAYMENT_PENDING;
        $mockOrder->payment_method = '';
        $mockOrder->gateway_transaction_id = null;
        $mockOrder->notes = '';
        $mockOrder->order_items = [];
        $mockOrder->delivery_address = null;
        $mockOrder->pickup_time = null;
        $mockOrder->special_instructions = null;
        
        $this->mockGateway->expects($this->once())
            ->method('handleWebhook')
            ->with($webhookData)
            ->willReturn($mockResult);
            
        $this->mockOrderRepo->expects($this->once())
            ->method('findBy')
            ->with(['gateway_transaction_id' => 'intent_123'])
            ->willReturn([$mockOrder]);
            
        $this->mockOrderRepo->expects($this->once())
            ->method('update')
            ->with(123, $this->callback(function($data) {
                return $data['status'] === Order::STATUS_CONFIRMED &&
                       $data['payment_status'] === Order::PAYMENT_PAID;
            }));
        
        $result = $this->service->handleWebhook($webhookData);
        
        $this->assertTrue($result->success);
        $this->assertEquals(PaymentWebhookResult::EVENT_PAYMENT_SUCCEEDED, $result->eventType);
        $this->assertCount(1, $result->actions);
        $this->assertEquals('order_updated', $result->actions[0]['action']);
    }

    public function test_webhook_processing_order_not_found(): void
    {
        $webhookData = [
            'event_type' => 'payment.succeeded',
            'transaction_id' => 'txn_123'
        ];
        
        $mockResult = PaymentWebhookResult::success(
            eventType: PaymentWebhookResult::EVENT_PAYMENT_SUCCEEDED,
            data: $webhookData,
            transactionId: 'txn_123',
            gatewayTransactionId: 'intent_unknown'
        );
        
        $this->mockGateway->method('handleWebhook')->willReturn($mockResult);
        $this->mockOrderRepo->method('findBy')->willReturn([]); // No orders found
        $this->mockOrderRepo->expects($this->never())->method('update');
        
        $result = $this->service->handleWebhook($webhookData);
        
        $this->assertTrue($result->success);
        $this->assertEmpty($result->actions); // No order update action
    }

    public function test_gateway_exception_handling(): void
    {
        $request = new PaymentRequest(
            orderId: 123,
            customerId: 456,
            amount: 100.50,
            currency: 'ILS'
        );
        
        $this->mockGateway->expects($this->once())
            ->method('createPaymentIntent')
            ->willThrowException(PaymentException::cardDeclined('Card declined'));
        
        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Payment was declined by the bank');
        
        $this->service->processPayment($request, []);
    }

    public function test_generic_exception_conversion(): void
    {
        $request = new PaymentRequest(
            orderId: 123,
            customerId: 456,
            amount: 100.50,
            currency: 'ILS'
        );
        
        $this->mockGateway->expects($this->once())
            ->method('createPaymentIntent')
            ->willThrowException(new Exception('Database connection failed'));
        
        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Payment processing error: Database connection failed');
        
        $this->service->processPayment($request, []);
    }

    public function test_get_gateway_info(): void
    {
        $this->mockGateway->method('getGatewayId')->willReturn('test_gateway');
        $this->mockGateway->method('getDisplayName')->willReturn('Test Gateway');
        $this->mockGateway->method('getSupportedCurrencies')->willReturn(['ILS', 'USD']);
        $this->mockGateway->method('supportsAuthorization')->willReturn(true);
        $this->mockGateway->method('supportsCapture')->willReturn(true);
        $this->mockGateway->method('supportsRefunds')->willReturn(false);
        $this->mockGateway->method('supportsVoid')->willReturn(true);
        
        $info = $this->service->getGatewayInfo();
        
        $this->assertEquals('test_gateway', $info['gateway_id']);
        $this->assertEquals('Test Gateway', $info['display_name']);
        $this->assertEquals(['ILS', 'USD'], $info['supported_currencies']);
        $this->assertTrue($info['supports_authorize']);
        $this->assertTrue($info['supports_capture']);
        $this->assertFalse($info['supports_refund']);
        $this->assertTrue($info['supports_void']);
    }
}