<?php
namespace App\Command;

use App\Entity\Cms\ArticleImage;
use App\Repository\Cms\ArticleImageRepository;
use App\Service\Cms\Article;
use App\Service\Cms\Image;
use App\Service\Entity\Article as ArticleEntity;
use App\Service\HtmlProcessorForDisplay;
use App\ServiceCollection\Cms\ArticleCollection;
use App\ServiceCollection\Cms\ImageCollection;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TurboLabIt\BaseCommand\Command\AbstractBaseCommand;


/**
 * 📚 https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/images-articles.md
 */
#[AsCommand(
    name: 'ImagesToArticles',
    description: 'Attach (via ArticleImage junction) each image to the article(s) using it, remove the unused junctions'
)]
class ImagesToArticlesCommand extends AbstractBaseCommand
{
    protected bool $allowDryRunOpt          = true;
    protected array $arrArticleMap          = [];
    protected array $arrDeletedJunctions    = [];
    protected array $arrNewJunctions        = [];
    protected array $arrImagesNotFound      = [];


    public function __construct(
        protected ArticleCollection $articles, protected ImageCollection $images,
        protected ArticleImageRepository $articleImageRepository, protected HtmlProcessorForDisplay $htmlProcessor,
        protected EntityManagerInterface $entityManager
    )
    {
        parent::__construct();
    }


    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        parent::execute($input, $output);

        $this
            ->fxTitle("🚚 Loading articles...")
            ->articles->loadAll();

        $articlesNum = $this->articles->count();
        $this->fxOK("##$articlesNum## articles(s) loaded");

        $this->fxTitle("🔬 Scanning each article text to determine which images it actually uses....");
        $this->processItems($this->articles, [$this, 'scanOneArticle']);


        $junctions =
            $this
                ->fxTitle("🚚 Loading image-article junctions...")
                ->articleImageRepository->getAllComplete();

        $junctionsNum = count($junctions);
        $this->fxOK("##$junctionsNum## junctions(s) loaded");


        $this
            ->fxTitle("🚚 Loading images...")
            ->images->loadAll();

        $imagesNum = $this->images->count();
        $this->fxOK("##$imagesNum## images(s) loaded");


        $this->fxTitle("🚮 Deleting unused junctions....");
        $this->processItems($junctions, [$this, 'deleteOneJunctionIfUnused']);


        $this->fxTitle("🆕 Creating new junctions....");
        $this->processItems($this->arrArticleMap, [$this, 'createNewJunction']);


        $this->fxTitle("💾 Persisting....");
        if( $this->isNotDryRun() ) {
            $this->entityManager->flush();
        }


        $this->fxTitle("📊 Deleted junction(s)...");
        if( !empty($this->arrDeletedJunctions) ) {

            usort($this->arrDeletedJunctions, function(array $arr1, array $arr2) {
                return $arr1["articleId"] <=> $arr2["articleId"];
            });

            $arrDeletedJunctionsForReport =
                array_map(
                    fn($item) => array_intersect_key($item, array_flip(['articleUrl', 'imageUrl'])), $this->arrDeletedJunctions
                );

            (new Table($output))
                ->setHeaders(['Art. URL', 'Img URL'])
                ->setRows($arrDeletedJunctionsForReport)
                ->render();
        }

        $deletedJunctionsNum = count($this->arrDeletedJunctions);
        $this->fxOK("##$deletedJunctionsNum## junctions(s) deleted");


        $this->fxTitle("📊 Created junction(s)...");
        if( !empty($this->arrNewJunctions) ) {

            usort($this->arrNewJunctions, function(array $arr1, array $arr2) {
                return $arr1["articleId"] <=> $arr2["articleId"];
            });

            $arrCreatedJunctionsForReport =
                array_map(
                    fn($item) => array_intersect_key($item, array_flip(['articleUrl', 'imageUrl'])), $this->arrNewJunctions
                );

            (new Table($output))
                ->setHeaders(['Art. URL', 'Img URL'])
                ->setRows($arrCreatedJunctionsForReport)
                ->render();
        }

        $createdJunctionsNum = count($this->arrNewJunctions);
        $this->fxOK("##$createdJunctionsNum## junctions(s) created");

        $this->io->newLine();

