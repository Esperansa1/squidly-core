<?php
declare(strict_types=1);

/**
 * Polyfill‑style value‑object replacement for PHP 8.1 enums.
 * Works on PHP 7.4 while keeping the same API: ::from(), ::tryFrom(), $obj->value
 */
final class ItemType
{
    public const PRODUCT    = 'product';
    public const INGREDIENT = 'ingredient';

    /** @var string */
    public $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /** @throws InvalidArgumentException */
    public static function from(string $value): self
    {
        if (! in_array($value, [self::PRODUCT, self::INGREDIENT], true)) {
            throw new InvalidArgumentException('Invalid ItemType');
        }
        return new self($value);
    }

    public static function tryFrom(?string $value): ?self
    {
        return ($value !== null && in_array($value, [self::PRODUCT, self::INGREDIENT], true))
            ? new self($value)
            : null;
    }

    /** Sugar for comparisons — keeps call‑sites tidy */
    public function isProduct(): bool    { return $this->value === self::PRODUCT; }
    public function isIngredient(): bool { return $this->value === self::INGREDIENT; }
}
