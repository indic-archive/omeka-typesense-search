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

        // create the index
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

        $timeTotal = (int) (microtime(true) - $timeStart);
        $this->logger->info(new Message('Finished creating index. Elapsed %s seconds', $timeTotal));
    }
}
