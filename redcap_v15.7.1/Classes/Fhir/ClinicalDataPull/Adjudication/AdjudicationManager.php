<?php

namespace Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication;

class AdjudicationManager
{
    private $dataRetrievalService;
    private $dataProcessingService;
    private $adjudicationTrackingService;
    private $userInterfaceService;
    private $preSelectionService;
    private $dataOrganizationService;
    private $dataNormalizationService;
    private $databaseService;
    private $errorHandlingService;

    public function __construct(
        DataRetrievalService $dataRetrievalService,
        DataProcessingService $dataProcessingService,
        AdjudicationTrackingService $adjudicationTrackingService,
        UserInterfaceService $userInterfaceService,
        PreSelectionService $preSelectionService,
        DataOrganizationService $dataOrganizationService,
        DataNormalizationService $dataNormalizationService,
        DatabaseService $databaseService,
        ErrorHandlingService $errorHandlingService
    ) {
        $this->dataRetrievalService = $dataRetrievalService;
        $this->dataProcessingService = $dataProcessingService;
        $this->adjudicationTrackingService = $adjudicationTrackingService;
        $this->userInterfaceService = $userInterfaceService;
        $this->preSelectionService = $preSelectionService;
        $this->dataOrganizationService = $dataOrganizationService;
        $this->dataNormalizationService = $dataNormalizationService;
        $this->databaseService = $databaseService;
        $this->errorHandlingService = $errorHandlingService;
    }

    private $processedData;
    private $overallMetadata;
    private $record;
    private $parameters;

    public function processData($record, $event_id, $parameters)
    {
        try {
            $this->record = $record;
            $this->parameters = $parameters;
            
            // 1. Error Handling
            if (!$this->errorHandlingService->checkForErrors($parameters['data_array_src'])) {
                throw new \Exception('No data returned for this record!');
            }
    
            // 2. Data Retrieval and Preparation
            $form_data = $parameters['form_data'];
            $instance = $parameters['instance'];
            $repeat_instrument = $parameters['repeat_instrument'];
            $day_offset = $parameters['day_offset'];
            $day_offset_plusminus = $parameters['day_offset_plusminus'];
            $data_array_src = $parameters['data_array_src'];

            $mappings = $this->dataRetrievalService->getFieldMappings();
            $mappingInfo = $this->dataRetrievalService->processMappedFields();
    
            $redcapData = $this->dataRetrievalService->fetchRedcapData($record, $mappingInfo);
            $redcapData = $this->dataRetrievalService->mergeFormData($redcapData, $form_data, $record, $event_id, $instance, $repeat_instrument);
            $sourceData = $this->dataRetrievalService->prepareSourceData($data_array_src);
            
            // 3. Data Merging and Processing
            $mergedData = $this->dataProcessingService->mergeData($redcapData, $sourceData, $mappings, $day_offset, $day_offset_plusminus);
            $mergedData = $this->dataProcessingService->filterEmptyValues($mergedData);
            $lockedForms = $this->dataProcessingService->getLockedFormsAndEvents($record);
            $validationPatterns = $this->dataProcessingService->setupFieldValidations($mappingInfo->getMappedFields());
            $mergedData = $this->dataProcessingService->validateAllChecks($mergedData, $validationPatterns, $lockedForms);
            $this->overallMetadata = $this->dataProcessingService->calculateOverallMetadata($mergedData);
    
            // 4. Adjudication Tracking
            $adjudicatedValues = $this->adjudicationTrackingService->trackAdjudications($record, $mappingInfo->getMapIdList());
    
            // 5. Data Organization
            $this->dataOrganizationService->initializeFieldTracking($mergedData, $adjudicatedValues);
            $sortedData = $this->dataOrganizationService->sortEventsAndFields($mergedData);
    
            // 6. Pre-selection Logic
            $sortedData = $this->preSelectionService->applyPreSelection($sortedData, $mappings);
            $this->processedData = $this->preSelectionService->processCheckboxFields($sortedData);
            
            // 7. Database Interaction
            $itemCount = $this->dataOrganizationService->getNewValuesCount();
            $this->databaseService->updateItemCount($record, $itemCount);
            
            return $this->processedData;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function generateHtml()
    {
        try {
            if (!$this->processedData) {
                throw new \Exception('Data must be processed first. Call processData() before generateHtml().');
            }

            $page = $this->parameters['page'];
            $instance = $this->parameters['instance'];
            $record = $this->record;
            
            $normalizedData = $this->dataNormalizationService->normalize($this->processedData);
            $lockedForms = $this->dataProcessingService->getLockedFormsAndEvents($record);
            $validationPatterns = $this->dataProcessingService->setupFieldValidations([]);
            
            $tableRows = $this->userInterfaceService->generateTableRows($normalizedData, [
                'lockedForms' => $lockedForms,
                'validationPatterns' => $validationPatterns,
            ]);
            
            $itemCount = $this->dataOrganizationService->getNewValuesCount();
            $recordIdentifier = $this->dataProcessingService->getRecordIdentifierField();
            
            return $this->userInterfaceService->assembleHtmlInterface($tableRows, [
                'itemCount' => $itemCount,
                'recordIdentifier' => $recordIdentifier,
                'lastFetchTime' => $this->parameters['last_fetch_time'],
                'page' => $page,
                'instance' => $instance,
                'overallMetadata' => $this->overallMetadata,
            ]);
        } catch (\Throwable $th) {
            $message = $th->getMessage();
            $code = $th->getCode();
            return "<div>$message – code $code</div>";
        }
    }

    public function getProcessedData()
    {
        return $this->processedData;
    }

    public function getOverallMetadata()
    {
        return $this->overallMetadata;
    }

    public function getItemCount()
    {
        return $this->dataOrganizationService->getNewValuesCount();
    }
}
