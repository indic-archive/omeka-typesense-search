<?php

declare(strict_types=1);

namespace TypesenseSearch\Controller;

use Typesense\Client;
use Symfony\Component\HttpClient\HttplugClient;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;

class SearchController extends AbstractActionController
{
    protected $client;

    public function __construct(array $parameters)
    {
        $this->client = new Client(
            [
                'api_key' => $parameters['api_key'],
                'nodes' => [
                    [
                        'host' => $parameters['host'],
                        'port' => $parameters['port'],
                        'protocol' => $parameters['protocol'],
                    ],
                ],
                'client' => new HttplugClient(),
            ]
        );
    }

    public function searchAction()
    {
        $searchQ = $this->params()->fromQuery('query');

        $results = $this->client->collections['books']->documents->search(
            [
                'q' => $searchQ,
                'query_by' => 'title',
            ],
        );
        //var_dump($results['hits']);

        return new JsonModel([
            'results' => $results,
        ]);
    }
}
