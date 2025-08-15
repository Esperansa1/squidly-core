<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Unit;

use Address;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Address model
 * @covers \Address
 */
class AddressTest extends TestCase
{
    public function test_constructor_with_full_data(): void
    {
        $data = [
            'street' => '123 Main St',
            'city' => 'Tel Aviv',
            'zip' => '12345',
            'apartment' => '4B',
            'floor' => '2',
            'notes' => 'Ring doorbell twice',
            'is_default' => true,
            'latitude' => 32.0853,
            'longitude' => 34.7818,
        ];

        $address = new Address($data);

        $this->assertEquals('123 Main St', $address->street);
        $this->assertEquals('Tel Aviv', $address->city);
        $this->assertEquals('12345', $address->zip);
        $this->assertEquals('4B', $address->apartment);
        $this->assertEquals('2', $address->floor);
        $this->assertEquals('Ring doorbell twice', $address->notes);
        $this->assertTrue($address->is_default);
        $this->assertEquals(32.0853, $address->latitude);
        $this->assertEquals(34.7818, $address->longitude);
    }

    public function test_constructor_with_minimal_data(): void
    {
        $data = [
            'street' => '456 Side St',
            'city' => 'Jerusalem',
        ];

        $address = new Address($data);

        $this->assertEquals('456 Side St', $address->street);
        $this->assertEquals('Jerusalem', $address->city);
        $this->assertEquals('', $address->zip);
        $this->assertEquals('', $address->apartment);
        $this->assertEquals('', $address->floor);
        $this->assertEquals('', $address->notes);
        $this->assertFalse($address->is_default);
        $this->assertEquals(0.0, $address->latitude);
        $this->assertEquals(0.0, $address->longitude);
    }

    public function test_missing_required_street_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Street address is required');

        new Address(['city' => 'Tel Aviv']);
    }

    public function test_missing_required_city_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('City is required');

        new Address(['street' => '123 Main St']);
    }

    public function test_invalid_latitude_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid latitude value');

        new Address([
            'street' => '123 Main St',
            'city' => 'Tel Aviv',
            'latitude' => 91.0, // Invalid: > 90
        ]);
    }

    public function test_invalid_longitude_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid longitude value');

        new Address([
            'street' => '123 Main St',
            'city' => 'Tel Aviv',
            'longitude' => -181.0, // Invalid: < -180
        ]);
    }

    public function test_toArray_returns_complete_data(): void
    {
        $data = [
            'street' => '789 Test Ave',
            'city' => 'Haifa',
            'zip' => '67890',
            'apartment' => '10A',
            'floor' => '5',
            'notes' => 'Use side entrance',
            'is_default' => true,
            'latitude' => 32.7940,
            'longitude' => 34.9896,
        ];

        $address = new Address($data);
        $array = $address->toArray();

        $this->assertEquals($data, $array);
    }

    public function test_getFullAddress_with_all_fields(): void
    {
        $address = new Address([
            'street' => '123 Main St',
            'city' => 'Tel Aviv',
            'zip' => '12345',
            'apartment' => '4B',
            'floor' => '2',
        ]);

        $expected = '123 Main St, Apt 4B, Floor 2, Tel Aviv, 12345';
        $this->assertEquals($expected, $address->getFullAddress());
    }

    public function test_getFullAddress_with_minimal_fields(): void
    {
        $address = new Address([
            'street' => '456 Side St',
            'city' => 'Jerusalem',
        ]);

        $expected = '456 Side St, Jerusalem';
        $this->assertEquals($expected, $address->getFullAddress());
    }

    public function test_distanceTo_calculates_correctly(): void
    {
        // Tel Aviv coordinates
        $address = new Address([
            'street' => 'Test St',
            'city' => 'Tel Aviv',
            'latitude' => 32.0853,
            'longitude' => 34.7818,
        ]);

        // Jerusalem coordinates
        $jerusalemLat = 31.7683;
        $jerusalemLng = 35.2137;

        $distance = $address->distanceTo($jerusalemLat, $jerusalemLng);

        // Distance between Tel Aviv and Jerusalem is approximately 52-54 km
        $this->assertGreaterThan(50, $distance);
        $this->assertLessThan(55, $distance);
    }

    public function test_distanceTo_same_location_returns_zero(): void
    {
        $address = new Address([
            'street' => 'Test St',
            'city' => 'Tel Aviv',
            'latitude' => 32.0853,
            'longitude' => 34.7818,
        ]);

        $distance = $address->distanceTo(32.0853, 34.7818);

        $this->assertEquals(0.0, $distance, '', 0.001);
    }
}