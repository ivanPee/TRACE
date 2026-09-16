<?php

namespace Controllers;

use Core\ApiController;
use Core\Response;

class GeocodingController extends ApiController
{
    private const BACOLOD_CENTER = ['latitude' => 10.6765, 'longitude' => 122.9509];

    private array $bacolodPlaces = [
        'alijis' => ['name' => 'Barangay Alijis, Bacolod City, Negros Occidental, Philippines', 'latitude' => 10.64042, 'longitude' => 122.96132],
        'banago' => ['name' => 'Barangay Banago, Bacolod City, Negros Occidental, Philippines', 'latitude' => 10.70656, 'longitude' => 122.95601],
        'bata' => ['name' => 'Barangay Bata, Bacolod City, Negros Occidental, Philippines', 'latitude' => 10.70895, 'longitude' => 122.96729],
        'estefania' => ['name' => 'Barangay Estefania, Bacolod City, Negros Occidental, Philippines', 'latitude' => 10.68943, 'longitude' => 122.98205],
        'granada' => ['name' => 'Barangay Granada, Bacolod City, Negros Occidental, Philippines', 'latitude' => 10.67335, 'longitude' => 123.03696],
        'handumanan' => ['name' => 'Barangay Handumanan, Bacolod City, Negros Occidental, Philippines', 'latitude' => 10.62366, 'longitude' => 122.98158],
        'mansilingan' => ['name' => 'Barangay Mansilingan, Bacolod City, Negros Occidental, Philippines', 'latitude' => 10.64885, 'longitude' => 122.98928],
        'mandalagan' => ['name' => 'Barangay Mandalagan, Bacolod City, Negros Occidental, Philippines', 'latitude' => 10.69457, 'longitude' => 122.96476],
        'taculing' => ['name' => 'Barangay Taculing, Bacolod City, Negros Occidental, Philippines', 'latitude' => 10.65365, 'longitude' => 122.94946],
        'tangub' => ['name' => 'Barangay Tangub, Bacolod City, Negros Occidental, Philippines', 'latitude' => 10.62823, 'longitude' => 122.94442],
        'villamonte' => ['name' => 'Barangay Villamonte, Bacolod City, Negros Occidental, Philippines', 'latitude' => 10.67132, 'longitude' => 122.97313],
    ];

    public function search(): void
    {
        $input = $this->input();
        $query = trim((string) ($input['query'] ?? $input['q'] ?? ''));
        $center = [
            'latitude' => $this->coordinate($input['latitude'] ?? null, self::BACOLOD_CENTER['latitude']),
            'longitude' => $this->coordinate($input['longitude'] ?? null, self::BACOLOD_CENTER['longitude']),
        ];
        $limit = max(1, min(8, (int) ($input['limit'] ?? 5)));

        if ($query === '') {
            Response::json(['success' => false, 'message' => 'Address search text is required.'], 422);
        }

        $localResults = $this->localFallback($query, $center);
        $remoteResults = [];

        if ($this->hasStrongLocalMatch($localResults)) {
            Response::json([
                'success' => true,
                'data' => ['results' => array_slice($localResults, 0, $limit)],
            ]);
        }

        foreach ($this->queryVariants($query) as $variant) {
            $remoteResults = array_merge($remoteResults, $this->searchNominatim($variant, $limit), $this->searchPhoton($variant, $center, $limit));

            if (count($remoteResults) >= $limit) {
                break;
            }
        }

        $results = $this->sortByPrecision($this->dedupe(array_merge($localResults, $remoteResults)));

        if (!$results) {
            $results = $this->localFallback($query, $center, true);
        }

        Response::json([
            'success' => true,
            'data' => ['results' => array_slice($results, 0, $limit)],
        ]);
    }

    public function reverse(): void
    {
        $input = $this->input();
        $latitude = $this->coordinate($input['latitude'] ?? null, null);
        $longitude = $this->coordinate($input['longitude'] ?? null, null);

        if ($latitude === null || $longitude === null) {
            Response::json(['success' => false, 'message' => 'Latitude and longitude are required.'], 422);
        }

        $url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&addressdetails=1&zoom=18&lat=' . rawurlencode((string) $latitude) . '&lon=' . rawurlencode((string) $longitude);
        $json = $this->fetchJson($url);
        $precision = is_array($json) ? $this->nominatimPrecision($json) : '';
        $address = is_array($json) ? $this->formatNominatimAddress($json) : '';

        if ($address === '') {
            $nearest = $this->nearestBacolodPlace($latitude, $longitude);
            $address = $nearest ? $nearest['name'] : 'Pinned location';
        }

        Response::json([
            'success' => true,
            'data' => [
                'address' => $address,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'precision' => $precision,
            ],
        ]);
    }

