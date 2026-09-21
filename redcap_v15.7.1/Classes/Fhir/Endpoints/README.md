## How to add an endpoint to REDCap
* create the endpoint in Fhir/Endpoints/{FHIR version}
* add the category to Fhir/FhirCategory.php
* add the endpoint to the Endpoint factory in Fhir/Endpoints/{FHIR version}/EndpointFactory.php

## How to add a resource
* create the resource mapping in Fhir/Resources/{FHIR version}
* add the resource to the Resource factory in Fhir/Resources/{FHIR version}/ResourceFactory.php