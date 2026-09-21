<?php

return [
    'tab' => function($id, $name, $activeClass, $ariaSelected) {
        ob_start();
        ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $activeClass ?>" id="<?= $id ?>-tab" data-bs-toggle="tab" data-bs-target="#<?= $id ?>" type="button" role="tab" aria-controls="<?= $id ?>" aria-selected="<?= $ariaSelected ?>"><?= $name ?></button>
        </li>
        <?php
        return ob_get_clean();
    },
    'pane' => function($id, $name, $showActiveClass, $content) {
        ob_start();
        ?>
        <div class="tab-pane fade <?= $showActiveClass ?>" id="<?= $id ?>" role="tabpanel" aria-labelledby="<?= $id ?>-tab"><?= $name ?>
            <?= $content ?>
        </div>
        <?php
        return ob_get_clean();
    },
    'tabsAndPanesContainer' => function($tabsHtml, $panesHtml) {
        ob_start();
        ?>
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <?= $tabsHtml ?>
        </ul>
        <div class="tab-content" id="myTabContent">
            <?= $panesHtml ?>
        </div>
        <?php
        return ob_get_clean();
    }
];
