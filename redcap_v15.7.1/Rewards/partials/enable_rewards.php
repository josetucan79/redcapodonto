<?php
function generateAlphanumericString($length = 5) {
    // Define the characters that can be used in the string
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';

    // Generate a random string of the specified length
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }

    return $randomString;
}
$htmlElementID = generateAlphanumericString();
?>
<div data-rewards-<?=$htmlElementID?>>
    <div data-header>
        <p><?= $lang['rewards_project_enable_message'] ?></p>
    </div>
    <form action="" id="rewards_setup_form">
        <div class="form-group">
            <label class="font-weight-bold" for="rewards_enabled">Rewards Services</label>
            <div>
                <select name="rewards_enabled">
                    <option value="0" <?= $rewards_enabled ? '' : 'selected' ?>><?= $lang['global_23'] ?></option>
                    <option value="1" <?= $rewards_enabled ? 'selected' : '' ?>><?= $lang['index_30'] ?></option>
                </select>
            </div>
        </div>
        <?php if($rewards_enablement_message) : ?>
        <div class="py-2 my-2 border-top border-bottom">
            <?= remove_html_tags($rewards_enablement_message, ['script','style']) ?>
        </div>
        <div class="d-flex align-items-center justify-content-start gap-2 pt-2" data-span-2>
            <input type="hidden" name="rewards_confirm_continue" value="0">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="rewards_confirm_continue" id="rewards_confirm_continue" value="1" <?= $rewards_enabled ? 'checked' : '' ?>>
                <label class="form-check-label text-dangerrc boldish" for="rewards_confirm_continue">Confirm and continue</label>
            </div>
        </div>
        <?php else : ?>
            <input type="hidden" name="rewards_confirm_continue" value="1" >
        <?php endif; ?>
    </form>
</div>

<style>


[data-rewards-<?=$htmlElementID?>] form {
    border: 1px solid #e0e0e0;
    padding: 10px;
    background-color: #f8f8f8;
}

[data-rewards-<?=$htmlElementID?>] .form-group {
    display: grid;
    grid-template-columns: 1fr 1fr;
    grid-gap: 10px;
    align-items: center;
    margin-bottom: 0;
}

[data-rewards-<?=$htmlElementID?>] [data-span-2] {
    grid-column: span 2;
}

[data-rewards-<?=$htmlElementID?>] label {
    margin-right: 10px;
    margin-bottom: 0;
}

[data-rewards-<?=$htmlElementID?>] .form-group input {
    padding: 5px;
    border: 1px solid #ccc;
    border-radius: 4px;
}

</style>