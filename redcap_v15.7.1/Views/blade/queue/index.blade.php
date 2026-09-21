<h4 class="title"><i class="fas fa-tasks"></i> {{$lang['queue_system_monitor_page_title']}}</h4>

<div>

<p class="text-wrap">{!!$lang['queue_system_monitor_page_description']!!}</p>
</div>



<div id="queue-app"></div>


<style>
@import url('{{APP_PATH_JS}}vue/components/dist/style.css');
</style>

<script>
    if(!app_path_webroot_full) window.app_path_webroot_full = '{{$APP_PATH_WEBROOT_FULL}}';
    if(!redcap_version) window.redcap_version  = '{{$REDCAP_VERSION}}';
</script>

<script type="module">
import {QueueMonitor} from '{{$APP_PATH_JS}}vue/components/dist/lib.es.js'


window.addEventListener('DOMContentLoaded', e => {
    QueueMonitor('#queue-app')
})

</script>