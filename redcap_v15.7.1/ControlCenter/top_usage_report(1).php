<?php namespace Vanderbilt\REDCap\Classes\TopUsageReport;

/**
 Improvement ideas
    Unit test the queries?  The may still be inaccurate!
    Revisit "Things to keep in mind" section
        Find a way to detect when lines need to roll up under other lines (the "may add up to more than 100%" problem)?
            Scenario
                All API usage is 20%
                    Project 1 usage 2%
                    Other 18%
                Project 1 usage is 5%
                    API usage 2%
                    UI usage 3%
            Or would it be better to have two modes (overall vs. specific)?
                Coming up with another scenario with multiple users & projects might help answer this
    Figure out goal next steps, and talk to Rob about them
        Maybe auto throttle after connection limit reached now?
    Consider line charts?
        line chart w/ area underneath broken up by one category at a time?  Can then drill down into that category to break it down further by another category?
        alternatively, could keep current table and add links for each "Type" that display it over time (e.g. line graph, or table w/ totals by the hour)                
        If we don't hadd line charts, consider this:
            For each line, should we show +/- for last hour, last X hours, yesterday, last week, last month?
    Down the road
        Could include module usage stats to show DB usage for settings, logs, etc.  Could find bad actors that add too many settings/logs, don't clean up old logs, etc.
        Could also include record stats records cache, maybe with "potential data points" by comparing to metadata table, w/ button to verify
*/

include 'header.php';
if (!ACCESS_ADMIN_DASHBOARDS) redirect(APP_PATH_WEBROOT);

use ExternalModules\ExternalModules;

?>
<h4 style="margin-top: 0;"><i class="fas fa-chart-line" style="margin-left:1px;margin-right:1px;"></i> <?php echo $lang['top_usage_report_01'] ?></h4>

<style>
    #pagecontainer{
        max-width: 1350px;
    }

    #top-usage-container{
        .controls{
            position: relative;
            margin-top: 25px;
            margin-bottom: -20px;

            label{
                display: inline-block;
                min-width: 75px;
            }

            input[type=checkbox]{
                margin-right: 5px;
                vertical-align: -2px;
            }

            button{
                /** Make Apply button appear above the wrapper containing the Search box */
                position: relative;
                z-index: 10;
            }
        }

        th{
            padding-right: 15px;
            max-width: 55px;
        }
        
        td:nth-child(1){
            white-space: nowrap;
        }

        th:nth-child(2),
        td:nth-child(2){
            max-width: 375px;
            overflow-wrap: break-word;
        }

        td:nth-child(n+3){
            text-align: right;
            padding-right: 28px;
        }

        a{
            text-decoration: underline;
        }
    }
</style>
<?php

$report = new TopUsageReport();
$columns = [];

$error = false;
if(strtotime($report->getStartTime()) < TopUsageReport::getMinimumStartDate()){
    $error = 'top_usage_report_18';
    $data = [];
}
else{
    $data = $report->getData();
    if(empty($data)){
        $error = 'top_usage_report_17';
    }

    $sortColumn = 0;
    foreach(array_keys($data[0] ?? []) as $i=>$column){
        $columns[] = [
            'title' => $column,
            'orderSequence' => ['desc', 'asc'],
        ];
    
        if($column === $lang['top_usage_report_04']){
            $sortColumn = $i;
        }
    }
}

$rows = [];
foreach($data as $row){
    $rows[] = array_values($row);
}