        return $this->endWithSuccess();
    }


    protected function scanOneArticle($key, Article $article) : static
    {
        $articleId  = $article->getId();
        $articleUrl = $article->getShortUrl();

        $this->arrArticleMap[$articleId] = [
            'articleId' => $articleId,
            'Images'    => []
        ];

        $spotlightId = $article->getSpotlight()?->getId();
        if( !empty($spotlightId) ) {
            $this->arrArticleMap[$articleId]['Images'][] = $spotlightId;
        }

        $text = $article->getEntity()->getBody();

        if( empty($text) ) {
            return $this;
        }

        $domDoc = $this->htmlProcessor->parseHTML($text);
        if( $domDoc === false ) {
            $this->endWithError("Parsing of article ## $articleUrl ## failed");
        }

        $imgNodes = $domDoc->getElementsByTagName('img');
        if( $imgNodes->length == 0 ) {
            return $this;
        }

        foreach($imgNodes as $img) {

            $arrMatches = [];
            $src        = $img->getAttribute('src');
            $found      = preg_match(HtmlProcessorForDisplay::REGEX_IMAGE_PLACEHOLDER, $src, $arrMatches);

            if( !$found ) {
                $found = preg_match(HtmlProcessorForDisplay::REGEX_IMAGE_SHORTURL, $src, $arrMatches);
            }

            if( !$found ) {
                $this->endWithError("Extracting image ID from article ## $articleUrl ## failed");
            }

            $this->arrArticleMap[$articleId]['Images'][] = (int)$arrMatches[0];
        }

        $this->arrArticleMap[$articleId]['Images'] = array_unique($this->arrArticleMap[$articleId]['Images']);

        return $this;
    }


    protected function deleteOneJunctionIfUnused($key, ArticleImage $junction) : static
    {
        $article    = $junction->getArticle();
        $articleId  = $article->getId();
        $imageId    = $junction->getImage()->getId();


        if( $articleId == Article::ID_ABOUT_US && in_array($imageId, [Image::ID_404, Image::ID_DEFAULT_SPOTLIGHT]) ) {

            // special case! these images are attached on the "about us" article, but not used
            return $this->removeFromArticleMap($articleId, $imageId);
        }


        $dateCutoff = (new DateTime())->modify('-9 months');
        if( $junction->getUpdatedAt() > $dateCutoff ||  $article->getUpdatedAt() > $dateCutoff ) {

            // don't delete "too new" assoc
            return $this->removeFromArticleMap($articleId, $imageId);
        }


        $articleImages = $this->arrArticleMap[$articleId]['Images'] ?? [];
        $key = array_search($imageId, $articleImages);

        if( $key !== false ) {

            // it's already attached
            return $this->removeFromArticleMap($articleId, $imageId);
        }


        // it's attached, but not shown
        $junctionId = $junction->getId();
        $this->arrDeletedJunctions[$junctionId] = [
            'articleId'     => $articleId,
            'imageId'       => $imageId,
            'articleUrl'    => $this->articles->get($articleId)->getShortUrl(),
            'imageUrl'      => $this->images->get($imageId)->getShortUrl(Image::SIZE_MAX)
        ];

        $this->articleImageRepository->delete($junction, false);

        // fail-safe - this shouldn't happen (the image is not shown)
        return $this->removeFromArticleMap($articleId, $imageId);
    }


    protected function removeFromArticleMap(int $articleId, int $imageId) : static
    {
        $articleImages = $this->arrArticleMap[$articleId]['Images'] ?? [];
        $key = array_search($imageId, $articleImages);

        if( $key !== false ) {
            unset($this->arrArticleMap[$articleId]['Images'][$key]);
        }

        return $this;
    }


    protected function createNewJunction($key, array $arrMapItem) : static
    {
        if( empty($arrMapItem["Images"]) ) {
            return $this;
        }

        $articleId  = $arrMapItem['articleId'];
        $article    = $this->articles->get($articleId);

        foreach($arrMapItem["Images"] as $imageId) {

            $image = $this->images->get($imageId);

            if( empty($image) && $articleId == Article::ID_QUALITY_TEST ) {
                // special case! it uses a non-existing image, but it must be kept
                continue;
            }

            $logEntry = [
                'articleId'     => $articleId,
                'imageId'       => $imageId,
                'articleUrl'    => $article->getShortUrl(),
                'imageUrl'      => $image?->getShortUrl(Image::SIZE_MAX)
            ];

            if( empty($image) ) {

                $this->arrImagesNotFound = $logEntry;
                continue;
            }

            $this->arrNewJunctions[] = $logEntry;

            $newJunction =
                (new ArticleImage())
                    ->setArticle($article->getEntity())
                    ->setImage($image->getEntity())
                    ->setRanking(0);

            $this->articleImageRepository->save($newJunction, false);
        }

        return $this;
    }
}
