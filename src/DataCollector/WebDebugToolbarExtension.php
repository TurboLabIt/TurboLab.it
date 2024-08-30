<?php
namespace App\DataCollector;

use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


// 📚 https://symfony.com/doc/current/profiler.html#creating-a-data-collector
class WebDebugToolbarExtension extends AbstractDataCollector
{
    public function collect(Request $request, Response $response, ?\Throwable $exception = null) : void
    {
        // Collect the data that you want to display in the toolbar
        $this->data = [
            'custom_value' => 'Your custom value',
        ];
    }


    public function getCustomValue() : string
    {
        return $this->data['custom_value'];
    }

    public static function getTemplate() : ?string { return 'dev/web-debug-toolbar-extension.html.twig'; }
}
