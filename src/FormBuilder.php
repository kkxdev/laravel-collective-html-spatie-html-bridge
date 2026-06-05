<?php

declare(strict_types=1);

namespace Kkxdev\HtmlCompat;

use Kkxdev\HtmlCompat\Contracts\FormBuilderInterface;
use Kkxdev\HtmlCompat\Support\AttributesBag;
use Kkxdev\HtmlCompat\Support\ValueResolver;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Traits\Macroable;

/**
 * LaravelCollective-compatible form builder backed by Spatie Laravel HTML.
 *
 * Preserves the complete LaravelCollective\Html\FormBuilder public API so that
 * existing Blade templates need zero changes.
 *
 * Value resolution precedence (LaravelCollective behaviour):
 *   1. old()        – flashed input from a previous request
 *   2. model value  – bound via Form::model()
 *   3. explicit     – second argument to the method
 *   4. null         – nothing
 */
class FormBuilder implements FormBuilderInterface
{
    use Macroable {
        __call as macroCall;
        __callStatic as macroCallStatic;
    }

    /** @var array<string> Option keys that are LaravelCollective-specific, not raw HTML attrs. */
    private const FORM_SPECIFIC_KEYS = ['method', 'url', 'route', 'action', 'files'];

    protected mixed $model = null;
    protected ?string $currentMethod = null;
    protected ValueResolver $valueResolver;

    public function __construct(
        protected UrlGenerator $url,
        protected Request $request
    ) {
        $this->valueResolver = new ValueResolver($request);
    }

    // =========================================================================
    // Form lifecycle
    // =========================================================================

    /**
     * Open an HTML <form> tag.
     *
     * Supported options (beyond raw HTML attributes):
     *   method  – HTTP verb; PUT/PATCH/DELETE are spoofed via hidden _method
     *   url     – explicit URL string or [url, param, …] array
     *   route   – named route string or [name, param, …] array
     *   action  – controller action string or [Controller@method, param, …] array
     *   files   – bool; when true sets enctype="multipart/form-data"
     */
    public function open(array $options = []): HtmlString
    {
        $method = strtoupper($options['method'] ?? 'POST');
        $spoofedMethod = null;

        if (in_array($method, ['PUT', 'PATCH', 'DELETE'], true)) {
            $spoofedMethod = $method;
            $method = 'POST';
        } elseif (!in_array($method, ['GET', 'POST'], true)) {
            $method = 'POST';
        }

        $this->currentMethod = $method;

        $formAttributes = [
            'method'         => strtolower($method),
            'action'         => $this->resolveFormAction($options),
            'accept-charset' => 'UTF-8',
        ];

        if (!empty($options['files'])) {
            $formAttributes['enctype'] = 'multipart/form-data';
        }

        // Pass through any extra HTML attributes the caller provided
        foreach ($options as $key => $value) {
            if (!in_array($key, self::FORM_SPECIFIC_KEYS, true)) {
                $formAttributes[$key] = $value;
            }
        }

        $html = '<form' . AttributesBag::renderToString($formAttributes) . '>';

        if ($method !== 'GET') {
            $html .= $this->token()->toHtml();
        }

        if ($spoofedMethod !== null) {
            $html .= $this->buildHiddenTag('_method', $spoofedMethod);
        }

        return new HtmlString($html);
    }

    /**
     * Open a form bound to an Eloquent model or plain array.
     *
     * Field values will be pre-populated from the model (unless overridden by
     * old() flash input).
     */
    public function model(mixed $model, array $options = []): HtmlString
    {
        $this->model = $model;
        $this->valueResolver->setModel($model);

        return $this->open($options);
    }

    /**
     * Close the current form and clear model binding.
     */
    public function close(): HtmlString
    {
        $this->model = null;
        $this->currentMethod = null;
        $this->valueResolver->setModel(null);

        return new HtmlString('</form>');
    }

    /**
     * Detach the bound model without closing the form.
     */
    public function resetModel(): void
    {
        $this->model = null;
        $this->valueResolver->setModel(null);
    }

    /**
     * Render a hidden CSRF token input.
     */
    public function token(): HtmlString
    {
        return new HtmlString(
            $this->buildHiddenTag('_token', $this->getCsrfToken())
        );
    }

