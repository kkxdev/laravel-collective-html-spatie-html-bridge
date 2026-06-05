# laravel-collective-html-spatie-html-bridge

> Drop-in compatibility layer — keep every existing `Form::` and `Html::` Blade call working while the rendering engine
> moves from the abandoned `laravelcollective/html` to the actively-maintained `spatie/laravel-html`.

---

## The problem

`laravelcollective/html` is no longer maintained and will eventually block Laravel upgrades. A straight find-and-replace
migration rewrites every Blade template at once — a high-risk, all-or-nothing change in a production codebase.

## The solution

This package sits between your templates and Spatie's library. It:

- **exposes the identical `Form::*` / `Html::*` facade API** your Blade files already use
- **delegates all rendering to `spatie/laravel-html` v3** under the hood
- **preserves every LaravelCollective behaviour** (value precedence, checkbox hidden inputs, method spoofing, model
  binding, macros, …)
- allows **zero template changes on day one**, then gradual migration at your own pace

---

## Requirements

| Requirement         | Version                   |
|---------------------|---------------------------|
| PHP                 | ^8.0                      |
| Laravel             | ^8.0 (also tested on 9.x) |
| spatie/laravel-html | ^3.0                      |

---

## Installation

### 1. Remove the old package

```bash
composer remove laravelcollective/html
```

### 2. Install this package

```bash
composer require kkxdev/laravel-collective-html-spatie-html-bridge
```

The service provider and facade aliases are auto-discovered via the `extra.laravel` section in `composer.json` — **no
further config needed** for most apps.

### 3. Manual registration (only if auto-discovery is disabled)

```php
// config/app.php

'providers' => [
    Kkxdev\HtmlCompat\HtmlCompatServiceProvider::class,
],

'aliases' => [
    'Form' => Kkxdev\HtmlCompat\Facades\Form::class,
    'Html' => Kkxdev\HtmlCompat\Facades\Html::class,
],
```

---

## Usage

### Form facade

All existing Blade syntax continues to work without modification.

```blade
{{-- Open a form --}}
{!! Form::open(['route' => 'users.store']) !!}

{!! Form::label('name', 'Full name') !!}
{!! Form::text('name', null, ['class' => 'form-control', 'required' => true]) !!}

{!! Form::label('email', 'Email') !!}
{!! Form::email('email', null, ['class' => 'form-control']) !!}

{!! Form::submit('Save', ['class' => 'btn btn-primary']) !!}
{!! Form::close() !!}
```

#### Model binding

Pre-populates every field from a model. Old flash input always wins over the model value.

```blade
{!! Form::model($user, ['route' => ['users.update', $user->id], 'method' => 'PUT']) !!}

{!! Form::text('name') !!}        {{-- filled from $user->name --}}
{!! Form::email('email') !!}      {{-- filled from $user->email --}}

{!! Form::close() !!}
```

#### All supported Form methods

```blade
{{-- Inputs --}}
{!! Form::text('name', null, ['class' => 'form-control']) !!}
{!! Form::email('email') !!}
{!! Form::password('password') !!}
{!! Form::hidden('user_id', 42) !!}
{!! Form::number('age', null, ['min' => 0, 'max' => 120, 'step' => 1]) !!}
{!! Form::textarea('bio', null, ['rows' => 5, 'class' => 'form-control']) !!}

{{-- Select with placeholder --}}
{!! Form::select('status', $statuses, null, ['placeholder' => 'Choose...', 'class' => 'form-select']) !!}

{{-- Checkbox — a hidden input is emitted automatically so an unchecked box still submits a value --}}
{!! Form::checkbox('active', 1, true) !!}
{{--
  Renders:
    <input type="hidden" name="active" value="0">
    <input type="checkbox" name="active" value="1" checked>
--}}

{{-- Suppress the hidden input --}}
{!! Form::checkbox('active', 1, true, ['no_hidden' => true]) !!}

{{-- Custom unchecked value --}}
{!! Form::checkbox('active', 1, true, ['unchecked_value' => 'off']) !!}

{{-- Radio --}}
{!! Form::radio('role', 'admin') !!}
{!! Form::radio('role', 'user', true) !!}

{{-- File — remember to set 'files' => true on open --}}
{!! Form::open(['url' => '/upload', 'files' => true]) !!}
{!! Form::file('avatar') !!}
{!! Form::close() !!}

{{-- Label --}}
{!! Form::label('first_name', 'First name', ['class' => 'form-label']) !!}
{{-- If no value given, the name is auto-formatted: first_name → "First Name" --}}
{!! Form::label('first_name') !!}

{{-- Submit / button --}}
{!! Form::submit('Save', ['class' => 'btn btn-primary']) !!}
{!! Form::button('Reset', ['type' => 'reset', 'class' => 'btn btn-secondary']) !!}
```

