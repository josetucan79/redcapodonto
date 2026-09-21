<?php

include 'header.php';
if (!ACCESS_CONTROL_CENTER) redirect(APP_PATH_WEBROOT);

// Get current admins
$admins = User::getAdmins();
// Add placeholder for adding new admins
$admins[0] = array('username'=>'', 'user_lastname'=>'', 'user_firstname'=>'', 'admin_rights'=>'0', 'super_user'=>'0', 'account_manager'=>'0', 'access_system_config'=>'0',
	                'access_system_upgrade'=>'0','access_external_module_install'=>'0', 'access_admin_dashboards'=>'0');

// User search text box
$usernameTextbox = RCView::div(array('class'=>'input-group ms-1 mb-1 fs12 nowrap'),
                        RCView::text(array('id'=>'user_search', 'class'=>'x-form-text x-form-field', 'placeholder'=>$lang['control_center_4745'], 'maxlength'=>'255', 'style'=>'width:180px')) .
						RCView::div(array('class'=>'input-group-append nowrap'),
                            RCView::button(array('id'=>'add_admin_btn', 'class'=>'btn btn-xs btn-rcgreen fs12 rcgreen_a11y'), '<i class="fas fa-plus"></i> '.$lang['design_171']) .
                            // Hidden input for userid value
                            RCView::hidden(array('id'=>'new_admin_userid'))
                        )
                    );

