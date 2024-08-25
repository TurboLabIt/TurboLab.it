<?php
namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\HttpClient\HttpClient;
use TurboLabIt\Messengers\FacebookMessenger;


#[AsDecorator(decorates: FacebookMessenger::class)]
class FacebookMessengerDecorator extends FacebookMessenger
{
    protected FacebookMessenger $inner;
    protected array $arrConfig;
    protected string $pageId;
    protected string $token;


    public function __construct(FacebookMessenger $inner)
    {
        $this->inner = $inner;
        $this->arrConfig =
            (new \ReflectionClass($this->inner))
                ->getProperty('arrConfig')->getValue($inner);

        $this->pageId = $this->arrConfig["Facebook"]["page"]["id"];
        $this->token  = $this->arrConfig["Facebook"]["page"]["token"];
    }


    public function sendUrlToPage(string $url, array $arrParams = []) : string
    {
        $endpoint = "https://graph.facebook.com/v19.0/{$this->pageId}/feed";

        $this->lastResponse =
            HttpClient::create()->request('POST', $endpoint, [
                "headers" => [
                    "Content-Type" => "application/json",
                ],
                "query" => [
                    "link"          => $url,
                    "access_token"  => $this->token
                ]
            ]);

        $content = $this->lastResponse->getContent(false);
        $oJson = json_decode($content);

        if( empty($oJson) ) {
            throw new \Exception("JSON response decoding error");
        }

        if( !empty($oJson->error) ) {
            throw new \Exception($oJson->error->message);
        }

        return $oJson->id;
    }
}