#### `Form::open()` options

| Key      | Description                                                                      |
|----------|----------------------------------------------------------------------------------|
| `url`    | Raw URL string or `['/path', param, …]` array                                    |
| `route`  | Named route — `'users.store'` or `['users.update', $id]`                         |
| `action` | Controller action — `'UserController@store'` or `['UserController@update', $id]` |
| `method` | HTTP verb. `PUT`, `PATCH`, `DELETE` emit a hidden `_method` field                |
| `files`  | `true` → adds `enctype="multipart/form-data"`                                    |

Any additional key is passed through as a raw HTML attribute on `<form>`.

```blade
{!! Form::open(['route' => 'users.store', 'class' => 'needs-validation', 'novalidate' => true]) !!}
```

---

### Html facade

```blade
{!! Html::link('https://example.com', 'Visit us', ['class' => 'btn btn-link']) !!}
{!! Html::script('/js/app.js', ['defer' => true]) !!}
{!! Html::style('/css/app.css') !!}
{!! Html::image('/img/logo.png', 'Logo', ['class' => 'img-fluid', 'width' => '120']) !!}
{!! Html::tag('div', ['class' => 'container', 'id' => 'main']) !!}

{{ Html::entities('<script>alert(1)</script>') }}
{{ Html::decode('&lt;b&gt;Bold&lt;/b&gt;') }}
```

---

## Attribute behaviour

| Options array value         | Result                        |
|-----------------------------|-------------------------------|
| `'class' => 'form-control'` | `class="form-control"`        |
| `'required' => true`        | `required="required"`         |
| `'disabled' => false`       | *(omitted)*                   |
| `'disabled' => null`        | *(omitted)*                   |
| `'data-id' => 10`           | `data-id="10"`                |
| `'aria-label' => 'Name'`    | `aria-label="Name"`           |
| `'autocomplete' => true`    | `autocomplete="autocomplete"` |

`data-*` and `aria-*` attributes pass through without any special handling.

---

## Value resolution precedence

For every field, the displayed value is resolved in this order:

```
1. old()        — flash input from a previous (failed) form submission
2. Model value  — attribute from the model bound via Form::model()
3. Explicit     — second argument passed directly to the method
4. null         — nothing rendered for value=""
```

Bracket notation for nested fields is fully supported:

```blade
{!! Form::text('user[name]') !!}           {{-- old input key: user.name --}}
{!! Form::text('address[city]') !!}        {{-- old input key: address.city --}}
{!! Form::text('meta[tags][]') !!}         {{-- old input key: meta.tags --}}
```

---

## Macro support

Register custom helpers in any service provider:

```php
use Kkxdev\HtmlCompat\FormBuilder;
use Kkxdev\HtmlCompat\HtmlBuilder;

FormBuilder::macro('dateField', function (string $name, $value = null, array $options = []) {
    return $this->text($name, $value, array_merge(['type' => 'date'], $options));
});

HtmlBuilder::macro('badge', function (string $text, string $color = 'primary') {
    return new \Illuminate\Support\HtmlString(
        "<span class=\"badge bg-{$color}\">" . e($text) . "</span>"
    );
});
```

Then in Blade:

```blade
{!! Form::dateField('published_at', null, ['class' => 'form-control']) !!}
{!! Html::badge('New', 'success') !!}
```

---

## Unsupported methods — graceful degradation

Calling a method that does not exist will **never throw a fatal error**. Instead:

1. A `warning` is written to the Laravel log.
2. An empty `HtmlString` is returned so the page continues to render.

```
[2024-01-15 10:23:45] local.WARNING: Unsupported Form method called — no output rendered.
{"method":"customWidget","class":"Kkxdev\\HtmlCompat\\FormBuilder"}
```

This lets you discover undiscovered usages in production logs without taking the site down.

---

## Supported method reference

### Form

