<?php
namespace App\Tests\Security;

use App\Tests\BaseT;
use PHPUnit\Framework\Attributes\DataProvider;


/**
 * Anti-regression guard for docs/security-audit.md findings #37 and #43 — now RESOLVED.
 *
 * Two internal maintenance pages exposed their data to anonymous visitors:
 *   #37 /immagini/orfane        (ImageController::orphans)   — orphan-image list
 *   #43 /scarica/da-controllare (FileController::needFixing) — files-to-fix maintenance report
 *
 * Fix (per Zane, "like /info"): the page still loads for everyone (HTTP 200), but the sensitive
 * data is computed and rendered only for logged-in users — anonymous visitors get a "devi eseguire
 * login" gate + login form instead of the data. This test asserts that, for an anonymous request,
 * each page loads AND shows the login gate AND does NOT leak its data.
 */
class MaintenancePagesLoginRequiredTest extends BaseT
{
    public static function maintenancePageProvider() : array
    {
        // [ url, a data string that must appear ONLY when logged in ]
        return [
            'orphan-images (#37)'     => ['/immagini/orfane',        'le seguenti immagini non sono utilizzate'],
            'files-need-fixing (#43)' => ['/scarica/da-controllare', 'file mancanti su filesystem'],
        ];
    }


    #[DataProvider('maintenancePageProvider')]
    public function testAnonymousGetsThePageButNotTheData(string $url, string $dataMarker) : void
    {
        $html = $this->fetchHtml($url); // asserts HTTP 200 (page loads) and lowercases the body

        $this->assertStringContainsString('devi eseguire login', $html,
            "$url must load for anonymous but show the login gate (like /info).");

        $this->assertStringNotContainsString($dataMarker, $html,
            "$url must NOT expose its maintenance data to an anonymous visitor.");
    }
}
