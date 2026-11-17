<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test case binding
|--------------------------------------------------------------------------
| Feature and Arch tests boot the application. Unit tests stay framework-free.
*/

pest()->extend(TestCase::class)->in('Feature', 'Arch');