| Method                                        | Notes                                                                                  |
|-----------------------------------------------|----------------------------------------------------------------------------------------|
| `open(array $options)`                        | Accepts `url`, `route`, `action`, `method`, `files`, plus raw HTML attrs               |
| `model($model, array $options)`               | Binds Eloquent model, array, or stdClass                                               |
| `close()`                                     | Outputs `</form>` and clears model binding                                             |
| `token()`                                     | Standalone `<input type="hidden" name="_token">`                                       |
| `label($name, $value, $options, $escape)`     | Auto-capitalises `snake_case` names when no value given                                |
| `text($name, $value, $options)`               |                                                                                        |
| `email($name, $value, $options)`              |                                                                                        |
| `password($name, $options)`                   | Never pre-populated from old() or model                                                |
| `hidden($name, $value, $options)`             |                                                                                        |
| `number($name, $value, $options)`             | Supports `min`, `max`, `step`                                                          |
| `textarea($name, $value, $options)`           |                                                                                        |
| `select($name, $list, $selected, $options)`   | `placeholder` key adds an empty first option                                           |
| `checkbox($name, $value, $checked, $options)` | Auto-emits hidden input; opt out with `no_hidden`; custom value with `unchecked_value` |
| `radio($name, $value, $checked, $options)`    |                                                                                        |
| `file($name, $options)`                       |                                                                                        |
| `submit($value, $options)`                    |                                                                                        |
| `button($value, $options)`                    | `type` key sets button type (default `button`)                                         |
| `input($type, $name, $value, $options)`       | Generic escape hatch for any input type                                                |
| `getValueAttribute($name, $value)`            | Exposed for callers that need direct value resolution                                  |
| `resetModel()`                                | Detaches model without closing the form                                                |

### Html

| Method                                              | Notes                                         |
|-----------------------------------------------------|-----------------------------------------------|
| `link($url, $title, $attributes, $secure, $escape)` | Title defaults to URL; escaping on by default |
| `script($url, $attributes)`                         | Renders `<script src="…"></script>`           |
| `style($url, $attributes)`                          | Renders `<link rel="stylesheet" href="…">`    |
| `image($url, $alt, $attributes)`                    | Renders `<img src="…" alt="…">`               |
| `tag($tag, $attributes)`                            | Generic opening tag builder                   |
| `entities($value)`                                  | `htmlentities()` wrapper                      |
| `decode($value)`                                    | `html_entity_decode()` wrapper                |

---

## Migration strategy

Migration is entirely optional — this wrapper can be kept permanently. If you want to move off it over time, here is a
safe path:

### Phase 1 — Drop-in (day one)

```bash
composer remove laravelcollective/html
composer require kkxdev/laravel-collective-html-spatie-html-bridge
```

No template changes. All `Form::` and `Html::` calls route through this package.

### Phase 2 — Incremental template migration

Replace individual Blade calls with the native Spatie API as you touch each file:

```blade
{{-- Before --}}
{!! Form::text('name', null, ['class' => 'form-control']) !!}

{{-- After --}}
{!! html()->text('name')->class('form-control') !!}
```

Both styles can coexist in the same template during the transition.

### Phase 3 — Remove the wrapper

Once all templates have been migrated, remove this package:

```bash
composer remove kkxdev/laravel-collective-html-spatie-html-bridge
```

---

## Architecture

```
Blade template
    │
    │  {!! Form::text('name') !!}
    ▼
Kkxdev\HtmlCompat\Facades\Form          ← facade (container accessor)
    │
    ▼
Kkxdev\HtmlCompat\FormBuilder           ← stateful builder (singleton per request)
    │
    ├── Support\ValueResolver           ← old() › model › explicit › null
    │
    └── Support\AttributesBag           ← boolean attr handling, data-*, aria-*
            │
            ▼
    spatie/laravel-html elements        ← actual HTML rendering + escaping
```

---

## Running tests

```bash
composer install
vendor/bin/phpunit
```

The test suite runs on PHP 8.0 with Orchestra Testbench v6 (Laravel 8 environment) and covers:

- Form open / close / method spoofing / CSRF
- All input types and their attribute handling
- Model binding (Eloquent, array, stdClass)
- `old()` flash input precedence
- Checkbox hidden input behaviour
- Nested bracket-notation field names
- Macro registration and invocation
- Graceful degradation for unknown methods

---

## License

MIT — [kkxdev](https://github.com/kkxdev)
