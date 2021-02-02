<?php
namespace SimpleReference\Site\BlockLayout;

use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Zend\View\Renderer\PhpRenderer;

use Laminas\ServiceManager\Factory\FactoryInterface;

use SimpleReference\Form\SimpleReferenceBlockForm;
use Laminas\Form\FormElementManager;

class SimpleReference extends AbstractBlockLayout
{
	/**
     * @var FormElementManager
     */
    protected $formElementManager;

    /**
     * @var array
     */
	protected $defaultSettings = [];
	
    /**
     * @param FormElementManager $formElementManager
     * @param array $defaultSettings
     */
    public function __construct(FormElementManager $formElementManager, array $defaultSettings)
    {
        $this->formElementManager = $formElementManager;
        $this->defaultSettings = $defaultSettings;
    }

	public function getLabel() {
		return 'SimpleReference';
	}

	public function form(PhpRenderer $view, SiteRepresentation $site,
        SitePageRepresentation $page = null, SitePageBlockRepresentation $block = null
    ) {
		$form = $this->formElementManager->get(SimpleReferenceBlockForm::class);

		$data = $block
			? $block->data() + $this->defaultSettings
			: $this->defaultSettings;
		$form->setData([
			'o:block[__blockIndex__][o:data][title]' => $data['title'],
			'o:block[__blockIndex__][o:data][property_name]' => $data['property_name'],
			'o:block[__blockIndex__][o:data][property_name2]' => $data['property_name2'],
		]);
		$form->prepare();

		$html = '';
		$html .= '<a href="#" class="collapse" aria-label="collapse"><h4>' . $view->translate('Options'). '</h4></a>';
		$html .= '<div class="collapsible" style="padding-top:6px;">';
		$html .= $view->formCollection($form);
        $html .= '</div>';
		return $html;
    }