$addSplunkLinkForVUMC = function(){
    if(isVanderbilt()){
        $url = 'https://splunk.app.vumc.org/en-US/app/search/search?display.page.search.mode=fast&dispatch.sample_ratio=1&q=search%20index%3D%22victr_ori%22%20sourcetype%3Daccess_combined%0Ahost%20IN%20(ori1007lp%2C%20ori1008lp)%0A%7C%20rex%20field%3D_raw%20%22(%3Fms)%5E(%3FP%3Cclient_ip%3E(%5C%5Cd%7B1%2C3%7D%5C%5C.)%7B3%7D%5C%5Cd%7B1%2C3%7D)%5C%5Cs%2B(%3FP%3Cremote_logname%3E%5B%5C%5Cw-%5D%2B)%5C%5Cs%2B(%3FP%3Cremote_username%3E%5B%5C%5Cw-%5D%2B)%5C%5Cs(%3FP%3Ctimestamp%3E%5C%5C%5B%5C%5Cd%7B1%2C2%7D%2F%5C%5Cw%2B%2F20%5C%5Cd%7B2%7D%3A%5C%5Cd%7B2%7D%3A%5C%5Cd%7B2%7D%3A%5C%5Cd%7B2%7D%5C%5Cs%5C%5C-%5C%5Cd%7B4%7D%5C%5C%5D)%5C%5Cs%5C%22(%3FP%3Crequest_method%3E%5BA-Z%5D%2B)%5C%5Cs(%3FP%3Crequest_first_line%3E%5B%5E%5C%22%5D%2B%5C%5Cw%2B%5B%5E%5C%22%5D%2B)%5C%22%5C%5Cs(%3FP%3Cresponse%3E%5C%5Cd%7B1%2C4%7D)%5C%5Cs(%3FP%3Cresponse_bytes%3E%5B%5C%5Cd-%5D%2B)%5C%5Cs(%3FP%3Cresponse_time%3E%5C%5Cd%2B)ms%5C%5Cs(%3FP%3Cresponse_pid%3E%5C%5Cd%2B)%5C%5Cs%5C%22(%3FP%3Creferrer%3E%5B%5E%5C%22%5D%2B)%5C%22%5C%5Cs%5C%22(%3FP%3Cuser_agent%3E%5B%5E%5C%22%5D%2B)%5C%22%22%20offset_field%3D_extracted_fields_bounds%20%7C%20rex%20field%3D_raw%20%22%5E(%3F%3A%5B%5E%3D%5C%5Cn%5D*%3D)%7B3%7D(%3FP%3Cid%3E%5B%5E%5C%22%5D%2B)%22%20offset_field%3D_extracted_fields_bounds0%20%7C%20rex%20field%3D_raw%20%22%5E(%3FP%3Cip%3E%5B%5E%20%5D%2B)%22%20offset_field%3D_extracted_fields_bounds1%20%7C%20rex%20field%3D_raw%20%22%5E(%3FP%3Cclient_ip%3E(%5C%5Cd%7B1%2C3%7D%5C%5C.)%7B3%7D%5C%5Cd%7B1%2C3%7D)%5C%5Cs%2B(%3FP%3Cremote_logname%3E%5B%5C%5Cw-%5D%2B)%5C%5Cs%2B(%3FP%3Cremote_username%3E%5B%5C%5Cw-%5D%2B)%5C%5Cs(%3FP%3Ctimestamp%3E%5C%5C%5B%5C%5Cd%7B1%2C2%7D%2F%5C%5Cw%2B%2F20%5C%5Cd%7B2%7D%3A%5C%5Cd%7B2%7D%3A%5C%5Cd%7B2%7D%3A%5C%5Cd%7B2%7D%5C%5Cs%5C%5C-%5C%5Cd%7B4%7D%5C%5C%5D)%5C%5Cs%5C%22(%3FP%3Crequest_method%3E%5BA-Z%5D%2B)%5C%5Cs(%3FP%3Crequest_first_line%3E%5B%5E%5C%22%5D%2B%5C%5Cw%2B%5B%5E%5C%22%5D%2B)%5C%22%5C%5Cs(%3FP%3Cresponse%3E%5C%5Cd%7B1%2C4%7D)%5C%5Cs(%3FP%3Cresponse_bytes%3E%5B%5C%5Cd-%5D%2B)%5C%5Cs(%3FP%3Cresponse_time%3E%5C%5Cd%2B)ms%5C%5Cs(%3FP%3Cresponse_pid%3E%5C%5Cd%2B)%5C%5Cs%5C%22(%3FP%3Creferrer%3E%5B%5E%5C%22%5D%2B)%5C%22%5C%5Cs%5C%22(%3FP%3Cuser_agent%3E%5B%5E%5C%22%5D%2B)%5C%22%22%20offset_field%3D_extracted_fields_bounds2%20%7C%20rex%20field%3D_raw%20%22%5E(%3FP%3Csrc_ip%3E%5B%5E%5C%5C-%5D%2B)%22%20offset_field%3D_extracted_fields_bounds3%0A%7C%20eval%20request_first_line%3Dsubstr(request_first_line%2C%201%2C%20200)%20%7C%20eval%20minutes%3Dresponse_time%2F1000%2F60%20%7C%20stats%20count(request_first_line)%20as%20requests%2C%20sum(response_time)%2C%20sum(minutes)%20as%20total_minutes%20BY%20request_first_line%20%7C%20eval%20seconds_per_request%3Dround(total_minutes*60%2Frequests%2C%203)%20%7C%20eval%20total_minutes%3Dround(total_minutes%2C%201)%20%7C%20sort%20-total_minutes&earliest=-7d%40h&latest=now';
        return " <a target='_blank' href='$url'>Click here</a> to view a similar report in Splunk that includes ALL requests.";
    }
    else{
        return '';
    }
};

