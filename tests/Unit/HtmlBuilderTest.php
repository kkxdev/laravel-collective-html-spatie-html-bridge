<?php

declare(strict_types=1);

namespace Kkxdev\HtmlCompat\Tests\Unit;

use Kkxdev\HtmlCompat\HtmlBuilder;
use Kkxdev\HtmlCompat\Tests\TestCase;
use Illuminate\Support\HtmlString;

class HtmlBuilderTest extends TestCase
{
    private HtmlBuilder $html;

    protected function setUp(): void
    {
        parent::setUp();
        $this->html = $this->app->make(HtmlBuilder::class);
    }

    // =========================================================================
    // Html::link()
    // =========================================================================

    public function test_link_renders_anchor(): void
    {
        $html = (string) $this->html->link('https://example.com', 'Visit us');

        $this->assertStringContainsString('<a', $html);
        $this->assertStringContainsString('href="https://example.com"', $html);
        $this->assertStringContainsString('Visit us', $html);
    }

    public function test_link_uses_url_as_title_when_no_title_given(): void
    {
        $html = (string) $this->html->link('https://example.com');

        $this->assertStringContainsString('https://example.com', $html);
    }

    public function test_link_applies_class(): void
    {
        $html = (string) $this->html->link('/', 'Home', ['class' => 'nav-link']);

        $this->assertStringContainsString('class="nav-link"', $html);
    }

    public function test_link_escapes_title_by_default(): void
    {
        $html = (string) $this->html->link('/', '<b>Bold</b>');

        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringContainsString('&lt;b&gt;', $html);
    }

    public function test_link_does_not_escape_when_escape_is_false(): void
    {
        $html = (string) $this->html->link('/', '<b>Bold</b>', [], null, false);

        $this->assertStringContainsString('<b>Bold</b>', $html);
    }

    // =========================================================================
    // Html::script()
    // =========================================================================

    public function test_script_renders(): void
    {
        $html = (string) $this->html->script('/js/app.js');

        $this->assertStringContainsString('<script', $html);
        $this->assertStringContainsString('/js/app.js', $html);
    }

    public function test_script_applies_attributes(): void
    {
        $html = (string) $this->html->script('/js/app.js', ['defer' => true]);

        $this->assertStringContainsString('defer', $html);
    }

    // =========================================================================
    // Html::style()
    // =========================================================================

    public function test_style_renders(): void
    {
        $html = (string) $this->html->style('/css/app.css');

        $this->assertStringContainsString('<link', $html);
        $this->assertStringContainsString('/css/app.css', $html);
    }

    // =========================================================================
    // Html::image()
    // =========================================================================

    public function test_image_renders(): void
    {
        $html = (string) $this->html->image('/img/logo.png', 'Logo');

        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('src="/img/logo.png"', $html);
        $this->assertStringContainsString('alt="Logo"', $html);
    }

    public function test_image_applies_extra_attributes(): void
    {
        $html = (string) $this->html->image('/img/logo.png', null, ['class' => 'img-fluid', 'width' => '100']);

        $this->assertStringContainsString('class="img-fluid"', $html);
        $this->assertStringContainsString('width="100"', $html);
    }

    // =========================================================================
    // Html::tag()
    // =========================================================================

    public function test_tag_renders_opening_tag(): void
    {
        $html = (string) $this->html->tag('div', ['class' => 'wrapper', 'id' => 'main']);

        $this->assertStringContainsString('<div', $html);
        $this->assertStringContainsString('class="wrapper"', $html);
        $this->assertStringContainsString('id="main"', $html);
    }

    // =========================================================================
    // Html::entities() / Html::decode()
    // =========================================================================

    public function test_entities_encodes_special_chars(): void
    {
        $result = $this->html->entities('<b>Hello & World</b>');

        $this->assertStringContainsString('&lt;', $result);
        $this->assertStringContainsString('&amp;', $result);
    }

    public function test_decode_reverses_entities(): void
    {
        $result = $this->html->decode('&lt;b&gt;Hello&lt;/b&gt;');

        $this->assertStringContainsString('<b>', $result);
    }

    // =========================================================================
    // Macro support
    // =========================================================================

    public function test_html_macro_can_be_registered_and_called(): void
    {
        HtmlBuilder::macro('badge', function (string $text, string $color = 'primary') {
            return new HtmlString("<span class=\"badge bg-{$color}\">{$text}</span>");
        });

        $result = (string) $this->html->badge('New', 'success');

        $this->assertStringContainsString('badge bg-success', $result);
        $this->assertStringContainsString('New', $result);
    }

    // =========================================================================
    // Graceful degradation
    // =========================================================================

    public function test_unsupported_method_returns_empty_html_string(): void
    {
        $result = $this->html->nonExistentMethod();

        $this->assertInstanceOf(HtmlString::class, $result);
        $this->assertSame('', (string) $result);
    }
}
