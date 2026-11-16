<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = parent::createApplication();
        $database = $app['config']->get('database.connections.pgsql.database');
        if ($app['config']->get('database.default') !== 'pgsql' || ! is_string($database) || ! str_ends_with($database, '_test')) {
            throw new \RuntimeException('Tests require a dedicated PostgreSQL database ending in _test.');
        }
        return $app;
    }
}
