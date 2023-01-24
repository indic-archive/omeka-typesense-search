<?php

declare(strict_types=1);

namespace TypesenseSearch\Job;

use Omeka\Job\AbstractJob;
use Omeka\Stdlib\Message;
use Typesense\Client;
use Symfony\Component\HttpClient\HttplugClient;

class CreateIndex extends AbstractJob
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

        $timeStart = microtime(true);
        $this->logger->info(new Message('Creating a new index #%s', $indexName));

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

        // create the index
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

        $timeTotal = (int) (microtime(true) - $timeStart);
        $this->logger->info(new Message('Finished creating index. Elapsed %s seconds', $timeTotal));
    }
}
