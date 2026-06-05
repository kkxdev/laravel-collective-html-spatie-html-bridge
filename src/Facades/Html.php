<?php

declare(strict_types=1);

namespace Kkxdev\HtmlCompat\Facades;

use Kkxdev\HtmlCompat\HtmlBuilder;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Illuminate\Support\HtmlString link(string $url, string|null $title = null, array $attributes = [], bool|null $secure = null, bool $escape = true)
 * @method static \Illuminate\Support\HtmlString script(string $url, array $attributes = [], bool|null $secure = null)
 * @method static \Illuminate\Support\HtmlString style(string $url, array $attributes = [], bool|null $secure = null)
 * @method static \Illuminate\Support\HtmlString image(string $url, string|null $alt = null, array $attributes = [], bool|null $secure = null)
 * @method static \Illuminate\Support\HtmlString tag(string $tag, array $attributes = [])
 * @method static string entities(string $value)
 * @method static string decode(string $value)
 * @method static void macro(string $name, callable $macro)
 *
 * @see \Kkxdev\HtmlCompat\HtmlBuilder
 */
class Html extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return HtmlBuilder::class;
    }
}
