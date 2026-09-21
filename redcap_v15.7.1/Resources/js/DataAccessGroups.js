'use strict';
//var dagSwitcherDataTable;
var setUserDagAjaxPath = app_path_webroot+'index.php?route=DataAccessGroupsController:saveUserDAG&pid='+pid;
var pageSize = 100;

var DAG_Switcher_Config = (function(window, document, $, undefined) {
    var app_path_images;
    var setUserDagAjaxPath;
    var table;
    var rowoption;
    var pageSize;

    function getTable() {
        //$('#dag-switcher-table-container').html('');
        //$('#dag-switcher-spin').show();
        rowoption = $('#dag-switcher-config-container input[name="rowoption"]:checked').val();
        initDataTable(rowoption, pageSize);
    }

    function initDataTable(rowoption, pageSize)
    {
        $('#downloadUploadUsersDagsDropdown').menu();
        $('#downloadUploadUsersDagsDropdownDiv ul li a').click(function(){
            $('#downloadUploadUsersDagsDropdownDiv').hide();
        });

        // Set table params
        var dataTableParams = {
            "autoWidth": false,
            "processing": true,
            "paging": true,
            "pageLength": 25,
            "info": false,
            "aaSorting": [],
            "fixedHeader": { header: true, footer: false },
            "searching": true,
            "ordering": true,
            "oLanguage": { "sSearch": "" },
            "fixedColumns": true,
            scrollY: round($(window).height()*0.7)+"px",
            scrollX: true,
            ajax: app_path_webroot+'index.php?route=DataAccessGroupsController:getDagSwitcherTable&tablerowsonly=1&pid='+pid+'&rowoption='+rowoption,
            columnDefs: [
                {
                    "targets": 0,
                    "render": function ( celldata, type, row ) {
                        var celltext;
                        if (typeof celldata.is_super !== 'undefined' && celldata.is_super) {
                            celltext = '<span style="color:#777;" title="Admins see all!">'+celldata.rowref+'</span>';
                        } else {
                            celltext = celldata.rowref;
                        }
                        return celltext;
                    }
                },
                {
                    "targets": "_all",
                    "render": function ( celldata, type, row ) {
                        var checked = (celldata.enabled!==undefined && celldata.enabled)?"checked":"";
                        var disabled = (celldata.is_super!==undefined && celldata.is_super)?'disabled title="Admins see all!"':'';
                        if (type==='display') {
                            return "<input type='checkbox' data-dag='"+celldata.dagid+"' data-user='"+celldata.user+"' "+checked+" "+disabled+" title='"+celldata.dagname+" : "+celldata.user+"'></input><img src='"+app_path_images+"progress_circle.gif' style='display:none;'>";
                        } else {
                            return celldata.enabled+'-'+celldata.rowref; // for sorting
                        }
                    }
                }
            ]
        };
        $('#dag-switcher-table').DataTable(dataTableParams);
        $('#dag-switcher-table_filter.dataTables_filter input[type="search"]').attr('type','text').prop('placeholder','Search').css('background-color','#fff');
        $('select[name="dag-switcher-table_length"]').css('background-color','#fff');

        $('#dag-switcher-table tbody').on('change', 'input', function () {
            var cb = $(this);
            var parentTd = cb.parent('td');
            var spinner = parentTd.find('img');
            var hiddenSpan = parentTd.find('span.hidden');

            cb.hide();
            spinner.show();

            var colour = '#ff3300'; // redish
            var user = cb.data('user');
            var dag = cb.data('dag');
            var enabled = cb.is(':checked');

            $.ajax({
                method: 'POST',
                url: setUserDagAjaxPath,
                data: { user: user, dag: dag, enabled: enabled },
                dataType: 'json'
            })
                .done(function(data) {
                    if (data.result==='1') {
                        colour = '#66ff99'; // greenish
                    } else {
                        enabled = !enabled; // changing the selection failed so change it back to what it waa
                    }
                })
                .fail(function(data) {
                    console.log(data);
                    enabled = !enabled; // changing the selection failed so change it back to what it waa
                })
                .always(function(data) {
                    cb.prop('checked', enabled);
                    parentTd.effect('highlight', {color:colour}, 3000);
                    spinner.hide();
                    hiddenSpan.html(enabled ? '1' : '0');
                    cb.show();
                });
        });
    }

    function initRowOption() {
        $('#dag-switcher-config-container').delegate('input[name=rowoption]','change', function () {
            refreshDagSwitcherTable(true);
        });
    }

    return {
        initPage: function(app_path_img, setUserDagPath, setPageSize) {
            app_path_images = app_path_img;
            setUserDagAjaxPath = setUserDagPath;
            pageSize = setPageSize;
            initRowOption();
            if (displayDSonPageLoad) {
                getTable();
                $('#dag-switcher-table-container').show();
                $('#dag-switcher-enable-btn-parent').hide();
            } else {
                $('#dag-switcher-table-container').hide();
                $('#dag-switcher-enable-btn-parent').show();
                $('#dag-switcher-enable-btn').click(function(){
                    displayDSonPageLoad = 1;
                    DAG_Switcher_Config.initPage(app_path_images, setUserDagAjaxPath, pageSize);
                });
            }
        }
    };
})(window, document, jQuery);

