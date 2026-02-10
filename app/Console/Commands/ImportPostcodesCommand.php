<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\NormalizePostcodeAction;
use App\Jobs\ProcessPostcodeBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\LazyCollection;

final class ImportPostcodesCommand extends Command
{
    private const int CHUNK_SIZE = 500;

    private const array POSTCODE_HEADERS = ['postcode', 'pcd', 'pcds'];

    private const array LATITUDE_HEADERS = ['latitude', 'lat'];

    private const array LONGITUDE_HEADERS = ['longitude', 'lon', 'lng', 'long'];

    protected $signature = 'import:postcodes
        {--path= : Path to the CSV file}
        {--no-header : CSV file has no header row}
        {--sync : Run jobs synchronously}';

    protected $description = 'Import UK postcodes from a CSV file';

    public function handle(NormalizePostcodeAction $normalizePostcode): int
    {
        $path = $this->option('path');

        if (! $path) {
            $this->error('The --path option is required.');

            return self::FAILURE;
        }

        if (! file_exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $hasHeader = ! $this->option('no-header');
        $rows = $this->readCsv($path, $hasHeader);

        if ($this->option('sync')) {
            return $this->handleSync($rows, $normalizePostcode);
        }

        return $this->handleAsync($rows);
    }

    private function handleSync(LazyCollection $rows, NormalizePostcodeAction $normalizePostcode): int
    {
        $startTime = microtime(true);
        $processed = 0;
        $skipped = 0;
        $chunkCount = 0;

        // DB::disableQueryLog();

        $this->info('Importing postcodes...');

        foreach ($rows->chunk(self::CHUNK_SIZE) as $chunk) {
            $result = ProcessPostcodeBatch::processRows($chunk->values()->all(), $normalizePostcode);

            $processed += $result['processed'];
            $skipped += $result['skipped'];
            $chunkCount++;

            if ($chunkCount % 100 === 0) {
                $this->output->write("\rProcessed {$chunkCount} chunk(s) ({$processed} imported, {$skipped} skipped)...");
            }
        }

        $elapsed = round(microtime(true) - $startTime, 2);

        $this->output->write("\r");
        $this->newLine();
        $this->table(
            ['Metric', 'Value'],
            [
                ['Processed', $processed],
                ['Skipped', $skipped],
                ['Elapsed (s)', $elapsed],
            ]
        );

        return self::SUCCESS;
    }

    private function handleAsync(LazyCollection $rows): int
    {
        $batch = Bus::batch([])
            ->name('Import Postcodes')
            ->allowFailures()
            ->dispatch();

        Cache::put("postcode-import:{$batch->id}:processed", 0);
        Cache::put("postcode-import:{$batch->id}:skipped", 0);

        // DB::disableQueryLog();

        $this->info('Reading CSV and dispatching jobs...');

        $jobCount = 0;
        $rowCount = 0;

        foreach ($rows->chunk(self::CHUNK_SIZE) as $chunk) {
            $chunkData = $chunk->values()->all();
            $rowCount += count($chunkData);

            $batch->add([new ProcessPostcodeBatch($chunkData)]);
            $jobCount++;

            if ($jobCount % 100 === 0) {
                $this->output->write("\rDispatched {$jobCount} job(s) ({$rowCount} rows)...");
            }
        }

        if ($jobCount === 0) {
            $this->warn('No rows found in the CSV file.');

            return self::SUCCESS;
        }

        $this->output->write("\r");
        $this->info("Dispatched {$jobCount} job(s) ({$rowCount} rows).");
        $this->info("Batch ID: {$batch->id}");
        $this->info('Run `php artisan queue:work` to process the jobs.');

        return self::SUCCESS;
    }

    /**
     * @return LazyCollection<int, array<string, string>>
     */
    private function readCsv(string $path, bool $hasHeader): LazyCollection
    {
        return LazyCollection::make(function () use ($path, $hasHeader) {
            $handle = fopen($path, 'r');

            if ($handle === false) {
                return;
            }

            $columnMap = null;

            if ($hasHeader) {
                $headerRow = fgetcsv($handle, escape: '\\');

                if ($headerRow === false) {
                    fclose($handle);

                    return;
                }

                $columnMap = $this->resolveColumnMap($headerRow);
            }

            while (($row = fgetcsv($handle, escape: '\\')) !== false) {
                if ($columnMap !== null) {
                    yield [
                        'postcode' => $row[$columnMap['postcode']] ?? '',
                        'latitude' => $row[$columnMap['latitude']] ?? '',
                        'longitude' => $row[$columnMap['longitude']] ?? '',
                    ];
                } else {
                    yield [
                        'postcode' => $row[0] ?? '',
                        'latitude' => $row[1] ?? '',
                        'longitude' => $row[2] ?? '',
                    ];
                }
            }

            fclose($handle);
        });
    }

    /**
     * @param  array<int, string>  $headers
     * @return array<string, int>
     */
    private function resolveColumnMap(array $headers): array
    {
        $normalizedHeaders = array_map(fn (string $header): string => mb_strtolower(mb_trim($header)), $headers);

        return [
            'postcode' => $this->findColumnIndex($normalizedHeaders, self::POSTCODE_HEADERS) ?? 0,
            'latitude' => $this->findColumnIndex($normalizedHeaders, self::LATITUDE_HEADERS) ?? 1,
            'longitude' => $this->findColumnIndex($normalizedHeaders, self::LONGITUDE_HEADERS) ?? 2,
        ];
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string>  $candidates
     */
    private function findColumnIndex(array $headers, array $candidates): ?int
    {
        foreach ($candidates as $candidate) {
            $index = array_search($candidate, $headers, true);

            if ($index !== false) {
                return $index;
            }
        }

        return null;
    }
}
