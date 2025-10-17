<?php
namespace App\Command;

use App\Service\Entity\Image as ImageEntity;
use App\Service\Cms\ImageEditor;
use App\ServiceCollection\Cms\ImageEditorCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TurboLabIt\BaseCommand\Command\AbstractBaseCommand;


/**
 * 📚 https://github.com/TurboLabIt/TurboLab.it/blob/main/docs/images-articles.md
 */
#[AsCommand(name: 'ImagesDelete')]
class ImagesDeleteCommand extends AbstractBaseCommand
{
    protected bool $allowDryRunOpt      = true;
    protected array $arrDeletedImages   = [];

    public function __construct(protected ImageEditorCollection $images) { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        parent::execute($input, $output);

        $this->fxTitle("🚚 Loading images...");
        $this->images->loadToDelete();
        $count = $this->images->count();
        $this->fxOK("##$count## image(s) loaded");

        if($count == 0) { return $this->endWithSuccess(); }

        $this->fxTitle("🚮 Deleting...");
        $this->processItems($this->images, [$this, 'deleteOneImage']);

        $this->fxTitle("📊 Deleted image(s)...");
        if( !empty($this->arrDeletedImages) ) {

            $arrDeletedImagesForReport =
                array_map(
                    fn($item) => array_intersect_key($item, array_flip(['imageId', 'imageUrl'])), $this->arrDeletedImages
                );

            (new Table($output))
                ->setHeaders(['Image ID', 'Image URL'])
                ->setRows($arrDeletedImagesForReport)
                ->render();
        }

        $deletedImagesNum = count($this->arrDeletedImages);
        $this->fxOK("##$deletedImagesNum## images(s) deleted");

        $this->io->newLine();

        return $this->endWithSuccess();
    }


    protected function deleteOneImage($key, ImageEditor $image) : static
    {
        $imageId = $image->getId();
        $this->arrDeletedImages[$imageId] = [
            'imageId'   => $imageId,
            'imageUrl'  => $image->getShortUrl(ImageEditor::SIZE_MAX)
        ];

        if( $this->isNotDryRun() ) {
            $image->delete();
        }

        return $this;
    }
}
