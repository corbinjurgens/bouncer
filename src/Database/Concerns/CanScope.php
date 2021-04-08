<?php

namespace Corbinjurgens\Bouncer\Database\Concerns;

use Corbinjurgens\Bouncer\Database\Models;
use Corbinjurgens\Bouncer\Clipboard;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Builder;

trait CanScope
{
	/**
	 * Query items only where the user can do something. 
	 * Multiple abilities defaults to an any query (user should be able to do at least one)
	 *
	 * @param $query
	 * @param array|string $ability
	 * @param \Illuminate\Database\Eloquent\Model|null $authority // only user, role not supported
	 * @param Closure $skip_closure // if resolves to true, doesn't apply query filtering, can be shared with the same Gate::before() closure used
	 * @return void
	 
	 */
    function scopeWhereCan(Builder $query, $ability, Model $authority = null, $skip_closure = null){
		$abilities = (array) $ability;
		$user = $authority ?? auth()->user();
		$forbid_ids = [];
		$allowed_ids = [];
		$force_empty = false;
		
		
		
		if ($skip_closure instanceof \Closure){
			foreach($abilities as $ability){
				$skip = $skip_closure($user, $ability, [$this]);
				if ($skip === true){
					return;
				}
			}
		}
		
		$pipe = Clipboard::collectionPipe($this, false, null, $abilities, false);
		$forbidden_abilities = $user->getForbiddenAbilities()->pipe($pipe);						
		$allowed_abilities = $user->getAbilities()->pipe($pipe);
		
		// ------ Forbidden --------
		// All abilities are forbidden for this user, and therefore each of the abilities
		// we are testing for will also be forbidden. Will check current table and * table
		$forbidden_abilities_all = $forbidden_abilities->whereNull('entity_id');
		$forbidden_abilities_total = $forbidden_abilities_all->where('only_owned', false);
		if ($forbidden_abilities_total->where('name', '*')->isNotEmpty()){
			//dd('force_empty_all');
			return $this->forceEmptyResults($query);
		}
		
		// Check for each ability to see if there are any that aren't blocked on the overall scope
		// Will check if any of the abilities aren't blocked, as we are looking to see if the user can do any
		$total_block = $forbidden_abilities_total->isNotEmpty();
		if ($total_block){
			foreach($abilities as $ability){
				$empty = $forbidden_abilities_total->where('name', $ability)->isEmpty();
				if ($empty === true){
					$total_block = false;
					break;
				}
			}
		}
		if ($total_block === true){
			//dd('force_empty');
			return $this->forceEmptyResults($query);
		}
		
		
		// ------ Owned Forbidden --------
		// All owned forbidden
		$owned_block = false;
		$forbidden_abilities_total_owned = $forbidden_abilities_all->where('only_owned', true);
		if ($forbidden_abilities_total->where('name', '*')->isNotEmpty()){
			$owned_block = true;
			//dd('force_empty_all_owned');
			
		}
		if (!$owned_block){
			// Specific ability owned forbidden
			$forbidden_abilities_specific_owned = $forbidden_abilities_total_owned->where('name', '!=', '*');
			$total_block = $forbidden_abilities_specific_owned->isNotEmpty();
			if($total_block){
				foreach($abilities as $ability){
					$empty = $forbidden_abilities_specific_owned->where('name', $ability)->isEmpty();
					if ($empty === true){
						$total_block = false;
						break;
					}
				}
			}
			if ($total_block === true){
				//dd('force_empty_owned_by_ability');
				$owned_block = true;
			}
		}
		
		if ($owned_block === true){
			$query->whereNotIn($this->qualifyColumn($this->getKeyName()), function($query) use ($user){
				$query->select('t.'.$this->getKeyName())
					->from($this->getTable() . ' as t');
					Models::applyOwnedVia($query, $user, $this);
			});
		}
		
		
		// ------ Specific Forbidden --------
		// Check specific forbidden
		$forbidden_abilities_grouped = $forbidden_abilities->whereNotNull('entity_id')->groupBy('entity_id');
		foreach($forbidden_abilities_grouped as $id => $group){
			if ($group->where('name', '*')->isNotEmpty()){
				$forbid_ids[] = $id;
			}else{
				$total_block = true;
		
				foreach($abilities as $ability){
					$empty = $group->where('name', $ability)->isEmpty();
					if ($empty === true){
						$total_block = false;
						break;
					}
				}
				if ($total_block === true){
					$forbid_ids[] = $id;
				}
				
			}
		}
		
		$query->whereNotIn($this->getKeyName(), array_values(array_unique($forbid_ids)));
		
		// ------ Allowed --------
		// Allowed all abilities
		$abilities_total = $allowed_abilities->whereNull('entity_id');
		if ($abilities_total->where('only_owned', false)->isNotEmpty()){
			//dd('all');
			return;
		}
		
		$query->where(function(Builder $query) use ($allowed_ids, $abilities_total, $allowed_abilities, $user){
			// If can own this type of model, get all those owned
			$abilities_owned = $abilities_total->where('only_owned', true);
			if ($abilities_owned->isNotEmpty()){
				Models::applyOwnedVia($query, $user, $this);
				
			}
			// Get explicitly allowed ids. Not checking only owned here, as setting a user allowed toOwn a specific item is not intended use
			$abilities_specific = $allowed_abilities->whereNotNull('entity_id');
			$allowed_ids = array_merge($allowed_ids, $abilities_specific->where('only_owned', false)->pluck('entity_id')->all());
			
			$query->orWhereIn($this->getKeyName(), array_values(array_unique($allowed_ids)));
		});
		//dd('complete');
		
		
	}
	private function forceEmptyResults($query){
		$query->whereRaw('0 = 1');
	}
}
