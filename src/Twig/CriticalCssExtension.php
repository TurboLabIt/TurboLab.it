<?php
namespace App\Twig;

use RuntimeException;
use TurboLabIt\BaseCommand\Service\ProjectDir;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;


class CriticalCssExtension extends AbstractExtension
{
    public function __construct(protected ProjectDir $projectDir) {}


    public function getFunctions(): array
    {
        return [
            new TwigFunction('critical_css', [$this, 'getCriticalCss'], ['is_safe' => ['html']])
        ];
    }


    public function getCriticalCss(string $page) : string
    {
        $criticalPath = $this->projectDir->getProjectDir('public/build/critical') . $page . '.css';

        if(  !file_exists($criticalPath) ) {
            throw new RuntimeException('Critical CSS not found. Run: ##bash build.sh##');
        }

        // In production, fail silently if file doesn't exist
        if (!file_exists($criticalPath)) {
            return '';
        }

        $css = file_get_contents($criticalPath);

        return sprintf('<style id="critical-css">%s</style>', $css);
    }
}
