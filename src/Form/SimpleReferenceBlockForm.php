<?php
namespace SimpleReference\Form;

use Zend\Form\Element;
use Zend\Form\Form;

class SimpleReferenceBlockForm extends Form
{
	public function init()
	{
		$this->add([
			'name' => 'o:block[__blockIndex__][o:data][title]',
			'type' => Element\Text::class,
            'options' => [
				'label' => 'Title (option)',
			]
		]);

        $this->add([
            'name' => 'o:block[__blockIndex__][o:data][property_name]',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Date property name',
                'info' => 'Please insert property name to be used for sorting by year.',
            ],
        ]);

        $this->add([
            'name' => 'o:block[__blockIndex__][o:data][property_name2]',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'End date property name (range search)',
                'info' => 'Please insert property name to be used for end date.',
            ],
        ]);
	}
}
