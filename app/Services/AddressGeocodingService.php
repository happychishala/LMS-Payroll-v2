<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AddressGeocodingService
{
    public function lookup(?string $address, bool $allowRemote = false): ?array
    {
        $address = trim((string) $address);

        if ($address === '' || ! config('services.nominatim.enabled')) {
            return null;
        }

        $cacheKey = 'nominatim_lookup:' . md5(Str::lower($address));

        $cached = Cache::get($cacheKey);

        if ($cached) {
            return $cached;
        }

        if (! $allowRemote) {
            return null;
        }

        return Cache::remember($cacheKey, now()->addDays(30), function () use ($address) {
            $response = Http::timeout((int) config('services.nominatim.timeout', 8))
                ->acceptJson()
                ->withHeaders([
                    'User-Agent' => (string) config('services.nominatim.user_agent', config('app.name') . '/1.0'),
                    'Referer' => (string) config('app.url'),
                ])
                ->get((string) config('services.nominatim.endpoint', 'https://nominatim.openstreetmap.org/search'), [
                    'format' => 'jsonv2',
                    'addressdetails' => 1,
                    'limit' => 1,
                    'countrycodes' => 'zm',
                    'q' => $address,
                ]);

            if (! $response->successful()) {
                return null;
            }

            $payload = $response->json();

            if (! is_array($payload) || $payload === [] || ! is_array($payload[0] ?? null)) {
                return null;
            }

            $addressData = $payload[0]['address'] ?? [];

            if (! is_array($addressData)) {
                return null;
            }

            return [
                'town' => $addressData['city'] ?? $addressData['town'] ?? $addressData['village'] ?? $addressData['municipality'] ?? null,
                'district' => $addressData['county'] ?? $addressData['state_district'] ?? $addressData['city'] ?? $addressData['town'] ?? null,
                'province' => $addressData['state'] ?? $addressData['region'] ?? null,
                'country' => $addressData['country'] ?? null,
                'postcode' => $addressData['postcode'] ?? null,
                'display_name' => $payload[0]['display_name'] ?? null,
            ];
        });
    }
}
