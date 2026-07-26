<?php
namespace App\Tests\Security;

use App\Tests\BaseT;
use ReflectionMethod;
use TurboLabIt\MessengersBundle\LinkedInException;
use TurboLabIt\MessengersBundle\LinkedInPageMessenger;


/**
 * Anti-regression guard for docs/security-audit.md finding #36
 * ("Callback OAuth LinkedIn senza state: un link riscrive il token salvato") — now RESOLVED.
 *
 * The LinkedIn OAuth callback (LinkedInController) was a GET with no CSRF/state: it exchanged any
 * `code` from the query and overwrote the stored access token. getAuthCodeUrl() emitted no `state`
 * and nothing validated one, so a crafted link opened by an admin (phpBB cookie auth has no
 * SameSite) made the site exchange the attacker's `code`.
 *
 * Fix (in TurboLabIt/php-symfony-messenger): getAuthCodeUrl() now mints a CSPRNG `state`, stores it
 * server-side in var/ (the firewall is stateless, so no session — same reason the token lives in
 * var/), and the callback rejects any request whose `state` doesn't match the stored one.
 * verifyAndConsumeOAuthState() is fail-closed, constant-time, single-use and TTL-bound.
 */
class LinkedInOAuthStateTest extends BaseT
{
    private function messenger() : LinkedInPageMessenger
    {
        return static::getService(LinkedInPageMessenger::class);
    }


    private function stateFilePath(LinkedInPageMessenger $messenger) : string
    {
        return (new ReflectionMethod(LinkedInPageMessenger::class, 'getVarDirPath'))
            ->invoke($messenger, LinkedInPageMessenger::OAUTH_STATE_FILENAME);
    }


    private function generateStoredState(LinkedInPageMessenger $messenger) : string
    {
        return (new ReflectionMethod(LinkedInPageMessenger::class, 'generateAndStoreOAuthState'))->invoke($messenger);
    }


    private function assertStateRejected(LinkedInPageMessenger $messenger, ?string $state, string $why) : void
    {
        try {
            $messenger->verifyAndConsumeOAuthState($state);
            $this->fail("Expected the OAuth callback to be rejected: $why");
        } catch (LinkedInException) {
            $this->addToAssertionCount(1);
        }
    }


    public function testAuthorizationUrlCarriesTheServerStoredState() : void
    {
        $messenger = $this->messenger();
        $url = $messenger->getAuthCodeUrl('https://dev0.turbolab.it/setup/linkedin/auth/code-return-to/');

        $this->assertSame(1, preg_match('/[?&]state=([0-9a-f]{64})(?:&|$)/', $url, $matches),
            "The authorization URL must carry a 64-hex CSPRNG state: $url");
        $this->assertStringEqualsFile($this->stateFilePath($messenger), $matches[1],
            "The state in the URL must equal the server-stored state.");

        // consume it so the shared state file doesn't leak into other tests
        $messenger->verifyAndConsumeOAuthState($matches[1]);
    }


    public function testValidStateIsAcceptedExactlyOnce() : void
    {
        $messenger = $this->messenger();
        $state = $this->generateStoredState($messenger);

        $messenger->verifyAndConsumeOAuthState($state); // must not throw
        $this->addToAssertionCount(1);

        // single-use: the state was consumed, so a replay must be rejected
        $this->assertStateRejected($messenger, $state, 'replayed (single-use) state');
    }


    public function testForgedNullOrMissingStateIsRejected() : void
    {
        $messenger = $this->messenger();

        $this->generateStoredState($messenger);
        $this->assertStateRejected($messenger, 'deadbeef', 'wrong state value');   // also consumes the file

        $this->generateStoredState($messenger);
        $this->assertStateRejected($messenger, null, 'missing state param');

        // nothing was initiated → no stored state at all
        $this->assertStateRejected($messenger, 'whatever', 'no stored state');
    }


    public function testExpiredStateIsRejected() : void
    {
        $messenger = $this->messenger();
        $state = $this->generateStoredState($messenger);

        // age the stored state past its TTL
        touch($this->stateFilePath($messenger), time() - LinkedInPageMessenger::OAUTH_STATE_TTL_SECONDS - 60);

        $this->assertStateRejected($messenger, $state, 'expired state');
    }
}
