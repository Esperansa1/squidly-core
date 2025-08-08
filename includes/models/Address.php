<?php
// First, let's create the Address model that's referenced but missing
// File: includes/models/Address.php

declare(strict_types=1);

/**
 * Address Model
 * 
 * Represents a customer's delivery address
 */
class Address
{
    public string $street;
    public string $city;
    public string $zip;
    public string $apartment;
    public string $floor;
    public string $notes;
    public bool $is_default;
    public float $latitude;
    public float $longitude;

    public function __construct(array $data)
    {
        $this->street = (string) ($data['street'] ?? '');
        $this->city = (string) ($data['city'] ?? '');
        $this->zip = (string) ($data['zip'] ?? '');
        $this->apartment = (string) ($data['apartment'] ?? '');
        $this->floor = (string) ($data['floor'] ?? '');
        $this->notes = (string) ($data['notes'] ?? '');
        $this->is_default = (bool) ($data['is_default'] ?? false);
        $this->latitude = (float) ($data['latitude'] ?? 0.0);
        $this->longitude = (float) ($data['longitude'] ?? 0.0);
        
        $this->validate();
    }

    private function validate(): void
    {
        if (empty($this->street)) {
            throw new InvalidArgumentException('Street address is required');
        }
        
        if (empty($this->city)) {
            throw new InvalidArgumentException('City is required');
        }
        
        if ($this->latitude < -90 || $this->latitude > 90) {
            throw new InvalidArgumentException('Invalid latitude value');
        }
        
        if ($this->longitude < -180 || $this->longitude > 180) {
            throw new InvalidArgumentException('Invalid longitude value');
        }
    }

    public function toArray(): array
    {
        return [
            'street' => $this->street,
            'city' => $this->city,
            'zip' => $this->zip,
            'apartment' => $this->apartment,
            'floor' => $this->floor,
            'notes' => $this->notes,
            'is_default' => $this->is_default,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }

    public function getFullAddress(): string
    {
        $parts = [$this->street];
        
        if (!empty($this->apartment)) {
            $parts[] = 'Apt ' . $this->apartment;
        }
        
        if (!empty($this->floor)) {
            $parts[] = 'Floor ' . $this->floor;
        }
        
        $parts[] = $this->city;
        
        if (!empty($this->zip)) {
            $parts[] = $this->zip;
        }
        
        return implode(', ', $parts);
    }

    public function distanceTo(float $lat, float $lng): float
    {
        // Haversine formula for calculating distance between two points
        $earthRadius = 6371; // km
        
        $latDiff = deg2rad($lat - $this->latitude);
        $lngDiff = deg2rad($lng - $this->longitude);
        
        $a = sin($latDiff / 2) * sin($latDiff / 2) +
             cos(deg2rad($this->latitude)) * cos(deg2rad($lat)) *
             sin($lngDiff / 2) * sin($lngDiff / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;
    }
}