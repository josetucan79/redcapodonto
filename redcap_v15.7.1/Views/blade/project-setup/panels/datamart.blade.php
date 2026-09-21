@php
/**
 * helper functions
 */
// class applied to container of each settting
$getEnabledClass = function($property) {
    return boolval($property) ? 'enabled' : '';
};
// get enabled or disabled based on property
$getEnabledDisabled = function($property) use($lang){
    $enabled_text = $lang['control_center_153'];
    $disabled_text = $lang['survey_152'];
    return $property ? $enabled_text : $disabled_text;
};
// return the checked attribute for a button
$getChecked = function($property) {
    return boolval($property) ? 'checked="checked"' : '';
};
// return the class icon
$getIconClass = function($property) {
    if(boolval($property)) return 'fas fa-check-circle';
    else return 'fas fa-minus-circle'; 
};
@endphp

<span>{{$lang['data_mart_refresh_010']}}</span>
<div class="project-setup-option-wrapper {{$getEnabledClass($datamart_allow_create_revision)}}">
    <button
        {!!$getChecked($datamart_allow_create_revision)!!}
        class="btn btn-defaultrc btn-xs fs11"
        onclick="toggleSetting(event, 'datamart_allow_create_revision')">
        {{$getEnabledDisabled($datamart_allow_create_revision)}}
    </button>
    <i class="{{$getIconClass($datamart_allow_create_revision)}}" ></i>
    <span>
        {{$lang['data_mart_refresh_012']}}
        <span class="savedMsg">{{$lang['design_243']}}</span>
    </div>
</span>
<div class="project-setup-option-wrapper {{$getEnabledClass($datamart_allow_repeat_revision)}}">
    <button
        {!!$getChecked($datamart_allow_repeat_revision)!!}
        class="btn btn-defaultrc btn-xs fs11"
        onclick="toggleSetting(event, 'datamart_allow_repeat_revision')">
        {{$getEnabledDisabled($datamart_allow_repeat_revision)}}
    </button>
    <i class="{{$getIconClass($datamart_cron_enabled)}}"></i>
    <span>
        {{$lang['data_mart_refresh_011']}}
        <span class="savedMsg">{{$lang['design_243']}}</span>
        
    </span>
</div>
<div class="project-setup-option-wrapper {{$getEnabledClass($datamart_cron_enabled)}}">
    <button
        {!!$getChecked($datamart_cron_enabled)!!}
        class="btn btn-defaultrc btn-xs fs11"
        onclick="toggleSetting(event, 'datamart_cron_enabled')">
        {{$getEnabledDisabled($datamart_cron_enabled)}}
    </button>
    <i class="{{$getIconClass($datamart_cron_enabled)}}"></i>
    <span>
        {{$lang['data_mart_refresh_016']}}
        <span class="savedMsg">{{$lang['design_243']}}</span>
        @if($datamart_cron_enabled)
        <section class="cron-job-buttons d-block">
            @if($datamart_cron_end_date_alt)
            <div class="btn-group" role="group" aria-label="Basic example">
                <button type="button" class="btn btn-defaultrc btn-xs fs11 cron-job-date-trigger">
                    <i class="fas fa-calendar-alt"></i>
                    <span>{{$lang['control_center_4771']}} {{$datamart_cron_end_date_alt}}</span>
                </button>
                <button type="button" class="btn btn-defaultrc btn-xs fs11 cron-job-date-remove-trigger" title="{{$lang['survey_152']}}">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            @else
            <button class="btn btn-defaultrc btn-xs fs11 cron-job-date-trigger">
                <i class="fas fa-calendar-alt"></i>
                <span>{{$lang['control_center_4772']}}</span>
            </button>
            @endif
        </section>
        @endif
        <input readonly class="hidden" type="text" id="cron-job-datepicker" value="{{$datamart_cron_end_date}}">
    </span>

</div>

<script>
    (function(window, document, jQuery){
        /**
            * add a listener to an element to show the date modal
            */
        function setDateSelectListener(element, cron_job_datepicker) {
            element.addEventListener('click', function(event) {
                event.preventDefault()
                cron_job_datepicker.show().focus().hide();
            });
        }

        /**
            * add a listener to an element to set a date to null
            */
        function removeDateListener(element, callback) {
            element.addEventListener('click', function(event) {
                // closure for removing date setting
                var modal_title = "{{$lang['project_setup_modal_title']}}";
                var modal_body = "{{$lang['project_setup_modal_body']}}";
                var modal_okButton = "{{$lang['control_center_153']}}";
                modalDialog(modal_title, modal_body, modal_okButton).done(function() {
                    showSaveFeedback(element)
                    callback()
                })
            });
        }

        // show feedback when saving
        function showSaveFeedback(element) {
            try {
                var wrapper = element.closest('.project-setup-option-wrapper')
                var feedback_element = wrapper.querySelector('.savedMsg')
                feedback_element.style.visibility = 'visible'
                setTimeout(function() {
                    feedback_element.style.visibility = 'hidden'
                }, 500);
            } catch (error) {
                console.log('cannot find feedback element')
            }
        }

        $( function() {
            let pid = window.pid || null;
            let setting_name = 'datamart_cron_end_date';
            var datepicker_element = document.querySelector('#cron-job-datepicker');
            var cron_job_datepicker = $(datepicker_element).datepicker({
                dateFormat: "yy-mm-dd",
                altFormat: "mm-dd-yy",
                minDate: new Date(),
                // post setting and reload on select
                onSelect: function(value,element) {
                    if(!pid) return;
                    postSetting(pid, setting_name, value).done(function() {
                        showSaveFeedback(datepicker_element)
                        location.reload();
                    })
                }
            });
            // manage date change
            var cron_job_date_trigger_list = document.querySelectorAll('.cron-job-date-trigger');
            cron_job_date_trigger_list.forEach(function(element) {
                setDateSelectListener(element, cron_job_datepicker)
            });
            
            // manage date removal
            var removeDateCallback = function() {
                postSetting(pid, setting_name, null).done(function() {
                    location.reload();
                })
            };
            var cron_job_date_remove_trigger_list = document.querySelectorAll('.cron-job-date-remove-trigger');
            cron_job_date_remove_trigger_list.forEach(function(element) {
                removeDateListener(element, removeDateCallback)
            })
        } );
    }(window, document, jQuery))
</script>
    
<style>
.project-setup-option-wrapper {
    padding:2px 0;
    font-size:13px;
    color:#800000;
    display: flex;
    align-items: baseline;
}
.project-setup-option-wrapper.enabled {
    color: green;
}
.project-setup-option-wrapper > i {
    margin-right: 4px;
    margin-left: 6px;
}
</style>