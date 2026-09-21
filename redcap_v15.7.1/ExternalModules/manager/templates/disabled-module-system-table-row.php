<?php namespace ExternalModules;

/**
 * @psalm-suppress UndefinedGlobalVariable
 */
$versions = $versions;
/**
 * @psalm-suppress UndefinedGlobalVariable
 */
$moduleDirectoryPrefix = $moduleDirectoryPrefix;

$config = reset($versions);

// Determine if module is an example module
$isExampleModule = ExternalModules::isExampleModule($moduleDirectoryPrefix, array_keys($versions));

$deleteButtonDisabled = '';
if(isset($enabledModules[$moduleDirectoryPrefix])){
    //= Change Version
    $enableButtonText = ExternalModules::tt("em_manage_58"); 
    $enableButtonIcon = 'fas fa-sync-alt';
}
else{
    //= Enable
    $enableButtonText = ExternalModules::tt("em_manage_59"); 
    $enableButtonIcon = 'fas fa-plus-circle';
    $deleteButtonDisabled = $isExampleModule ? 'disabled' : ''; // Modules cannot be deleted if they are example modules
}

$enableButtonStyle = '';
if(empty($config)){
    $name = "None (config.json is missing for $moduleDirectoryPrefix)";
    $enableButtonStyle = 'display: none';
}
else{
    $name = trim($config['name']);
    if(empty($name)){
        //= None ('name' is not specified in config.json for {0})
        $name = ExternalModules::tt("em_manage_60", $moduleDirectoryPrefix);
    }
}

?>
<tr data-module='<?= $moduleDirectoryPrefix ?>'>
    <td>
        <?= $name ?>
        <div class="cc_info">
        <?php if (isset($enabledModules[$moduleDirectoryPrefix])) { ?>
        <!--= (Current version: {0}) -->
        <?=ExternalModules::tt("em_manage_61", $enabledModules[$moduleDirectoryPrefix])?>
        <?php } else { ?>
        <!--= (Not enabled) -->
        <?=ExternalModules::tt("em_manage_62")?>
        <?php } ?>
        </div>
    </td>
    <td>
        <select name="version">
            <?php
            foreach(array_keys($versions) as $version){
                echo "<option>$version</option>";
            }
            ?>
        </select>
    </td>
    <td class="external-modules-action-buttons">
        <button class='btn btn-success btn-xs enable-button' style='<?=$enableButtonStyle?>'>
            <span class="<?=$enableButtonIcon?>" aria-hidden="true"></span>
            <?=$enableButtonText?>
        </button> &nbsp;
        <button class='btn btn-defaultrc btn-xs delete-selected-button' <?=$deleteButtonDisabled?>>
            <span class="far fa-trash-alt" aria-hidden="true"></span>
            <?=ExternalModules::tt("em_manage_136")?>
        </button>
    </td>
</tr>