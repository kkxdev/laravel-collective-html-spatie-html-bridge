<?php

declare(strict_types=1);

namespace Kkxdev\HtmlCompat\Contracts;

use Illuminate\Support\HtmlString;

interface FormBuilderInterface
{
    public function open(array $options = []): HtmlString;

    public function model(mixed $model, array $options = []): HtmlString;

    public function close(): HtmlString;

    public function token(): HtmlString;

    public function label(string $name, ?string $value = null, array $options = [], bool $escape = true): HtmlString;

    public function text(string $name, mixed $value = null, array $options = []): HtmlString;

    public function email(string $name, mixed $value = null, array $options = []): HtmlString;

    public function password(string $name, array $options = []): HtmlString;

    public function hidden(string $name, mixed $value = null, array $options = []): HtmlString;

    public function number(string $name, mixed $value = null, array $options = []): HtmlString;

    public function textarea(string $name, mixed $value = null, array $options = []): HtmlString;

    public function select(string $name, array $list = [], mixed $selected = null, array $options = []): HtmlString;

    public function checkbox(string $name, mixed $value = 1, ?bool $checked = null, array $options = []): HtmlString;

    public function radio(string $name, mixed $value = null, ?bool $checked = null, array $options = []): HtmlString;

    public function file(string $name, array $options = []): HtmlString;

    public function submit(?string $value = null, array $options = []): HtmlString;

    public function button(?string $value = null, array $options = []): HtmlString;

    public function getValueAttribute(string $name, mixed $value = null): mixed;

    public function resetModel(): void;
}
