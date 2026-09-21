<?php namespace ExternalModules;

require_once __DIR__ . '/../redcap_connect.php';

$pid = ExternalModules::getProjectId();

if(empty($pid)){
    $headerAndFooterType = 'ControlCenter';
}
else{
    $headerAndFooterType = 'ProjectGeneral';
    ExternalModules::requireDesignRights();
}

require_once APP_PATH_DOCROOT . $headerAndFooterType . '/header.php';

?>

<div id='external-module-logs-wrapper'>

<style>
    #pagecontainer /* control center only */ {
        max-width: 1500px;
    }

    #external-module-logs-wrapper {
        max-width: 1000px;

        .logs-display-button{
            margin-bottom: 10px;
        }

        td.message-column{
            overflow-wrap: anywhere;
        }

        td.actions-column{
            min-width: 125px;
        }
    }

    #external-module-logs-wrapper > h4{
        margin-bottom: 10px;
    }

    #external-module-logs-wrapper > input{
        width: 160px;
    }

    #external-module-logs-wrapper > input,
    #external-module-logs-wrapper > select,
    #external-module-logs-wrapper .select2-selection{
        border: 1px solid #aaa !important;
        border-radius: 3px;
    }

    #external-module-logs-wrapper label{
        min-width: 155px;
    }

    #external-module-logs-wrapper .dataTables_wrapper{
        margin-top: 20px;
    }

    #external-module-logs-wrapper .dataTables_wrapper .paginate_input{
        max-width: 60px;
    }

    table.log-parameters{
        width: 100%;

        th, td{
            border: 1px solid lightgray;
            border-collapse: collapse;
            padding: 3px 5px
        }
    }
</style>

<h4><?=ExternalModules::tt('em_manage_113')?></h4>
<p><?=ExternalModules::tt('em_manage_115')?></p>
<br>

<?php


?>

<label><?=ExternalModules::tt('em_manage_122')?></label><input name='start' type='datetime-local'><br>
<label><?=ExternalModules::tt('em_manage_123')?></label><input name='end' type='datetime-local'><br>
<label><?=ExternalModules::tt('em_manage_124')?></label><input name='messageLengthLimit' type='number'><br>
<label><?=ExternalModules::tt('em_manage_133')?></label><input name='paramLengthLimit' type='number'><br>

<label style='margin-right: -3px'><?=ExternalModules::tt('em_manage_125')?></label>
<select name='modules' style='width: 350px;' multiple="multiple">
    <?php
    foreach(array_keys(ExternalModules::getEnabledModules()) as $prefix){
        echo "<option>$prefix</option>\n";
    }
    ?>
</select>
<br/><br/>
<button class='logs-display-button'><?=$lang['control_center_4877']?></button>

<table style='width: 100%'></table>

