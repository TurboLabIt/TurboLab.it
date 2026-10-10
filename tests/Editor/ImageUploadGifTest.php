<?php
namespace App\Tests\Editor;

use App\Entity\Cms\Image as ImageEntity;
use App\Exception\InvalidEnumException;
use App\Service\Cms\Image;
use App\Service\Factory;
use App\Tests\BaseT;
use App\Tests\ImageFixtures;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\File\UploadedFile;


/**
 * GIF is an upload-only format: authors upload it like a PNG or a JPEG, and it's served built in one
 * of the usual formats. GD reads only the first frame, so an animated GIF is served as a still image.
 *
 * @see \App\Entity\Cms\Image::getUploadFormats()
 * @see \App\Service\Cms\Image::build()
 */
class ImageUploadGifTest extends BaseT
{
    const int RED   = ImageFixtures::RED;
    const int BLUE  = ImageFixtures::BLUE;

    /** @var string[] */
    private array $tmpFiles = [];
    private ?string $previousBuildFormat = null;


    protected function setUp() : void
    {
        parent::setUp();
        // the build format is static: restore it, for the tests that run next in this process
        $this->previousBuildFormat = (new ReflectionProperty(Image::class, 'buildFileExtension'))->getValue();
    }


    protected function tearDown() : void
    {
        (new ReflectionProperty(Image::class, 'buildFileExtension'))->setValue(null, $this->previousBuildFormat);

        foreach($this->tmpFiles as $file) {
            @unlink($file);
        }
        $this->tmpFiles = [];
        parent::tearDown();
    }


    public function testGifIsAnUploadOnlyFormat() : void
    {
        $this->assertContains(ImageEntity::FORMAT_GIF, ImageEntity::getUploadFormats());
        $this->assertNotContains(ImageEntity::FORMAT_GIF, ImageEntity::getFormats());

        $this->expectException(InvalidEnumException::class);
        Image::setBuildFormat(ImageEntity::FORMAT_GIF);
    }


    public function testStaticGifIsServedLikeAPng() : void
    {
        $gd = imagecreate(200, 100);
        imagecolorallocate($gd, 0, 128, 0);

        $path = $this->tmpPath();
        imagegif($gd, $path);

        $this->uploadBuildAndCheck($path, 'grafico.gif', 'grafico', false, [200, 100], [0, 128, 0]);
    }


    public function testAnimatedGifIsServedAsItsFirstFrame() : void
    {
        $path = $this->makeAnimatedGif(200, 100, [[200, 100, 0, 0, self::RED], [200, 100, 0, 0, self::BLUE]]);
        $this->uploadBuildAndCheck($path, 'animazione.gif', 'animazione', true, [200, 100], [255, 0, 0]);
    }


    public function testFirstFrameSmallerThanTheCanvasIsNotStretched() : void
    {
        // a legal GIF: frame #1 is a 100x100 square inside a 400x200 canvas. GD would decode just the square;
        // the still of an animated GIF is its first frame merged onto the whole canvas, like the animation:
        // red square in the middle, transparent around it, never stretched
        $path = $this->makeAnimatedGif(400, 200, [[100, 100, 150, 50, self::RED], [400, 200, 0, 0, self::BLUE]]);
        $this->uploadBuildAndCheck($path, 'riquadro.gif', 'riquadro', true, [400, 200], [255, 0, 0], true);
    }


    private function uploadBuildAndCheck(
        string $path, string $fileName, string $expectedTitle, bool $expectedAnimated, array $expectedSize, array $expectedRgb,
        bool $expectedTransparentCorner = false
    ) : void
    {
        // lossless, so the pixel check can be exact
        Image::setBuildFormat(ImageEntity::FORMAT_PNG);

        $file   = new UploadedFile($path, $fileName, 'image/gif', null, true /* test mode */);
        $image  = static::getService(Factory::class)->createImageEditor()->createFromUploadedFile($file);

        try {
            $this->assertSame(ImageEntity::FORMAT_GIF, $image->getFormat());
            $this->assertStringEndsWith('.gif', $image->getOriginalFilePath());
            $this->assertFileExists( $image->getOriginalFilePath() );
            $this->assertSame($expectedTitle, $image->getTitle());
            $this->assertSame($expectedAnimated, $image->isAnimated());
            $this->assertSame(
                (int)$expectedAnimated,
                (int)static::getEntityManager()->getConnection()->fetchOne('SELECT animated FROM image WHERE id = ?', [$image->getId()]),
                'The animated flag must be persisted'
            );

            $built = imagecreatefromstring( $image->getContent(Image::SIZE_MED) );
            $this->assertSame('image/png', $image->getBuiltImageMimeType());
            $this->assertSame($expectedSize, [imagesx($built), imagesy($built)]);

            $centerColor = imagecolorsforindex($built, imagecolorat($built, intdiv($expectedSize[0], 2), intdiv($expectedSize[1], 2)));
            $this->assertSame($expectedRgb, [$centerColor['red'], $centerColor['green'], $centerColor['blue']]);

            $cornerAlpha = imagecolorsforindex($built, imagecolorat($built, 0, 0))['alpha'];
            $this->assertSame($expectedTransparentCorner, $cornerAlpha == 127);

        } finally {
            $image->delete();
        }
    }


    private function makeAnimatedGif(int $canvasWidth, int $canvasHeight, array $frames) : string
    {
        $path = $this->tmpPath();
        file_put_contents($path, ImageFixtures::decodableGif($canvasWidth, $canvasHeight, $frames));
        return $path;
    }


    private function tmpPath() : string
    {
        $path = tempnam(sys_get_temp_dir(), 'tli_gif_') . '.gif';
        $this->tmpFiles[] = $path;
        return $path;
    }
}
