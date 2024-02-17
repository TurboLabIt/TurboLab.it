<?php
namespace App\Tests\Smoke;

use App\Service\Cms\File;
use App\Tests\BaseT;
use Symfony\Component\HttpFoundation\Request;


class FileTest extends BaseT
{
    public static function localFileToTestProvider(): \Generator
    {
        yield [
            // 👀 https://turbolab.it/scarica/1
            [
                "id"            => 1,
                "title"         => "Windows Bootable DVD Generator 2021",
                "content-type"  => "application/zip",
                "format"        => "zip"
            ],
            // 👀 https://turbolab.it/scarica/362
            [
                "id"            => 362,
                "title"         => "Batch configurazione macOS in VirtualBox",
                "content-type"  => "text/x-msdos-batch; charset=UTF-8",
                "format"        => "bat"
            ],
            // 👀 https://turbolab.it/scarica/400
            [
                "id"            => 400,
                "title"         => "Estensioni video HEVC (appx 64 bit)",
                "content-type"  => "application/zip",
                "format"        => "appx"
            ],
        ];
    }


    /**
     * @dataProvider localFileToTestProvider
     */
    public function testLocalFiles(array $arrFile)
    {
        /** @var File $file */
        $file = static::getService("App\\Service\\Cms\\File");
        $file->load($arrFile["id"]);

        $url = $file->getUrl();
        $this->assertStringEndsWith('/scarica/' . $arrFile["id"], $url);

        $file = $this->fetchHtml($url, Request::METHOD_GET, false);
        $this->assertNotEmpty($file);
        $this->assertResponseHeaderSame('content-type', $arrFile["content-type"]);
        $this->assertResponseHeaderSame('content-disposition',
            'attachment; filename="' . $arrFile["title"] . "." . $arrFile["format"] . '"');
    }


    public function test404()
    {
        // 👀 https://turbolab.it/scarica/9999
        $this->expect404('/scarica/9999');
    }
}
