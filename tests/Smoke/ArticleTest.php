<?php
namespace App\Tests\Smoke;

use App\Entity\Cms\Article as ArticleEntity;
use App\Service\Cms\Article;
use App\Service\Cms\HtmlProcessor;
use App\Tests\BaseT;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DomCrawler\Crawler;


class ArticleTest extends BaseT
{
    protected static iterable $arrArticleEntity;

    public function testingPlayground()
    {
        /** @var Article $article */
        $article = static::getService("App\\Service\\Cms\\Article");
        $article->load(488);

        $url = $article->getUrl();
        $crawler = $this->fetchDomNode($url);
        $this->articleTitleAsH1Checker($article, $crawler);
    }


    public function testSpecialArticle()
    {
        // 👀 https://turbolab.it/1939

        /** @var Article $article */
        $article = static::getService("App\\Service\\Cms\\Article");
        $article->load(Article::ID_QUALITY_TEST);

        $url = $article->getUrl();

        $wrongTagUrl = '/pc-642/come-svolgere-test-automatici-turbolab.it-verifica-impianto-collaudo-oaueei-gbp-double-quoted-single-quoted-fine-1939';
        $this->expectRedirect($wrongTagUrl, $url);

        $wrongTagUrl = '/undefined-tag-99999999/come-svolgere-test-automatici-turbolab.it-verifica-impianto-collaudo-oaueei-gbp-double-quoted-single-quoted-fine-1939';
        $this->expectRedirect($wrongTagUrl, $url);

        $wrongArticleTitleUrl = '/turbolab.it-1/bao-1939';
        $this->expectRedirect($wrongArticleTitleUrl, $url);

        $crawler = $this->fetchDomNode($url);

        // H1
        $this->articleTitleAsH1Checker($article, $crawler, 'Come svolgere test automatici su TurboLab.it (verifica dell&apos;impianto &amp; &quot;collaudo&quot;) | @ &amp; òàùèéì # § |!&quot;£$%&amp;/()=?^ &lt; &gt; &quot;double-quoted&quot; &apos;single quoted&apos; \ / | » fine');

        // H2
        $crawler = $this->fetchDomNode($url, 'article');
        $H2s = $crawler->filter('h2');
        $countH2 = $H2s->count();
        $this->assertGreaterThan(3, $countH2);

        // intro paragraph
        $firstPContent = $crawler->filter('p')->first()->html();
        $this->assertStringContainsString('Questo è un articolo <em>di prova</em>, utilizzato dai <strong>test automatici</strong>', $firstPContent);

        // header (comments, updated, publishing status, authors)
        $articleMetaDataAsLi = $crawler->filter('ul')->first()->filter('li');
        $arrUnmatchedUlContent = ['comment', 'aggiornat', 'pubblicato', 'a cura di'];
        foreach($articleMetaDataAsLi as $li) {

            $liText = $li->textContent;

            foreach($arrUnmatchedUlContent as $k => $textToSearch) {

                if( stripos($liText, $textToSearch) !== false ) {

                    unset($arrUnmatchedUlContent[$k]);
                    break;
                }
            }
        }

        $this->assertEmpty($arrUnmatchedUlContent, 'Not found element(s): ' . implode('##', $arrUnmatchedUlContent) );

        $articleBodyCrawler = $crawler->filter('.tli-article-body');

            // first <li>s of the article (body content)
        $summaryLi = $articleBodyCrawler->filter('ul')->first()->filter('li');
        $arrUnmatchedUlContent = [
            'video da YouTube', 'formattazione',
            'link ad altri articoli', 'link a pagine di tag', 'link a file',
            'link alle pagine degli autori', 'caratteri "delicati"',
            'tutte le immagini'
        ];

        foreach($summaryLi as $li) {

            $liText = $li->textContent;

            foreach($arrUnmatchedUlContent as $k => $textToSearch) {

                if( stripos($liText, $textToSearch) !== false ) {

                    unset($arrUnmatchedUlContent[$k]);
                    break;
                }
            }
        }

        $this->assertEmpty($arrUnmatchedUlContent, 'Not found element(s): ' . implode('##', $arrUnmatchedUlContent) );

        // YouTube
        $iframes = $articleBodyCrawler->filter('iframe');
        $countYouTubeIframe = 0;
        foreach($iframes as $iframe) {

            $src = $iframe->getAttribute("src");
            if( stripos($src, 'youtube-nocookie.com/embed') !== false ) {
                $countYouTubeIframe++;
            }
        }

        $this->assertGreaterThanOrEqual(2, $countYouTubeIframe);

        // formatting styles
        $formattingStylesOl = $articleBodyCrawler->filter('ol')->first()->filter('li');
        $arrExpectedNodes = [1 => 'strong', 2 => 'em', 3 => 'code', 4 => 'ins'];
        foreach($arrExpectedNodes as $index => $expectedTagName) {

            $nodeTagName = $formattingStylesOl->getNode($index - 1)->firstChild->tagName;
            $this->assertEquals($expectedTagName, $nodeTagName);
        }

        //
        $this->internalLinksChecker($articleBodyCrawler);

        // fragile chars
        $fragileCharsH2Text = 'Caratteri "delicati"';
        $fragileCharsActualValue = null;
        foreach($H2s as $h2) {

            $h2Content = $h2->textContent;
            if( $h2Content === $fragileCharsH2Text) {
                $fragileCharsActualValue = $h2->nextSibling->nodeValue;
            }
        }

        $this->assertEquals('@ & òàùèéì # § |!"£$%&/()=?^ < > "double-quoted" \'single quoted\' \ / | » fine', $fragileCharsActualValue);

        //
        $this->internalImagesChecker($articleBodyCrawler);
    }


