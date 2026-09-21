<?php 
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\Shared;

/**
 * coding systems used in FHIR
 * 
 * @see https://hl7.org/fhir/2018Jan/terminologies-systems.html
 * @see https://hl7.org/fhir/2018Jan/icd.html
 * @see https://hl7.org/fhir/2018Jan/unii.html
 */
class CodingSystem
{
  /**
   * regular expressions used to identify coding systems
   */
  const  ICD_10_CM = '/^urn:oid:2.16.840.1.113883.6.90$/i'; // 'ICD-10-CM Clinical Modification'
  const  ICD_10_CM_1 = '/icd-10-cm/i'; // 'ICD-10-CM Clinical Modification'
  const  ICD_10_INTERNATIONAL_WHO = '/^urn:oid:2.16.840.1.113883.6.3$/i'; // "ICD-10 International (WHO)"
  const  ICD_10_INTERNATIONAL_WHO_DUTCH_VARIANT = '/^urn:oid:2.16.840.1.113883.6.3.2$/i'; // "ICD-10 International (Dutch Variant)"
  const  ICD_10_AE = '/^urn:oid:2.16.840.1.113883.6.3.1$/i'; // "ICD-10 American English"
  const  ICD_10_PCS = '/^urn:oid:2.16.840.1.113883.6.4$/i'; // "ICD-10 Procedure Codes"
  const  ICD_10_AM = '/icd-10-am/i'; // "ICD-10 Australian Modification"
  const  ICD_10_CANADA = '/^urn:oid:2.16.840.1.113883.6.94$/i'; // "ICD-10 Canadian Modification"
  const  ICD_10_CANADA_1 = '/icd-10-ca/i'; // "ICD-10 Canadian Modification"
  const  ICD_10_NL = '/^urn:oid:2.16.840.1.113883.6.3.2$/i'; // "ICD-10 NL"
  const  ICD_10_NL_1 = '/icd-10-nl/i'; // "ICD-10 NL"
  const  ICD_9_CM = '/icd-9-cm/i'; // 'ICD-9-CM'
  const  LOINC = '/loinc/i'; // 'LOINC'
  const  SNOMED_CT = '/^urn:oid:2.16.840.1.113883.6.96$/i'; // 'SNOMED CT'
  const  SNOMED_CT_1 = '/snomed/i'; // 'SNOMED CT'
  const  RxNorm = '/rxnorm/i'; // 'RxNorm'
  const  RxNorm_2 = '/^urn:oid:2.16.840.1.113883.6.88$/i'; // 'RxNorm'
  const  FDA_UNII = '/UNII/i'; // 'FDA UNII'
  const  FDA_UNII_2 = '/fdasis/i'; // 'FDA UNII'
  const  FDA_UNII_3 = '/^urn:oid:2.16.840.1.113883.4.9$/i'; // 'FDA UNII'
  const  NDF_RT = '/ndfrt/i'; // 'NDF-RT'
  const  NDF_RT_1 = '/^urn:oid:2.16.840.1.113883.3.26.1.5$/i'; // 'NDF-RT'
  const  CVX = '/cvx/i'; // 'CVX'
  const  NDC_NHRIC = '/ndc/i'; // 'NDC/NHRIC'
  const  AMA_CPT = '/cpt/i'; // 'AMA CPT'
  const  UCUM = '/unitsofmeasure/i'; // 'UCUM'
  const  NCI_Metathesaurus = '/ncimeta/i'; // 'NCI Metathesaurus'
  const  EXAMPLE_CLAIM_SUBTYPE_CODES = '^urn:oid:2.16.840.1.113883.4.642.1.567$'; // 'Example Claim SubType Codes'


  const RxNorm_NAME     = 'RxNorm';
  const NDF_RT_NAME     = 'NDF-RT';
  const FDA_UNII_NAME   = 'FDA UNII';
  const SNOMED_CT_NAME  = 'SNOMED CT';
  const ICD_9_CM_NAME   = 'ICD-9-CM';
  const ICD_10_CM_NAME  = 'ICD-10-CM';
}




