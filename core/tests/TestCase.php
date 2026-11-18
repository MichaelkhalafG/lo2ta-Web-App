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

    /**
     * Every API request authenticates from its own headers, as in production. Inside one test the application is
     * shared, and Sanctum's guard would otherwise keep the user of the previous request, so a request with another
     * seller's token, or none, could pass as the first seller and make an ownership test meaningless.
     * The dashboard's session guard (actingAs) is left alone.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $guard = $this->app['auth']->guard('sanctum');
        if (method_exists($guard, 'forgetUser')) {
            $guard->forgetUser();
        }

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }
}
