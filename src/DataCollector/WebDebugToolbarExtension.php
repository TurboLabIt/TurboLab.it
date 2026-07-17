<?php
namespace App\DataCollector;

use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;


/**
 * Not a real data collector: it collects nothing. It exists only to inject a
 * new toolbar item (handy dev links) into the web debug toolbar.
 * This is the officially supported way to add a toolbar item.
 * 📚 https://symfony.com/doc/current/profiler.html#creating-a-data-collector
 */
class WebDebugToolbarExtension extends AbstractDataCollector
{
    public function collect(Request $request, Response $response, ?Throwable $exception = null) : void
    {
        // Nothing to collect: the toolbar item is a static template.
    }

    public static function getTemplate() : ?string { return 'dev/web-debug-toolbar-extension.html.twig'; }
}
