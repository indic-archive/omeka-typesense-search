<?php

declare(strict_types=1);

namespace TypesenseSearch\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;

class ConfigForm extends Form
{
    public function init(): void
    {
        $this
            ->add([
                'type' => Element\Text::class,
                'name' => 'typesense_host',
                'options' => [
                    'label' => 'Typesense host here.',
                ],
                'attributes' => [
                    'id' => 'typesense_host',
                    'required' => true,
                ],
            ])

            ->add([
                'type' => Element\Text::class,
                'name' => 'typesense_protocol',
                'options' => [
                    'label' => 'Typesense protocol here.',
                ],
                'attributes' => [
                    'id' => 'typesense_protocol',
                    'required' => true,
                ],
            ])

            ->add([
                'type' => Element\Text::class,
                'name' => 'typesense_port',
                'options' => [
                    'label' => 'Typesense port here.',
                ],
                'attributes' => [
                    'id' => 'typesense_port',
                    'required' => true,
                ],
            ])

            ->add([
                'type' => Element\Text::class,
                'name' => 'typesense_api_key',
                'options' => [
                    'label' => 'Typesense API Key',
                ],
                'attributes' => [
                    'id' => 'typesense_api_key',
                    'required' => true,
                ],
            ])

            ->add([
                'type' => Element\Text::class,
                'name' => 'typesense_search_index',
                'options' => [
                    'label' => 'Typesense Search Index',
                ],
                'attributes' => [
                    'id' => 'typesense_search_index',
                    'required' => true,
                ],
            ]);

        $this->getInputFilter()
            ->add([
                'name' => 'typesense_host',
                'required' => true,
            ])
            ->add([
                'name' => 'typesense_protocol',
                'required' => true,
            ])
            ->add([
                'name' => 'typesense_port',
                'required' => true,
            ])
            ->add([
                'name' => 'typesense_api_key',
                'required' => true,
            ])
            ->add([
                'name' => 'typesense_search_index',
                'required' => true,
            ]);
    }
}
