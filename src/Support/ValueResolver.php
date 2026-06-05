<?php

declare(strict_types=1);

namespace Kkxdev\HtmlCompat\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Resolves the current value for a form field following LaravelCollective precedence:
 *
 *   1. old() flash input (highest — user just submitted a form with errors)
 *   2. Bound model attribute
 *   3. Explicitly passed $value
 *   4. null / default
 */
final class ValueResolver
{
    private mixed $model = null;

    public function __construct(private Request $request) {}

    public function setModel(mixed $model): void
    {
        $this->model = $model;
    }

    /**
     * Resolve the display value for a standard text-like input.
     */
    public function resolve(string $name, mixed $value = null): mixed
    {
        $key = $this->transformKey($name);

        $old = $this->getOldInput($key);
        if ($old !== null) {
            return $old;
        }

        if ($this->model !== null) {
            $modelValue = $this->getModelValue($key);
            if ($modelValue !== null) {
                return $modelValue;
            }
        }

        return $value;
    }

    /**
     * Resolve the checked state for a checkbox.
     *
     * When the session contains flash data (a form was submitted), only the
     * old() value is authoritative. If the key is absent from old input while
     * flash data exists, the checkbox is unchecked (no value was POSTed).
     */
    public function resolveChecked(string $name, mixed $checkboxValue, ?bool $checked): bool
    {
        $key = $this->transformKey($name);

        if ($this->request->hasSession() && $this->request->session()->hasOldInput()) {
            $old = $this->request->old($key);

            if ($old === null) {
                // Key absent from flash → checkbox was unchecked on submission
                return false;
            }

            if (is_array($old)) {
                return in_array((string) $checkboxValue, array_map('strval', $old), true);
            }

            return (string) $old === (string) $checkboxValue;
        }

        if ($this->model !== null) {
            $modelValue = $this->getModelValue($key);

            if ($modelValue !== null) {
                if (is_array($modelValue)) {
                    return in_array((string) $checkboxValue, array_map('strval', $modelValue), true);
                }

                if (is_bool($modelValue)) {
                    return $modelValue;
                }

                return (string) $modelValue === (string) $checkboxValue;
            }
        }

        return (bool) $checked;
    }

    /**
     * Resolve the checked state for a radio button.
     */
    public function resolveRadio(string $name, mixed $radioValue, ?bool $checked): bool
    {
        $key = $this->transformKey($name);

        if ($this->request->hasSession() && $this->request->session()->hasOldInput()) {
            $old = $this->request->old($key);

            if ($old === null) {
                return false;
            }

            return (string) $old === (string) $radioValue;
        }

        if ($this->model !== null) {
            $modelValue = $this->getModelValue($key);

            if ($modelValue !== null) {
                return (string) $modelValue === (string) $radioValue;
            }
        }

        return (bool) $checked;
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function getOldInput(string $key): mixed
    {
        if (!$this->request->hasSession()) {
            return null;
        }

        // old() returns null when the key is not present in flash data
        return $this->request->old($key);
    }

    private function getModelValue(string $key): mixed
    {
        if ($this->model === null) {
            return null;
        }

        // Eloquent: use getAttribute so accessors and casts are applied
        if ($this->model instanceof Model) {
            $attr = $this->model->getAttribute($key);

            // Attribute not found on the model — try nested dot-notation via data_get
            if ($attr === null && str_contains($key, '.')) {
                return data_get($this->model, $key);
            }

            return $attr;
        }

        // Plain arrays and stdClass objects via data_get (supports dot notation)
        return data_get($this->model, $key);
    }

    /**
     * Convert HTML bracket notation to dot notation for old() / data_get() lookups.
     *
     *   user[name]         → user.name
     *   address[city]      → address.city
     *   user[addr][city]   → user.addr.city
     *   tags[]             → tags
     */
    private function transformKey(string $key): string
    {
        // Replace ']' first (to nothing), then '[' to '.'
        $dotted = str_replace('[', '.', str_replace(']', '', $key));
        // Trim any trailing dots produced by trailing []
        return rtrim($dotted, '.');
    }
}