	public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
	{
        $site = $view->site;

        $services = $site->getServiceLocator();
        $connection = $services->get('Omeka\Connection');

        $property_name = '';
        if($block->dataValue('property_name') != '') {
            $property_name = $block->dataValue('property_name');
        }

        $property_name2 = '';
        if($block->dataValue('property_name2') != '') {
            $property_name2 = $block->dataValue('property_name2');
        }

        $grouped_items = array();

        $property_id = 0;
        $property_id2 = 0;

        if($property_name2 != '') {
            $qb = $connection->createQueryBuilder();

            $expr = $qb->expr();

            $parts = explode(':', $property_name2);
            if (count($parts) > 1) {
                $prefix = $parts[0];
                $local_name = $parts[1];

                $qb
                    ->select([
                        'property.id AS id'
                    ])
                    ->from('property', 'property')
                    ->innerJoin('property', 'vocabulary', 'vocabulary', 'property.vocabulary_id = vocabulary.id')
                    ->where($expr->eq('vocabulary.prefix', ':vocabulary_prefix'))
                    ->where($expr->eq('property.local_name', ':local_name'));
                $stmt = $connection->executeQuery($qb, ['vocabulary_prefix' => $prefix, 'local_name' => $local_name]);
                // Fetch by key pair is not supported by doctrine 2.0.
                $properties = $stmt->fetch(\PDO::FETCH_ASSOC);

                if ($properties !== false) {
                    $property_id2 = array_shift($properties);
                }
            }
        }

        if($property_name != '') {
            $qb = $connection->createQueryBuilder();

            $expr = $qb->expr();

            $parts = explode(':', $property_name);
            if (count($parts) > 1) {
                $prefix = $parts[0];
                $local_name = $parts[1];

                $qb
                    ->select([
                        'property.id AS id'
                    ])
                    ->from('property', 'property')
                    ->innerJoin('property', 'vocabulary', 'vocabulary', 'property.vocabulary_id = vocabulary.id')
                    ->where($expr->eq('vocabulary.prefix', ':vocabulary_prefix'))
                    ->where($expr->eq('property.local_name', ':local_name'));
                $stmt = $connection->executeQuery($qb, ['vocabulary_prefix' => $prefix, 'local_name' => $local_name]);
                // Fetch by key pair is not supported by doctrine 2.0.
                $properties = $stmt->fetch(\PDO::FETCH_ASSOC);

                if ($properties !== false) {
                    $property_id = array_shift($properties);
                }
            }
        }

        if($property_id > 0 && $property_id2 == 0) {

            $sql = "SELECT va_.value FROM `value` va_ 
            INNER JOIN item_site is_ ON va_.resource_id = is_.item_id   
            WHERE is_.site_id = :site_id AND va_.property_id = :property_id AND va_.is_public = 1 ORDER BY va_.value ASC";

            $stmt = $connection->executeQuery($sql, ['site_id' => $site->id(), 'property_id' => $property_id]);

            $items_by_year = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            foreach($items_by_year as $item) {
                $item = substr($item, 0, 4);
                if(!is_numeric($item)) {
                    continue;
                }
                $century = floor($item / 100);
                $decade = floor($item / 10);
                $year = intval($item);

                if(!isset($grouped_items[$century])) {
                    $grouped_items[$century]["total"] = 0;
                    $grouped_items[$century]["minyear"] = intval($century*100);
                    $grouped_items[$century]["maxyear"] = intval($century*100 + 99);
                    $grouped_items[$century]["children"] = array();
                }

                if(!isset($grouped_items[$century]["children"][$decade])) {
                    $grouped_items[$century]["children"][$decade]["minyear"] = intval($decade*10);
                    $grouped_items[$century]["children"][$decade]["maxyear"] = intval($decade*10 + 9);
                    $grouped_items[$century]["children"][$decade]["total"] = 0;
                    $grouped_items[$century]["children"][$decade]["children"] = array();
                }

                if(!isset($grouped_items[$century]["children"][$decade]["children"][$year])) {
                    $grouped_items[$century]["children"][$decade]["children"][$year]["total"] = 0;
                }

                $grouped_items[$century]["total"]++;
                $grouped_items[$century]["children"][$decade]["total"]++;
                $grouped_items[$century]["children"][$decade]["children"][$year]["total"]++;
            }
        }

        if($property_id > 0 && $property_id2 > 0) {

//            die($property_id."-".$property_id2);

            $sql = "SELECT resource_id, value FROM `numeric_data_types_timestamp` AS ndtt WHERE ndtt.`property_id` = :property_id ORDER BY resource_id, ndtt.id ASC";

            $stmt = $connection->executeQuery($sql, ['property_id' => $property_id]);
            $gt_resource_values = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $stmt = $connection->executeQuery($sql, ['property_id' => $property_id2]);
            $lt_resource_values = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $start_dates_by_resource_id = array();
            $end_dates_by_resource_id = array();

            foreach($gt_resource_values as $resource_value) {
                if(!isset($start_dates_by_resource_id[$resource_value['resource_id']])) {
                    $start_dates_by_resource_id[$resource_value['resource_id']] = array();
                }
                $start_dates_by_resource_id[$resource_value['resource_id']][] = $resource_value['value'];
            }

            foreach($lt_resource_values as $resource_value) {
                if(!isset($end_dates_by_resource_id[$resource_value['resource_id']])) {
                    $end_dates_by_resource_id[$resource_value['resource_id']] = array();
                }
                $end_dates_by_resource_id[$resource_value['resource_id']][] = $resource_value['value'];
            }

            $lowest_date = false;
            $biggest_date = false;

            $sql = "SELECT value FROM `numeric_data_types_timestamp` AS ndtt WHERE ndtt.`property_id` = :property_id ORDER BY value ASC LIMIT 1";
            $stmt = $connection->executeQuery($sql, ['property_id' => $property_id]);
            $lowest_date_value = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            if(count($lowest_date_value)) {
                $lowest_date = $lowest_date_value[0];
            }

            $sql = "SELECT value FROM `numeric_data_types_timestamp` AS ndtt WHERE ndtt.`property_id` = :property_id ORDER BY value DESC LIMIT 1";
            $stmt = $connection->executeQuery($sql, ['property_id' => $property_id2]);
            $biggest_date_value = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            if(count($biggest_date_value)) {
                $biggest_date = $biggest_date_value[0];
            }

            $min_year = 0;
            $max_year = 0;

            if($lowest_date && $biggest_date) {
                $min_year = date("Y",$lowest_date);
                $max_year = date("Y",$biggest_date);
            }

            if($min_year > 0 && $max_year > 0 && $max_year > $min_year) {

                for($year = $min_year; $year <= $max_year; $year++) {

                    $century = floor($year / 100);
                    $decade = floor($year / 10);

                    if(!isset($grouped_items[$century])) {
                        $grouped_items[$century]["total"] = 0;
                        $grouped_items[$century]["minyear"] = intval($century*100);
                        $grouped_items[$century]["maxyear"] = intval($century*100 + 99);
                        $grouped_items[$century]["children"] = array();

                        $gt_timestamp = mktime(0, 0, 0, 12, 31, $grouped_items[$century]["minyear"]-1);
                        $lt_timestamp = mktime(0, 0, 0, 1, 1, $grouped_items[$century]["maxyear"]+1);

                        $valid_resource_ids_century = array();

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
                                    if(!($startDateValue < $gt_timestamp && $endDateValue < $gt_timestamp) && !($startDateValue > $lt_timestamp && $endDateValue > $lt_timestamp)) {
                                        $valid = true;
                                    }
                                }
                            }

                            if($valid) {
                                $valid_resource_ids_century[$resource_id] = $resource_id;
                            }
                        }

                        $grouped_items[$century]["total"] = count($valid_resource_ids_century);
                    }

                    if(!isset($grouped_items[$century]["children"][$decade])) {
                        $grouped_items[$century]["children"][$decade]["minyear"] = intval($decade*10);
                        $grouped_items[$century]["children"][$decade]["maxyear"] = intval($decade*10 + 9);
                        $grouped_items[$century]["children"][$decade]["total"] = 0;
                        $grouped_items[$century]["children"][$decade]["children"] = array();

                        $gt_timestamp = mktime(0, 0, 0, 12, 31, $grouped_items[$century]["children"][$decade]["minyear"]-1);
                        $lt_timestamp = mktime(0, 0, 0, 1, 1, $grouped_items[$century]["children"][$decade]["maxyear"]+1);

                        $valid_resource_ids_decade = array();

                        foreach($start_dates_by_resource_id as $resource_id => $dates) {
                            $valid = false;

                            if(!isset($valid_resource_ids_century[$resource_id])) {
                                continue;
                            }

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
                                    if(!($startDateValue < $gt_timestamp && $endDateValue < $gt_timestamp) && !($startDateValue > $lt_timestamp && $endDateValue > $lt_timestamp)) {
                                        $valid = true;
                                    }
                                }
                            }

                            if($valid) {
                                $valid_resource_ids_decade[$resource_id] = $resource_id;
                            }
                        }

