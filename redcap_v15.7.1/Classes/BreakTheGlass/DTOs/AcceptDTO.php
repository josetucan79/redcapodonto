<?php
namespace Vanderbilt\REDCap\Classes\BreakTheGlass\DTOs;

use Vanderbilt\REDCap\Classes\DTOs\DTO;

class AcceptDTO extends DTO {
    /**
     * The Break-the-Glass view that was checked.
     * IsOptional: conditional 
     * IsArray: false
     *
     * @var String
     */
    public $CheckView;

    /**
     * The contact type if the check was for a specific encounter or contact.
     * IsOptional: true
     * IsArray: false
     *
     * @var String
     */
    public $ContactID;

    /**
     * The type of ID passed. Defaults to DAT. The possible values are DAT, UCI, and CSN.
     * IsOptional: true
     * IsArray: false
     *
     * @var String
     */
    public $ContactIDType;

    /**
     * The department where the user broke the glass.
     * IsOptional: true
     * IsArray: false
     *
     * @var String
     */
    public $DepartmentID;

    /**
     * The type of department ID passed. Defaults to Internal. The possible values are Internal, External, ExternalKey, Name, CID, and Identity ID Types.
     * IsOptional: true
     * IsArray: false
     *
     * @var String
     */
    public $DepartmentIDType;

    /**
     * The explanation the user entered for breaking the glass.
     * IsOptional: true
     * IsArray: false
     *
     * @var String
     */
    public $Explanation;

    /**
     * The token returned by STU3 and R4 FHIR requests containing data that the user must break the glass to access. Returned in OperationOutcome.issue.extension (fhir-btg-token).valueString.
     * IsOptional: conditional 
     * IsArray: false
     *
     * @var String
     */
    public $FhirBTGToken;

    /**
     * The patient's ID.
     * IsOptional: conditional 
     * IsArray: false
     *
     * @var String
     */
    public $PatientID;

    /**
     * Type for the provided ID (Internal, External, CID, Identity ID Type Descriptor, NationalID, CSN, MyChart login name, or FHIR). If the ID type is not provided, the default is Internal.
     * IsOptional: true
     * IsArray: false
     *
     * @var String
     */
    public $PatientIDType;

    /**
     * The reason the user entered for breaking the glass.
     * IsOptional: true
     * IsArray: false
     *
     * @var String
     */
    public $Reason;

    /**
     * The user's ID.
     * IsOptional: false
     * IsArray: false
     *
     * @var String
     */
    public $UserID;

    /**
     * The type of ID passed. Defaults to Internal. The possible values are Internal, External, Name, CID, SystemLogin, Alias, and Identity ID Types.
     * IsOptional: true
     * IsArray: false
     *
     * @var String
     */
    public $UserIDType;

}