<?php
namespace ExternalModules;

$project_id = $arguments[0];

?>
<script type="text/javascript">
	if(window.ExternalModules === undefined){
		window.ExternalModules = {}
	}

	window.ExternalModules.moduleDependentRequest = function(url, action){
		$.get(url, function(response){
			try{
				response = JSON.parse(response)
				action(response)
			}
			catch(e){
				if(response.startsWith('<!DOCTYPE')){
					console.error('An unexpected response was returned from ' + url)
					/**
					 * Assume the login page is being returned per:
					 * https://redcap.vumc.org/community/post.php?id=138578
					 */
					return
				}

				// Escape the response to prevent injection
				response = $('<div/>').text(response).html()
				
				simpleDialog(
					"<p><?=ExternalModules::tt('em_errors_164')?>:</p><pre style='max-height: 50vh; white-space: pre-wrap;'>" + response + "</pre>",
					<?=json_encode(ExternalModules::tt('em_manage_75'))?>,
					'module-error-dialog',
					1000
				)
			}
		})
	}
</script>
<?php

if(in_array(ExternalModules::getUsername(), [null, \System::SURVEY_RESPONDENT_USERID])){
	// Skip the following code for unauthenticated users.
	return;
}

/**
 * This line shouldn't be here, but it has been for a long time,
 * and removing it might have downstream effects for modules.
 * I guess we'll leave it for now.
 */
set_include_path('.' . PATH_SEPARATOR . get_include_path());

$links = ExternalModules::getLinks();

$linkDisplayed = false;

?>
<script type="text/javascript">
	<?php
	/**
	 * This if statement prevents the redcap_module_link_check_display() call below
	 * from executing on the EM control center page, in order to allow admins to disable modules
	 * even there's something wrong preventing the module from being instantiated.
	 */
	if(!ExternalModules::isControlCenterPage()){ ?>
		$(function () {
			if ($('#project-menu-logo').length > 0) {
				var menubox = $('#external_modules_panel .x-panel-body .menubox .menubox')

				<?php
				foreach($links as $_=>$link){
					$prefix = $link['prefix'];
					$framework_instance = ExternalModules::getFrameworkInstance($prefix);
					$module_instance = $framework_instance->getModuleInstance();

					try{
						$new_link = $module_instance->redcap_module_link_check_display($project_id, $link);
						if($new_link){
							if(is_array($new_link)){
								$link = $new_link;
							}
							// Moved this check here, as it makes no sense to append a link that has no display name
							// (which could be the case after returning from the hook).
							if(empty($link["name"])){
								continue;
							}

							$linkDisplayed = true;
							?>
							menubox.append(<?=json_encode($framework_instance->getLinkIconHtml($link))?>);
							<?php
						}
					}
					catch(\Throwable $e){
						ExternalModules::handleError(
							//= An exception was thrown when generating links
							ExternalModules::tt("em_errors_77"),
							$e->__toString(), $prefix);
					}
				}

				?>
			}
		})
	<?php } ?>
</script>

<?php

if(!$linkDisplayed && empty(ExternalModules::getMenuHeaderLinks($project_id))){
	/**
	 * Hide the External Modules section, since it doesn't contain any links.
	 * Hiding via style is faster than javascript, and prevents lag/flash for the user.
	 */
	echo "
		<style>
			#external_modules_panel{
				display: none;
			}
		</style>
	";
}

if(ExternalModules::isRoute('DataImportController:index')){
	ExternalModules::callHook('redcap_module_import_page_top', [$project_id]);
}
