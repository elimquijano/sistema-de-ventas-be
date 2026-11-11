<?php

namespace App\Services;

use App\Models\Client;

class ClientLocationResolver
{
    public const NEARBY_RADIUS_METERS = 50;

    public function findNearby(int $businessId, ?string $phone, $latitude, $longitude): ?Client
    {
        if (! $phone || $latitude === null || $longitude === null) {
            return null;
        }

        return Client::query()
            ->where('business_id', $businessId)
            ->where('phone', $phone)
            ->get()
            ->filter(fn (Client $client) => $this->distanceInMeters(
                (float) $latitude,
                (float) $longitude,
                (float) $client->latitude,
                (float) $client->longitude,
            ) <= self::NEARBY_RADIUS_METERS)
            ->sortBy(fn (Client $client) => $this->distanceInMeters(
                (float) $latitude,
                (float) $longitude,
                (float) $client->latitude,
                (float) $client->longitude,
            ))
            ->first();
    }

    /** @param array<string, mixed> $location */
    public function updateLocation(Client $client, array $location): Client
    {
        $client->fill(array_intersect_key($location, array_flip([
            'latitude',
            'longitude',
            'address',
            'address_detail',
            'route',
            'estimated_time',
            'approximate_distance',
        ])))->save();

        return $client;
    }

    private function distanceInMeters(float $latitudeA, float $longitudeA, float $latitudeB, float $longitudeB): float
    {
        $earthRadius = 6371000;
        $latitudeDelta = deg2rad($latitudeB - $latitudeA);
        $longitudeDelta = deg2rad($longitudeB - $longitudeA);

        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitudeA)) * cos(deg2rad($latitudeB))
            * sin($longitudeDelta / 2) ** 2;

        return 2 * $earthRadius * atan2(sqrt($a), sqrt(1 - $a));
    }
}
