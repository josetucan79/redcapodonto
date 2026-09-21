<?php


// Config for non-project pages


require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";

//If user is not a super user, go back to Home page
if (!ACCOUNT_MANAGER) redirect(APP_PATH_WEBROOT);



## DISPLAY PAGE
include 'header.php';
?>

<h4 id='email_users_header' style='margin-top: 0;'><i class="fas fa-envelope"></i> <?= $lang['email_users_02'] ?></h4>

<p><?= Language::tt('email_users_introduction_text_1') . " " .Language::tt('email_users_29', 'no emails for suspended users') ?></p>

<div class="mt-3 mb-2">
<div id="email-users" style="min-height:300px"></div>
</div>


<div class="email-footer mt-2">
	<div class="border rounded p-2">
		<div class="d-flex flex-column gap-1 mt-2">
			<span class="fw-bold d-block">Compose Message</span>
			<div>
				<span><i class="fas fa-edit fa-fw text-secondary"></i>:</span>
				<?= Language::tt('email_users_legend_edit', 'Edits the selected query.') ?>
			</div>
			<div>
				<span><i class="fas fa-vial fa-fw text-success"></i>:</span>
				<?= Language::tt('email_users_legend_test', 'Shows the results (list of users) for the selected query.') ?>
			</div>
			<div>
				<span><i class="fas fa-plus fa-fw text-primary"></i>:</span>
				<?= Language::tt('email_users_legend_create', 'Creates a new query.') ?>
			</div>
			<div>
				<span><i class="fas fa-file-lines fa-fw text-info"></i>:</span>
				<?= Language::tt('email_users_legend_preview', 'Preview a message.') ?>
			</div>
		</div>
	</div>

	<div class="border rounded p-2">
		<div class="d-flex flex-column gap-1 mt-2">
			<span class="fw-bold d-block">User Filter Manager</span>
			<div>
				<span><i class="fas fa-circle-plus fa-fw text-success"></i>:</span>
				<?= Language::tt('email_users_query_legend_add_rule', 'add new rule.') ?>
			</div>
			<div>
				<span><i class="fas fa-folder-plus fa-fw text-success"></i>:</span>
				<?= Language::tt('email_users_query_legend_add_group', 'add new group.') ?>
			</div>
			<div>
				<span><i class="fas fa-chevron-up fa-fw text-primary"></i>:</span>
				<?= Language::tt('email_users_query_legend_move_up', 'move node up.') ?>
			</div>
			<div>
				<span><i class="fas fa-chevron-down fa-fw text-primary"></i>:</span>
				<?= Language::tt('email_users_query_legend_move_down', 'move node down.') ?>
			</div>
			<div>
				<span><i class="fas fa-arrow-up-from-bracket fa-fw text-primary"></i>:</span>
				<?= Language::tt('email_users_query_legend_promote', 'promote node.') ?>
			</div>
			<div>
				<span><i class="fas fa-trash fa-fw text-danger"></i>:</span>
				<?= Language::tt('email_users_query_legend_delete', 'delete node.') ?>
			</div>
		</div>
	</div>

</div>


<style>
@import url('<?= APP_PATH_JS ?>vue/components/dist/style.css');
.email-footer {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 3px;
	> * {
		height: 100%;
	}
}
#email-users li.nav-item { font-weight: 600 !important; }
</style>

<script type="module">
import {EmailUsers} from '<?= getJSpath('vue/components/dist/lib.es.js') ?>'

EmailUsers('#email-users')

</script>

<?php
include 'footer.php';
