<?php
namespace App\Tests\Security;

use App\Command\ShareOnSocialCommand;
use App\Tests\BaseT;
use ReflectionMethod;
use TurboLabIt\MessengersBundle\TelegramMessenger;


/**
 * Anti-regression guard for docs/security-audit.md finding #29
 * ("Titolo articolo non escaped nel messaggio Telegram, parse_mode=HTML") — now RESOLVED.
 *
 * Article/video titles are stored RAW (a <script> survives, cfr. #19). ShareOnSocialCommand used
 * to interpolate the title straight into the Telegram message `<b>… <a href="…">$title</a></b>`
 * sent with parse_mode=HTML, and TelegramMessenger::messageEncoder() (html_entity_decode, keeping
 * only < > & " protected) does not neutralize it. A PoC confirmed a crafted title reaches the
 * channel payload as a live <a> — phishing under the official brand — or breaks the message so the
 * Telegram API rejects it (availability).
 *
 * Fix: buildTelegramArticleMessageHtml() HTML-escapes the dynamic parts. htmlspecialchars() emits
 * exactly the four entities messageEncoder() preserves, so the escaping survives to Telegram as
 * literal text. These tests drive the real composer + the real messageEncoder() (the exact string
 * POSTed to Telegram) and fail if the escaping is ever dropped.
 */
class ShareOnSocialTelegramEscapeTest extends BaseT
{
    // closes the legit anchor + bold, opens an attacker anchor, reopens bold
    private const string EVIL_TITLE =
        '</a></b> <a href="https://evil.example/phish">CLICCA QUI</a> <b>';


    /** The exact string ShareOnSocialCommand would POST to Telegram for the given title. */
    private function telegramPayloadFor(string $title, string $url = 'https://turbolab.it/some-article-123') : string
    {
        $command  = static::getService(ShareOnSocialCommand::class);
        $telegram = static::getService(TelegramMessenger::class);

        $composed = (new ReflectionMethod(ShareOnSocialCommand::class, 'buildTelegramArticleMessageHtml'))
            ->invoke($command, '📰', $url, $title);

        // messageEncoder() is the final transform sendMessage() applies before POSTing.
        return (new ReflectionMethod(TelegramMessenger::class, 'messageEncoder'))->invoke($telegram, $composed);
    }


    public function testCraftedTitleCannotInjectMarkupIntoTelegramMessage() : void
    {
        $url  = 'https://turbolab.it/some-article-123';
        $sent = $this->telegramPayloadFor(self::EVIL_TITLE, $url);

        // the attacker's anchor must NOT survive as live markup...
        $this->assertStringNotContainsString('href="https://evil.example/phish"', $sent,
            'A crafted title injected a live <a> into the Telegram channel post (#29).');
        // ...it must appear only as escaped literal text...
        $this->assertStringContainsString('&lt;/a&gt;', $sent);
        // ...and the legit message structure must be preserved.
        $this->assertStringContainsString("<a href=\"$url\">", $sent);
        $this->assertStringEndsWith('</a></b>', $sent);
    }


    public function testUnescapedCompositionWouldInject() : void
    {
        // Negative control: the same payload composed WITHOUT the fix must inject — otherwise the
        // assertions above would pass vacuously (e.g. if messageEncoder silently stripped anchors).
        $telegram = static::getService(TelegramMessenger::class);
        $rawComposed = '<b>📰 <a href="https://turbolab.it/x-1">' . self::EVIL_TITLE . '</a></b>';
        $sent = (new ReflectionMethod(TelegramMessenger::class, 'messageEncoder'))->invoke($telegram, $rawComposed);

        $this->assertStringContainsString('href="https://evil.example/phish"', $sent,
            'Sanity: an unescaped title should reach Telegram as a live anchor.');
    }


    public function testSpecialCharsBecomeValidTelegramHtml() : void
    {
        // Telegram HTML requires < > & escaped in text; a bare & would make the API reject the
        // message (availability). The fix must produce only escaped entities.
        $sent = $this->telegramPayloadFor('Tom & Jerry <win> "q"', 'https://turbolab.it/x-1');

        $this->assertStringContainsString('Tom &amp; Jerry', $sent);
        $this->assertStringContainsString('&lt;win&gt;', $sent);
        $this->assertStringNotContainsString('Tom & Jerry', $sent, 'A bare & must not reach Telegram.');
    }
}
