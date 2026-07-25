<?php
namespace App\Tests\Security;

use App\Command\ForumIntegrationBuilderCommand;
use App\Service\Cms\Article;
use App\Service\Factory;
use App\Tests\BaseT;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionMethod;
use Twig\Environment;
use Twig\Loader\ArrayLoader;


/**
 * Anti-regression guard for docs/security-audit.md finding #27
 * ("SSTI: il corpo dell'articolo 161 finisce in un template Twig di phpBB") — now RESOLVED.
 *
 * ForumIntegrationBuilderCommand reads the forum-rules article (161) body and the privacy-policy
 * article (617) title and writes them into src/Forum/ext-turbolabit/.../template/event/*.html.
 * phpBB compiles those files as Twig (autoescape off, no SandboxExtension) and renders
 * ucp_agreement_terms_before.html on the ANONYMOUS registration page (ucp.php?mode=register). An
 * active PoC confirmed the chain end-to-end: a `{{ 7*7 }}` / `{% … %}` in the article body/title
 * survives storage + display + the build, reaches the generated file live, and is EVALUATED — an
 * SSTI reachable by any ROLE_EDITOR or author of those two articles (a wider set than shell/sudo
 * users). The fix neutralizes Twig delimiters (braces → HTML entities) before the CMS content is
 * written into phpBB's template tree; the browser still shows literal `{` `}` to the reader.
 *
 * This test injects a probe into both articles, drives the REAL param builder + the REAL
 * registration template, then evaluates the result exactly the way phpBB does. It fails loudly if
 * the neutralization is ever dropped (the probe would evaluate to 49 / 42 again).
 */
class ForumIntegrationSstiTest extends BaseT
{
    // {{ 7*7 }} and {{ 6*7 }} render as 49 / 42 ONLY if phpBB's Twig actually evaluates them —
    // a literal (neutralized) copy keeps the source text, so the arithmetic result is the tell.
    private const string BODY_PROBE  = '<p>SSTI-BODY {{ 7*7 }} {% if 1 == 1 %}x{% endif %} end</p>';
    private const string TITLE_PROBE = 'SSTI-TITLE {{ 6*7 }} end';


    public function testForumRulesBodyAndTitleAreNotEvaluatedAsTwigByPhpBB() : void
    {
        static::loginAsSystem();

        /** @var Factory $factory */                     $factory = static::getService(Factory::class);
        /** @var EntityManagerInterface $em */           $em      = static::getEntityManager();
        /** @var Environment $twig */                    $twig    = static::getService(Environment::class);
        /** @var ForumIntegrationBuilderCommand $cmd */  $cmd     = static::getService(ForumIntegrationBuilderCommand::class);

        // sanity / negative control: the phpBB-like environment really does evaluate Twig, so the
        // "must not contain 49/42" assertions below are meaningful rather than vacuous.
        $this->assertSame('49', $this->renderAsPhpBB('{{ 7*7 }}'),
            'The phpBB-like Twig environment must evaluate {{ 7*7 }} — otherwise this test proves nothing.');

        $rulesEntity   = $factory->createArticleEditor()->load(Article::ID_FORUM_RULES)->getEntity();
        $privacyEntity = $factory->createArticleEditor()->load(Article::ID_PRIVACY_POLICY)->getEntity();
        $originalBody  = $rulesEntity->getBody();
        $originalTitle = $privacyEntity->getTitle();

        try {
            $rulesEntity->setBody(self::BODY_PROBE);
            $privacyEntity->setTitle(self::TITLE_PROBE);
            $em->flush();

            // Build params exactly as the command does (the fix lives in buildRegisterParams), then
            // render the real registration template — its `{{ mainText|raw }}` is the sink.
            // protected methods are invokable via reflection without setAccessible() since PHP 8.1
            $buildRegisterParams = new ReflectionMethod(ForumIntegrationBuilderCommand::class, 'buildRegisterParams');
            $params    = $buildRegisterParams->invoke($cmd);
            $generated = $twig->render('forum/registrazione.html.twig', $params);

            // Evaluate the generated file the way phpBB does: Twig, autoescape OFF, no sandbox.
            $evaluated = $this->renderAsPhpBB($generated);

            $this->assertStringNotContainsString('SSTI-BODY 49', $evaluated,
                'Article-161 body {{ 7*7 }} was evaluated by phpBB Twig — SSTI #27 regressed.');
            $this->assertStringNotContainsString('SSTI-TITLE 42', $evaluated,
                'Article-617 title {{ 6*7 }} was evaluated by phpBB Twig — SSTI #27 regressed.');

            // The generated phpBB template must carry no live delimiters from CMS content...
            $this->assertStringNotContainsString('{{ 7*7 }}', $generated, 'Live {{ }} left in the generated template.');
            $this->assertStringNotContainsString('{% if', $generated, 'Live {% %} left in the generated template.');

            // ...but the content itself must survive (neutralized), not be destroyed.
            $this->assertStringContainsString('SSTI-BODY', $evaluated, 'The body content must survive as literal text.');
            $this->assertStringContainsString('SSTI-TITLE', $evaluated, 'The title content must survive as literal text.');

        } finally {
            $rulesEntity->setBody($originalBody);
            $privacyEntity->setTitle($originalTitle);
            $em->flush();
        }
    }


    /** Render a string through a Twig environment configured like phpBB's (autoescape off, no sandbox). */
    private function renderAsPhpBB(string $templateSource) : string
    {
        $phpbbTwig = new Environment(new ArrayLoader(['gen' => $templateSource]), ['autoescape' => false]);
        return $phpbbTwig->render('gen');
    }
}
