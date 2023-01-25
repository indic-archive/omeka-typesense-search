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
        // get weights by the order of property
        $result = array();
        $weights = array();
        $propertyCt = count($this->indexProperties);
        foreach (array_values($this->indexProperties) as $i => $property) {
            $result[] = str_replace(':', '_', $property);
            $weights[] = strval($propertyCt - $i);
        }
        $queryBy = implode(',', $result);
        $queryByWeights = implode(',', $weights);

        try {
            $results = $this->client->collections[$this->indexName]->documents->search(
                [
                    'q' => $searchQ,
                    'query_by' => $queryBy,
                    'query_by_weights' => $queryByWeights,
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

        return new JsonModel([
            'results' => $this->_getFormattedSearchResults($results),
        ]);
    }

    public function dropIndexAction()
    {
        $jobArgs = [];
        $jobArgs["index_name"] = $this->indexName;
        $this->jobDispatcher()->dispatch(\TypesenseSearch\Job\DeleteIndex::class, $jobArgs);

        return new JsonModel([
            'message' => 'index dropped',
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

    /**
     * Format typesense results into list of objects with url, text
     * @param array $results typesense results.
     * @return array
     */
    protected function _getFormattedSearchResults(array $results)
    {
        $siteSlug = $this->params()->fromRoute('site-slug');
        $url = $this->viewHelpers()->get('Url');

        // match contents inside brackets and replace it with fields from index
        $re = '/\{(.*?)\}/m';
        preg_match_all($re, $this->resultFormat, $matches);
        $formatFields = array();
        if (!empty($matches)) {
            foreach ($matches[0] as $match) {
                // remove brackets
                $field = str_replace(["{", "}"], "", $match);
                // change dcterms:title to dcterms_title (index field name)
                $field = str_replace(":", "_", $field);

                array_push($formatFields, $field);
            }
        }

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

            // get a associative array of all matched fields present in the highlight / document
            $matchedFields = [];
            if (!empty($hit["highlights"])) {
                foreach ($hit["highlights"] as $highlight) {
                    if (in_array($highlight["field"], $formatFields)) {
                        if (array_key_exists("snippet", $highlight)) {
                            $matchedFields[$highlight["field"]] =  $highlight["snippet"];
                        } else if (array_key_exists("snippets", $highlight)) {
                            $matchedFields[$highlight["field"]] =  $highlight["snippets"][0];
                        }
                    }
                }
            }

            // fill the rest of the fields from document object.
            if (count($matchedFields) != count($formatFields) && !empty($hit["document"])) {
                // fallback to document fields
                foreach ($hit["document"] as $k => $v) {
                    if (array_key_exists($k, $matchedFields)) {
                        continue;
                    }

                    if (is_array($v) && !empty($v)) {
                        $matchedFields[$k] = strval($v[0]);
                    } else if (!is_array($v)) {
                        $matchedFields[$k] = strval($v);
                    }
                }
            }

            // replace the formatted string with the matched fields
            if (!empty($matches)) {
                $data = $this->resultFormat;
                foreach ($matches[0] as $match) {
                    // remove brackets
                    $field = str_replace(["{", "}"], "", $match);
                    // change dcterms:title to dcterms_title (index field name)
                    $field = str_replace(":", "_", $field);

                    if (isset($matchedFields[$field])) {
                        $data = str_replace($match, $matchedFields[$field], $data);
                    } else {
                        $data = str_replace($match, "", $data);
                    }
                }
                $document["text"] = $data;
            } else {
                $document["text"] = $hit["document"]["dcterms_title"][0];
            }


            // make the highlights bold
            $document["text"] = str_replace("<mark>", "<b>", $document["text"]);
            $document["text"] = str_replace("</mark>", "</b>", $document["text"]);

            array_push($searchResults, $document);
        }

        return $searchResults;
    }
}
