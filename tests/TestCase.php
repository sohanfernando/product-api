<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        if (! $app->environment('testing') ||
            $app['config']->get('database.default') !== 'sqlite' ||
            $app['config']->get('database.connections.sqlite.database') !== ':memory:' ||
            $app['config']->get('database.connections.sqlite.url')) {
            throw new \RuntimeException('Tests require the isolated SQLite :memory: database. Clear cached configuration and use phpunit.xml.');
        }

        return $app;
    }
}