<script>
    $(() => {
        const wrapper = $('#external-module-logs-wrapper')

        const getInput = name => {
            let element
            if(name === 'modules'){
                element = 'select'
            }
            else{
                element = 'input'
            }

            return wrapper.find(element + '[name=' + name + ']')
        }
        
        const defaultValues = {
            'start': <?=json_encode(date('Y-m-d 00:00'))?>,
            'end': <?=json_encode(date('Y-m-d 23:59'))?>,
            'messageLengthLimit': 200,
            'paramLengthLimit': 100,
            'modules': ''
        }

        const inputs = Object.keys(defaultValues)

        const params = new URLSearchParams(location.search)
        inputs.forEach((name) => {
            let value = params.get(name)
            if(!value){
                value = defaultValues[name]
            }

            if(name === 'modules'){
                value = value.split(',')
            }

            getInput(name).val(value)
        })

        wrapper.find('.logs-display-button').click(() => {
            showProgress(true)
            const params = new URLSearchParams()
            params.set('pid','<?=is_numeric($pid) ? $pid : ''?>')

            inputs.forEach((name) => {
                const input = getInput(name)
                params.set(input.attr('name'), input.val())
            })
            location.search = params
        })

        getInput('modules').select2()

        const table = wrapper.find('table').DataTable( {
            order: [[ 0, 'dec' ]],
            deferRender: true,
            pagingType: "input",
            ajax: {
                url: 'ajax/get-logs.php',
                data: data => {
                    data.pid = <?=json_encode($pid)?>;
                    inputs.forEach(
                        inputClass => data[inputClass] = getInput(inputClass).val()
                    )
                },
                error: (xhr, error, code) => {
                    const response = xhr.responseText
                    if(response.substr(0, 1000).includes('Allowed memory size of')){
                        alert(<?=json_encode(ExternalModules::tt('em_manage_146'))?>)
                    }
                    else{
                        alert(<?=json_encode(ExternalModules::tt('em_manage_143') . ' ' . ExternalModules::tt('em_manage_144'))?>)
                        console.log(<?=json_encode(ExternalModules::tt('em_manage_143') . ' ' . ExternalModules::tt('em_manage_145'))?>, response)
                    }
                }
            },
            columns: [
                {
                    data: 'log_id',
                    visible: false // this column is only used for sorting
                },
                {
                    data: 'timestamp',
                    title: '<?=ExternalModules::tt('em_manage_126')?>',
                    width: '125px',
                },
                {
                    data: 'directory_prefix',
                    title: '<?=ExternalModules::tt('em_manage_127')?>',
                    width: '125px',
                },
                {
                    data: 'project_id',
                    title: '<?=ExternalModules::tt('em_manage_128')?>',
                    width: '50px',
                    visible: <?=json_encode(empty($pid))?>,
                    className: 'dt-body-right',
                    render: (data, type, row, meta) => {
                        if(data){
                            var url = <?=json_encode(APP_PATH_WEBROOT_PARENT . 'redcap_v' . REDCAP_VERSION . '/index.php?pid=')?> + data
                            return "<a target='_blank' href='" + url + "' style='text-decoration: underline'>" + data + "</a>"
                        }
                        else{
                            return ''
                        }
                    }
                },
                {
                    data: 'record',
                    title: '<?=ExternalModules::tt('em_manage_147')?>',
                    width: '70px'
                },
                {
                    data: 'message',
                    title: '<?=ExternalModules::tt('em_manage_129')?>',
                    className: 'message-column',
                    render: (data, type, row, meta) => {
                        if(data.length == getInput('messageLengthLimit').val()){
                            data += '...'
                        }
                        
                        return data
                    }
                },
                {
                    data: 'username',
                    title: '<?=ExternalModules::tt('em_manage_148')?>',
                    width: '70px'
                },
                {
                    data: 'params',
                    title: '<?=ExternalModules::tt('em_manage_134')?>',
                    className: 'actions-column',
                    // Some params contain sensitive values per https://github.com/vanderbilt-redcap/external-module-framework/discussions/612
                    visible: <?=json_encode(ExternalModules::isAdminWithModuleInstallPrivileges())?>,
                    render: (data, type, row, meta) => {
                        if(data){
                            return "<button class='show-parameters' data-row-index='" + meta.row + "'>Show Parameters</button>"
                        }
                        else{
                            return ""
                        }
                    }
                }
            ]
        }).on('processing.dt', (e, settings, processing) => {
            if(processing){
                showProgress(true)
            }
            else{
                showProgress(false)
            }
        }).on('click', 'button.show-parameters', (e) => {
            const rowIndex = e.target.getAttribute('data-row-index')
            const params = table.rows(rowIndex).data()[0].params
            const paramLengthLimit = parseInt(getInput('paramLengthLimit').val())

            let content = `
                <table class='log-parameters'>
                    <tr>
                        <th>Name</th>
                        <th>Value</th>
                    </tr>
            `
            for(let [name, value] of Object.entries(params)){
                if(value.length == paramLengthLimit){
                    value += '...'
                }

                content += `<tr><td>${name}</td><td>${value}</td></tr>`
            }

            content += '</table>'

            simpleDialog(content, 'Log Entry Parameters', null, 1000)
        })
    })
</script>

</div>

<?php

require_once APP_PATH_DOCROOT . $headerAndFooterType . '/footer.php';