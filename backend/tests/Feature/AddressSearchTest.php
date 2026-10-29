<?php

namespace Tests\Feature;

use App\Models\PostalLocality;
use App\Models\Tenant;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AddressSearchTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->tenant = Tenant::firstWhere('slug', 'anna-rosenwasser');

        PostalLocality::insert([
            ['postal_code' => '8004', 'additional_digit' => '00', 'name' => 'Zürich', 'country' => 'CH'],
            ['postal_code' => '8005', 'additional_digit' => '00', 'name' => 'Zürich', 'country' => 'CH'],
            ['postal_code' => '6083', 'additional_digit' => '00', 'name' => 'Hasliberg Hohfluh', 'country' => 'CH'],
            ['postal_code' => '9490', 'additional_digit' => '00', 'name' => 'Vaduz', 'country' => 'LI'],
        ]);
    }

    protected function base(): string
    {
        return '/api/tenants/'.$this->tenant->slug.'/address';
    }

    protected function feature(array $properties): array
    {
        return ['type' => 'Feature', 'properties' => $properties, 'geometry' => ['type' => 'Point', 'coordinates' => [8.5, 47.4]]];
    }

    protected function fakePhoton(array $features): void
    {
        Http::fake(['photon:2322/*' => Http::response(['type' => 'FeatureCollection', 'features' => $features])]);
    }

    public function test_streets_come_from_photon_restricted_to_ch_and_li(): void
    {
        $this->fakePhoton([
            $this->feature(['type' => 'house', 'street' => 'Langstrasse', 'housenumber' => '12', 'postcode' => '8004', 'city' => 'Zürich', 'countrycode' => 'CH']),
            $this->feature(['type' => 'street', 'name' => 'Städtle', 'postcode' => '9490', 'city' => 'Vaduz', 'countrycode' => 'LI']),
            $this->feature(['type' => 'house', 'street' => 'Langstrasse', 'housenumber' => '12', 'postcode' => '78462', 'city' => 'Konstanz', 'countrycode' => 'DE']),
            $this->feature(['type' => 'city', 'name' => 'Zürich', 'countrycode' => 'CH']),
        ]);

        $this->getJson($this->base().'/streets?q=Langstrasse 12')
            ->assertOk()
            ->assertExactJson(['data' => [
                ['label' => 'Langstrasse 12, 8004 Zürich', 'street' => 'Langstrasse', 'house_number' => '12', 'postal_code' => '8004', 'town' => 'Zürich', 'country' => 'CH'],
                ['label' => 'Städtle, 9490 Vaduz', 'street' => 'Städtle', 'house_number' => null, 'postal_code' => '9490', 'town' => 'Vaduz', 'country' => 'LI'],
            ]]);

        Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'countrycode=CH&countrycode=LI')
            && str_contains($request->url(), 'layer=house&layer=street')
            && str_contains($request->url(), 'lang=default'));
    }

    public function test_street_town_uses_the_official_postal_name(): void
    {
        $this->fakePhoton([
            $this->feature(['type' => 'house', 'street' => 'Bidmistrasse', 'housenumber' => '3', 'postcode' => '6083', 'city' => 'Hasliberg', 'countrycode' => 'CH']),
        ]);

        $this->getJson($this->base().'/streets?q=Bidmistrasse 3')
            ->assertOk()
            ->assertJsonPath('data.0.town', 'Hasliberg Hohfluh')
            ->assertJsonPath('data.0.label', 'Bidmistrasse 3, 6083 Hasliberg Hohfluh');
    }

    public function test_street_results_are_cached_per_normalised_query(): void
    {
        $this->fakePhoton([
            $this->feature(['type' => 'house', 'street' => 'Langstrasse', 'housenumber' => '12', 'postcode' => '8004', 'city' => 'Zürich', 'countrycode' => 'CH']),
        ]);

        $this->getJson($this->base().'/streets?q=Langstrasse 12')->assertOk();
        $this->getJson($this->base().'/streets?q='.urlencode('  langstrasse   12 '))->assertOk()->assertJsonCount(1, 'data');

        Http::assertSentCount(1);
    }

    public function test_photon_failure_returns_no_suggestions_and_is_not_cached(): void
    {
        Http::fake(['photon:2322/*' => Http::sequence()
            ->push('boom', 500)
            ->push(['features' => [$this->feature(['type' => 'house', 'street' => 'Langstrasse', 'housenumber' => '12', 'postcode' => '8004', 'city' => 'Zürich', 'countrycode' => 'CH'])]])]);

        $this->getJson($this->base().'/streets?q=Langstrasse 12')->assertOk()->assertExactJson(['data' => []]);
        $this->getJson($this->base().'/streets?q=Langstrasse 12')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_postcodes_match_by_prefix(): void
    {
        $this->getJson($this->base().'/postcodes?q=800')
            ->assertOk()
            ->assertExactJson(['data' => [
                ['label' => '8004 Zürich', 'postal_code' => '8004', 'town' => 'Zürich', 'country' => 'CH'],
                ['label' => '8005 Zürich', 'postal_code' => '8005', 'town' => 'Zürich', 'country' => 'CH'],
            ]]);

        $this->getJson($this->base().'/postcodes?q=9490')->assertOk()->assertJsonPath('data.0.country', 'LI');
    }

    public function test_queries_are_validated(): void
    {
        $this->getJson($this->base().'/streets?q=ab')->assertUnprocessable();
        $this->getJson($this->base().'/postcodes?q=80a')->assertUnprocessable();
        $this->getJson($this->base().'/postcodes?q=80041')->assertUnprocessable();
    }

    public function test_inactive_shop_has_no_address_search(): void
    {
        $this->tenant->update(['is_active' => false]);

        $this->getJson($this->base().'/postcodes?q=80')->assertNotFound();
    }

    public function test_address_search_is_rate_limited(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->getJson($this->base().'/postcodes?q=80')->assertOk();
        }

        $this->getJson($this->base().'/postcodes?q=80')->assertTooManyRequests();
    }

    public function test_import_command_reads_the_official_csv(): void
    {
        $csv = "\u{FEFF}Ortschaftsname;PLZ4;Zusatzziffer;ZIP_ID;Gemeindename;BFS-Nr;Kantonskürzel;Adressenanteil;E;N;Sprache;Validity\r\n"
            ."Binz;8122;00;4510;Maur;195;ZH;100 %;8.62;47.35;de;2008-07-01\r\n"
            ."Nendeln;9485;00;5389;Eschen;7007;;99.054 %;9.54;47.19;de;2008-07-01\r\n"
            ."Nendeln;9485;00;5389;Gamprin;7009;;0.946 %;9.54;47.19;de;2008-07-01\r\n";
        $path = tempnam(sys_get_temp_dir(), 'plz');
        file_put_contents($path, $csv);

        $this->artisan('address:import-postcodes', ['--file' => $path])->assertSuccessful();
        unlink($path);

        $this->assertSame(
            [['8122', 'Binz', 'CH'], ['9485', 'Nendeln', 'LI']],
            PostalLocality::orderBy('postal_code')->get()->map(fn ($l) => [$l->postal_code, $l->name, $l->country])->all(),
        );
    }

    public function test_import_keeps_existing_data_when_the_download_looks_broken(): void
    {
        Http::fake(['data.geo.admin.ch/*' => Http::response('not a zip')]);

        try {
            $this->artisan('address:import-postcodes')->run();
            $this->fail('A broken archive should not import.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Could not open the postal code archive.', $e->getMessage());
        }

        $this->assertSame(4, PostalLocality::count());
    }
}
