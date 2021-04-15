<?php
// cj custom
namespace Corbinjurgens\Bouncer\Control;
use Cache;
use Auth;

use Corbinjurgens\Bouncer\Control;

trait Tools
{
	
	
	public static function groupPermissions($permissions){
		return $permissions->groupBy(['entity_type', 'entity_id'])
			->map(function($item){
				return $item->map(function($item){
					return $item->keyBy('name');
				});
			});
	}
	
	public static function optionsArray($array, $defaults){
		if (!is_array($array)) return [];
		$processed = [];
		foreach($array as $key => $value){
			$target_key = (is_array($value) ? $key : $value);
			$options = (is_array($value) ? array_replace($defaults, $value) : $defaults);
			
			$processed[$target_key] = $options;
		}
		return $processed;
	}
	
}