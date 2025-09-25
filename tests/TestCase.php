<?php

namespace Darvis\Mailtrap\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Darvis\Mailtrap\MailtrapServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            MailtrapServiceProvider::class,
        ];
    }
}