    private function searchNominatim(string $query, int $limit): array
    {
        $url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&addressdetails=1&countrycodes=ph&limit=' . $limit . '&q=' . rawurlencode($query);
        $json = $this->fetchJson($url);

        if (!is_array($json)) {
            return [];
        }

        return array_values(array_filter(array_map(fn ($item) => $this->result(
            $this->formatNominatimAddress($item),
            $item['lat'] ?? null,
            $item['lon'] ?? null,
            'OpenStreetMap',
            $this->nominatimPrecision($item)
        ), $json)));
    }

    private function searchPhoton(string $query, array $center, int $limit): array
    {
        $url = 'https://photon.komoot.io/api/?limit=' . $limit
            . '&lang=en&lat=' . rawurlencode((string) $center['latitude'])
            . '&lon=' . rawurlencode((string) $center['longitude'])
            . '&q=' . rawurlencode($query);
        $json = $this->fetchJson($url);

        if (!is_array($json) || empty($json['features']) || !is_array($json['features'])) {
            return [];
        }

        return array_values(array_filter(array_map(function ($feature) {
            $props = $feature['properties'] ?? [];
            $coordinates = $feature['geometry']['coordinates'] ?? [];
            $address = $this->compactAddress(
                $props['housenumber'] ?? '',
                $props['name'] ?? '',
                $props['street'] ?? '',
                $props['district'] ?? '',
                $props['city'] ?? $props['county'] ?? '',
                $props['state'] ?? '',
                $props['country'] ?? ''
            );

            return $this->result($address, $coordinates[1] ?? null, $coordinates[0] ?? null, 'OpenStreetMap', $props['osm_value'] ?? 'place');
        }, $json['features'])));
    }

    private function localFallback(string $query, array $center, bool $includeApproximate = false): array
    {
        $normalized = $this->normalize($query);
        $results = [];

        if (str_contains($normalized, 'juarez') && str_contains($normalized, 'alijis')) {
            $results[] = $this->result('Juarez Street, Barangay Alijis, Bacolod City, Negros Occidental, Philippines', 10.64042, 122.96132, 'TRACE local map', 'street');
        }

        foreach ($this->bacolodPlaces as $key => $place) {
            if (str_contains($normalized, $key)) {
                $results[] = $this->result($place['name'], $place['latitude'], $place['longitude'], 'TRACE local map', 'barangay');
            }
        }

        if (!$results && str_contains($normalized, 'bacolod')) {
            $results[] = $this->result('Bacolod City, Negros Occidental, Philippines', self::BACOLOD_CENTER['latitude'], self::BACOLOD_CENTER['longitude'], 'TRACE local map', 'city');
        }

        if (!$results && $includeApproximate) {
            $results[] = $this->result($query . ' (approximate pin)', $center['latitude'], $center['longitude'], 'TRACE typed pin', 'typed coordinate');
        }

        return array_values(array_filter($results));
    }

    private function queryVariants(string $query): array
    {
        $clean = trim(preg_replace('/\s+/', ' ', $query));
        $barangay = preg_replace('/\bbrgy\.?\b/i', 'Barangay', $clean);
        $withoutBarangayPrefix = preg_replace('/\bbarangay\b/i', '', $barangay);
        $variants = [$clean, $barangay, trim($withoutBarangayPrefix)];

        foreach ([$clean, $barangay, trim($withoutBarangayPrefix)] as $variant) {
            $normalized = $this->normalize($variant);

            if ($variant !== '' && !str_contains($normalized, 'philippines')) {
                if (!str_contains($normalized, 'bacolod') && !str_contains($normalized, 'negros')) {
                    $variants[] = $variant . ', Bacolod City, Negros Occidental, Philippines';
                    continue;
                }

                $variants[] = $variant . ', Negros Occidental, Philippines';
            }
        }

        return array_values(array_unique(array_filter(array_map('trim', $variants))));
    }

