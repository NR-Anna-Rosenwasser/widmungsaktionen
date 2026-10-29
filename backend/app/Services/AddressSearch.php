<?php

namespace App\Services;

use App\Models\PostalLocality;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Address suggestions for the order form, only CH and LI (the countries we
 * ship to). Streets come from the self-hosted Photon geocoder, cached per
 * normalised query; when Photon is unreachable the form simply gets no
 * suggestions and the address is typed by hand. Postal codes and town names
 * come from the official directory in postal_localities.
 */
class AddressSearch
{
    public const COUNTRIES = ['CH', 'LI'];

    /**
     * Streets and house numbers matching what the customer typed so far.
     *
     * @return array<int, array{label: string, street: string, house_number: ?string, postal_code: ?string, town: ?string, country: string}>
     */
    public function streets(string $query): array
    {
        $key = 'address:v1:streets:'.sha1(Str::of($query)->squish()->lower());

        if (is_array($hit = Cache::get($key))) {
            return $hit;
        }

        // A failed lookup is not cached, so suggestions come back once Photon does.
        if (($features = $this->photon($query)) === null) {
            return [];
        }

        $streets = $this->withPostalNames(collect($features)
            ->map(fn (array $properties) => $this->address($properties))
            ->filter()
            ->values()
            ->all());

        Cache::put($key, $streets, config($streets
            ? 'widmungen.address_search.cache_ttl'
            : 'widmungen.address_search.empty_cache_ttl'));

        return $streets;
    }

    /**
     * Postal code + town pairs for a (partial) Swiss or Liechtenstein postal code.
     *
     * @return array<int, array{label: string, postal_code: string, town: string, country: string}>
     */
    public function postcodes(string $query): array
    {
        // An indexed prefix lookup over ~4000 rows, so no cache needed.
        return PostalLocality::query()
            ->where('postal_code', 'like', $query.'%')
            ->orderBy('postal_code')
            ->orderBy('additional_digit')
            ->limit(10)
            ->get()
            ->map(fn (PostalLocality $locality) => [
                'label' => $locality->postal_code.' '.$locality->name,
                'postal_code' => $locality->postal_code,
                'town' => $locality->name,
                'country' => $locality->country,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>|null feature properties, null when Photon failed
     */
    protected function photon(string $query): ?array
    {
        // lang=default: local names («Zürich», not «Zurich»), as they belong on a letter.
        // Repeated parameters (countrycode=…&countrycode=…) need a hand-built query string.
        $queryString = http_build_query(['q' => $query, 'lang' => 'default', 'limit' => 10])
            .collect(self::COUNTRIES)->map(fn ($c) => '&countrycode='.$c)->join('')
            .'&layer=house&layer=street';

        try {
            $features = Http::timeout(config('widmungen.address_search.timeout'))
                ->acceptJson()
                ->get(rtrim(config('widmungen.address_search.photon_url'), '/').'/api?'.$queryString)
                ->throw()
                ->json('features', []);
        } catch (ConnectionException|RequestException $e) {
            report($e);

            return null;
        }

        return collect($features)
            ->pluck('properties')
            ->filter(fn ($p) => is_array($p) && in_array(strtoupper($p['countrycode'] ?? ''), self::COUNTRIES, true))
            ->map(fn (array $p) => [...$p, 'countrycode' => strtoupper($p['countrycode'])])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $p
     */
    protected function address(array $p): ?array
    {
        // Houses carry the street in «street»; street features carry it in «name».
        $street = ($p['type'] ?? null) === 'street' ? ($p['name'] ?? null) : ($p['street'] ?? null);

        if (! $street) {
            return null;
        }

        return [
            'street' => $street,
            'house_number' => $p['housenumber'] ?? null,
            'postal_code' => isset($p['postcode']) && preg_match('/^\d{4}$/', $p['postcode']) ? $p['postcode'] : null,
            'town' => $p['city'] ?? null,
            'country' => $p['countrycode'],
        ];
    }

    /**
     * OSM often names the municipality («Hasliberg») where the post uses the
     * locality («Hasliberg Hohfluh»): take the official name for the postal code.
     *
     * @param  array<int, array<string, mixed>>  $addresses
     * @return array<int, array<string, mixed>>
     */
    protected function withPostalNames(array $addresses): array
    {
        $names = PostalLocality::query()
            ->whereIn('postal_code', array_filter(array_column($addresses, 'postal_code')))
            ->orderBy('additional_digit')
            ->get()
            ->groupBy('postal_code');

        return collect($addresses)
            ->map(function (array $address) use ($names) {
                $official = $names->get($address['postal_code'])?->pluck('name');
                if ($official?->isNotEmpty() && ! $official->contains($address['town'])) {
                    $address['town'] = $official->first();
                }

                $place = trim($address['postal_code'].' '.$address['town']);

                return [
                    'label' => trim($address['street'].' '.$address['house_number']).($place !== '' ? ', '.$place : ''),
                    ...$address,
                ];
            })
            ->unique('label')
            ->values()
            ->all();
    }
}
