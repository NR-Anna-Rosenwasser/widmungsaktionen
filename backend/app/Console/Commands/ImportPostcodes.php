<?php

namespace App\Console\Commands;

use App\Models\PostalLocality;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use ZipArchive;

#[Signature('address:import-postcodes {--file= : Local CSV instead of downloading the official directory}')]
#[Description('Import the official directory of postal codes and localities (CH + LI) from swisstopo')]
class ImportPostcodes extends Command
{
    public function handle(): int
    {
        $csv = $this->option('file') ? file_get_contents($this->option('file')) : $this->download();

        $rows = $this->parse($csv);

        // A broken download must never empty the table the order form relies on.
        if (count($rows) < 1000 && ! $this->option('file')) {
            $this->error('Only '.count($rows).' localities found, keeping the existing ones.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($rows) {
            PostalLocality::query()->delete();
            foreach (array_chunk($rows, 500) as $chunk) {
                PostalLocality::insert($chunk);
            }
        });

        $this->info('Imported '.count($rows).' postal localities.');

        return self::SUCCESS;
    }

    protected function download(): string
    {
        $zip = Http::timeout(60)->get(config('widmungen.address_search.postcodes_url'))->throw()->body();

        $path = tempnam(sys_get_temp_dir(), 'plz');
        file_put_contents($path, $zip);

        try {
            $archive = new ZipArchive;
            if ($archive->open($path) !== true) {
                throw new RuntimeException('Could not open the postal code archive.');
            }

            for ($i = 0; $i < $archive->numFiles; $i++) {
                if (str_ends_with($archive->getNameIndex($i), '.csv')) {
                    return $archive->getFromIndex($i);
                }
            }

            throw new RuntimeException('No CSV in the postal code archive.');
        } finally {
            @unlink($path);
        }
    }

    /**
     * Columns: Ortschaftsname;PLZ4;Zusatzziffer;ZIP_ID;Gemeindename;BFS-Nr;Kantonskürzel;…
     * A locality spanning several municipalities appears once per municipality.
     * Liechtenstein rows have no canton.
     *
     * @return array<int, array{postal_code: string, additional_digit: string, name: string, country: string}>
     */
    protected function parse(string $csv): array
    {
        $lines = preg_split('/\r\n|\n/', trim(preg_replace('/^\xEF\xBB\xBF/', '', $csv)));
        array_shift($lines);

        return collect($lines)
            ->map(fn (string $line) => str_getcsv($line, ';', '"', ''))
            ->filter(fn (array $cols) => count($cols) >= 7 && preg_match('/^\d{4}$/', $cols[1]))
            ->map(fn (array $cols) => [
                'postal_code' => $cols[1],
                'additional_digit' => $cols[2],
                'name' => trim($cols[0]),
                'country' => $cols[6] === '' ? 'LI' : 'CH',
            ])
            ->unique(fn (array $row) => $row['postal_code'].'|'.$row['additional_digit'].'|'.$row['name'])
            ->values()
            ->all();
    }
}
