<?php

declare(strict_types=1);

namespace TypesenseSearch;

if (!class_exists(\Generic\AbstractModule::class)) {
    require file_exists(dirname(__DIR__) . '/Generic/AbstractModule.php')
        ? dirname(__DIR__) . '/Generic/AbstractModule.php'
        : __DIR__ . '/src/Generic/AbstractModule.php';
}

use Generic\AbstractModule;
// use Laminas\EventManager\Event;
use Laminas\Mvc\MvcEvent;
// use Laminas\EventManager\SharedEventManagerInterface;
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

    // public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    // {
    //     $sharedEventManager->attach(
    //         '*',
    //         'view.layout',
    //         [$this, 'addTypesense']
    //     );
    // }

    // public function addTypesense(Event $event): void
    // {
    //     $view = $event->getTarget();
    //     $assetUrl = $view->plugin('assetUrl');
    //     $view->headScript()
    //         ->appendFile($assetUrl('js/typesense.min.js', 'TypesenseSearch'), 'text/javascript', ['defer' => 'defer']);
    // }
}