// Build table
$rows = '';
foreach ($admins as $this_ui_id=>$attr)
{
    $checked_admin_rights = $attr['admin_rights'] ? 'checked' : '';
    $disabled_admin_rights = ($this_ui_id == UI_ID) ? 'disabled' : ''; // Prevent the current user from accidentally removing their own rights to this page
    $checked_super_user = $attr['super_user'] ? 'checked' : '';
    $checked_account_manager = $attr['account_manager'] ? 'checked' : '';
    $checked_access_system_config = $attr['access_system_config'] ? 'checked' : '';
    $checked_access_system_upgrade = $attr['access_system_upgrade'] ? 'checked' : '';
    $checked_access_external_module_install = $attr['access_external_module_install'] ? 'checked' : '';
    $checked_access_admin_dashboards = $attr['access_admin_dashboards'] ? 'checked' : '';
    if ($attr['username'] == '') {
        // Add new user textbox
		$user_display = $usernameTextbox;
		$pt = " pt-3";
    } else {
        // Display user name
        $user_display = RCView::span(array('class'=>'mx-2'.($this_ui_id == UI_ID ? ' text-danger' : '')),
                            RCView::span(array('class'=>'fs13 boldish me-1'), $attr['username']) .
							" ".RCView::span(array('class'=>'text-secondary nowrap'),
                                ($attr['user_lastname'] == '' ? '' : $lang['leftparen'].$attr['user_firstname']." ".$attr['user_lastname'].$lang['rightparen'])
                            )
                        );
		$pt = "";
    }
    $rows .=
		RCView::tr(array('id'=>'user'.$this_ui_id),
			RCView::td(array('class'=>'fs12 p-1'.$pt, 'style'=>'width:210px;min-width:210px;'),
				$user_display
			) .
			RCView::td(array('class'=>'p-1 text-center'.$pt),
				RCView::span(array('class'=>'hidden'), $attr['admin_rights']) .
                RCView::checkbox(array('id'=>$this_ui_id."-admin_rights", $checked_admin_rights=>$checked_admin_rights, $disabled_admin_rights=>$disabled_admin_rights)) .
				RCView::img(array('src'=>'progress_circle.gif', 'style'=>'display:none;'))
			) .
			RCView::td(array('class'=>'p-1 text-center'.$pt),
				RCView::span(array('class'=>'hidden'), $attr['super_user']) .
				RCView::checkbox(array('id'=>$this_ui_id."-super_user", $checked_super_user=>$checked_super_user)).
				RCView::img(array('src'=>'progress_circle.gif', 'style'=>'display:none;'))
			) .
			RCView::td(array('class'=>'p-1 text-center'.$pt),
				RCView::span(array('class'=>'hidden'), $attr['account_manager']) .
				RCView::checkbox(array('id'=>$this_ui_id."-account_manager", $checked_account_manager=>$checked_account_manager)).
				RCView::img(array('src'=>'progress_circle.gif', 'style'=>'display:none;'))
			) .
			RCView::td(array('class'=>'p-1 text-center'.$pt),
				RCView::span(array('class'=>'hidden'), $attr['access_system_config']) .
				RCView::checkbox(array('id'=>$this_ui_id."-access_system_config", $checked_access_system_config=>$checked_access_system_config)).
				RCView::img(array('src'=>'progress_circle.gif', 'style'=>'display:none;'))
			) .
			RCView::td(array('class'=>'p-1 text-center'.$pt),
				RCView::span(array('class'=>'hidden'), $attr['access_system_upgrade']) .
				RCView::checkbox(array('id'=>$this_ui_id."-access_system_upgrade", $checked_access_system_upgrade=>$checked_access_system_upgrade)).
				RCView::img(array('src'=>'progress_circle.gif', 'style'=>'display:none;'))
			) .
			RCView::td(array('class'=>'p-1 text-center'.$pt),
				RCView::span(array('class'=>'hidden'), $attr['access_external_module_install']) .
				RCView::checkbox(array('id'=>$this_ui_id."-access_external_module_install", $checked_access_external_module_install=>$checked_access_external_module_install)).
				RCView::img(array('src'=>'progress_circle.gif', 'style'=>'display:none;'))
			) .
			RCView::td(array('class'=>'p-1 text-center'.$pt),
				RCView::span(array('class'=>'hidden'), $attr['access_admin_dashboards']) .
				RCView::checkbox(array('id'=>$this_ui_id."-access_admin_dashboards", $checked_access_admin_dashboards=>$checked_access_admin_dashboards)).
				RCView::img(array('src'=>'progress_circle.gif', 'style'=>'display:none;'))
			)
		);
}
$html =
RCView::table(array('class'=>'table table-striped table-bordered display compact no-footer','id'=>'admin-rights-table'),
	RCView::thead(array(),
		RCView::tr(array('style'=>'line-height:1.1;'),
			RCView::th(array('class'=>'fs15 py-3 px-3 text-center text-primary', 'style'=>'width:210px;min-width:210px;'),
				$lang['control_center_4744']
			) .
			RCView::th(array('class'=>'fs12 p-1 pt-3 text-center boldish', 'style'=>'min-width:90px;'),
				$lang['control_center_4741'].'<div class="fs15 m-1"><i class="fas fa-user-shield"></i></div>'
			) .
			RCView::th(array('class'=>'fs12 p-1 pt-3 text-center boldish', 'style'=>'min-width:95px;'),
				$lang['control_center_4736'].'<div class="fs15 m-1"><i class="fas fa-layer-group"></i></div>'
			) .
			RCView::th(array('class'=>'fs12 p-1 pt-3 text-center boldish', 'style'=>'min-width:90px;'),
				$lang['control_center_4737'].'<div class="fs15 m-1"><i class="fas fa-user-friends"></i></div>'
			) .
			RCView::th(array('class'=>'fs12 p-1 pt-3 text-center boldish', 'style'=>'min-width:90px;'),
				$lang['control_center_4738'].'<div class="fs15 m-1"><i class="fas fa-cog"></i></div>'
			) .
			RCView::th(array('class'=>'fs12 p-1 pt-3 text-center boldish', 'style'=>'min-width:90px;'),
				$lang['control_center_4739'].'<div class="fs15 m-1"><i class="fas fa-arrow-alt-circle-up"></i></div>'
			) .
			RCView::th(array('class'=>'fs12 p-1 pt-3 text-center boldish', 'style'=>'min-width:90px;'),
				$lang['control_center_4740'].'<div class="fs15 m-1"><i class="fas fa-cube"></i></div>'
			) .
			RCView::th(array('class'=>'fs12 p-1 pt-3 text-center boldish', 'style'=>'min-width:90px;'),
				$lang['control_center_4742'].'<div class="fs15 m-1"><i class="fas fa-chart-bar"></i></div>'
			)
		)
    ) .
	RCView::tbody(array(), $rows)
);