    private function fetchJson(string $url): mixed
    {
        $headers = [
            'Accept: application/json',
            'User-Agent: TRACE-Mobile/1.0 (local school transport app)',
        ];

        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            $body = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            curl_close($curl);

            return $body && $status >= 200 && $status < 300 ? json_decode((string) $body, true) : null;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 5,
                'header' => implode("\r\n", $headers),
            ],
        ]);
        $body = @file_get_contents($url, false, $context);

        return $body ? json_decode((string) $body, true) : null;
    }

    private function hasStrongLocalMatch(array $results): bool
    {
        foreach ($results as $result) {
            $address = strtolower((string) ($result['address'] ?? ''));

            if ($address !== '' && !str_starts_with($address, 'bacolod city,')) {
                return true;
            }
        }

        return false;
    }

    private function result(string $address, mixed $latitude, mixed $longitude, string $source, string $precision = ''): ?array
    {
        $latitude = $this->coordinate($latitude, null);
        $longitude = $this->coordinate($longitude, null);
        $address = trim($address);

        if ($address === '' || $latitude === null || $longitude === null) {
            return null;
        }

        return [
            'id' => sha1(strtolower($address) . '|' . round($latitude, 6) . '|' . round($longitude, 6)),
            'address' => $address,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'source' => $source,
            'precision' => $precision,
        ];
    }

    private function formatNominatimAddress(array $item): string
    {
        $address = is_array($item['address'] ?? null) ? $item['address'] : [];
        $road = $this->compactAddress($address['house_number'] ?? '', $address['road'] ?? $address['pedestrian'] ?? $address['footway'] ?? $address['path'] ?? '');
        $block = $address['neighbourhood'] ?? $address['quarter'] ?? $address['hamlet'] ?? '';
        $barangay = $address['suburb'] ?? $address['village'] ?? $address['city_district'] ?? '';
        $city = $address['city'] ?? $address['town'] ?? $address['municipality'] ?? $address['county'] ?? '';
        $province = $address['state'] ?? $address['region'] ?? '';
        $country = $address['country'] ?? 'Philippines';
        $formatted = $this->compactAddress($road, $block, $barangay, $city, $province, $country);

        return $formatted !== '' ? $formatted : trim((string) ($item['display_name'] ?? ''));
    }

    private function nominatimPrecision(array $item): string
    {
        $address = is_array($item['address'] ?? null) ? $item['address'] : [];

        if (!empty($address['house_number'])) {
            return 'house or block';
        }

        if (!empty($address['road']) || !empty($address['pedestrian']) || !empty($address['footway']) || !empty($address['path'])) {
            return 'street';
        }

        if (!empty($address['neighbourhood']) || !empty($address['quarter'])) {
            return 'block or neighbourhood';
        }

        if (!empty($address['suburb']) || !empty($address['village']) || !empty($address['city_district'])) {
            return 'barangay';
        }

        return (string) ($item['type'] ?? 'place');
    }

    private function dedupe(array $items): array
    {
        $seen = [];
        $unique = [];

        foreach ($items as $item) {
            if (!$item) {
                continue;
            }

            $key = strtolower($item['address']) . '|' . round((float) $item['latitude'], 5) . '|' . round((float) $item['longitude'], 5);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $item;
        }

        return $unique;
    }

    private function sortByPrecision(array $items): array
    {
        usort($items, function ($left, $right) {
            $leftRank = $this->precisionRank((string) ($left['precision'] ?? ''));
            $rightRank = $this->precisionRank((string) ($right['precision'] ?? ''));

            if ($leftRank !== $rightRank) {
                return $leftRank <=> $rightRank;
            }

            return strlen((string) ($right['address'] ?? '')) <=> strlen((string) ($left['address'] ?? ''));
        });

        return $items;
    }

    private function precisionRank(string $precision): int
    {
        $normalized = $this->normalize($precision);

        if (str_contains($normalized, 'house') || str_contains($normalized, 'block')) {
            return 1;
        }

        if (str_contains($normalized, 'street') || str_contains($normalized, 'road')) {
            return 2;
        }

        if (str_contains($normalized, 'neighbourhood') || str_contains($normalized, 'barangay')) {
            return 3;
        }

        if (str_contains($normalized, 'city')) {
            return 4;
        }

        return 5;
    }

    private function nearestBacolodPlace(float $latitude, float $longitude): ?array
    {
        $nearest = null;
        $nearestDistance = PHP_FLOAT_MAX;

        foreach ($this->bacolodPlaces as $place) {
            $distance = abs($latitude - $place['latitude']) + abs($longitude - $place['longitude']);

            if ($distance < $nearestDistance) {
                $nearestDistance = $distance;
                $nearest = $place;
            }
        }

        return $nearestDistance < 0.08 ? $nearest : null;
    }

    private function compactAddress(string ...$parts): string
    {
        return implode(', ', array_values(array_filter(array_map('trim', $parts))));
    }

    private function normalize(string $value): string
    {
        return strtolower(trim(preg_replace('/[^a-z0-9]+/i', ' ', $value)));
    }

    private function coordinate(mixed $value, ?float $fallback): ?float
    {
        return is_numeric($value) ? (float) $value : $fallback;
    }
}
