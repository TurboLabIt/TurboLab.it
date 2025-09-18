<?php
namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use TurboLabIt\BaseCommand\Traits\EnvTrait;


class GitHub
{
    private const API_BASE_URL = 'https://api.github.com/';

    use EnvTrait;


    public function __construct(
        protected array $arrConfig,
        protected HttpClientInterface $httpClient, protected ParameterBagInterface $parameters
    )
    {}


    public function createIssue(string $issueTitle, string $issueBody) : array
    {
        $endpointUrl = static::API_BASE_URL . 'repos/' . $this->arrConfig["github"]["path"] . '/issues';

        $arrPayload = [
            'title' => $this->getEnvTag() . $issueTitle,
            'body'  => $issueBody,
        ];

        if( $this->isNotProd() ) {
            return [
                'html_url'  => 'https://github.com/TurboLabIt/TurboLab.it/issues/00-test',
                'number'    => '00-test'
            ];
        }

        $response =
            $this->httpClient->request(Request::METHOD_POST, $endpointUrl, [
                'headers' => [
                    'Accept' => 'application/vnd.github.v3+json',
                    'Authorization' => 'Bearer ' . $this->arrConfig["github"]["token"],
                ],
                'json' => $arrPayload,
            ]);

        $statusCode = $response->getStatusCode();
        return $response->toArray();
    }
}