// Page-specific JS and CSS
loadJS('Project.js');
addLangToJS(array('control_center_4746', 'control_center_4747', 'control_center_4748', 'create_project_97', 'rights_104', 'control_center_4749', 'control_center_4750', 'control_center_4752', 'global_03', 'control_center_4753'));
?>
<script type="text/javascript">
$(function(){
    enableFixedTableHdrs('admin-rights-table',true, true, false, 50);
    <?php if (ADMIN_RIGHTS) { ?>
        enableAdminUserSearch();
        $('#admin-rights-table tr:last td').each(function(){
            $(this).css('background-color','#fff');
        });
    <?php } else { ?>
        $('#admin-rights-table tr:last').remove();
        $('#admin-rights-table :input[type="checkbox"]').prop('disabled', true);
    <?php } ?>
});
</script>
<style type="text/css">
#pagecontainer { max-width: 1500px;  }
p { max-width: 1000px;  }
#admin-rights-table { width: 1000px; margin-bottom: 150px; }
#FixedTableHdrsEnable { display:none !important; }
input[type=checkbox] { width: 16px; height: 16px; }
#admin-rights-descriptions { display:none; }
#admin-rights-descriptions li { margin-bottom:5px; }
</style>
<h4 style="margin-top: 0;"><i class="fas fa-user-shield"></i> <?php echo $lang['control_center_4735'] ?><?php if (!ADMIN_RIGHTS) { print "<span class='browseProjPid fs12 font-weight-normal ms-3'>{$lang['control_center_4751']}</span>"; } ?></h4>
<p><?php echo $lang['control_center_4743']." <a href='javascript:;' id='go-to-add-admin' class='boldish' style='text-decoration: underline;'> ".$lang['control_center_4763']."</a> ".$lang['control_center_4764'] ?></p>
<p class="mb-0"><a href="javascript:;" onclick="$('#admin-rights-descriptions').toggle('blind','fast');" class='fs14' style="text-decoration: underline;"><i class="fas fa-info-circle"></i> <?php echo $lang['control_center_4755'] ?></a></p>
<ul id="admin-rights-descriptions" class="mb-0 mt-3">
    <li><b><i class="fas fa-user-shield"></i> <?=$lang['control_center_4735']?></b> &ndash; <?=$lang['control_center_4756']?></li>
    <li><b><i class="fas fa-layer-group"></i> <?=$lang['control_center_4736']?></b> &ndash; <?=$lang['control_center_4757']?></li>
    <li><b><i class="fas fa-user-friends"></i> <?=$lang['control_center_4737']?></b> &ndash; <?=$lang['control_center_4758']?></li>
    <li><b><i class="fas fa-cog"></i> <?=$lang['control_center_4738']?></b> &ndash; <?=$lang['control_center_4759']?></li>
    <li><b><i class="fas fa-arrow-alt-circle-up"></i> <?=$lang['control_center_4739']?></b> &ndash; <?=$lang['control_center_4760']?></li>
    <li><b><i class="fas fa-cube"></i> <?=$lang['control_center_4740']?></b> &ndash; <?=$lang['control_center_4761']?></li>
    <li><b><i class="fas fa-chart-bar"></i> <?=$lang['control_center_4742']?></b> &ndash; <?=$lang['control_center_4762']?></li>
</ul>
<div><?=$html?></div>
<?php 
include 'footer.php';