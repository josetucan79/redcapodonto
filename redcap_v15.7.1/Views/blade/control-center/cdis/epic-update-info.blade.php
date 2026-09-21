@php
$checkEpicUpgradeEligibility = function() use($form_data) {
    $upgradableClientIds = [
        'prod'=>'3b69d8c5-6f17-451f-b137-02953e258e19',
        'non-prod'=>'6717e289-d9b9-461e-95df-6b3bb2edea71',

        'test'=>'77b4aee5-ee0e-46f4-9df8-8fd3367488ed',
        'test-non-prod'=>'8503bafc-8fe8-4631-a657-0b4a00019bf5',
    ];
    $fhir_client_id = @$form_data['fhir_client_id'];
    return in_array($fhir_client_id, $upgradableClientIds);
};
$canUpgrade = $checkEpicUpgradeEligibility();
$redcapAppOrchardLink = 'https://apporchard.epic.com/Gallery?id=11302';
$redcapAppVersion = '2.0';
@endphp



@if($canUpgrade)
<div class="mt-2 alert alert-primary alert-dismissible fade show" role="alert">
		<span type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></span>
    <p>
        <i class="fas fa-info-circle"></i> <!-- this icon's 1) style prefix == fas and 2) icon name == camera -->
        <strong>Upgrade available!</strong>
    </p>
    <p>A new version of the REDCap app is available on the Epic App Orchard.</p>

    <!-- Button trigger modal -->
    <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#upgradeModal">
      <span>learn more</span>
    </button>
</div>

<!-- Modal -->
<div class="modal fade" id="upgradeModal" tabindex="-1" aria-labelledby="upgradeModal" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="upgradeModal">Upgrade available</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p>REDCap app <strong>version {{$redcapAppVersion}}</strong> is available on the Epic App Orchard.</p>
        <p>The app is compatible with the <strong>R4 FHIR</strong> standard and provides new resources like:</p>
        <ul class="font-italic">
            <li>Adverse Events</li>
            <li>Core Characteristics (Observation)</li>
            <li>Encounters</li>
            <li>Immunizations</li>
        </ul>
        <p>The new App is <strong>backward compatible</strong> with the <strong>DSTU2 FHIR</strong> standard currently used in REDCap.</p>
        <p>To start the upgrade process follow <a href="{{$redcapAppOrchardLink}}" target="_blank">this link <i class="fas fa-external-link-alt"></i></a> and have someone with authority at your institution click on the "request download" button.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
        <a class="btn btn-primary btn-sm text-light" role="button" href="{{$redcapAppOrchardLink }}" target="_blank">Upgrade</a>
      </div>
    </div>
  </div>
</div>


<style>
    [role="alert"].alert {
        border: 1px solid rgba(0,0,0,.1) !important;
    }

</style>
@endif