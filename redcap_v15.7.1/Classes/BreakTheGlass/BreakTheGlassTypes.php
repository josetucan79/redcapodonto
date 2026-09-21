<?php
namespace Vanderbilt\REDCap\Classes\BreakTheGlass;

final class BreakTheGlassTypes {
  
	// user types
	const USER_INTERNAL = 'Internal';
	const USER_EXTERNAL = 'External';
	const USER_EXTERNALKEY = 'ExternalKey';
	const USER_CID = 'CID';
	const USER_NAME = 'Name';
	const USER_SYSTEMLOGIN = 'SystemLogin';
	const USER_ALIAS = 'Alias';
	const USER_IIT = 'IIT';

	// patient types
	const PATIENT_TYPE_INTERNAL = 'Internal';
	const PATIENT_TYPE_EXTERNAL = 'External';
	const PATIENT_TYPE_CID = 'CID';
	const PATIENT_TYPE_MRN = 'MRN';
	const PATIENT_TYPE_NATIONALID = 'NationalID';
	const PATIENT_TYPE_CSN = 'CSN';
	const PATIENT_TYPE_FHIR = 'FHIR';
	// MyChart login name (WPR 110) ??
	// Identity ID Type Descriptor (I IIT 600) ??

	// department types
	const DEPARTMENT_TYPE_INTERNAL = 'Internal';
	const DEPARTMENT_TYPE_EXTERNAL = 'External';
	const DEPARTMENT_TYPE_EXTERNALKEY = 'ExternalKey';
	const DEPARTMENT_TYPE_CID = 'CID';
	const DEPARTMENT_TYPE_NAME = 'Name';
	const DEPARTMENT_TYPE_IIT = 'IIT';


	public static function userTypes() {
		return [
			self::USER_INTERNAL,
			self::USER_EXTERNAL,
			self::USER_EXTERNALKEY,
			self::USER_CID,
			self::USER_NAME,
			self::USER_SYSTEMLOGIN,
			self::USER_ALIAS,
			self::USER_IIT,
		];
	}

	public static function patientTypes() {
		return [
				self::PATIENT_TYPE_INTERNAL,
			self::PATIENT_TYPE_EXTERNAL,
			self::PATIENT_TYPE_CID,
			self::PATIENT_TYPE_MRN,
			self::PATIENT_TYPE_NATIONALID,
			self::PATIENT_TYPE_CSN,
			self::PATIENT_TYPE_FHIR,
		];
	}

	public static function departmentTypes() {
		return [
				self::DEPARTMENT_TYPE_INTERNAL,
			self::DEPARTMENT_TYPE_EXTERNAL,
			self::DEPARTMENT_TYPE_EXTERNALKEY,
			self::DEPARTMENT_TYPE_CID,
			self::DEPARTMENT_TYPE_NAME,
			self::DEPARTMENT_TYPE_IIT,
		];
	}

}