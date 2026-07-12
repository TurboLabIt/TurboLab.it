<?php
namespace App\Tests\Smoke;

use App\Tests\BaseT;
use Symfony\Contracts\Cache\TagAwareCacheInterface;


class ManifestTest extends BaseT
{
    public function testManifestIsValidJson()
    {
        // the controller caches the rendered manifest for 90 days:
        // evict it so this test always exercises a fresh render of the template
        static::getService(TagAwareCacheInterface::class)->delete('manifest.json');

        $json = $this->fetchHtml('/manifest.json', toLower: false);
        $this->assertJson($json, "manifest.json is not valid JSON!");

        $manifest = json_decode($json, true);
        $this->assertSame('TurboLab.it', $manifest["short_name"]);
        $this->assertSame('TurboLab.it | Guide PC, Windows, Linux, Android e Bitcoin', $manifest["name"]);
        $this->assertCount(4, $manifest["icons"]);
        $this->assertCount(4, $manifest["shortcuts"]);

        foreach($manifest["shortcuts"] as $shortcut) {
            $this->assertIsArray($shortcut["icons"], "Every shortcut must have its own icons array");
            $this->assertCount(2, $shortcut["icons"]);
        }
    }
}
