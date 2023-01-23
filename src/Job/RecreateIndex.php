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
    `value`.`resource_id` as `resource_id`,
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
    AND `resource`.`is_public` = true
GROUP BY
    `resource`.`id`;
SQL;
        $results = $connection->fetchAll($sql);

        $indexProperties = $this->getArg('index_fields');

        $documents = array();
        foreach ($results as $result) {
            $fields = explode('|', $result['fields']);
            $values = explode('|', $result['values']);

            // Add defaults index properties
            $document = [
                'resource_id' => strval($result['resource_id']),
            ];

            foreach ($indexProperties as $property) {
                $fieldName = str_replace(":", "_", $property);
                $document[$fieldName] = [];
            }

            foreach (array_combine($fields, $values) as $key => $value) {
                if (!array_key_exists($key, $document)) {
                    continue;
                }

                array_push($document[$key], $value);
            }

            array_push($documents, $document);
        }

        $this->logger->info(new Message('Fetched %s documents', count($documents)));

        // Get the parameters passed to the job.
        $indexName = $this->getArg('index_name');

        $timeStart = microtime(true);
        $this->logger->info(new Message('Started indexing items under #%s', $indexName));

        $urlParts = parse_url($settings->get('typesense_url'));
        $client = new Client(
            [
                'api_key' => $settings->get('typesense_api_key'),
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

        // recreate the index
        try {
            $client->collections[$indexName]->delete();
        } catch (\Exception $e) {
            $this->logger->err(new Message('Error deleting index #%s, err: %s', $indexName, $e->getMessage()));
            return;
        }


        $indexFields = [
            ['name' => 'resource_id', 'type' => 'string', "index" => true, "optional" => true]
        ];
        foreach ($indexProperties as $property) {
            $fieldName = str_replace(":", "_", $property);
            $fields = [
                'name' => $fieldName,
                'type' => 'string[]'
            ];
            if ($fieldName == 'dcterms_title' && $fieldName == 'dcterms_alternative') {
                $fields['infix'] = True;
            }

            array_push($indexFields, $fields);
        }

        try {
            $client->collections->create(
                [
                    'name' => 'books',
                    'fields' => $indexFields,
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
