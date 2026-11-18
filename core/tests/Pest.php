<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test case binding
|--------------------------------------------------------------------------
| Feature tests boot the application on the guarded test database and roll it back after each test.
| Arch tests boot it without the database. Unit tests stay framework-free.
| Run one suite at a time (handover 7.11: two runs at once deadlocked).
*/

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(TestCase::class)->in('Arch');

require_once __DIR__.'/Support/seller.php';

/**
 * Headers the Next.js server sends on keyed API calls.
 *
 * @return array<string, string>
 */
function apiHeaders(): array
{
    $key = config('services.web.server_key');

    return ['X-Api-Key' => is_string($key) ? $key : '', 'Accept' => 'application/json'];
}
