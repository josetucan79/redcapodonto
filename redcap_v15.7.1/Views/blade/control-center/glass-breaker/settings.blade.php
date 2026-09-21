@php
use Vanderbilt\REDCap\Classes\BreakTheGlass\GlassBreaker;

/**
 * config names:
 * - fhir_break_the_glass_enabled (disabled, FHIR, username_token)
 * - fhir_break_the_glass_username_token_base_url
 * - fhir_break_the_glass_token_usertype
 * - fhir_break_the_glass_token_username
 * - fhir_break_the_glass_token_password
 */

function isSelected($current, $option)
{
	return ($current==$option) ? 'selected' : '';
}
@endphp

{{-- title --}}
<tr>
	<td class="cc_label" style="font-weight:normal;border-top:1px solid #ccc;" colspan="2" >
		<div style="margin-bottom:10px;font-weight:bold;color:#C00000;">{{ $lang['break_glass_003'] }}</div>
		<div style="margin-bottom:10px;">{!! $lang['break_glass_004'] !!}</div>
	</td>
</tr>

{{-- is enabled --}}
<tr>
	<td class="cc_label">
        {{$lang['break_the_glass_settings_01']}}
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="max-width:380px;" name="fhir_break_the_glass_enabled">
            <option value="" {{ isSelected($form_data['fhir_break_the_glass_enabled'], '') }}>{{ $lang['break_the_glass_disabled'] }}</option>
            <option value="enabled" {{ isSelected($form_data['fhir_break_the_glass_enabled'], 'enabled') }}>{{$lang['break_the_glass_enabled']}}</option>
		</select>
        <div class="cc_info">
			<span>{{$lang['break_glass_description']}}</span><br/>
		</div>
	</td>
</tr>

{{-- EHR user type --}} 
<tr>
	<td class="cc_label">
		{{ $lang['break_glass_007'] }}
		<div class="cc_info">{!! $lang['break_glass_ehr'] !!}</div>
	</td>
	<td class="cc_data">
		<select class="x-form-text x-form-field" style="" name="fhir_break_the_glass_ehr_usertype">
			@foreach($userTypes as $ehr_user_type)
			<option value="{{$ehr_user_type}}" {{ isSelected($form_data['fhir_break_the_glass_ehr_usertype'], $ehr_user_type) }}>{{$ehr_user_type}}</option>
			@endforeach
		</select>
		<div class="cc_info">
			<span>{!! $lang['break_glass_usertype_ehr'] !!}</span><br/>
		</div>
	</td>
</tr>