<?php

declare(strict_types=1);

namespace TypesenseSearch\Service\Controller;

use TypesenseSearch\Controller\SearchController;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Omeka\File\Exception\ConfigException;


/**
 * Factory class for instantiating SearchController from settings.
 */
class SearchControllerFactory implements FactoryInterface
{
    /**
     * Create a new instance of SearchController from settings and return it.
     *
     * @return SearchController
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $settings = $services->get('Omeka\Settings');
        $parameters = [
            'url' => $settings->get('typesense_url', 'http://localhost:8108'),
            'api_key' => $settings->get('typesense_api_key'),
            'search_index' => $settings->get('typesense_search_index'),
            'index_properties' => $settings->get('typesense_index_properties'),
            'search_result_formatting' => $settings->get('typesense_search_result_format'),
        ];

        if (empty($parameters['index_properties'])) {
            throw new ConfigException('Index properties are required.');
        }

        if (empty($parameters['search_index'])) {
            throw new ConfigException('Search index is required.');
        }

        if (empty($parameters['url']) || empty($parameters['api_key'])) {
            throw new ConfigException('Host, API Key are required.');
        }

        return new SearchController(
            $services->get('Omeka\Logger'),
            $parameters
        );
    }
}
