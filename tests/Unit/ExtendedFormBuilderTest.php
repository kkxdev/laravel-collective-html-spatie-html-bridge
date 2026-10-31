<?php

declare(strict_types=1);

namespace Kkxdev\HtmlCompat\Tests\Unit;

use Carbon\Carbon;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\DataProvider;
use Kkxdev\HtmlCompat\FormBuilder;
use Kkxdev\HtmlCompat\Tests\TestCase;

class ExtendedFormBuilderTest extends TestCase
{
    private FormBuilder $form;

    protected function setUp(): void
    {
        parent::setUp();
        $this->form = $this->app->make(FormBuilder::class);
    }

    protected function tearDown(): void
    {
        FormBuilder::flushComponents();
        parent::tearDown();
    }

    /** @dataProvider simpleInputs */
    #[DataProvider('simpleInputs')]
    public function test_simple_input_types(string $method, string $type): void
    {
        $html = (string) $this->form->{$method}('field', 'v', ['class' => 'x']);

        $this->assertStringContainsString('type="' . $type . '"', $html);
        $this->assertStringContainsString('name="field"', $html);
        $this->assertStringContainsString('value="v"', $html);
        $this->assertStringContainsString('class="x"', $html);
    }

    public static function simpleInputs(): array
    {
        return [
            'url' => ['url', 'url'], 'tel' => ['tel', 'tel'], 'search' => ['search', 'search'],
            'color' => ['color', 'color'], 'range' => ['range', 'range'], 'date' => ['date', 'date'],
            'time' => ['time', 'time'], 'month' => ['month', 'month'], 'week' => ['week', 'week'],
            'datetimeLocal' => ['datetimeLocal', 'datetime-local'],
        ];
    }

    public function test_date_formats_carbon_values(): void
    {
        $date = Carbon::create(2026, 3, 9, 14, 5);

        $this->assertStringContainsString('value="2026-03-09"', (string) $this->form->date('d', $date));
        $this->assertStringContainsString('value="14:05"', (string) $this->form->time('t', $date));
        $this->assertStringContainsString('value="2026-03-09T14:05"', (string) $this->form->datetimeLocal('dt', $date));
    }

    public function test_week_uses_iso_week_numbering_year(): void
    {
        // 2024-12-30 belongs to ISO week 1 of 2025
        $html = (string) $this->form->week('w', Carbon::create(2024, 12, 30));

        $this->assertStringContainsString('value="2025-W01"', $html);
    }

    public function test_date_uses_model_value(): void
    {
        $this->form->model(['born' => Carbon::create(2000, 1, 2)]);

        $this->assertStringContainsString('value="2000-01-02"', (string) $this->form->date('born'));
    }

    public function test_reset_and_image(): void
    {
        $this->assertStringContainsString('type="reset"', (string) $this->form->reset('Clear'));
        $this->assertStringContainsString('value="Clear"', (string) $this->form->reset('Clear'));

        $html = (string) $this->form->image('img/go.png', 'go');
        $this->assertStringContainsString('type="image"', $html);
        $this->assertStringContainsString('src="http://localhost/img/go.png"', $html);
    }

    public function test_datalist(): void
    {
        $html = (string) $this->form->datalist('cities', ['Delhi', 'dl' => 'Delhi NCR']);

        $this->assertStringContainsString('<datalist id="cities">', $html);
        $this->assertStringContainsString('<option value="Delhi">', $html);
        $this->assertStringContainsString('<option value="dl">Delhi NCR</option>', $html);
        $this->assertStringEndsWith('</datalist>', $html);
    }

    public function test_select_range_and_year(): void
    {
        $html = (string) $this->form->selectRange('age', 18, 20, 19);

        $this->assertStringContainsString('<select name="age"', $html);
        $this->assertStringContainsString('value="18"', $html);
        $this->assertMatchesRegularExpression('/<option[^>]*value="19"[^>]*selected|<option[^>]*selected[^>]*value="19"/', $html);

        $this->assertStringContainsString('value="2001"', (string) $this->form->selectYear('y', 2000, 2002));
    }

    public function test_select_month(): void
    {
        $html = (string) $this->form->selectMonth('m', 3);

        $this->assertStringContainsString('January', $html);
        $this->assertStringContainsString('December', $html);
        $this->assertMatchesRegularExpression('/<option[^>]*value="3"[^>]*selected|<option[^>]*selected[^>]*value="3"/', $html);
    }

    public function test_select_placeholder_is_empty_first_option(): void
    {
        $html = (string) $this->form->select('s', ['a' => 'A'], null, ['placeholder' => 'Choose...']);

        $this->assertStringNotContainsString('placeholder=', $html);
        $this->assertLessThan(strpos($html, 'value="a"'), strpos($html, 'Choose...'));
    }

    public function test_select_multiple_with_array_selection(): void
    {
        $html = (string) $this->form->select('tags[]', ['1' => 'One', '2' => 'Two'], ['2'], ['multiple' => true]);

        $this->assertStringContainsString('multiple', $html);
        $this->assertSame(1, substr_count($html, 'selected="selected"'), $html);
    }

    public function test_select_optgroups(): void
    {
        $html = (string) $this->form->select('s', ['Group' => ['a' => 'A']]);

        $this->assertStringContainsString('<optgroup', $html);
        $this->assertStringContainsString('label="Group"', $html);
    }

    public function test_component_renders_view_with_signature_defaults(): void
    {
        View::addNamespace('t', __DIR__ . '/../views');
        FormBuilder::component('bsText', 't::text', ['name', 'value' => 'dflt', 'attributes' => []]);

        $this->assertTrue(FormBuilder::hasComponent('bsText'));
        $this->assertSame('field:dflt', trim((string) $this->form->bsText('field')));
        $this->assertSame('field:given', trim((string) $this->form->bsText('field', 'given')));
    }

    public function test_submit_renders_input_like_collective(): void
    {
        $html = (string) $this->form->submit('Save', ['class' => 'btn']);

        $this->assertStringStartsWith('<input', $html);
        $this->assertStringContainsString('type="submit"', $html);
        $this->assertStringContainsString('value="Save"', $html);
        $this->assertStringContainsString('class="btn"', $html);
        $this->assertStringNotContainsString('<button', $html);

        $this->assertStringNotContainsString('value=', (string) $this->form->submit());
    }

    public function test_open_accepts_raw_action_url_and_real_controller_actions(): void
    {
        $this->assertStringContainsString('action="/raw/url"', (string) $this->form->open(['action' => '/raw/url']));

        \Illuminate\Support\Facades\Route::get('/home', [\Kkxdev\HtmlCompat\Tests\Fixtures\DummyController::class, 'index']);
        app('router')->getRoutes()->refreshActionLookups();

        $this->assertStringContainsString(
            'action="http://localhost/home"',
            (string) $this->form->open(['action' => \Kkxdev\HtmlCompat\Tests\Fixtures\DummyController::class . '@index', 'method' => 'GET'])
        );
    }

    public function test_unknown_method_still_degrades_gracefully(): void
    {
        $this->assertSame('', (string) $this->form->doesNotExist());
    }
}
