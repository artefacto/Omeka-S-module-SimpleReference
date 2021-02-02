<?php
namespace SimpleReference\Service\BlockLayout;

use Interop\Container\ContainerInterface;
use SimpleReference\Site\BlockLayout\SimpleReference;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SimpleReferenceFactory implements FactoryInterface
{
	public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
	{
		return new SimpleReference(
			$services->get('FormElementManager'),
			$services->get('Config')['DefaultSettings']['SimpleReferenceBlockForm']
		);
	}
}
?>