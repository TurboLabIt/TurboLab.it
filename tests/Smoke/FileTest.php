<?php
namespace App\Tests\Smoke;

use App\Service\Cms\File;
use App\Tests\BaseT;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;


class FileTest extends BaseT
{
    public static function localFileToTestProvider() : Generator
    {
        yield from [
            // 👀 https://turbolab.it/scarica/1
            [
                "id"            => 1,
                "title"         => "Windows Bootable DVD Generator",
                "contentType"   => "application/zip",
                "format"        => "zip"
            ],
            // 👀 https://turbolab.it/scarica/362
            [
                "id"            => 362,
                "title"         => "Batch configurazione macOS in VirtualBox",
                "contentType"   => "text/x-msdos-batch; charset=UTF-8",
                "format"        => "bat"
            ],
            // 👀 https://turbolab.it/scarica/400
            [
                "id"            => 400,
                "title"         => "Estensioni video HEVC (appx 64 bit)",
                "contentType"   => "application/zip",
                "format"        => "appx"
            ],
        ];
    }


    #[DataProvider('localFileToTestProvider')]
    public function testLocalFiles(int $id, string $title, string $contentType, string $format)
    {
        /** @var File $file */
        $file = static::getService("App\\Service\\Cms\\File");
        $file->load($id);

        $url = $file->getUrl();
        $this->assertStringEndsWith('/scarica/' . $id, $url);

        $file = $this->fetchHtml($url, Request::METHOD_GET, false);
        $this->assertNotEmpty($file);
        $this->assertResponseHeaderSame('content-type', $contentType);
        $this->assertResponseHeaderSame('content-disposition',
            'attachment; filename="' . $title . "." . $format . '"');
    }


    public function test404()
    {
        // 👀 https://turbolab.it/scarica/9999
        $this->expect404('/scarica/9999');
    }
}
