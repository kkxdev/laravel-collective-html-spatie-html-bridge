<?php

declare(strict_types=1);

namespace Kkxdev\HtmlCompat\Support;

/**
 * Converts LaravelCollective-style option arrays into Spatie HTML element attributes.
 *
 * Handles:
 * - Boolean true  → renders the attribute (e.g. required="required")
 * - Boolean false → omits the attribute entirely
 * - data-* / aria-* pass-through
 * - Package-internal keys (no_hidden, unchecked_value) are stripped
 */
final class AttributesBag
{
    private const BOOLEAN_HTML_ATTRS = [
        'required', 'disabled', 'readonly', 'checked', 'selected',
        'multiple', 'autofocus', 'open', 'default', 'defer', 'async',
        'allowfullscreen', 'autoplay', 'controls', 'loop', 'muted',
        'novalidate', 'reversed', 'scoped', 'seamless',
    ];

    /** Keys consumed internally by the wrapper — never passed to spatie elements. */
    private const INTERNAL_KEYS = ['no_hidden', 'unchecked_value'];

    /**
     * Apply a LaravelCollective options array to a Spatie element.
     *
     * @param  mixed  $element  Any Spatie\Html\Elements\BaseElement instance.
     * @param  array  $options  LaravelCollective-style HTML attribute array.
     * @return mixed            The modified element (new immutable instance).
     */
    public static function apply(mixed $element, array $options): mixed
    {
        foreach ($options as $key => $value) {
            if (in_array($key, self::INTERNAL_KEYS, true)) {
                continue;
            }

            // false  → omit attribute
            if ($value === false) {
                continue;
            }

            // null → omit attribute
            if ($value === null) {
                continue;
            }

            if ($key === 'class') {
                $element = $element->class($value);
                continue;
            }

            if ($key === 'id') {
                $element = $element->id($value);
                continue;
            }

            // Boolean HTML attributes
            if (in_array($key, self::BOOLEAN_HTML_ATTRS, true)) {
                if ($value === true || $value === $key || $value === 'true') {
                    $element = $element->attribute($key, $key);
                }
                // false already handled above
                continue;
            }

            // Generic boolean true: render as attribute="attribute"
            if ($value === true) {
                $element = $element->attribute($key, $key);
                continue;
            }

            $element = $element->attribute($key, (string) $value);
        }

        return $element;
    }

    /**
     * Render an attribute map to an HTML attribute string (for manual tag building).
     *
     * Used when spatie cannot be used for the outer tag (e.g. <form>).
     *
     * @param  array  $attributes
     * @return string  Starts with a leading space when non-empty.
     */
    public static function renderToString(array $attributes): string
    {
        $parts = [];

        foreach ($attributes as $key => $value) {
            if (in_array($key, self::INTERNAL_KEYS, true)) {
                continue;
            }

            if ($value === false || $value === null) {
                continue;
            }

            if ($value === true || $value === $key) {
                $parts[] = htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8', false);
                continue;
            }

            $escapedKey   = htmlspecialchars((string) $key,   ENT_QUOTES, 'UTF-8', false);
            $escapedValue = htmlspecialchars((string) $value,  ENT_QUOTES, 'UTF-8', false);
            $parts[] = "{$escapedKey}=\"{$escapedValue}\"";
        }

        return $parts ? ' ' . implode(' ', $parts) : '';
    }
}
