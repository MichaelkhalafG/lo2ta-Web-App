<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * The suite refuses to run unless mail cannot leave the machine and the database is the scratch test one.
     * It does not rely on the launcher: a test once mailed real customers on a previous project.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $mailer = config('mail.default');
        if ($mailer !== 'array') {
            throw new RuntimeException('Tests must use the array mailer, got: '.(is_string($mailer) ? $mailer : 'unknown'));
        }

        $connection = config('database.default');
        $database = is_string($connection) ? config("database.connections.$connection.database") : null;
        if (! is_string($database) || ! str_ends_with($database, '_test')) {
            throw new RuntimeException('Tests must run on a *_test database.');
        }
    }
}
