# Leaving laravelcollective/html without rewriting a single Blade file

*How I built a drop-in bridge from `Form::` / `Html::` to `spatie/laravel-html`, and tested it on Laravel 8 through 13.*

<!-- Suggested tags: Laravel, PHP, Open Source, Migration, Web Development -->

## The problem nobody schedules

If your Laravel app is more than a few years old, it probably has this in `composer.json`:

```json
"laravelcollective/html": "^6.0"
```

And somewhere in `resources/views`, hundreds or thousands of lines like:

```blade
{!! Form::open(['route' => 'users.store']) !!}
    {!! Form::label('name', 'Full name') !!}
    {!! Form::text('name', null, ['class' => 'form-control']) !!}
{!! Form::close() !!}
```

`laravelcollective/html` is no longer maintained, and sooner or later it blocks a framework upgrade. The maintained
alternative, `spatie/laravel-html`, is good, but its API is different. A straight migration means rewriting every
Blade template in one go: a big, risky, all-or-nothing change in a production codebase. Paid automated upgrade
tools exist, but they rewrite your templates and you still have to review the diff.

In my case there were over ten thousand `Form::` calls spread across several applications.

## The idea: swap the engine, keep the API

Instead of changing the templates, change what is behind them.

`kkxdev/laravel-collective-html-spatie-html-bridge` registers the same `Form` and `Html` facades, so your existing
Blade keeps working, and delegates the actual rendering to `spatie/laravel-html`.

```bash
composer remove laravelcollective/html
composer require kkxdev/laravel-collective-html-spatie-html-bridge
```

That's it. The service provider and facade aliases are auto-discovered. No template changes on day one, and you can
migrate to Spatie's native API at your own pace afterwards.

## The hard part is behaviour, not method names

Mapping `Form::text()` to `html()->input('text', ...)` is the easy 10%. The real work is reproducing the small
behaviours that templates silently depend on:

- **Value precedence.** `old()` input beats the bound model value, which beats the explicit argument.
- **Nested names.** `user[address][city]` must read `old('user.address.city')`.
- **Checkboxes** emit a hidden input first, so an unchecked box still submits `0`.
- **Method spoofing.** `PUT` / `PATCH` / `DELETE` open a `POST` form with a hidden `_method`.
- **Selects**: placeholder as an empty first option, optgroups from nested arrays, `multiple` with array selections.
- **Dates.** `Form::date('born', $carbon)` has to format a `Carbon` instance as `Y-m-d`.
- **Components and macros**: `Form::component()` and `Form::macro()` still work.

```blade
{!! Form::model($user, ['route' => ['users.update', $user], 'method' => 'PUT']) !!}
    {!! Form::text('name') !!}            {{-- from $user->name, or old('name') --}}
    {!! Form::date('born') !!}            {{-- Carbon → 2000-01-02 --}}
    {!! Form::checkbox('active', 1) !!}   {{-- hidden 0 + checkbox --}}
{!! Form::close() !!}
```

## How it's built

```
Blade → Form:: / Html:: facade → FormBuilder / HtmlBuilder → spatie/laravel-html
                                      │
                                      └─ ValueResolver (old() → model → explicit)
                                      └─ AttributesBag (true/false/null → HTML attrs)
```

A small `AttributesBag` translates Collective-style option arrays (`'required' => true`, `'disabled' => false`) into
element attributes, and a `ValueResolver` implements the precedence rules in one place.

One deliberate design choice: unknown methods **don't crash**, they log a warning and render nothing. That keeps a
half-migrated app up. (If you'd prefer to fail loudly in development, that is easy to add.)

## Testing it properly

A bridge like this is only useful if it works on the Laravel versions people actually run, so the test suite runs
against Orchestra Testbench on **Laravel 8, 9, 10, 11, 12 and 13**, each on a PHP version that release officially
supports. 96 tests pass in every combination. The CI matrix is in the repository.

One honest limit: the package needs PHP 8.0+, so a Laravel 8 app still on PHP 7.4 can't use it.

## What it is not

It is a compatibility layer, not a code rewriter. It keeps your templates working; it won't convert them to Spatie
syntax for you. A natural next step is a command that scans your Blade files and reports which `Form::` / `Html::`
calls are used, so you can see what is covered before you switch.

## Try it

- Packagist: https://packagist.org/packages/kkxdev/laravel-collective-html-spatie-html-bridge
- GitHub: https://github.com/kkxdev/laravel-collective-html-spatie-html-bridge

Issues and PRs welcome, especially for any `Form::` call whose output differs from what Collective produced.
