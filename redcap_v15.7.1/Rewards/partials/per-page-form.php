<form method="get" style="display: inline;">
    <?php foreach ($_GET as $key => $val): if ($key === '_per_page') continue; ?>
        <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($val) ?>">
    <?php endforeach; ?>
    <label>
        Per page:
        <select name="_per_page" onchange="this.form.submit()">
            <?php foreach ([10, 25, 50, 100] as $size): ?>
                <option value="<?= $size ?>" <?= $perPage == $size ? 'selected' : '' ?>><?= $size ?></option>
            <?php endforeach; ?>
        </select>
    </label>
</form>
