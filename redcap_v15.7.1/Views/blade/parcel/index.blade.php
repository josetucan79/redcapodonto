
<div style="max-width: 780px;">

    <div style="font-size:18px;">
        <h4 class="fs18" style="margin-top:10px;"><i class="fas fa-fire"></i> CDIS messages</h4>
    </div>
    <div>
        <p>This messaging system is designed to securely store and manage messages that could contain sensitive data such as protected health information (PHI).</p>
        <p>All messages can be read only by the designated recipient, and are encrypted to ensure the confidentiality and integrity of the data.</p>
        <p>The system includes a functionality that automatically deletes messages once they reach their expiration date:
            this ensures that PHI is only accessible to authorized individuals for the necessary duration, and that it is subsequently removed to maintain compliance with relevant regulations.</p>
        <p class="alert alert-info">Please note that messages displayed here could be related to any CDIS project.</p>
    </div>
    <div id="parcel-app"></div>
</div>

<style>
@import url('{{APP_PATH_JS}}vue/components/dist/style.css');
</style>
<script>
    if(!app_path_webroot_full) window.app_path_webroot_full = '{{$APP_PATH_WEBROOT_FULL}}';
    if(!redcap_version) window.redcap_version  = '{{$REDCAP_VERSION}}';
</script>
<script type="module">
import { Parcel } from '<?= getJSpath('vue/components/dist/lib.es.js') ?>'

Parcel('#parcel-app')
</script>
