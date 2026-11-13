<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Safety net: refuse to run against anything but a *_testing database.
     * RefreshDatabase drops every table, so pointing the suite at the
     * development database (e.g. because Docker exports DB_DATABASE) would
     * wipe it. This runs before any trait touches the database.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $connection = $app['config']->get('database.default');
        $database = (string) $app['config']->get("database.connections.{$connection}.database");

        if ($database !== ':memory:' && ! str_ends_with($database, '_testing')) {
            throw new RuntimeException(
                "Tests must use a *_testing database, got [{$connection}:{$database}]. Check phpunit.xml."
            );
        }

        return $app;
    }
}