                        $grouped_items[$century]["children"][$decade]["total"] = count($valid_resource_ids_decade);
                    }

                    if(!isset($grouped_items[$century]["children"][$decade]["children"][$year])) {

                        $gt_timestamp = mktime(0, 0, 0, 12, 31, $year-1);
                        $lt_timestamp = mktime(0, 0, 0, 1, 1, $year+1);

                        $valid_resource_ids = array();

                        foreach($start_dates_by_resource_id as $resource_id => $dates) {

                            if(!isset($valid_resource_ids_decade[$resource_id])) {
                                continue;
                            }

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
                                    if(!($startDateValue < $gt_timestamp && $endDateValue < $gt_timestamp) && !($startDateValue > $lt_timestamp && $endDateValue > $lt_timestamp)) {
                                        $valid = true;
                                    }
                                }
                            }

                            if($valid) {
                                $valid_resource_ids[$resource_id] = $resource_id;
                            }
                        }

                        $grouped_items[$century]["children"][$decade]["children"][$year]["total"] = count($valid_resource_ids);
                    }
                }

            }

        }

		return $view->partial('common/block-layout/simple-reference', [
			'title' => $block->dataValue('title'),
            'items_by_year' => $grouped_items,
            'property_id' => $property_id,
            'property_id2' => $property_id2
		]);
	}
}
