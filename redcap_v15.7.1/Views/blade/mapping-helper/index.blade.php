@php
$objHtmlPage = new HtmlPage();
@endphp
<noscript>
    <strong>We're sorry but Mapping Helper doesn't work properly without JavaScript enabled. Please enable it to continue.</strong>
</noscript>

 @if(!$browser_supported)
<h3>
    <i class="fas fa-exclamation-triangle"></i>
    <span>This feature is not available for your browser.</span>
</h3>

@else
<h3>{{$lang['mapping_helper_01']}}</h3>

<div style="max-width:900px;">
    <p>
        The Mapping Helper utility will assist you in finding the fields in your EHR
        (electronic health record system) that you would like to utilize in your
        Clinical Data Pull project or Clinical Data Mart project.
        If you already have appropriate privileges for pulling data into REDCap from your EHR,
        you may enter a valid MRN (medical record number) into any of the tabs in the Mapping
        Helper to pull all the data for that patient for those data categories. Once the data is pulled,
        it will be displayed on the page for you to view. The EHR field names and LOINC codes will
        be displayed, thus allowing you to find the specific fields you need to use during the
        field mapping process of your CDP or Data Mart project.
    </p>
    <div class="alert alert-info">
        <head>Please note</head>
        <p>Dates returned by FHIR systems are in Zulu military time.</p>
        <p>Where applicable, the Mapping Helper will provide a "local" timestamp reflecting the timezone of the REDCap server (<?= date_default_timezone_get(); ?>).</p>
    </div>
    <div id="mapping-helper"></div>
</div>

<style>
    @import url('<?= APP_PATH_JS ?>vue/components/dist/style.css');
</style>

<script type="module">
import { MappingHelper } from '<?= getJSpath('vue/components/dist/lib.es.js') ?>'

MappingHelper('#mapping-helper')

</script>
@endif
