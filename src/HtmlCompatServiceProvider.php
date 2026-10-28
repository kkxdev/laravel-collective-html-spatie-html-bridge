<?php

declare(strict_types=1);

namespace Kkxdev\HtmlCompat;

use Kkxdev\HtmlCompat\Facades\Form as FormFacade;
use Kkxdev\HtmlCompat\Facades\Html as HtmlFacade;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

class HtmlCompatServiceProvider extends ServiceProvider
{
    /**
     * All bindings are singletons: FormBuilder keeps stateful model binding
     * across a single request, so one instance per request is correct.
     */
    public function register(): void
    {
        $this->registerFormBuilder();
        $this->registerHtmlBuilder();
        $this->registerAliases();
    }

    public function boot(): void
    {
        // Nothing to publish — this is a runtime-only package.
    }

    // -------------------------------------------------------------------------

    private function registerFormBuilder(): void
    {
        $this->app->singleton(FormBuilder::class, function ($app) {
            return new FormBuilder(
                $app->make(UrlGenerator::class),
                $app->make(Request::class)
            );
        });

        // Backward-compat alias used by the facade and by any code that resolves
        // 'form' from the container (matching LaravelCollective's binding key).
        $this->app->alias(FormBuilder::class, 'form');
    }

    private function registerHtmlBuilder(): void
    {
        $this->app->singleton(HtmlBuilder::class, function ($app) {
            return new HtmlBuilder($app->make(UrlGenerator::class));
        });

        $this->app->alias(HtmlBuilder::class, 'html.compat');
    }

    private function registerAliases(): void
    {
        $loader = \Illuminate\Foundation\AliasLoader::getInstance();
        $loader->alias('Form', FormFacade::class);
        $loader->alias('Html', HtmlFacade::class);
    }

    // -------------------------------------------------------------------------

    /**
     * Declare the services provided so the framework can lazy-load them.
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            FormBuilder::class,
            HtmlBuilder::class,
            'form',
            'html.compat',
        ];
    }
}
