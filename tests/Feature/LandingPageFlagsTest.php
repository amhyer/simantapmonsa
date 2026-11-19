<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LandingPageFlagsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Http::fake([
            '*' => Http::response([], 503),
        ]);
    }

    public function test_landing_hides_demo_video_newsletter_and_belum_tersedia_by_default(): void
    {
        $this->assertFalse(config('simantap.landing.show_demo_video'));
        $this->assertFalse(config('simantap.landing.show_newsletter'));

        $response = $this->get(route('landing'));
        $response->assertOk();

        $html = $response->getContent();
        $this->assertDoesNotMatchRegularExpression('/belum tersedia/i', $html);
        $this->assertStringNotContainsString('id="demo"', $html);
        $this->assertStringNotContainsString('href="#demo"', $html);
        $this->assertStringNotContainsString('id="updates"', $html);
        $this->assertStringNotContainsString('href="#updates"', $html);
        $this->assertDoesNotMatchRegularExpression('/Video demo/i', $html);
        $this->assertDoesNotMatchRegularExpression('/Newsletter/i', $html);

        // Pastikan tidak ada anchor yang mengarah ke bagian tersembunyi
        preg_match_all('/href="#([A-Za-z][A-Za-z0-9_-]*)"/', $html, $links);
        preg_match_all('/id="([A-Za-z][A-Za-z0-9_-]*)"/', $html, $ids);
        $idMap = array_fill_keys($ids[1], true);
        foreach ($links[1] as $target) {
            $this->assertArrayHasKey($target, $idMap, "Anchor #{$target} tidak memiliki elemen target saat flag false.");
        }
    }

    public function test_landing_shows_demo_video_and_newsletter_when_flags_enabled(): void
    {
        config([
            'simantap.landing.show_demo_video' => true,
            'simantap.landing.show_newsletter' => true,
        ]);

        $response = $this->get(route('landing'));
        $response->assertOk()
            ->assertSee('id="demo"', false)
            ->assertSee('href="#demo"', false)
            ->assertSee('Video demo belum tersedia')
            ->assertSee('id="updates"', false)
            ->assertSee('Newsletter email belum tersedia');
    }
}