    public function test404()
    {
        // 👀 https://turbolab.it/turbolab.it-1/bao-9999
        $this->expect404('/turbolab.it-1/bao-9999');
    }


    public static function articleToTestProvider()
    {
        if( empty(static::$arrArticleEntity) ) {
            static::$arrArticleEntity = static::getEntityManager()->getRepository(ArticleEntity::class)->findLatestPublished();
        }

        /** @var ArticleEntity $entity */
        foreach(static::$arrArticleEntity as $entity) {
            yield [[
                "entity"    => $entity,
                "service"   => static::getService("App\\Service\\Factory")->createArticle($entity)
            ]];
        }
    }


    #[DataProvider('articleToTestProvider')]
    public function testOpenAllArticles(array $arrData)
    {
        static::$client = null;

        $entity  = $arrData["entity"];
        $article = $arrData["service"];

        $shortUrl = $article->getShortUrl();
        $assertFailureMessage = "Failing URL: $shortUrl";
        $this->assertStringEndsWith("/" . $entity->getId(), $shortUrl, $assertFailureMessage);

        $url = $article->getUrl();
        $this->assertStringEndsWith("-" . $entity->getId(), $url, $assertFailureMessage);

        $this->expectRedirect($shortUrl, $url);

        $crawler = $this->fetchDomNode($url);

        //
        $this->articleTitleAsH1Checker($article, $crawler);
    }


    protected function articleTitleAsH1Checker(Article $article, Crawler $crawler, ?string $expectedH1 = null) : void
    {
        $assertFailureMessage = "Failing URL: " . $article->getShortUrl();

        $title = $article->getTitleWithFreshUpdatedAt();
        $this->assertNotEmpty($title, $assertFailureMessage);

        foreach(HtmlProcessor::ACCENTED_LETTERS as $accentedLetter) {

            $accentedLetterEntity = htmlentities($accentedLetter);
            $this->assertStringNotContainsString($accentedLetterEntity, $title);
        }

        $this->assertStringNotContainsString('&nbsp;', $title);

        $H1FromCrawler = $crawler->filter('article h1')->html();
        $H1FromCrawler = $this->encodeQuotes($H1FromCrawler);
        $this->assertEquals($title, $H1FromCrawler, $assertFailureMessage);

        if( $expectedH1 !== null ) {
            $this->assertEquals($expectedH1, $H1FromCrawler, "Explict H1 check failure! " . $assertFailureMessage);
        }
    }
}
