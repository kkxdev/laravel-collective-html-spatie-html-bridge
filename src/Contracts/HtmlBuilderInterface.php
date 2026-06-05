<?php

declare(strict_types=1);

namespace Kkxdev\HtmlCompat\Contracts;

use Illuminate\Support\HtmlString;

interface HtmlBuilderInterface
{
    public function link(string $url, ?string $title = null, array $attributes = [], ?bool $secure = null, bool $escape = true): HtmlString;

    public function script(string $url, array $attributes = [], ?bool $secure = null): HtmlString;

    public function style(string $url, array $attributes = [], ?bool $secure = null): HtmlString;

    public function image(string $url, ?string $alt = null, array $attributes = [], ?bool $secure = null): HtmlString;

    public function tag(string $tag, array $attributes = []): HtmlString;

    public function entities(string $value): string;

    public function decode(string $value): string;
}
