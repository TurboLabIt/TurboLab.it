<?php
namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;


class TliExtension extends AbstractExtension
{
    public function getFilters()
    {
        return [
            new TwigFilter('friendlyNum', [TliRuntime::class, 'friendlyNum'], ['is_safe' => ['html']]),
            new TwigFilter('friendlyDate', [TliRuntime::class, 'friendlyDate'], ['is_safe' => ['html']]),
        ];
    }
}
