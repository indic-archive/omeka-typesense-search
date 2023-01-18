<?php

declare(strict_types=1);

namespace TypesenseSearch\Job;

use Omeka\Job\AbstractJob;
use Omeka\Stdlib\Message;
use Typesense\Client;
use Symfony\Component\HttpClient\HttplugClient;

class RecreateIndex extends AbstractJob
{
    /**
     * @var Logger
     */
    protected $logger;

    public function perform(): void
    {
        $services = $this->getServiceLocator();
        $this->logger = $services->get('Omeka\Logger');
        $settings = $services->get('Omeka\Settings');

        $connection = $services->get('Omeka\Connection');
        $sql = <<<'SQL'
SELECT
    GROUP_CONCAT(
        CONCAT(
            `vocabulary`.`prefix`,
            '_',
            `property`.`local_name`
        ) SEPARATOR "|"
    ) AS `fields`,
    GROUP_CONCAT(`value`.`value` SEPARATOR "|") AS `values`
FROM
    `value` AS `value`
    LEFT JOIN `resource` ON `resource`.`id` = `value`.`resource_id`
    LEFT JOIN `property` ON `property`.`id` = `value`.`property_id`
    LEFT JOIN `vocabulary` ON `vocabulary`.`id` = `property`.`vocabulary_id`
WHERE
    `resource`.`resource_type` = 'Omeka\\Entity\\Item'
GROUP BY
    `resource`.`id`;
SQL;
        $results = $connection->fetchAll($sql);

        $documents = array();
        foreach ($results as $result) {
            $fields = explode('|', $result['fields']);
            $values = explode('|', $result['values']);

            $document = [
                'dcterms_identifier' => [],
                'dcterms_title' => [],
                'dcterms_alternative' => [],
                'dcterms_abstract' => [],
                'dcterms_creator' => [],
                'dcterms_issued' => [],
                'dcterms_subject' => [],
                'dcterms_language' => [],
                'dcterms_extent' => [],
                'dcterms_publisher' => [],
                'bibo_producer' => [],
                'dcterms_coverage' => [],
            ];

            foreach (array_combine($fields, $values) as $key => $value) {
                if (!array_key_exists($key, $document)) {
                    $document[$key] = array();
                }

                array_push($document[$key], $value);
            }

            array_push($documents, $document);
        }

        // Get the parameters passed to the job.
        $indexName = $this->getArg('index_name');

        $timeStart = microtime(true);
        $this->logger->info(new Message('Started indexing items under #%s', $indexName));

        $client = new Client(
            [
                'api_key' => $settings->get('typesense_api_key'),
                'nodes' => [
                    [
                        'host' => $settings->get('typesense_host'),
                        'port' => $settings->get('typesense_port'),
                        'protocol' => $settings->get('typesense_protocol'),
                    ],
                ],
                'client' => new HttplugClient(),
            ]
        );

        // recreate the index
        try {
            $client->collections[$indexName]->delete();
        } catch (\Exception $e) {
            $this->logger->err(new Message('Error deleing index #%s, err: %s', $indexName, $e->getMessage()));
            return;
        }

        try {
            $client->collections->create(
                [
                    'name' => 'books',
                    'fields' => [
                        ['name' => 'dcterms_identifier', 'type' => 'string[]'],
                        [
                            'name' => 'dcterms_title',
                            'type' => 'string[]',
                            'infix' => True,
                        ],
                        [
                            'name' => 'dcterms_alternative',
                            'type' => 'string[]',
                            'infix' => True,
                        ],
                        ['name' => 'dcterms_abstract', 'type' => 'string[]'],
                        ['name' => 'dcterms_creator', 'type' => 'string[]'],
                        [
                            'name' => 'dcterms_issued',
                            'type' => 'string[]',
                        ],  # issue after adding this
                        ['name' => 'dcterms_subject', 'type' => 'string[]'],
                        ['name' => 'dcterms_language', 'type' => 'string[]'],
                        ['name' => 'dcterms_extent', 'type' => 'string[]'],
                        ['name' => 'dcterms_publisher', 'type' => 'string[]'],
                        ['name' => 'bibo_producer', 'type' => 'string[]'],
                        ['name' => 'dcterms_coverage', 'type' => 'string[]'],
                    ],
                    'token_separators' => ['-'],
                ]
            );
        } catch (\Exception $e) {
            $this->logger->err(new Message('Error creating index #%s, err: %s', $indexName, $e->getMessage()));
            return;
        }

        try {
            $response = $client->collections[$indexName]->documents->import($documents, [
                'action' => 'create',
            ]);
        } catch (\Exception $e) {
            $this->logger->err(new Message('Error importing documents to index #%s, err: %s', $indexName, $e->getMessage()));
            return;
        }

        // count the number of success
        $successes = array_column($response, "success");
        $count = count(array_keys($successes, 1));

        $timeTotal = (int) (microtime(true) - $timeStart);
        $this->logger->info(new Message('Finished indexing. Count(%s), Elapsed %s seconds', $count, $timeTotal));
    }
}
