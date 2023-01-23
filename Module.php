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
        $sharedEventManager->attach(
            '*',
            'view.layout',
            [$this, 'addSearchAssets']
        );
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

        $useTypesenseHighlight = $controller->params()->fromPost('typesense_search_override_highlights');
        $settings->set('typesense_search_override_highlights', $useTypesenseHighlight);

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
            'useTypesenseHighlight' => $data['typesense_search_override_highlights'],
        ]);
    }
}
