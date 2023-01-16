<?php

declare(strict_types=1);

namespace TypesenseSearch\Controller;

use Typesense\Client;
use Symfony\Component\HttpClient\HttplugClient;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;

/**
 * Controller for search action that returns a json response.
 */
class SearchController extends AbstractActionController
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var Index
     */
    protected $indexName;

    /**
     * @param array $parameters typesense configurations.
     */
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
        $this->indexName = $parameters['search_index'];
    }

    /**
     * Search the query param in the index and return json response.
     */
    public function searchAction()
    {
        $searchQ = $this->params()->fromQuery('query');

        if ($searchQ == "*") {
            return new JsonModel([
                'results' => [],
            ]);
        }

        $results = $this->client->collections[$this->indexName]->documents->search(
            [
                'q' => $searchQ,
                'query_by' => 'dcterms_title,dcterms_alternative,dcterms_creator,dcterms_subject,dcterms_abstract,dcterms_publisher',
                'query_by_weights' => '5,5,2,1,1,1',
                'highlight_full_fields' => 'dcterms_title',
                'page' => 1,
                'per_page' => 15,
                'infix' => 'fallback',
                //'sort_by' => 'dcterms_issued:desc',
            ],
        );
        //var_dump($results['hits']);

        return new JsonModel([
            'results' => $results,
        ]);
    }
}
