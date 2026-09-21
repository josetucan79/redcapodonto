<?php

namespace Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\Scopes;


abstract class Scopes implements ScopesInterface
{
    const PATIENT_LEVEL = 'patient';
    const USER_LEVEL = 'user';
    const SYSTEM_LEVEL = 'system';
    const ASTERISK_LEVEL = '*';

    /**
     * level to apply to resource scopes
     *
     * @var string
     */
    protected $level = self::ASTERISK_LEVEL;
    
    /**
     * list of scopes
     *
     * @var array
     */
    protected $scopes = [];

    /**
     * regex used to filter available scopes
     *
     * @var string
     */
    protected $filter = null;

    public function get(): array {
        $filter = function($scope) {
            $regexp = $this->filter;
            if(is_null($regexp)) return true;
            return preg_match("/$regexp/i", $scope)!==1;
        };
        $callback = function($scope) {
            return $this->applyLevel($scope, $this->level);
        };
        $scopes = $this->scopes;
        $scopes = array_filter($scopes, $filter);
        $scopes = array_map($callback, $scopes);
        return $scopes;
    }

    public function setLevel($level) {
        $this->level = $level;
    }

    public function setFilter($regexp) {
        $this->filter = $regexp;
    }

    public function applyLevel($scope, $levelOverride) {
        $regexp = "/^(?:(?<level>(?!launch)[^\/]*)\/)?(?<resource>[^\.]+)(?:\.(?<permissions>.*))?/";
        preg_match($regexp, $scope, $matches);
        $level = $matches['level'] ?? '';
		if(!$level) return $scope; // return the scope unmodified if has no level
		$resource = $matches["resource"];
		// compose level and resource
		$modified_scope = "{$levelOverride}/{$resource}";
		// add the permission if available
        $permissions = $matches["permissions"] ?? null;
		if($permissions) $modified_scope .= ".$permissions";
		return $modified_scope;
    }

    public function __toString() {
        $scopes = $this->get();
        return implode(' ', $scopes);
    }

}