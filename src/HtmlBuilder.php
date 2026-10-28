<?php

declare(strict_types=1);

namespace Kkxdev\HtmlCompat;

use Kkxdev\HtmlCompat\Contracts\HtmlBuilderInterface;
use Kkxdev\HtmlCompat\Support\AttributesBag;
use Kkxdev\HtmlCompat\Support\Componentable;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Traits\Macroable;

/**
 * LaravelCollective-compatible HTML builder backed by Spatie Laravel HTML.
 *
 * Covers the Html:: facade methods used most frequently in existing codebases.
 */
class HtmlBuilder implements HtmlBuilderInterface
{
    use Componentable;
    use Macroable {
        __call as macroCall;
        __callStatic as macroCallStatic;
    }

    public function __construct(protected ?UrlGenerator $url = null)
    {
        $this->url ??= app(UrlGenerator::class);
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
        // Same as LaravelCollective: relative URLs become absolute and $secure forces https
        $url = $this->url->to($url, [], $secure);

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
    // URL-aware links (LaravelCollective parity)
    // =========================================================================

    public function linkAsset(string $url, ?string $title = null, array $attributes = [], ?bool $secure = null, bool $escape = true): HtmlString
    {
        return $this->link($this->url->asset($url, $secure), $title ?? $url, $attributes, $secure, $escape);
    }

    public function linkSecureAsset(string $url, ?string $title = null, array $attributes = [], bool $escape = true): HtmlString
    {
        return $this->linkAsset($url, $title, $attributes, true, $escape);
    }

    public function secureLink(string $url, ?string $title = null, array $attributes = [], bool $escape = true): HtmlString
    {
        return $this->link($this->url->secure($url), $title ?? $url, $attributes, true, $escape);
    }

    public function linkRoute(string $name, ?string $title = null, mixed $parameters = [], array $attributes = [], ?bool $secure = null, bool $escape = true): HtmlString
    {
        $url = $this->url->route($name, $parameters);

        return $this->link($url, $title ?? $url, $attributes, $secure, $escape);
    }

    public function linkAction(string $action, ?string $title = null, mixed $parameters = [], array $attributes = [], ?bool $secure = null, bool $escape = true): HtmlString
    {
        $url = $this->url->action($action, $parameters);

        return $this->link($url, $title ?? $url, $attributes, $secure, $escape);
    }

    public function asset(string $path, ?bool $secure = null): string
    {
        return $this->url->asset($path, $secure);
    }

    public function secureAsset(string $path): string
    {
        return $this->url->asset($path, true);
    }

    public function mailto(string $email, ?string $title = null, array $attributes = [], bool $escape = true): HtmlString
    {
        return $this->link('mailto:' . $email, $title ?? $email, $attributes, null, $escape);
    }

    /** Html::email() — the address with "@" encoded to deter naive scrapers. */
    public function email(string $email): string
    {
        return str_replace('@', '&#64;', htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }

    public function favicon(string $url, array $attributes = [], ?bool $secure = null): HtmlString
    {
        if ($secure !== null) {
            $url = $this->url->asset($url, $secure);
        }

        $attributes = array_merge(['rel' => 'shortcut icon', 'type' => 'image/x-icon', 'href' => $url], $attributes);

        return new HtmlString('<link' . AttributesBag::renderToString($attributes) . '>');
    }

    public function meta(?string $name, ?string $content, array $attributes = []): HtmlString
    {
        $attributes = array_merge(['name' => $name, 'content' => $content], $attributes);

        return new HtmlString('<meta' . AttributesBag::renderToString($attributes) . '>');
    }

    // =========================================================================
    // Lists, media, misc
    // =========================================================================

    public function ol(array $list, array $attributes = []): HtmlString
    {
        return $this->listing('ol', $list, $attributes);
    }

    public function ul(array $list, array $attributes = []): HtmlString
    {
        return $this->listing('ul', $list, $attributes);
    }

    /**
     * Nested arrays render as sub-lists; string keys become the parent item's label.
     */
    protected function listing(string $type, array $list, array $attributes = []): HtmlString
    {
        if ($list === []) {
            return new HtmlString('');
        }

        $html = '';

        foreach ($list as $key => $value) {
            if (is_array($value)) {
                $html .= '<li>' . (is_string($key) ? e($key) : '') . $this->listing($type, $value)->toHtml() . '</li>';
            } else {
                $html .= '<li>' . e((string) $value) . '</li>';
            }
        }

        return new HtmlString("<{$type}" . AttributesBag::renderToString($attributes) . ">{$html}</{$type}>");
    }

    /** @param string|array $source one URL or a list of URLs */
    public function audio(string|array $source, array $attributes = []): HtmlString
    {
        return $this->media('audio', $source, $attributes);
    }

    public function video(string|array $source, array $attributes = []): HtmlString
    {
        return $this->media('video', $source, $attributes);
    }

    protected function media(string $tag, string|array $source, array $attributes): HtmlString
    {
        $sources = '';

        foreach ((array) $source as $src) {
            $sources .= '<source' . AttributesBag::renderToString(['src' => $src]) . '>';
        }

        return new HtmlString("<{$tag}" . AttributesBag::renderToString($attributes) . ">{$sources}</{$tag}>");
    }

    public function nbsp(int $num = 1): HtmlString
    {
        return new HtmlString(str_repeat('&nbsp;', max($num, 0)));
    }

    /** Html::attributes() — render an attribute array as a string (leading space when non-empty). */
    public function attributes(array $attributes): string
    {
        return AttributesBag::renderToString($attributes);
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
        if (static::hasComponent($method)) {
            return $this->renderComponent($method, $parameters);
        }

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
