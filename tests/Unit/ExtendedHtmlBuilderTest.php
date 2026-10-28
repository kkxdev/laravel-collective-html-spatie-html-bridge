<?php

declare(strict_types=1);

namespace Kkxdev\HtmlCompat\Tests\Unit;

use Illuminate\Support\Facades\Route;
use Kkxdev\HtmlCompat\HtmlBuilder;
use Kkxdev\HtmlCompat\Tests\TestCase;

class ExtendedHtmlBuilderTest extends TestCase
{
    private HtmlBuilder $html;

    protected function setUp(): void
    {
        parent::setUp();
        $this->html = $this->app->make(HtmlBuilder::class);
    }

    protected function tearDown(): void
    {
        HtmlBuilder::flushComponents();
        parent::tearDown();
    }

    public function test_link_asset(): void
    {
        $html = (string) $this->html->linkAsset('files/a.pdf', 'PDF', ['class' => 'dl']);

        $this->assertStringContainsString('href="http://localhost/files/a.pdf"', $html);
        $this->assertStringContainsString('>PDF</a>', $html);
        $this->assertStringContainsString('class="dl"', $html);
    }

    public function test_link_secure_asset_and_secure_link(): void
    {
        $this->assertStringContainsString('href="https://localhost/a.css"', (string) $this->html->linkSecureAsset('a.css'));
        $this->assertStringContainsString('href="https://localhost/x"', (string) $this->html->secureLink('x'));
    }

    public function test_link_route(): void
    {
        Route::get('/users/{id}', fn () => '')->name('users.show');
        app('router')->getRoutes()->refreshNameLookups();

        $html = (string) $this->html->linkRoute('users.show', 'View', ['id' => 5], ['class' => 'btn']);

        $this->assertStringContainsString('href="http://localhost/users/5"', $html);
        $this->assertStringContainsString('>View</a>', $html);
    }

    public function test_link_action(): void
    {
        Route::get('/home', [DummyController::class, 'index']);
        app('router')->getRoutes()->refreshActionLookups();

        $html = (string) $this->html->linkAction(DummyController::class . '@index', 'Home');

        $this->assertStringContainsString('href="http://localhost/home"', $html);
    }

    public function test_link_normalizes_relative_urls_and_honours_secure(): void
    {
        $this->assertStringContainsString('href="http://localhost/about"', (string) $this->html->link('/about', 'About'));
        $this->assertStringContainsString('href="https://localhost/about"', (string) $this->html->link('/about', 'About', [], true));
        $this->assertStringContainsString('href="https://example.com/x"', (string) $this->html->link('https://example.com/x'));
        $this->assertStringContainsString('href="#top"', (string) $this->html->link('#top', 'Top'));
        $this->assertStringContainsString('href="mailto:a@b.c"', (string) $this->html->link('mailto:a@b.c'));
    }

    public function test_email_escapes_html(): void
    {
        $out = $this->html->email('"><script>x</script>@a.c');

        $this->assertStringNotContainsString('<script>', $out);
        $this->assertStringContainsString('&#64;a.c', $out);
    }

    public function test_favicon_uses_asset_only_when_secure_given(): void
    {
        $this->assertStringContainsString('href="/f.ico"', (string) $this->html->favicon('/f.ico'));
        $this->assertStringContainsString('href="https://localhost/f.ico"', (string) $this->html->favicon('f.ico', [], true));
        $this->assertStringContainsString('href="http://localhost/f.ico"', (string) $this->html->favicon('f.ico', [], false));
    }

    public function test_mailto_and_email(): void
    {
        $this->assertStringContainsString('href="mailto:a@b.c"', (string) $this->html->mailto('a@b.c', 'Mail'));
        $this->assertSame('a&#64;b.c', $this->html->email('a@b.c'));
    }

    public function test_favicon_and_meta(): void
    {
        $this->assertStringContainsString('rel="shortcut icon"', (string) $this->html->favicon('/f.ico'));
        $this->assertStringContainsString('href="/f.ico"', (string) $this->html->favicon('/f.ico'));
        $this->assertSame('<meta name="author" content="me">', (string) $this->html->meta('author', 'me'));
    }

    public function test_lists(): void
    {
        $this->assertSame('<ul class="x"><li>a</li><li>b</li></ul>', (string) $this->html->ul(['a', 'b'], ['class' => 'x']));
        $this->assertSame('<ol><li>a</li></ol>', (string) $this->html->ol(['a']));
        $this->assertSame('<ul><li>P<ul><li>c</li></ul></li></ul>', (string) $this->html->ul(['P' => ['c']]));
        $this->assertSame('', (string) $this->html->ul([]));
    }

    public function test_lists_escape_content(): void
    {
        $this->assertStringContainsString('&lt;b&gt;', (string) $this->html->ul(['<b>']));
    }

    public function test_audio_video(): void
    {
        $this->assertSame('<audio controls><source src="a.mp3"></audio>', (string) $this->html->audio('a.mp3', ['controls' => true]));
        $this->assertStringContainsString('<source src="b.webm"><source src="b.mp4">', (string) $this->html->video(['b.webm', 'b.mp4']));
    }

    public function test_nbsp_and_attributes(): void
    {
        $this->assertSame('&nbsp;&nbsp;', (string) $this->html->nbsp(2));
        $this->assertSame(' class="a" id="b"', $this->html->attributes(['class' => 'a', 'id' => 'b']));
    }

    public function test_asset_helpers(): void
    {
        $this->assertSame('http://localhost/a.js', $this->html->asset('a.js'));
        $this->assertSame('https://localhost/a.js', $this->html->secureAsset('a.js'));
    }

    public function test_global_link_to_helpers(): void
    {
        Route::get('/r', fn () => '')->name('r');
        app('router')->getRoutes()->refreshNameLookups();

        $this->assertStringContainsString('href="https://example.com"', (string) link_to('https://example.com', 'Ex'));
        $this->assertStringContainsString('href="http://localhost/a"', (string) link_to_asset('a'));
        $this->assertStringContainsString('href="http://localhost/r"', (string) link_to_route('r', 'R'));
    }
}

class DummyController
{
    public function index(): string
    {
        return '';
    }
}
