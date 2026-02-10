<?php

declare(strict_types=1);

namespace App\Actions;

final readonly class NormalizePostcodeAction
{
    /**
     * Normalize a UK postcode to uppercase with a single space before the last 3 characters.
     */
    public function handle(string $postcode): string
    {
        $postcode = mb_strtoupper(mb_trim($postcode));
        $postcode = preg_replace('/\s+/', '', $postcode);

        return mb_substr($postcode, 0, -3).' '.mb_substr($postcode, -3);
    }
}
