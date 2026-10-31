<?php

use Illuminate\Support\HtmlString;
use Kkxdev\HtmlCompat\HtmlBuilder;

if (!function_exists('link_to')) {
    function link_to(string $url, ?string $title = null, array $attributes = [], ?bool $secure = null, bool $escape = true): HtmlString
    {
        return app(HtmlBuilder::class)->link($url, $title, $attributes, $secure, $escape);
    }
}

if (!function_exists('link_to_asset')) {
    function link_to_asset(string $url, ?string $title = null, array $attributes = [], ?bool $secure = null): HtmlString
    {
        return app(HtmlBuilder::class)->linkAsset($url, $title, $attributes, $secure);
    }
}

if (!function_exists('link_to_route')) {
    function link_to_route(string $name, ?string $title = null, mixed $parameters = [], array $attributes = []): HtmlString
    {
        return app(HtmlBuilder::class)->linkRoute($name, $title, $parameters, $attributes);
    }
}

if (!function_exists('link_to_action')) {
    function link_to_action(string $action, ?string $title = null, mixed $parameters = [], array $attributes = []): HtmlString
    {
        return app(HtmlBuilder::class)->linkAction($action, $title, $parameters, $attributes);
    }
}
