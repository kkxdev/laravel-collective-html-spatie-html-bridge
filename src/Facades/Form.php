<?php

declare(strict_types=1);

namespace Kkxdev\HtmlCompat\Facades;

use Kkxdev\HtmlCompat\FormBuilder;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Illuminate\Support\HtmlString open(array $options = [])
 * @method static \Illuminate\Support\HtmlString model(mixed $model, array $options = [])
 * @method static \Illuminate\Support\HtmlString close()
 * @method static \Illuminate\Support\HtmlString token()
 * @method static \Illuminate\Support\HtmlString label(string $name, string|null $value = null, array $options = [], bool $escape = true)
 * @method static \Illuminate\Support\HtmlString text(string $name, mixed $value = null, array $options = [])
 * @method static \Illuminate\Support\HtmlString email(string $name, mixed $value = null, array $options = [])
 * @method static \Illuminate\Support\HtmlString password(string $name, array $options = [])
 * @method static \Illuminate\Support\HtmlString hidden(string $name, mixed $value = null, array $options = [])
 * @method static \Illuminate\Support\HtmlString number(string $name, mixed $value = null, array $options = [])
 * @method static \Illuminate\Support\HtmlString textarea(string $name, mixed $value = null, array $options = [])
 * @method static \Illuminate\Support\HtmlString select(string $name, array $list = [], mixed $selected = null, array $options = [])
 * @method static \Illuminate\Support\HtmlString checkbox(string $name, mixed $value = 1, bool|null $checked = null, array $options = [])
 * @method static \Illuminate\Support\HtmlString radio(string $name, mixed $value = null, bool|null $checked = null, array $options = [])
 * @method static \Illuminate\Support\HtmlString file(string $name, array $options = [])
 * @method static \Illuminate\Support\HtmlString submit(string|null $value = null, array $options = [])
 * @method static \Illuminate\Support\HtmlString button(string|null $value = null, array $options = [])
 * @method static \Illuminate\Support\HtmlString input(string $type, string $name, mixed $value = null, array $options = [])
 * @method static mixed getValueAttribute(string $name, mixed $value = null)
 * @method static void resetModel()
 * @method static void macro(string $name, callable $macro)
 *
 * @see \Kkxdev\HtmlCompat\FormBuilder
 */
class Form extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FormBuilder::class;
    }
}
