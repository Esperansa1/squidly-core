<?php
declare(strict_types=1);

/**
 * Payment Exception
 * 
 * Exception thrown for payment-related errors
 */
class PaymentException extends Exception
{
    public ?string $errorCode;
    public ?string $gatewayMessage;
    public array $gatewayData;

    // Error Codes
    public const ERROR_GATEWAY_NOT_CONFIGURED = 'gateway_not_configured';
    public const ERROR_INVALID_AMOUNT = 'invalid_amount';
    public const ERROR_INVALID_CURRENCY = 'invalid_currency';
    public const ERROR_CARD_DECLINED = 'card_declined';
    public const ERROR_INSUFFICIENT_FUNDS = 'insufficient_funds';
    public const ERROR_EXPIRED_CARD = 'expired_card';
    public const ERROR_INVALID_CARD = 'invalid_card';
    public const ERROR_PROCESSING_ERROR = 'processing_error';
    public const ERROR_GATEWAY_ERROR = 'gateway_error';
    public const ERROR_NETWORK_ERROR = 'network_error';
    public const ERROR_INVALID_REQUEST = 'invalid_request';
    public const ERROR_AUTHENTICATION_FAILED = 'authentication_failed';
    public const ERROR_TRANSACTION_NOT_FOUND = 'transaction_not_found';
    public const ERROR_ALREADY_CAPTURED = 'already_captured';
    public const ERROR_ALREADY_REFUNDED = 'already_refunded';
    public const ERROR_REFUND_AMOUNT_EXCEEDS = 'refund_amount_exceeds';

    public function __construct(
        string $message,
        ?string $errorCode = null,
        ?string $gatewayMessage = null,
        array $gatewayData = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        
        $this->errorCode = $errorCode;
        $this->gatewayMessage = $gatewayMessage;
        $this->gatewayData = $gatewayData;
    }

    /**
     * Create gateway configuration error
     */
    public static function gatewayNotConfigured(string $gatewayId): self
    {
        return new self(
            message: "Payment gateway '{$gatewayId}' is not properly configured",
            errorCode: self::ERROR_GATEWAY_NOT_CONFIGURED
        );
    }

    /**
     * Create invalid amount error
     */
    public static function invalidAmount(float $amount): self
    {
        return new self(
            message: "Invalid payment amount: {$amount}",
            errorCode: self::ERROR_INVALID_AMOUNT
        );
    }

    /**
     * Create card declined error
     */
    public static function cardDeclined(string $gatewayMessage = '', array $gatewayData = []): self
    {
        return new self(
            message: 'Payment was declined by the bank',
            errorCode: self::ERROR_CARD_DECLINED,
            gatewayMessage: $gatewayMessage,
            gatewayData: $gatewayData
        );
    }

    /**
     * Create insufficient funds error
     */
    public static function insufficientFunds(string $gatewayMessage = '', array $gatewayData = []): self
    {
        return new self(
            message: 'Insufficient funds to complete the payment',
            errorCode: self::ERROR_INSUFFICIENT_FUNDS,
            gatewayMessage: $gatewayMessage,
            gatewayData: $gatewayData
        );
    }

    /**
     * Create expired card error
     */
    public static function expiredCard(): self
    {
        return new self(
            message: 'The payment card has expired',
            errorCode: self::ERROR_EXPIRED_CARD
        );
    }

    /**
     * Create invalid card error
     */
    public static function invalidCard(string $gatewayMessage = ''): self
    {
        return new self(
            message: 'Invalid card information provided',
            errorCode: self::ERROR_INVALID_CARD,
            gatewayMessage: $gatewayMessage
        );
    }

    /**
     * Create processing error
     */
    public static function processingError(string $message, array $gatewayData = []): self
    {
        return new self(
            message: "Payment processing error: {$message}",
            errorCode: self::ERROR_PROCESSING_ERROR,
            gatewayData: $gatewayData
        );
    }

    /**
     * Create gateway error
     */
    public static function gatewayError(string $gatewayMessage, array $gatewayData = []): self
    {
        return new self(
            message: "Payment gateway error: {$gatewayMessage}",
            errorCode: self::ERROR_GATEWAY_ERROR,
            gatewayMessage: $gatewayMessage,
            gatewayData: $gatewayData
        );
    }

    /**
     * Create network error
     */
    public static function networkError(string $message = 'Network communication failed'): self
    {
        return new self(
            message: $message,
            errorCode: self::ERROR_NETWORK_ERROR
        );
    }

    /**
     * Create transaction not found error
     */
    public static function transactionNotFound(string $transactionId): self
    {
        return new self(
            message: "Transaction not found: {$transactionId}",
            errorCode: self::ERROR_TRANSACTION_NOT_FOUND
        );
    }

    /**
     * Create already captured error
     */
    public static function alreadyCaptured(string $transactionId): self
    {
        return new self(
            message: "Transaction {$transactionId} has already been captured",
            errorCode: self::ERROR_ALREADY_CAPTURED
        );
    }

    /**
     * Create refund amount exceeds error
     */
    public static function refundAmountExceeds(float $requestedAmount, float $availableAmount): self
    {
        return new self(
            message: "Refund amount {$requestedAmount} exceeds available amount {$availableAmount}",
            errorCode: self::ERROR_REFUND_AMOUNT_EXCEEDS
        );
    }

    /**
     * Get user-friendly error message
     */
    public function getUserMessage(): string
    {
        switch ($this->errorCode) {
            case self::ERROR_CARD_DECLINED:
                return 'Your payment was declined. Please try a different payment method.';
            
            case self::ERROR_INSUFFICIENT_FUNDS:
                return 'Insufficient funds. Please check your account balance or try a different card.';
            
            case self::ERROR_EXPIRED_CARD:
                return 'Your card has expired. Please use a different payment method.';
            
            case self::ERROR_INVALID_CARD:
                return 'Invalid card information. Please check your card details and try again.';
            
            case self::ERROR_NETWORK_ERROR:
                return 'Network error. Please check your connection and try again.';
            
            case self::ERROR_PROCESSING_ERROR:
            case self::ERROR_GATEWAY_ERROR:
                return 'Payment processing error. Please try again or contact support.';
            
            default:
                return 'Payment failed. Please try again or contact support.';
        }
    }

    /**
     * Convert to array for logging/debugging
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
            'gateway_message' => $this->gatewayMessage,
            'gateway_data' => $this->gatewayData,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'trace' => $this->getTraceAsString(),
        ];
    }
}