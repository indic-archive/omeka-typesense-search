<?php

declare(strict_types=1);

namespace TypesenseSearch;

if (!class_exists(\Generic\AbstractModule::class)) {
    require file_exists(dirname(__DIR__) . '/Generic/AbstractModule.php')
        ? dirname(__DIR__) . '/Generic/AbstractModule.php'
        : __DIR__ . '/src/Generic/AbstractModule.php';
}

use Generic\AbstractModule;
use Laminas\EventManager\Event;
use Laminas\Mvc\MvcEvent;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\View\Renderer\PhpRenderer;
use Laminas\EventManager\SharedEventManagerInterface;
use Omeka\Module\Exception\ModuleCannotInstallException;
use Typesense\Client;
use Symfony\Component\HttpClient\HttplugClient;
use Omeka\Stdlib\Message;


class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);

        // load vendor sdk (eg., typesense-php)
        require_once __DIR__ . '/vendor/autoload.php';

        // allow this controller for all users.
        $acl = $this->getServiceLocator()->get('Omeka\Acl');
        $acl->allow(null, 'TypesenseSearch\Controller\SearchController');
    }

    protected function preInstall(): void
    {
        if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
            $t = $this->getServiceLocator()->get('MvcTranslator');
            throw new ModuleCannotInstallException(
                $t->translate('The Typesense library should be installed.') // @translate
                    . ' ' . $t->translate('See module’s installation documentation.') // @translate
            );
        }
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        // add search assets
        $sharedEventManager->attach(
            '*',
            'view.layout',
            [$this, 'addSearchAssets']
        );

        // add listeners for add/update/delete
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.create.post',
            [$this, 'updateSearchIndex']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.update.post',
            [$this, 'updateSearchIndex']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.delete.post',
            [$this, 'updateSearchIndex']
        );
    }

    public function updateSearchIndex(Event $event): void
    {
        $serviceLocator = $this->getServiceLocator();
        $api = $serviceLocator->get('Omeka\ApiManager');
        $settings = $serviceLocator->get('Omeka\Settings');
        $logger = $serviceLocator->get('Omeka\Logger');

        $urlParts = parse_url($settings->get('typesense_url'));
        $indexName = $settings->get('typesense_search_index');
        $indexProperties = $settings->get('typesense_index_properties');
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

        $request = $event->getParam('request');
        $response = $event->getParam('response');

        if ($request->getOperation() == 'create' || $request->getOperation() == 'update') {
            $updated = $response->getContent();

            // skip private items
            if (!$updated->isPublic()) {
                return;
            }
            $document = [
                'resource_id' => strval($updated->getId()),
            ];

            foreach ($indexProperties as $property) {
                $fieldName = str_replace(":", "_", $property);
                $document[$fieldName] = [];
            }

            foreach ($updated->getValues() as $value) {
                $p = $value->getProperty();
                $fieldName = $p->getVocabulary()->getPrefix() . "_" . $p->getLocalName();
                $fieldValue = $value->getValue();

                if (!array_key_exists($fieldName, $document)) {
                    continue;
                }

                array_push($document[$fieldName], strval($fieldValue));
            }

            try {
                $response = $client->collections[$indexName]->documents->upsert($document);
            } catch (\Exception $e) {
                $logger->err(new Message('Error upserting resource(#%s) to index #%s, err: %s', $updated->getId(), $indexName, $e->getMessage()));
                return;
            }

            $logger->info(new Message('Upserted resource(#%s) to index #%s', $updated->getId(), $indexName));
        } else if ($request->getOperation() == 'delete') {
            $id = $request->getId();

            try {
                $response = $client->collections[$indexName]->documents->delete(['filter_by' => 'resource_id:' . $id]);
            } catch (\Exception $e) {
                $logger->err(new Message('Error deleting resource(#%s) from index #%s, err: %s', $id, $indexName, $e->getMessage()));
                return;
            }
            $logger->info(new Message('Deleted resource(#%s) from index #%s', $id, $indexName));
        }
    }

    /**
     * Get asset revision path based on the gulp-rev manifest.
     */
    protected function assetRevPath($filename)
    {
        $manifestPath = __DIR__ . '/rev-manifest.json';

        if (file_exists($manifestPath)) {
            $manifest = json_decode(file_get_contents($manifestPath), TRUE);
        } else {
            $manifest = [];
        }

        if (array_key_exists($filename, $manifest)) {
            return $manifest[$filename];
        }

        return $filename;
    }

    /**
     * Adds search assets into all views.
     */
    public function addSearchAssets(Event $event): void
    {
        $view = $event->getTarget();
        $assetUrl = $view->plugin('assetUrl');
        $view->headScript()
            ->appendFile($assetUrl('public/' . $this->assetRevPath('bundle.js'), 'TypesenseSearch'), 'text/javascript');

        $view->headLink()
            ->appendStylesheet($assetUrl('public/' . $this->assetRevPath('stylesheet.css'), 'TypesenseSearch'));
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $config = $this->getConfig();
        $space = strtolower(static::NAMESPACE);
        if (empty($config[$space]['config'])) {
            return true;
        }

        $services = $this->getServiceLocator();
        $formManager = $services->get('FormElementManager');
        $formClass = static::NAMESPACE . '\Form\ConfigForm';
        if (!$formManager->has($formClass)) {
            return true;
        }

        $params = $controller->getRequest()->getPost();

        $form = $formManager->get($formClass);
        $form->init();
        $form->setData($params);
        if (!$form->isValid()) {
            $controller->messenger()->addErrors($form->getMessages());
            return false;
        }

        $params = $form->getData();

        $settings = $services->get('Omeka\Settings');
        $prevIndexName = $settings->get('typesense_search_index');

        $defaultSettings = $config[$space]['config'];
        $params = array_intersect_key($params, $defaultSettings);
        foreach ($params as $name => $value) {
            $settings->set($name, $value);
        }

        // Set index properties from the form post.
        $indexProperties = $controller->params()->fromPost('index-properties', []);
        $settings->set('typesense_index_properties', $indexProperties);

        // Set result formatting
        $resultFormatting = $controller->params()->fromPost('typesense_search_result_format');
        $settings->set('typesense_search_result_format', $resultFormatting);

        // Get job dispatcher.
        $curIndexName = $settings->get('typesense_search_index');
        $dispatcher = $services->get('Omeka\Job\Dispatcher');

        // Index name changed, delete older one.
        if ($prevIndexName != $curIndexName) {
            $jobArgs = [];
            $jobArgs["index_name"] = $prevIndexName;
            $dispatcher->dispatch(\TypesenseSearch\Job\DeleteIndex::class, $jobArgs);
        }

        // Create a new index
        $jobArgs = [];
        $jobArgs["index_name"] = $curIndexName;
        $jobArgs["index_fields"] = $indexProperties;
        $dispatcher->dispatch(\TypesenseSearch\Job\CreateIndex::class, $jobArgs);

        return true;
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $services = $this->getServiceLocator();

        $formManager = $services->get('FormElementManager');
        $formClass = static::NAMESPACE . '\Form\ConfigForm';
        if (!$formManager->has($formClass)) {
            return '';
        }

        // Simplify config of modules.
        $renderer->ckEditor();

        $settings = $services->get('Omeka\Settings');

        $this->initDataToPopulate($settings, 'config');

        $data = $this->prepareDataToPopulate($settings, 'config');
        if (is_null($data)) {
            return '';
        }

        $form = $formManager->get($formClass);
        $form->init();
        $form->setData($data);
        $form->prepare();
        return $renderer->render('typesense-search/config-form', [
            'data' => $data,
            'form' => $form,
            'indexProperties' => $data['typesense_index_properties'],
            'resultFormatting' => $data['typesense_search_result_format'],
        ]);
    }
}
