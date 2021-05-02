<?php
// cj custom
namespace Corbinjurgens\Bouncer\Control\Concerns;


use Corbinjurgens\Bouncer\Control as c;
use Corbinjurgens\Bouncer\Database\Models;
use Corbinjurgens\Bouncer\Control\Tools;
use Corbinjurgens\Bouncer\Contracts\Clipboard;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Container\Container;
use InvalidArgumentException;
use Auth;

trait DealsWithUsers
{
	use Tools;
	
	private $current_authority = null;
	
	private $target_authority = null;
	
	private function processAuthority($authority = null){
		if (is_string($authority)) {
			$authority = Models::role()->firstOrCreate([
                'name' => $authority
            ]);
        }
		return $authority;
	}
	
	private $target_permissions;
	
	/**
	 * Get permissions directly, not from getAbilities or getForbiddenAbilitis,
	 * to ensure we are only getting abilities directly on the authority
	 * as opposed to on the user, role and everyone
	 */
	private function loadPermissions($authority = null){
		$permissions = [
			'abilities' => ($authority instanceof Model) ? $authority->getDirectAbilities() : Container::getInstance()->make(Clipboard::class)->getDirectAbilities(null),
			'forbidden_abilities' => ($authority instanceof Model) ? $authority->getDirectForbiddenAbilities() : Container::getInstance()->make(Clipboard::class)->getDirectAbilities(null, false),
		];
		return $permissions;
	}
	
	private $target_type = null;
	
	private function loadType($authority = null){
		if (is_null($authority)){
			return 'everyone';
		}else if ($authority instanceof Model){
			return $authority->getTable() == Models::table('roles') ? 'role' : 'user';
		}else{
			throw new InvalidArgumentException(
				"User type not valid"
			);
		}
	}
	
	private function loadCurrent($authority = null){
		$authority = $authority ?? Auth::user();
		$this->current_authority = $this->processAuthority($authority);
	}
	private function loadTarget($authority = null){
		$this->target_authority = $this->processAuthority($authority);
		$this->target_permissions = $this->loadPermissions($this->target_authority);
		$this->target_type = $this->loadType($this->target_authority);
		
	}
	
	private $strict_user_mode = true;
	
	/**
	 * Allow anyone with access to the permission page to give any permissions
	 * even if they themselves cannot do the action
	 * In the case you have multiple levels of admins it may be best to leave this as low level admin could
	 * make any other user do somthing they cant, or even edit their own permissions if the ui allows it
	 * By default it will be strict true to prevent this, but you can set strict false
	 */
	public function setStrict(bool $strict_user_mode = true){
		$this->strict_user_mode = $strict_user_mode;
		return $this;
	}
	
	private function getUserAdmin(){
		return $this->target_authority ? $this->target_authority->admin : 0;
	}
	
	/**
	 * Permission checks
	 */
	protected function userCan($user, $ability, $table = null, $id = null){
		if ($user === null){
			// It is a question if I should instead check for abilities that everyone can do
			// but for now I will assume everyone means everyone logged in
			return false;
		}
		$instance = $this->toTableInstance($table, $id);
		return $user->can($ability, $instance) == true;
	}
	protected function currentUserCan($ability, $table = null, $id = null){
		if ($this->strict_user_mode === false){
			return True;
		}
		// Only run if set to check if current user has the right (so they dont give rights for things they dont have themselves etc)
		return $this->userCan($this->current_authority, $ability, $table, $id);
	}
	protected function targetUserCan($ability, $table = null, $id = null){
		return $this->userCan($this->target_authority, $ability, $table, $id);
	}
	
	/**
	 * Checks if according to the bouncercontrol config, the users admin level is even one 
	 * that allows the permission types (ie, permissions, anything, forbid_permissions) at all
	 * By looking to $user->admin
	 * Check auto passes if config minimum checks are null, or if authority admin passes null (eg if giving permissions to role)
	 */
	protected function userCanBasic($user, $level){
		if (is_null($level)){
			return true;
		}
		$admin_level = ($user instanceof Model) ? $user->admin : null;
		return is_null($admin_level) ? true : $admin_level >= $level;
	}
	
	
	
	
	
	
}