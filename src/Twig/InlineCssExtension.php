<?php
namespace App\Twig;

use RuntimeException;
use Symfony\Component\Asset\Packages;
use TurboLabIt\BaseCommand\Service\ProjectDir;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;


class InlineCssExtension extends AbstractExtension
{
    public function __construct(protected Packages $packages, protected ProjectDir $projectDir) {}


    public function getFunctions(): array
    {
        return [
            new TwigFunction('inline_css', [$this, 'inlineCss'], ['is_safe' => ['html']])
        ];
    }


    public function inlineCss(string $path): string
    {
        $fullPath = $this->packages->getUrl($path);
        $filePath = $this->projectDir->getProjectDir('public') . parse_url($fullPath, PHP_URL_PATH);

        if( !file_exists($filePath) ) {
            throw new RuntimeException("File not found: $filePath");
        }

        return '<style>' . file_get_contents($filePath) . '</style>';
    }
}
