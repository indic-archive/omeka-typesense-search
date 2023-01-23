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
                'name' => 'typesense_url',
                'options' => [
                    'label' => 'Typesense URL',
                ],
                'attributes' => [
                    'id' => 'typesense_url',
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
                'name' => 'typesense_url',
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