    // =========================================================================
    // Label
    // =========================================================================

    public function label(string $name, ?string $value = null, array $options = [], bool $escape = true): HtmlString
    {
        $content = $value ?? ucwords(str_replace(['_', '-', '.'], ' ', $name));

        if ($escape) {
            $content = e($content);
        }

        $element = html()->label($content, $name);
        $element = AttributesBag::apply($element, $options);

        return new HtmlString((string) $element);
    }

    // =========================================================================
    // Text-like inputs
    // =========================================================================

    public function text(string $name, mixed $value = null, array $options = []): HtmlString
    {
        return $this->buildInput('text', $name, $value, $options);
    }

    public function email(string $name, mixed $value = null, array $options = []): HtmlString
    {
        return $this->buildInput('email', $name, $value, $options);
    }

    public function password(string $name, array $options = []): HtmlString
    {
        // Passwords are never pre-populated — even from model binding
        $element = html()->password($name);
        $element = AttributesBag::apply($element, $options);

        return new HtmlString((string) $element);
    }

    public function hidden(string $name, mixed $value = null, array $options = []): HtmlString
    {
        $element = html()->hidden($name, $value !== null ? (string) $value : null);
        $element = AttributesBag::apply($element, $options);

        return new HtmlString((string) $element);
    }

    public function number(string $name, mixed $value = null, array $options = []): HtmlString
    {
        return $this->buildInput('number', $name, $value, $options);
    }

    public function textarea(string $name, mixed $value = null, array $options = []): HtmlString
    {
        $value = $this->valueResolver->resolve($name, $value);

        $element = html()->textarea($name, $value !== null ? (string) $value : null);
        $element = AttributesBag::apply($element, $options);

        return new HtmlString((string) $element);
    }

    // =========================================================================
    // Select
    // =========================================================================

    /**
     * Render a <select> element.
     *
     * @param  array  $list               Associative [value => label] pairs.
     *                                    Nested arrays produce <optgroup> groups.
     * @param  mixed  $selected           Pre-selected value(s); resolved via
     *                                    old() / model binding before use.
     * @param  array  $options            HTML attributes. Use 'placeholder' to
     *                                    prepend an empty disabled option.
     */
    public function select(
        string $name,
        array  $list     = [],
        mixed  $selected = null,
        array  $options  = [],
        array  $optionsAttributes  = [],
        array  $optgroupsAttributes = []
    ): HtmlString {
        $selected = $this->valueResolver->resolve($name, $selected);

        // Prepend a placeholder option if requested
        $placeholder = $options['placeholder'] ?? null;
        $cleanOptions = array_diff_key($options, ['placeholder' => '']);

        if ($placeholder !== null) {
            $list = ['' => $placeholder] + $list;
        }

        $element = html()->select($name, $list, $selected);
        $element = AttributesBag::apply($element, $cleanOptions);

        return new HtmlString((string) $element);
    }

    // =========================================================================
    // Checkable inputs
    // =========================================================================

    /**
     * Render a checkbox.
     *
     * Emits a hidden input before the checkbox so that an unchecked box still
     * submits a value (LaravelCollective behaviour):
     *
     *   <input type="hidden"   name="active" value="0">
     *   <input type="checkbox" name="active" value="1" checked>
     *
     * Pass 'no_hidden' => true in $options to suppress the hidden input.
     * Pass 'unchecked_value' => 'your-value' to override the hidden value.
     */
    public function checkbox(string $name, mixed $value = 1, ?bool $checked = null, array $options = []): HtmlString
    {
        $isChecked = $this->valueResolver->resolveChecked($name, $value, $checked);

        $element = html()->checkbox($name, $isChecked, (string) $value);
        $element = AttributesBag::apply($element, $options);

        $noHidden = !empty($options['no_hidden']);

        if ($noHidden) {
            return new HtmlString((string) $element);
        }

        $uncheckedValue = isset($options['unchecked_value'])
            ? (string) $options['unchecked_value']
            : '0';

        $hidden = $this->buildHiddenTag($name, $uncheckedValue);

        return new HtmlString($hidden . (string) $element);
    }

