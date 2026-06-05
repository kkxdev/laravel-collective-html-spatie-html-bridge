<?php

declare(strict_types=1);

namespace Kkxdev\HtmlCompat\Tests;

use Kkxdev\HtmlCompat\HtmlCompatServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Spatie\Html\HtmlServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            HtmlServiceProvider::class,
            HtmlCompatServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Form' => \Kkxdev\HtmlCompat\Facades\Form::class,
            'Html' => \Kkxdev\HtmlCompat\Facades\Html::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('session.driver', 'array');
    }
}
