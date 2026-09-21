<?php
namespace ExternalModules;

class User
{
	private $framework;
	private $username;
	private $user_info;

	function __construct($framework, $username){
		$this->framework = $framework;
		$this->username = $username;
	}

	function getRights($project_ids = null){
		return ExternalModules::getUserRights($project_ids, $this->username);
	}

	/**
	 * @return bool
	 */
	function hasDesignRights($project_id = null){
		if($this->isSuperUser()){
			return true;
		}

		if(!$project_id){
			$project_id = $this->framework->requireProjectId();
		}

		$rights = $this->getRights($project_id);
		return $rights['design'] === '1';
	}

	private function getUserInfo(){
		if(!isset($this->user_info)){
			$this->user_info = ExternalModules::getUserInfo($this->username);
		}

		return $this->user_info;
	}

	function getUsername() {
		return $this->username;
	}

	/**
	 * @return bool
	 */
	function isSuperUser(){
		$userInfo = $this->getUserInfo();
		return $userInfo['super_user'] === 1;
	}

	function getEmail(){
		$userInfo = $this->getUserInfo();
		return $userInfo['user_email'];
	}
}