?>

<div id='top-usage-container'>
    <p class="grayed fs11 my-3 p-2" style="color:#A00000;border:1px solid #ccc;background-color:#eee;line-height:1.3;"><i class="fa-solid fa-circle-info"></i> <?=$lang['top_usage_report_19']?></p>
    <p><?=$lang['top_usage_report_13']?></p>
    <ul>
        <li><?=$lang['top_usage_report_21']?></li>
        <li><?=$lang['top_usage_report_14'] . $addSplunkLinkForVUMC()?></li>
        <li><?=$lang['top_usage_report_15']?></li>
        <li><a target='_blank' href='https://github.com/vanderbilt-redcap/external-module-framework-docs/blob/main/crons.md#timed-crons-deprecated'>External Module Timed Crons</a> <?=$lang['top_usage_report_16']?>.
    </ul>
    <div class='controls'>
        <?php
        echo $report->getControls($rows);
        if($error){
            ?>
            <div class="red" style="margin-top: 20px;">
                <img src="<?php echo APP_PATH_IMAGES ?>cross.png" style="vertical-align: -4px"> <?=$lang[$error]?>
            </div>
            <?php
        }
        ?>
    </div>
    <table></table>
</div>

<script>
(() => {
    const container = document.querySelector('#top-usage-container')

    const columns = <?=json_encode(ExternalModules::escape($columns))?>;
    if(columns.length > 0){
        // At least one column is required for the table to render
        
        columns[1].render = (data, type, row, meta) => {
            if(row[0].startsWith(<?=json_encode($lang['global_65'])?>)){
                const pid = data.substring(data.lastIndexOf("(")).split('(')[1].split(')')[0]
                data = '<a target="_blank" href="<?=APP_PATH_WEBROOT?>' + 'index.php?pid=' + pid + '">' + data + '</a>'
            }

            return data
        }
    
        $(container.querySelector('table')).DataTable({
            columns: columns,
            data: <?=json_encode(ExternalModules::escape($rows))?>,
            order: [[<?=$sortColumn?>, 'desc']],
            paging: false
        })
    }

    const controls = container.querySelector('.controls')
    controls.querySelector('button').onclick = () => {
        showProgress(true)
        const params = new URLSearchParams(location.search)

        controls.querySelectorAll('input').forEach((input) => {
            if(
                !input.name
                ||
                (
                    input.type === 'checkbox'
                    &&
                    !input.checked
                )
            ){
                params.delete(input.name)
            }
            else{
                params.set(input.name, input.value)
            }
        })

        location.search = params
    }
})()
</script>

<?php include 'footer.php';