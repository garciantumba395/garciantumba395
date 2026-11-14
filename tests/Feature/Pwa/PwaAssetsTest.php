<?php

namespace Tests\Feature\Pwa;

use Tests\TestCase;

class PwaAssetsTest extends TestCase
{
    public function test_manifest_is_valid_and_icons_exist_with_the_declared_size(): void
    {
        $m = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('standalone', $m['display']);
        $this->assertSame('/', $m['start_url']);
        $this->assertSame('fr', $m['lang']);
        $purposes = [];
        foreach ($m['icons'] as $icon) {
            $file = public_path(ltrim($icon['src'], '/'));
            $this->assertFileExists($file);
            [$w, $h] = getimagesize($file);
            $this->assertSame($icon['sizes'], "{$w}x{$h}");
            $purposes[] = $icon['purpose'];
        }
        $this->assertContains('maskable', $purposes);
    }

    public function test_layouts_reference_the_manifest(): void
    {
        foreach (['app', 'guest'] as $layout) {
            $this->assertStringContainsString('/manifest.webmanifest', file_get_contents(resource_path("views/layouts/{$layout}.blade.php")));
        }
    }

    public function test_service_worker_never_caches_pages_or_data(): void
    {
        $sw = file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("req.method !== 'GET'", $sw);
        $this->assertStringContainsString("'/offline.html'", $sw);
        // Les navigations ne passent jamais par le cache : réseau, puis page hors connexion.
        $this->assertMatchesRegularExpression('/navigate.*?fetch\(req\)\.catch/s', $sw);
        // Seules les ressources statiques versionnées sont mises en cache.
        $this->assertSame(2, substr_count($sw, 'startsWith('));
        $this->assertStringNotContainsString('livewire', strtolower(str_replace('(jamais', '', explode('*/', $sw)[1])));
        $this->assertFileExists(public_path('offline.html'));
    }
}
