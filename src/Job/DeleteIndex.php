<?php

declare(strict_types=1);

namespace TypesenseSearch\Job;

use Omeka\Job\AbstractJob;
use Omeka\Stdlib\Message;
use Typesense\Client;
use Symfony\Component\HttpClient\HttplugClient;

class DeleteIndex extends AbstractJob
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

        // delete the index
        try {
            $client->collections[$indexName]->delete();
        } catch (\Exception $e) {
            $this->logger->err(new Message('Error deleting index #%s, err: %s', $indexName, $e->getMessage()));
            return;
        }

        $timeTotal = (int) (microtime(true) - $timeStart);
        $this->logger->info(new Message('Deleted index #%s. Elapsed %s seconds', $indexName, $timeTotal));
    }
}
