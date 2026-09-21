<?php
namespace ExternalModules;
set_include_path('.' . PATH_SEPARATOR . get_include_path());
require_once __DIR__ . '/../../redcap_connect.php';

$prefix = ExternalModules::getPrefix();
$pid = ExternalModules::getProjectId();

if(!ExternalModules::hasProjectSettingSavePermission($prefix)){
	//= You do not have permission to get or set rich text files.
	throw new \Exception(ExternalModules::tt("em_errors_98")); 
}

$files = ExternalModules::getProjectSetting($prefix, $pid, ExternalModules::RICH_TEXT_UPLOADED_FILE_LIST);

if(!$files){
	$files = [];
}

if($_SERVER['REQUEST_METHOD'] == 'POST'){
	$file = @$_FILES['file'];
	$edocToDelete = @$_POST['edoc-to-delete'];

	if($file){
		$edocId = \Files::uploadFile($file);

		$files[] = [
			'edocId' => $edocId,
			'name' => $file['name']
		];
	}
	else if($edocToDelete){
		ExternalModules::deleteEDoc($edocToDelete);

		/**
		 * Using array_splice() is not safe here because of the way it was previously implemented
		 * which sometimes resulted in $files like [1 => $someDocId].
		 */
		$newFiles = [];
		foreach($files as $file){
			if($file['edocId'] == $edocToDelete){
				continue;
			}

			$newFiles[] = $file;
		}

		$files = $newFiles;
	}

	ExternalModules::setProjectSetting($prefix, $pid, ExternalModules::RICH_TEXT_UPLOADED_FILE_LIST, $files);
}

?>

<style>
	#external-modules-rich-text-upload-button{
		margin: 5px;
	}

	#external-modules-rich-text-file-table{
		margin-top: 5px;
		border-collapse: collapse;
		width: 100%;
	}

	#external-modules-rich-text-file-table tr{
		border-top: 1px solid #dadada;
	}

	#external-modules-rich-text-file-table td{
		padding: 5px;
	}

	#external-modules-rich-text-file-table form{
		margin-bottom: 0px;
	}
</style>

<script type='text/javascript' src='<?= APP_PATH_WEBROOT . 'Resources/webpack/js/bundle.js' ?>'></script>

<button id="external-modules-rich-text-upload-button">
	<!--= Upload a file -->
	<?=ExternalModules::tt("em_manage_83")?>
</button>

<table id="external-modules-rich-text-file-table">
	<?php
	foreach($files as $file){
		$edocId = ExternalModules::escape($file['edocId']);
		$name = $file['name'];
		?>
		<tr>
			<td><a href="<?=ExternalModules::escape(ExternalModules::getRichTextFileUrl($prefix, $pid, $edocId, $name))?>"><?=ExternalModules::escape($name)?></a></td>
			<td>
				<form method='POST' enctype='multipart/form-data'>
					<input type="hidden" name="edoc-to-delete" value="<?=$edocId?>">
					<input type="hidden" name="redcap_csrf_token" value="<?=\System::getCsrfToken()?>">
					<button class="delete">
						<!--= Delete -->
						<?=ExternalModules::tt("em_manage_84")?>
					</button>
				</form>
			</td>
		</tr>
		<?php
	}
	?>
</table>

<form id='external-modules-rich-text-form' method='POST' enctype='multipart/form-data' style='display: none'>
	<input name="file" type="file">
	<input type="hidden" name="redcap_csrf_token" value="<?=\System::getCsrfToken()?>">
</form>

<script type="text/javascript">
	$(function() {
		var form = $('#external-modules-rich-text-form')
		var fileInput = form.find('input[type=file]')

		$('#external-modules-rich-text-upload-button').click(function () {
			fileInput.click()
		})

		fileInput.change(function () {
			form.submit()
		})
	})

	$(function(){
		var table = $('#external-modules-rich-text-file-table')

		table.find('a').click(function(e){
			e.preventDefault()
			parent.ExternalModules.currentFilePickerCallback(this.href)
		})

		table.find('button.delete').click(function(){
			//= Are you sure you want to permanently delete this file?
			return confirm(<?=ExternalModules::tt_js("em_manage_85")?>)
		})
	})
</script>
