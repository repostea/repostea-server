<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class LanguageHelperTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('languages.available', [
            'en' => [
                'name' => 'English',
                'native' => 'English',
                'flag' => 'gb',
                'active' => true,
            ],
            'es' => [
                'name' => 'Spanish',
                'native' => 'Español',
                'flag' => 'es',
                'active' => true,
            ],
            'fr' => [
                'name' => 'French',
                'native' => 'Français',
                'flag' => 'fr',
                'active' => false,
            ],
        ]);
    }

    #[Test]
    public function localized_route_adds_current_locale_to_parameters(): void
    {
        App::setLocale('es');

        $url = localized_route('home');

        $this->assertStringContainsString('/es', $url);
    }

    #[Test]
    public function localized_route_uses_specified_locale(): void
    {
        App::setLocale('es');

        $url = localized_route('home', ['locale' => 'en']);

        $this->assertStringContainsString('/en', $url);
        $this->assertStringNotContainsString('/es', $url);
    }

    #[Test]
    public function localized_route_works_with_all_parameters(): void
    {
        App::setLocale('es');

        $url = localized_route('manifesto', ['extra' => 'value']);

        $this->assertStringContainsString('/es/manifesto', $url);
        $this->assertStringContainsString('extra=value', $url);
    }

    #[Test]
    public function get_language_switchers_returns_only_active_languages(): void
    {
        // Make a request to an existing route to set the current route
        $this->get('/es');

        App::setLocale('es');

        $switchers = get_language_switchers();

        // Test is using mocked config from setUp which has 2 active languages (en and es)
        $this->assertCount(2, $switchers);
        $this->assertContains('en', array_column($switchers, 'code'));
        $this->assertContains('es', array_column($switchers, 'code'));
        $this->assertNotContains('fr', array_column($switchers, 'code'));
    }

    #[Test]
    public function get_language_switchers_has_correct_structure(): void
    {
        // Make a request to an existing route to set the current route
        $this->get('/en');

        App::setLocale('en');

        $switchers = get_language_switchers();

        $expectedKeys = ['code', 'name', 'native', 'flag', 'url', 'active'];
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $switchers[0]);
        }
    }

    #[Test]
    public function get_language_switchers_marks_current_locale_as_active(): void
    {
        // Make a request to an existing route to set the current route
        $this->get('/es');

        App::setLocale('es');

        $switchers = get_language_switchers();

        $esLanguage = collect($switchers)->firstWhere('code', 'es');

        $this->assertNotNull($esLanguage);
        $this->assertTrue($esLanguage['active']);
    }
}