    /**
     * Render a radio button.
     */
    public function radio(string $name, mixed $value = null, ?bool $checked = null, array $options = []): HtmlString
    {
        $isChecked = $this->valueResolver->resolveRadio($name, $value, $checked);

        $spatieValue = $value !== null ? (string) $value : null;
        $element = html()->radio($name, $isChecked, $spatieValue);
        $element = AttributesBag::apply($element, $options);

        return new HtmlString((string) $element);
    }

    // =========================================================================
    // File, submit, button
    // =========================================================================

    public function file(string $name, array $options = []): HtmlString
    {
        $element = html()->file($name);
        $element = AttributesBag::apply($element, $options);

        return new HtmlString((string) $element);
    }

    public function submit(?string $value = null, array $options = []): HtmlString
    {
        $element = html()->submit($value);
        $element = AttributesBag::apply($element, $options);

        return new HtmlString((string) $element);
    }

    public function button(?string $value = null, array $options = []): HtmlString
    {
        $type = $options['type'] ?? 'button';
        $cleanOptions = array_diff_key($options, ['type' => '']);

        $element = html()->button($value, $type);
        $element = AttributesBag::apply($element, $cleanOptions);

        return new HtmlString((string) $element);
    }

    // =========================================================================
    // Generic input (escape hatch)
    // =========================================================================

    public function input(string $type, string $name, mixed $value = null, array $options = []): HtmlString
    {
        $value = $this->valueResolver->resolve($name, $value);

        $element = html()->input($type, $name, $value !== null ? (string) $value : null);
        $element = AttributesBag::apply($element, $options);

        return new HtmlString((string) $element);
    }

    // =========================================================================
    // Value resolution (public for callers that need it directly)
    // =========================================================================

    public function getValueAttribute(string $name, mixed $value = null): mixed
    {
        return $this->valueResolver->resolve($name, $value);
    }

    // =========================================================================
    // Magic: macros + graceful degradation for unsupported methods
    // =========================================================================

    public function __call(string $method, array $parameters): mixed
    {
        if (static::hasMacro($method)) {
            return $this->macroCall($method, $parameters);
        }

        logger()->warning('Unsupported Form method called — no output rendered.', [
            'method' => $method,
            'class'  => static::class,
        ]);

        return new HtmlString('');
    }

    // =========================================================================
    // Internals
    // =========================================================================

    private function buildInput(string $type, string $name, mixed $value, array $options): HtmlString
    {
        $value = $this->valueResolver->resolve($name, $value);

        $element = html()->input($type, $name, $value !== null ? (string) $value : null);
        $element = AttributesBag::apply($element, $options);

        return new HtmlString((string) $element);
    }

    private function buildHiddenTag(string $name, string $value): string
    {
        $n = htmlspecialchars($name,  ENT_QUOTES, 'UTF-8', false);
        $v = htmlspecialchars($value, ENT_QUOTES, 'UTF-8', false);

        return "<input type=\"hidden\" name=\"{$n}\" value=\"{$v}\">";
    }

    private function resolveFormAction(array $options): string
    {
        if (isset($options['url'])) {
            if (is_array($options['url'])) {
                return $this->url->to(
                    (string) $options['url'][0],
                    array_slice($options['url'], 1)
                );
            }
            return $this->url->to((string) $options['url']);
        }

        if (isset($options['route'])) {
            if (is_array($options['route'])) {
                return $this->url->route(
                    (string) $options['route'][0],
                    array_slice($options['route'], 1)
                );
            }
            return $this->url->route((string) $options['route']);
        }

        if (isset($options['action'])) {
            if (is_array($options['action'])) {
                return $this->url->action(
                    $options['action'][0],
                    array_slice($options['action'], 1)
                );
            }
            return $this->url->action($options['action']);
        }

        return $this->url->current();
    }

    private function getCsrfToken(): string
    {
        if ($this->request->hasSession()) {
            // Store::token() is docblock-typed but has no cast — null when _token absent
            return (string) $this->request->session()->token();
        }

        if (function_exists('csrf_token')) {
            return (string) csrf_token();
        }

        return '';
    }
}