function refreshDagSwitcherTable(showProgressBar) {
    if (typeof showProgressBar == 'undefined') showProgressBar = false;
    if (showProgressBar) showProgress(1);
    var rowoption = $('#dag-switcher-config-container input[name=rowoption]:checked').val();
    $.get(app_path_webroot+'index.php?route=DataAccessGroupsController:getDagSwitcherTable&pid='+pid+'&rowoption='+rowoption, { }, function(data){
        $('#dag-switcher-config-container-parent').html(data);
        DAG_Switcher_Config.initPage(app_path_images, setUserDagAjaxPath, pageSize);
        if (showProgressBar) showProgress(0, 0);
    });
}

function renameDAG(field,idfld)
{
    $.post(app_path_webroot+"index.php?route=DataAccessGroupsController:ajax&pid="+pid+"&action=rename",{ group_id: idfld, item: field.value },function(data){
        $('#'+idfld).html(data);
        // Now do a follow-up ajax request to reload entire DAG table (in case any unique group names changed)
        $.get(app_path_webroot+"index.php?route=DataAccessGroupsController:ajax&pid="+pid,{ }, function(data){
            $('#group_table').html(data);
            editbox_init();
            initWidgets();
            refreshDagSwitcherTable();
        });
    });
}

function fieldEnter(field,evt,idfld) {
    evt = (evt) ? evt : window.event;
    if (evt.keyCode == 13 && field.value != "") {
        renameDAG(field,idfld);
        return false;
    }
    return true;
}

function change(actual_id) {
    var existing_dag_name = $('#'+actual_id).text().trim();
    $('#'+actual_id).html("<div id=\""+actual_id+"_field_parent\"><input id=\""+actual_id+"_field\" class=\"x-form-text x-form-field\" style=\"width:90%;\" maxlength=\"100\" type=\"text\" value=\"" + existing_dag_name.replace(/\"/g,'&quot;') + "\" curval=\"" + existing_dag_name.replace(/\"/g,'&quot;') + "\" "
        + "onkeypress=\"return fieldEnter(this,event,'" +actual_id+ "');\" onblur=\"$('#"+actual_id+"').html( $(this).attr('curval') );\"/>"
        + "<div style='font-weight:normal;color:#777;font-size:11px;'>"+lang.data_access_groups_ajax_38+"</div></div>");
    $('#'+actual_id+"_field").focus();
}

function editbox_init(){
    // Set onclick
    $('.editText').click(function(){
        var actual_id = $(this).prop('id');
        if (!$('#'+actual_id+"_field").length) {
            change(actual_id);
        }
    });
    // Add floating pencil next to group name when mouseover
    $('.editText')
        .mouseenter(function(){
            $(this).css('background','#fafafa url("'+app_path_images+'pencil_small3.png") no-repeat right');
        })
        .mouseleave(function(){
            $(this).css('background','');
        });
}

// Execute label editing on page load
$(function(){
    DAG_Switcher_Config.initPage(app_path_images, setUserDagAjaxPath, pageSize);
    editbox_init();
});

function select_group(user) {
    $.get(app_path_webroot+'index.php?route=DataAccessGroupsController:ajax&pid='+pid+'&action=select_group&user='+user,{ },function(data){
        $('#groups').val(data);
    });
}

function hidedagMsg() {
    setTimeout(function(){
        $('.dagMsg').removeClass('hidden');
    },100);
    setTimeout(function(){
        $('.dagMsg').slideToggle(1500);
    },5000);
}

function add_group() {
    if ($('#new_group').val() != lang.rights_179) {
        $('#new_group').prop('disabled',true);
        $('#new_group_button').prop('disabled',true);
        $('#progress_img').css('visibility','visible');
        $.post(app_path_webroot+'index.php?route=DataAccessGroupsController:ajax&pid='+pid+'&action=add',{ item: $('#new_group').val() },function(data){
            $('#group_table').html(data);
            editbox_init();
            initWidgets();
            hidedagMsg();
            refreshDagSwitcherTable();
        });
    }
}
function add_user_to_group() {
    if ($('#group_users').val() == '') {
        simpleDialog(lang.data_access_groups_ajax_17,null,null,null,"$('#group_users').focus();");
    } else {
        $('#groups').prop('disabled',true);
        $('#group_users').prop('disabled',true);
        $('#progress_img_user').css('visibility','visible');
        $.post(app_path_webroot+'index.php?route=DataAccessGroupsController:ajax&pid='+pid+'&action=add_user',{ user: $('#group_users').val(), group_id: $('#groups').val() },function(data){
            $('#group_table').html(data);
            editbox_init();
            initWidgets();
            hidedagMsg();
            refreshDagSwitcherTable();
        });
    }
}

function del_msg(this_group_id,this_group_name) {
    if (randomizationDagStrata == "1") {
        simpleDialog(lang.rights_319, lang.rights_318);
        return;
    }
    var delDagAjax = function(){
        $.post(app_path_webroot+'index.php?route=DataAccessGroupsController:ajax&pid='+pid+'&action=delete',{ item: this_group_id },function(data){
            $('#group_table').html(data);
            editbox_init();
            initWidgets();
            hidedagMsg();
            refreshDagSwitcherTable();
        });
    };
    simpleDialog(lang.rights_184+' "<b>'+this_group_name+'</b>"'+lang.questionmark,lang.rights_185,null,null,null,lang.global_53,delDagAjax,lang.global_19);
}
