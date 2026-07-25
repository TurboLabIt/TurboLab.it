<?php
namespace App\Tests\Security;

use App\Controller\Editor\ArticleEditorController;
use App\Tests\BaseT;
use DOMDocument;
use DOMXPath;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Mime\Address;


/**
 * Anti-regression guard for docs/security-audit.md finding #30
 * ("XSS: username decodificato nella status bar dell'editor, jQuery .html()") — now RESOLVED.
 *
 * ArticleEditBaseController::handleNotification() appends each notification recipient's display name
 * to the JSON "message". That name is a phpBB username returned by User::getUsername(), which runs
 * HtmlProcessorBase::decode() — so a stored-encoded `<img src=x onerror=…>` username comes back as
 * live markup. article-edit-statusbar.js renders the message with jQuery .html() (attached node), so
 * the payload would execute in the publishing editor's session. phpBB's validate_username forbids
 * quotes but not angle brackets, and fixture user 4015 proves such an account is registrable.
 *
 * Fix: handleNotification() now escapes the names via formatRecipientNames() (htmlspecialchars)
 * before they enter the message. This test drives that method with hostile Address names.
 */
class EditorNotificationUsernameXssTest extends BaseT
{
    /** Invoke the protected formatRecipientNames() without booting the controller. */
    private function formatRecipientNames(array $recipients) : string
    {
        $instance = (new ReflectionClass(ArticleEditorController::class))->newInstanceWithoutConstructor();
        return (new ReflectionMethod(ArticleEditorController::class, 'formatRecipientNames'))
            ->invoke($instance, $recipients);
    }


    public function testRecipientUsernamesAreHtmlEscapedInStatusBarMessage() : void
    {
        $message = $this->formatRecipientNames([
            new Address('attacker@example.com', '<img src=x onerror=alert(document.domain)>'),
            new Address('editor@example.com', 'Mario "Rossi" & Co'),
        ]);

        // the markup must survive only as escaped text...
        $this->assertStringNotContainsString('<img', $message);
        $this->assertStringContainsString('&lt;img', $message);

        // ...and rendering the message the way the status bar does (.html()) yields no live node
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<div id="root">' . $message . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $this->assertSame(0, $xpath->query('//img')->length, "Injected <img> in status-bar message: $message");
        foreach ($xpath->query('//@*') as $attr) {
            $this->assertDoesNotMatchRegularExpression('/^on/i', $attr->nodeName,
                "Injected event-handler attribute in status-bar message: $message");
        }
    }


    public function testNormalUsernameIsPreserved() : void
    {
        $message = $this->formatRecipientNames([new Address('u@example.com', 'Mario Rossi')]);
        $this->assertStringContainsString('Mario Rossi', $message);
    }
}
