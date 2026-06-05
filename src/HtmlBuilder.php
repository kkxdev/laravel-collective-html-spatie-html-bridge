<?php

declare(strict_types=1);

namespace Kkxdev\HtmlCompat;

use Kkxdev\HtmlCompat\Contracts\HtmlBuilderInterface;
use Kkxdev\HtmlCompat\Support\AttributesBag;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Traits\Macroable;

/**
 * LaravelCollective-compatible HTML builder backed by Spatie Laravel HTML.
 *
 * Covers the Html:: facade methods used most frequently in existing codebases.
 */
class HtmlBuilder implements HtmlBuilderInterface
{
    use Macroable {
        __call as macroCall;
        __callStatic as macroCallStatic;
    }

    // =========================================================================
    // Links / anchors
    // =========================================================================

    /**
     * Generate an anchor tag.
     *
     * @param  bool  $escape  Escape the title text (default true, matching LaravelCollective).
     */
    public function link(
        string  $url,
        ?string $title      = null,
        array   $attributes = [],
        ?bool   $secure     = null,
        bool    $escape     = true
    ): HtmlString {
        $content = $title ?? $url;

        if ($escape) {
            $content = e($content);
        }

        $element = html()->a($url, $content);
        $element = AttributesBag::apply($element, $attributes);

        return new HtmlString((string) $element);
    }

    // =========================================================================
    // Assets
    // =========================================================================

    /**
     * Generate a <script src="…"></script> tag.
     *
     * Spatie v3 does not expose a dedicated script() helper; we use the generic
     * element builder which renders non-void tags correctly.
     */
    public function script(string $url, array $attributes = [], ?bool $secure = null): HtmlString
    {
        $element = html()->element('script')->attribute('src', $url);
        $element = AttributesBag::apply($element, $attributes);

        return new HtmlString((string) $element);
    }

    /**
     * Generate a <link rel="stylesheet" href="…"> tag.
     *
     * Spatie v3 does not expose a dedicated style() helper; we use the generic
     * element builder. 'link' is a void element so it renders without </link>.
     */
    public function style(string $url, array $attributes = [], ?bool $secure = null): HtmlString
    {
        $element = html()->element('link')
            ->attribute('rel', 'stylesheet')
            ->attribute('href', $url);
        $element = AttributesBag::apply($element, $attributes);

        return new HtmlString((string) $element);
    }

    /**
     * Generate an <img> tag.
     *
     * Spatie v3 exposes this as html()->img(), not html()->image().
     */
    public function image(string $url, ?string $alt = null, array $attributes = [], ?bool $secure = null): HtmlString
    {
        $element = html()->img($url, $alt);

        // src / alt are already set — remove duplicates from the extra array
        $cleanAttributes = array_diff_key($attributes, ['alt' => '', 'src' => '']);
        $element = AttributesBag::apply($element, $cleanAttributes);

        return new HtmlString((string) $element);
    }

    // =========================================================================
    // Generic tag builder
    // =========================================================================

    /**
     * Render an arbitrary HTML opening tag with the given attributes.
     *
     * Example: Html::tag('div', ['class' => 'container'])  → <div class="container">
     */
    public function tag(string $tag, array $attributes = []): HtmlString
    {
        $attrString = AttributesBag::renderToString($attributes);

        return new HtmlString("<{$tag}{$attrString}>");
    }

    // =========================================================================
    // String helpers (mirror LaravelCollective)
    // =========================================================================

    /**
     * Convert all applicable characters to HTML entities.
     */
    public function entities(string $value): string
    {
        return htmlentities($value, ENT_QUOTES, 'UTF-8', false);
    }

    /**
     * Convert HTML entities back to their original characters.
     */
    public function decode(string $value): string
    {
        return html_entity_decode($value, ENT_QUOTES, 'UTF-8');
    }

    // =========================================================================
    // Magic: macros + graceful degradation
    // =========================================================================

    public function __call(string $method, array $parameters): mixed
    {
        if (static::hasMacro($method)) {
            return $this->macroCall($method, $parameters);
        }

        logger()->warning('Unsupported Html method called — no output rendered.', [
            'method' => $method,
            'class'  => static::class,
        ]);

        return new HtmlString('');
    }
}
