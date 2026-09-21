<?php include __DIR__.'/partials/header.php'; ?>
<?php include __DIR__.'/partials/tabs.php'; ?>
<?php
$criteriaData = require __DIR__.'/partials/configuration_check.php';
$valid = $criteriaData['valid'] ?? false;
$legend = $criteriaData['legend'] ?? '';

?>
<?php if(!$valid) : ?>
<div style="width: auto; max-width: 800px;">
    <?= $legend ?>
</div>
<?php else : ?>
<div style="width: auto; max-width: 1000px;">
    <?php include __DIR__.'/partials/instructions.php'; ?>
    <div id="app"></div>
    <hr>
    <?php include __DIR__.'/partials/status_legend.php'; ?>
</div>
<style>
    @import url('<?= APP_PATH_JS ?>vue/components/dist/style.css');

    /* fix for the smart variables dialog positioning */
    .ui-dialog.ui-front[role="dialog"] {
        z-index: 999999;
    }
</style>

<script type="module">
import { Rewards } from '<?= getJSpath('vue/components/dist/lib.es.js') ?>'
Rewards('#app')

</script>
<script type="module">
    const initLogicTextArea = () => {
        const LogicTextArea = document.getElementById('logic')
        if(!LogicTextArea) return
        LogicTextArea.addEventListener('click', e => {
            openLogicEditor($(LogicTextArea));
            logicSuggestSearchTip(LogicTextArea, e);
        })
        LogicTextArea.addEventListener('keydown', e => {
            logicSuggestSearchTip(LogicTextArea, e);
        })
    }

</script>
<?php endif; ?>
<?php
// Footer
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
