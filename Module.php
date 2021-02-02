<?php
namespace SimpleReference;

use Doctrine\ORM\QueryBuilder;
use Laminas\EventManager\Event;
use NumericDataTypes\DataType\Timestamp;
use Omeka\Module\AbstractModule;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Mvc\MvcEvent;

class Module extends AbstractModule
{
	public function getConfig()
	{
		return include __DIR__ . '/config/module.config.php';
	}

    public function attachListeners(\Laminas\EventManager\SharedEventManagerInterface $sharedEventManager)
    {
        $sharedEventManager->attach(
            'Omeka\Api\Adapter\ItemAdapter',
            'api.search.query',
            [$this, 'buildQuery']
        );
    }

    /**
     * Build numerical queries.
     *
     * @param Event $event
     */
    public function buildQuery(Event $event)
    {
        $query = $event->getParam('request')->getContent();
        if (!isset($query['simref'])) {
            return;
        }

        $adapter = $event->getTarget();
        $qb = $event->getParam('queryBuilder');

        $this->_buildQuery($adapter, $qb, $query);
    }

    private function _buildQuery(\Omeka\Api\Adapter\ItemAdapter $adapter, QueryBuilder $qb, array $query)
    {
        $timestampInstance = new Timestamp();

        $lt_set = false;
        $gt_set = false;

        if (isset($query['simref']['ts']['lt']['val'])
            && isset($query['simref']['ts']['lt']['pid'])
            && is_numeric($query['simref']['ts']['lt']['pid'])
        ) {
            $value = $query['simref']['ts']['lt']['val'];
            $lt_propertyId = $query['simref']['ts']['lt']['pid'];
            if ($timestampInstance->isValid(['@value' => $value])) {
                $date = $timestampInstance->getDateTimeFromValue($value);
                $lt_number = $date['date']->getTimestamp();
                $lt_set = true;
            }
        }
        if (isset($query['simref']['ts']['gt']['val'])
            && isset($query['simref']['ts']['gt']['pid'])
            && is_numeric($query['simref']['ts']['gt']['pid'])
        ) {
            $value = $query['simref']['ts']['gt']['val'];
            $gt_propertyId = $query['simref']['ts']['gt']['pid'];
            if ($timestampInstance->isValid(['@value' => $value])) {
                $date = $timestampInstance->getDateTimeFromValue($value);
                $gt_number = $date['date']->getTimestamp();
                $gt_set = true;
            }
        }

        if($lt_set && $gt_set) {

            $valid_resource_ids = array(0);

            $services = $this->getServiceLocator();
            $connection = $services->get('Omeka\Connection');

            $start_dates_by_resource_id = array();
            $end_dates_by_resource_id = array();

            $sql = "SELECT resource_id, value FROM `numeric_data_types_timestamp` AS ndtt WHERE ndtt.`property_id` = :property_id ORDER BY resource_id, ndtt.id ASC";

            $stmt = $connection->executeQuery($sql, ['property_id' => $gt_propertyId]);
            $gt_resource_values = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach($gt_resource_values as $resource_value) {
                if(!isset($start_dates_by_resource_id[$resource_value['resource_id']])) {
                    $start_dates_by_resource_id[$resource_value['resource_id']] = array();
                }
                $start_dates_by_resource_id[$resource_value['resource_id']][] = $resource_value['value'];
            }

            $stmt = $connection->executeQuery($sql, ['property_id' => $lt_propertyId]);
            $lt_resource_values = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach($lt_resource_values as $resource_value) {
                if(!isset($end_dates_by_resource_id[$resource_value['resource_id']])) {
                    $end_dates_by_resource_id[$resource_value['resource_id']] = array();
                }
                $end_dates_by_resource_id[$resource_value['resource_id']][] = $resource_value['value'];
            }

            foreach($start_dates_by_resource_id as $resource_id => $dates) {

                $valid = false;

                if(count($start_dates_by_resource_id[$resource_id]) > 0) {
                    foreach($dates as $k => $startDateValue) {
                        if(isset($end_dates_by_resource_id[$resource_id][$k])) {
                            $endDateValue = $end_dates_by_resource_id[$resource_id][$k];
                        }
                        else if($startDateValue >= 946728000){
                            $endDateValue = 9999999999999999999;
                        }
                        else {
                            $endDateValue = $startDateValue + 31622400;
                        }
                        if(!($startDateValue < $gt_number && $endDateValue < $gt_number) && !($startDateValue > $lt_number && $endDateValue > $lt_number)) {
                            $valid = true;
                        }
                    }
                }

                if($valid) {
                    $valid_resource_ids[$resource_id] = $resource_id;
                }
            }

            $qb->andWhere($qb->expr()->in(
                "omeka_root.id",
                implode(", ", $valid_resource_ids)
            ));

        }
    }


}