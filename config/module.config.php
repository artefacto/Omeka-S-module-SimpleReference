<?php
namespace SimpleReference;

use Zend\Math\Rand;

return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ]
    ],
	'block_layouts' => [
        'factories' => [
            'simplereference' => Service\BlockLayout\SimpleReferenceFactory::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\SimpleReferenceBlockForm::class => Form\SimpleReferenceBlockForm::class,
        ],
    ],
    'DefaultSettings' => [
        'SimpleReferenceBlockForm' => [
            'title' => '',
            'property_name' => '',
            'property_name2' => '',
        ]
    ]
];