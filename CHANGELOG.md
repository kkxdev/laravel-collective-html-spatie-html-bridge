# Changelog

## 1.0.0 - 2026-10-31

First public release.

- Drop-in `Form::` / `Html::` facades backed by `spatie/laravel-html` v3.
- Form: open/close/model/token, label, text, email, password, hidden, number, textarea, select (placeholder, optgroups,
  multiple), checkbox, radio, file, submit, button, input, plus date, time, datetime, datetimeLocal, month, week, url,
  tel, search, color, range, reset, image, datalist, selectRange, selectYear, selectMonth.
- Html: link, script, style, image, tag, linkAsset, linkSecureAsset, secureLink, linkRoute, linkAction, asset,
  secureAsset, mailto, email, favicon, meta, ul, ol, audio, video, nbsp, attributes, entities, decode.
- `Form::component()` / `Html::component()`, macros, and the `link_to*` global helpers.
- Laravel 8–13 (PHP ^8.0).

### Notable behaviour (matches LaravelCollective)

- `Form::submit()` renders `<input type="submit" value="…">`, not a `<button>`.
- `Form::open(['action' => …])` treats the value as a controller action and falls back to the literal string when it
  is not a defined action.
- `Form::week()` uses the ISO week-numbering year.
- `Html::link()` passes URLs through `UrlGenerator::to()`: relative links become absolute and `$secure = true` forces
  https. `Html::email()` HTML-escapes its input; `Html::favicon()` honours `$secure`.
