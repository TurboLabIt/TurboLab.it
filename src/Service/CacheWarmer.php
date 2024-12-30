<?php
namespace App\Service;

use App\Controller\BaseController;
use App\ServiceCollection\BaseServiceEntityCollection;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use App\ServiceCollection\Cms\ArticleCollection;
use App\ServiceCollection\Cms\TagCollection;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use TurboLabIt\BaseCommand\Service\BashFx;


class CacheWarmer implements CacheWarmerInterface
{
    const string CLI_COMMAND_NAME = "🔥 TLI Cache Warming";
    protected ?BashFx $bashFx = null;
    protected array $arrRequestedUrls = [];


    public function __construct(
        protected ParameterBagInterface $parameterBag,
        protected TagAwareCacheInterface $cachePool,
        protected UrlGeneratorInterface $urlGenerator, protected HttpClientInterface $httpClient,
        protected TagCollection $tagCollection, protected ArticleCollection $articleCollection,
    )
    {}


    public function warmUp(string $cacheDir, ?string $buildDir = null) : array
    {
        if(PHP_SAPI === 'cli') {

            $this->bashFx = new BashFx();
            $this->bashFx->setIo( new ArgvInput(), new ConsoleOutput());
        }

        $currentEnv = $this->parameterBag->get("kernel.environment");
        if( in_array($currentEnv, ["dev"]) ) {

            $this->bashFx?->fxOK(static::CLI_COMMAND_NAME . " skipped on {$currentEnv}");
            return [];
        }

        $this->bashFx?->fxHeader("🔥 " . static::CLI_COMMAND_NAME);

        $this
            ->warmHomePage()
            ->warmCategories()
            ->warmNewestArticles()
            ->warmTopViewsArticles();

        $this->bashFx?->fxEndFooter(0, static::CLI_COMMAND_NAME);

        return [];
    }

    public function isOptional() : bool { return true; }


    public function warmHomePage() : static
    {
        $this->bashFx?->fxTitle("Warming the main front pages...");

        foreach(['app_home', 'app_news'] as $route) {

            $url = $this->urlGenerator->generate($route, [], UrlGeneratorInterface::ABSOLUTE_URL);
            $this->arrRequestedUrls[] = $url;

            $this->cachePool->invalidateTags([$route]);

            $this->httpRequest($url);
        }

        return $this;
    }


    public function warmCategories() : static
    {
        $this->bashFx?->fxTitle("Warming the categories...");
        return $this->requestEach( $this->tagCollection->loadCategories(), true );
    }


    public function warmNewestArticles() : static
    {
        $this->bashFx?->fxTitle("Warming the latest articles...");
        return
            $this
                ->requestEach( $this->articleCollection->loadLatestPublished() )
                ->requestEach( $this->articleCollection->loadLatestNewsPublished() );
    }


    public function warmTopViewsArticles() : static
    {
        $this->bashFx?->fxTitle("Warming top views articles...");
        return
            $this
                ->requestEach( $this->articleCollection->loadTopViews(1, 180) )
                ->requestEach( $this->articleCollection->loadTopViews() );
    }


    protected function requestEach(BaseServiceEntityCollection $collection, bool $wipeCache = false) : static
    {
        foreach($collection as $item) {

            $url = $item->getUrl();

            if( in_array($url, $this->arrRequestedUrls) ) {
                continue;
            }

            $this->arrRequestedUrls[] = $url;

            if($wipeCache) {
                $this->cachePool->invalidateTags([ $item->getCacheKey() ]);
            }

            $this->httpRequest($url);
        }

        return $this;
    }


    protected function httpRequest(string $url) : static
    {
        $this->bashFx?->fxInfo($url);
        $this->httpClient->request('GET', $url, [
            'verify_peer'   => false,
            'verify_host'   => false,
            'headers'       => [BaseController::CACHE_WARMER_HEADER => 1]
        ]);

        return $this;
    }
}
