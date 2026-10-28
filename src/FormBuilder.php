<?php

declare(strict_types=1);

namespace Kkxdev\HtmlCompat;

use Kkxdev\HtmlCompat\Contracts\FormBuilderInterface;
use Kkxdev\HtmlCompat\Support\AttributesBag;
use Kkxdev\HtmlCompat\Support\Componentable;
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
    use Componentable;
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
    // Additional input types (LaravelCollective parity)
    // =========================================================================

    /** Form::date() — DateTimeInterface values are formatted as Y-m-d. */
    public function date(string $name, mixed $value = null, array $options = []): HtmlString
    {
        return $this->buildInput('date', $name, $value, $options, 'Y-m-d');
    }

    public function time(string $name, mixed $value = null, array $options = []): HtmlString
    {
        return $this->buildInput('time', $name, $value, $options, 'H:i');
    }

    public function datetime(string $name, mixed $value = null, array $options = []): HtmlString
    {
        return $this->buildInput('datetime', $name, $value, $options, \DateTimeInterface::RFC3339);
    }

    public function datetimeLocal(string $name, mixed $value = null, array $options = []): HtmlString
    {
        return $this->buildInput('datetime-local', $name, $value, $options, 'Y-m-d\TH:i');
    }

    public function month(string $name, mixed $value = null, array $options = []): HtmlString
    {
        return $this->buildInput('month', $name, $value, $options, 'Y-m');
    }

    public function week(string $name, mixed $value = null, array $options = []): HtmlString
    {
        return $this->buildInput('week', $name, $value, $options, 'o-\WW');
    }

    public function url(string $name, mixed $value = null, array $options = []): HtmlString
    {
        return $this->buildInput('url', $name, $value, $options);
    }

    public function tel(string $name, mixed $value = null, array $options = []): HtmlString
    {
        return $this->buildInput('tel', $name, $value, $options);
    }

    public function search(string $name, mixed $value = null, array $options = []): HtmlString
    {
        return $this->buildInput('search', $name, $value, $options);
    }

    public function color(string $name, mixed $value = null, array $options = []): HtmlString
    {
        return $this->buildInput('color', $name, $value, $options);
    }

    public function range(string $name, mixed $value = null, array $options = []): HtmlString
    {
        return $this->buildInput('range', $name, $value, $options);
    }

    /** Form::reset() — <input type="reset">. */
    public function reset(?string $value = null, array $options = []): HtmlString
    {
        $element = html()->input('reset');
        if ($value !== null) {
            $element = $element->attribute('value', $value);
        }
        $element = AttributesBag::apply($element, $options);

        return new HtmlString((string) $element);
    }

    /** Form::image() — <input type="image" src="…">. */
    public function image(string $url, ?string $name = null, array $options = []): HtmlString
    {
        $options['src'] = $this->url->asset($url);

        $element = html()->input('image', $name);
        $element = AttributesBag::apply($element, $options);

        return new HtmlString((string) $element);
    }

    /**
     * Form::datalist() — list may be [value => label] or a plain list of values.
     */
    public function datalist(string $id, array $list = [], array $options = []): HtmlString
    {
        $options['id'] = $id;
        $html = '<datalist' . AttributesBag::renderToString($options) . '>';

        foreach ($list as $key => $label) {
            $html .= is_int($key)
                ? '<option value="' . e((string) $label) . '">'
                : '<option value="' . e((string) $key) . '">' . e((string) $label) . '</option>';
        }

        return new HtmlString($html . '</datalist>');
    }

    // =========================================================================
    // Select helpers
    // =========================================================================

    /** Form::selectRange('age', 18, 65) */
    public function selectRange(string $name, int|string $begin, int|string $end, mixed $selected = null, array $options = []): HtmlString
    {
        $range = range($begin, $end);

        return $this->select($name, array_combine($range, $range), $selected, $options);
    }

    /** Form::selectYear('year', 2000, 2030) — identical to selectRange. */
    public function selectYear(string $name, int|string $begin, int|string $end, mixed $selected = null, array $options = []): HtmlString
    {
        return $this->selectRange($name, $begin, $end, $selected, $options);
    }

    /** Form::selectMonth('month') — keys 1..12, labels are month names. */
    public function selectMonth(string $name, mixed $selected = null, array $options = [], string $format = 'F'): HtmlString
    {
        $months = [];

        foreach (range(1, 12) as $month) {
            $months[$month] = date($format, mktime(0, 0, 0, $month, 1));
        }

        return $this->select($name, $months, $selected, $options);
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
        if (static::hasComponent($method)) {
            return $this->renderComponent($method, $parameters);
        }

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

    private function buildInput(string $type, string $name, mixed $value, array $options, ?string $dateFormat = null): HtmlString
    {
        $value = $this->valueResolver->resolve($name, $value);

        if ($dateFormat !== null && $value instanceof \DateTimeInterface) {
            $value = $value->format($dateFormat);
        }

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
