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

        // Get the parameters passed to the job.
        $indexName = $this->getArg('index_name');
        $indexProperties = $this->getArg('index_fields');

        $connection = $services->get('Omeka\Connection');

        // Fetch all resources with its properties key, value flattened out.
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
    `resource`.`id`
SQL;

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

        // retrieve collection
        $hasCollection = true;
        try {
            $collection = $client->collections[$indexName]->retrieve();
        } catch (\Exception $e) {
            $this->logger->err(new Message('Error fetching index #%s. Skipping delete, err: %s', $indexName, $e->getMessage()));
            $hasCollection = false;
        }

        // check if the collection exists and delete it.
        if ($hasCollection && $collection["name"] == $indexName) {
            try {
                $client->collections[$indexName]->delete();
            } catch (\Exception $e) {
                $this->logger->err(new Message('Error deleting index #%s, err: %s', $indexName, $e->getMessage()));
            }
        }

        // Create index with the list of properties configured in module.
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
                    'name' => $indexName,
                    'fields' => $indexFields,
                    'token_separators' => ['-'],
                ]
            );
        } catch (\Exception $e) {
            $this->logger->err(new Message('Error creating index #%s, err: %s', $indexName, $e->getMessage()));
            return;
        }


        $batchSize = 1000;
        $totalDocuments = 0;
        $count = 0;
        do {
            $sqlBatch = $sql . " LIMIT $batchSize OFFSET $totalDocuments;";

            $results = $connection->fetchAll($sqlBatch);

            $batchDocuments = array();
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

                for ($i = 0, $len = count($fields); $i < $len; $i++) {
                    $key = $fields[$i];
                    $value = $values[$i];

                    // Skip if the key doesn't exist in the document
                    if (!array_key_exists($key, $document)) {
                        continue;
                    }

                    array_push($document[$key], $value);
                }

                array_push($batchDocuments, $document);
            }

            $totalBatchDocuments = count($batchDocuments);
            $totalDocuments += $totalBatchDocuments;

            $this->logger->info(new Message('Fetched %s documents', count($batchDocuments)));


            // import the documents into typesense index.
            try {
                $response = $client->collections[$indexName]->documents->import($batchDocuments, [
                    'action' => 'create',
                ]);
            } catch (\Exception $e) {
                $this->logger->err(new Message('Error importing documents to index #%s, err: %s', $indexName, $e->getMessage()));
                return;
            }
            $this->logger->info(new Message('Finished above.'));

            // count the number of success
            $successes = array_column($response, "success");
            $count += count(array_keys($successes, 1));
        } while ($totalBatchDocuments === $batchSize);

        $timeTotal = (int) (microtime(true) - $timeStart);
        $this->logger->info(new Message('Finished indexing. Count(%s), Elapsed %s seconds', $count, $timeTotal));
    }
}
