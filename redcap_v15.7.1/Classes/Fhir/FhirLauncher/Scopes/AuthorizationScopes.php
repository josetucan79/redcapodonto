<?php

namespace Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\Scopes;


final class AuthorizationScopes extends Scopes
{
	protected $scopes = [
		'launch', // do not send in standalone launch
		'openid',
		'fhirUser',
		'online_access', // for refresh token
		'*/AdverseEvent.read',
		'*/AllergyIntolerance.read',
		'*/Appointment.read',
		'*/CarePlan.read',
		'*/Condition.read',
		'*/Coverage.read',
		'*/Device.read',
		'*/Binary.read',
		'*/DocumentReference.read',
		'*/DiagnosticReport.read',
		'*/Encounter.read',
		'*/FamilyMemberHistory.read',
		'*/Immunization.read',
		'*/MedicationOrder.read',
		'*/MedicationRequest.read',
		'*/Observation.read',
		'*/Patient.read',
		'*/Procedure.read',
		'*/ResearchStudy.read',
		'*/QuestionnaireResponse.read',
		'*/QuestionnaireResponse.write',
	];
	
}