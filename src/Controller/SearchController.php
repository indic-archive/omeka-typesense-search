<?php

declare(strict_types=1);

namespace TypesenseSearch\Controller;

use Typesense\Client;
use Symfony\Component\HttpClient\HttplugClient;
use Laminas\Mvc\Controller\AbstractActionController;
use Omeka\Stdlib\Message;
use Laminas\Log\Logger;
use Laminas\View\Model\JsonModel;

/**
 * Controller for search action that returns a json response.
 */
class SearchController extends AbstractActionController
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var string
     */
    protected $indexName;

    /**
     * @var array
     */
    protected $indexProperties;

    /**
     * @var string
     */
    protected $resultFormat;

    /**
     * @var boolean
     */
    protected $useTypesenseHighlights;

    /**
     * @param array $parameters typesense configurations.
     */
    public function __construct(Logger $logger, array $parameters)
    {
        $urlParts = parse_url($parameters['url']);
        $this->client = new Client(
            [
                'api_key' => $parameters['api_key'],
                'nodes' => [
                    [
                        'host' => $urlParts['host'],
                        'port' => $urlParts['port'],
                        'protocol' => $urlParts['scheme'],
                    ],
                ],
                'client' => new HttplugClient(),
            ]
        );
        $this->indexName = $parameters['search_index'];
        $this->indexProperties = $parameters['index_properties'];
        $this->resultFormat = $parameters['search_result_formatting'];
        $this->useTypesenseHighlights = $parameters['use_typesense_highlights'];
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

        // convert [dcterms:title,dcterms:alternative] => 'dcterms_title,dcterms_alternative'
        $result = array();
        foreach ($this->indexProperties as $property) {
            $result[] = str_replace(':', '_', $property);
        }
        $queryBy = implode(',', $result);

        try {
            $results = $this->client->collections[$this->indexName]->documents->search(
                [
                    'q' => $searchQ,
                    'query_by' => $queryBy,
                    //'query_by_weights' => '5,5,2,1,1,1',
                    'highlight_full_fields' => 'dcterms_title',
                    'page' => 1,
                    'per_page' => 15,
                    'infix' => 'fallback',
                    //'sort_by' => 'dcterms_issued:desc',
                ],
            );
        } catch (\Exception $e) {
            //$this->logger->err(new Message('Error searching index #%s, err: %s', $this->indexName, $e->getMessage()));
            return new JsonModel([
                'results' => [],
            ]);
        }

        /**
         * results
         * {
         *      {
         *          "url": "/omeka/item/test-1",
         *          "text": "Test <b>1</b>"
         *      }
         * }
         */


        $siteSlug = $this->params()->fromRoute('site-slug');
        $url = $this->viewHelpers()->get('Url');

        $searchResults = [];
        foreach ($results['hits'] as $hit) {
            $document = [];
            // generate item url based on resource id.
            $document["url"] = $url(
                'site/resource-id',
                [
                    'site-slug' => $siteSlug,
                    'controller' => 'item',
                    'id' => $hit['document']['resource_id'],
                ],
                ['force_canonical' => false]
            );
            $document["text"] = "";

            // add text from typesense response
            // 1. If useTypesenseHighlights enabled, use highlights.
            // 2. Otherwise, use the dublin core format specified in admin module.
            $highlights = $hit["highlights"];
            if ($this->useTypesenseHighlights && !empty($highlights)) {
                // Use highlighted snippet if not empty
                if (array_key_exists("snippet", $highlights[0])) {
                    $document["text"] = $highlights[0]["snippet"];
                } else if (array_key_exists("snippets", $highlights[0])) {
                    if (!empty($highlights[0]["snippets"])) {
                        $document["text"] = $highlights[0]["snippets"][0];
                    }
                }
            } else if (!empty($hit["document"])) {
                // Fallback to configurable formatting
                // match contents inside brackets and replace it with fields from index
                $re = '/\{(.*?)\}/m';
                preg_match_all($re, $this->resultFormat, $matches);

                if (!empty($matches)) {
                    $data = $this->resultFormat;
                    foreach ($matches[0] as $match) {
                        $field = str_replace(["{", "}"], "", $match);
                        $field = str_replace(":", "_", $field);

                        if (isset($hit["document"][$field])) {
                            if (!empty($hit["document"][$field])) {
                                $data = str_replace($match, strval($hit["document"][$field][0]), $data);
                            } else {
                                // replace the unmatched dc terms with empty value
                                $data = str_replace($match, "", $data);
                            }
                        }
                    }

                    $document["text"] = $data;
                } else {
                    $document["text"] = $hit["document"]["dcterms_title"][0];
                }
            }

            // make the highlights bold
            $document["text"] = str_replace("<mark>", "<b>", $document["text"]);
            $document["text"] = str_replace("</mark>", "</b>", $document["text"]);

            array_push($searchResults, $document);
        }

        return new JsonModel([
            'results' => $searchResults,
            'typesense' => $results,
        ]);
    }

    public function dropIndexAction()
    {
        try {
            $this->client->collections[$this->indexName]->delete();
        } catch (\Exception $e) {
            //$this->logger->err(new Message('Error deleting index #%s, err: %s', $this->indexName, $e->getMessage()));
            return new JsonModel([
                'message' => 'error deleting index',
            ]);
        }

        return new JsonModel([
            'message' => 'index dropped',
        ]);
    }

    public function createIndexAction()
    {
        $jobArgs = [];
        $jobArgs["index_name"] = $this->indexName;
        $jobArgs["index_fields"] = $this->indexProperties;
        $this->jobDispatcher()->dispatch(\TypesenseSearch\Job\CreateIndex::class, $jobArgs);

        return new JsonModel([
            'message' => 'index created',
        ]);
    }

    public function recreateIndexAction()
    {
        $jobArgs = [];
        $jobArgs["index_name"] = $this->indexName;
        $jobArgs["index_fields"] = $this->indexProperties;
        $this->jobDispatcher()->dispatch(\TypesenseSearch\Job\RecreateIndex::class, $jobArgs);

        return new JsonModel([
            'message' => 'started reindexing',
        ]);
    }
}
