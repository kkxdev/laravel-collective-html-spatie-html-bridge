<?php

declare(strict_types=1);

namespace Kkxdev\HtmlCompat\Support;

use Illuminate\Support\HtmlString;

/**
 * LaravelCollective-style custom components: Form::component() / Html::component().
 *
 * Form::component('bsText', 'components.form.text', ['name', 'value' => null, 'attributes' => []]);
 * Form::bsText('email', 'a@b.c');
 */
trait Componentable
{
    /** @var array<string, array{view: string, signature: array}> */
    protected static array $components = [];

    public static function component(string $name, string $view, array $signature): void
    {
        static::$components[$name] = ['view' => $view, 'signature' => $signature];
    }

    public static function hasComponent(string $name): bool
    {
        return isset(static::$components[$name]);
    }

    protected function renderComponent(string $name, array $arguments): HtmlString
    {
        $component = static::$components[$name];
        $data = [];
        $position = 0;

        foreach ($component['signature'] as $key => $default) {
            // ['name', 'value' => null]: numeric keys are required params with no default
            $variable = is_int($key) ? $default : $key;
            $default = is_int($key) ? null : $default;

            $data[$variable] = array_key_exists($position, $arguments) ? $arguments[$position] : $default;
            $position++;
        }

        return new HtmlString(view($component['view'], $data)->render());
    }

    public static function flushComponents(): void
    {
        static::$components = [];
    }
}
