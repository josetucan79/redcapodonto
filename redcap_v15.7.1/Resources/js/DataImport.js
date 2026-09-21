// Submit form to import records
function importDataSubmit(require_change_reason) {

	// If data change reason is required for existing record, loop through each, check for text in each, and add to form for submission
	if (require_change_reason)
	{
		var count_empty = 0;
		$('.change_reason').each(function(){
			var row_num = $(this).prop('id').replace('reason-','');
			var this_reason = $('#reason-'+row_num).val();
			if (trim(this_reason) == "") {
				count_empty++;
			} else {
				$('#change-reasons-div').append("<input name='records[]' value='"+$('#record-'+row_num).html()+"'><input name='events[]' value='"+$('#event-'+row_num).html()+"'><textarea name='reasons[]'>"+this_reason+"</textarea>");
			}
		});
		if (count_empty > 0) {
			$('#change-reasons-div').html('');
			var msg = lang.data_import_tool_394.replace('{0}', count_empty);
			simpleDialog(msg);
			return false;
		}
	}
	$('#uploadmain2').css('display','none');
	$('#progress2').css('display','block');
	document.form2.submit();
}