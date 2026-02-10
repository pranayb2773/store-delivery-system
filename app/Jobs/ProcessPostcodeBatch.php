<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\NormalizePostcodeAction;
use App\Models\Postcode;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Support\Facades\Cache;

final class ProcessPostcodeBatch implements ShouldQueue
{
    use Batchable, Queueable;

    private const string UK_POSTCODE_REGEX = '/^[A-Z]{1,2}\d[A-Z\d]?\s\d[A-Z]{2}$/';

    /**
     * @param  array<int, array<string, string>>  $rows
     */
    public function __construct(public array $rows) {}

    /**
     * Process rows and upsert valid postcodes. Used by both the job and the command (sync mode).
     *
     * @param  array<int, array<string, string>>  $rows
     * @return array{processed: int, skipped: int}
     */
    public static function processRows(array $rows, NormalizePostcodeAction $normalizePostcode): array
    {
        $validRows = [];
        $skipped = 0;

        foreach ($rows as $row) {
            $postcode = $normalizePostcode->handle($row['postcode']);
            $latitude = $row['latitude'];
            $longitude = $row['longitude'];

            if (! self::isValidRow($postcode, $latitude, $longitude)) {
                $skipped++;

                continue;
            }

            $validRows[] = [
                'postcode' => $postcode,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ];
        }

        if ($validRows !== []) {
            Postcode::upsert($validRows, uniqueBy: ['postcode'], update: ['latitude', 'longitude']);
        }

        return ['processed' => count($validRows), 'skipped' => $skipped];
    }

    /**
     * @return array<int, SkipIfBatchCancelled>
     */
    public function middleware(): array
    {
        return [new SkipIfBatchCancelled];
    }

    public function handle(NormalizePostcodeAction $normalizePostcode): void
    {
        $result = self::processRows($this->rows, $normalizePostcode);

        $batchId = $this->batch()->id;
        Cache::increment("postcode-import:{$batchId}:processed", $result['processed']);
        Cache::increment("postcode-import:{$batchId}:skipped", $result['skipped']);
    }

    private static function isValidRow(string $postcode, mixed $latitude, mixed $longitude): bool
    {
        if (! preg_match(self::UK_POSTCODE_REGEX, $postcode)) {
            return false;
        }

        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return false;
        }

        $latitude = (float) $latitude;
        $longitude = (float) $longitude;

        return $latitude >= -90 && $latitude <= 90
            && $longitude >= -180 && $longitude <= 180;
    }
}
