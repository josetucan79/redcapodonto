<?php
/**
 * Tableau Connector
 */
class TableauConnector
{
    /**
     * Print the page of instructions
     */
    public function printInstructionsPageContent() {
        global $lang;
        $url = APP_PATH_WEBROOT_FULL."api/?content=tableau";

        $text = '<div>
                    <div class="mb-3 fs13" style="line-height: 1.2;">'.$lang['data_export_tool_258'].'</div>
                <div style="color:#800000;margin:10px 0 5px;font-size:18px;font-weight:bold;">'.$lang['global_24'].'</div>
                <div class="clear"></div>  
                <div class="boldish mt-3" style="background-color:#f5f5f5;padding:8px;border:1px solid #ccc;">
                    <ol>
                        <li style="padding-bottom: 8px;">'.$lang['data_export_tool_259'].'</li>
                        <li style="padding-bottom: 8px;">'.$lang['data_export_tool_260'].'<br>
                            <input value="'.$url.'" id="wdc-url" class="staticInput mt-2 fs14" readonly style="color:#e83e8c;width:440px;font-family:SFMono-Regular,Menlo,Monaco,Consolas,\'Liberation Mono\',\'Courier New\',monospace" type="text" onclick="this.select();">
                            <button class="btn btn-primaryrc btn-xs btn-clipboard ms-1 fs12" onclick="copyUrlToClipboard(this); return false;" title="'.js_escape2($lang['global_137']).'" data-clipboard-target="#wdc-url" style="margin-top: 8px;padding:3px 8px 3px 6px;"><i class="fas fa-paste"></i> '.$lang['global_137'].'</button>
                        </li>
                        <li style="padding-bottom: 8px;">'.$lang['data_export_tool_261'].'
                            <div style="font-style: italic;">'.$lang['data_export_tool_262'].'</div>
                            <div style="margin-bottom:0; font-weight:normal; padding-bottom: 8px;">
                                <ul>
                                    <li>'.$lang['data_export_tool_263'].'</li>
                                    <li>'.$lang['data_export_tool_264'].'</li>
                                    <li>'.$lang['data_export_tool_265'].'</li>
                                    <li>'.$lang['data_export_tool_266'].'</li>
                                </ul>
                            </div>
                        </li>
                        <li style="padding-bottom: 8px;">'.$lang['data_export_tool_267'].'
                            <br><span style="color:#666;font-size:12px;margin-right:4px; font-weight: normal;">('.$lang['data_export_tool_268'].')</span></li>
                        <li>'.$lang['data_export_tool_269'].'</li>
                    </ol>
                </div>   
            </div>';
        return $text;
    }

    /**
     * Print the page of html content (form)
     */
    public function printConnectorPageContent() {
        global $lang;
?>
        <style type="text/css">
            .input-group-prepend { min-width:250px; }
            .input-group-text { min-width:250px; }
        </style>
        <div class="row">
            <div class="col-sm-12 text-center">
                <div style="margin:10px 0;"><img src="<?php echo APP_PATH_IMAGES ?>redcap-logo-large.png" /></div>
                <div style="margin:10px 0; font-weight: bold;" class="lead"><?php echo $lang['data_export_tool_270']?></div>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-12 col-sm-offset-1">
                <p class="mb-3 fs12"><?php echo $lang['data_export_tool_271']?></p>
                <div class="form-group">
                    <input type="hidden" id="url" value="<?php echo APP_PATH_WEBROOT_FULL?>api/">
                    <div class="x-input-group mb-2">
                        <div class="input-group-prepend">
                            <span class="input-group-text" style=""><?php echo $lang['control_center_333']?></span>
                        </div>
                        <input type="text" class="form-control" id="token" placeholder="<?php echo $lang['data_export_tool_272']?>" autocomplete="new-password">
                    </div>
                    <div class="x-input-group mb-2">
                        <div class="input-group-prepend">
                            <span class="input-group-text" style=""><?php echo $lang['data_export_tool_273']?></span>
                        </div>
                        <div class="form-control">
                            <label class="form-check-label form-check-inline"><input type="radio" name="raworlabel" value="raw" class="form-check-input" checked=""><?php echo $lang['data_export_tool_274']?></label>
                            <label class="form-check-label form-check-inline"><input type="radio" name="raworlabel" value="label" class="form-check-input"><?php echo $lang['data_comp_tool_26']?></label>
                        </div>
                    </div>
                    <div class="x-input-group mb-2">
                        <div class="input-group-prepend">
                            <span class="input-group-text" style=""><?php echo $lang['data_export_tool_275']?></span>
                        </div>
                        <div class="form-control">
                            <label class="form-check-label form-check-inline"><input type="radio" name="varorlabel" value="var" class="form-check-input" checked=""><?php echo $lang['data_export_tool_276']?></label>
                            <label class="form-check-label form-check-inline"><input type="radio" name="varorlabel" value="label" class="form-check-input"><?php echo $lang['data_comp_tool_26']?></label>
                        </div>
                    </div>
                    <div class="x-input-group mb-2">
                        <div class="input-group-prepend">
                            <span class="input-group-text" style=""><?php echo $lang['data_export_tool_277']?></span>
                        </div>
                        <div class="form-control">
                            <label class="form-check-label form-check-inline"><input type="radio" name="incldag" value="1" class="form-check-input"><?php echo $lang['design_100']?></label>
                            <label class="form-check-label form-check-inline"><input type="radio" name="incldag" value="0" class="form-check-input" checked=""><?php echo $lang['design_99']?></label>
                        </div>
                    </div>
                    <div class="x-input-group mb-2">
                        <div class="input-group-prepend">
                            <span class="input-group-text" style=""><?php echo $lang['data_export_tool_278']?></span>
                        </div>
                        <input type="text" class="form-control" id="fieldList" placeholder="<?php echo $lang['data_export_tool_279']?>" autocomplete="new-password">
                    </div>
                    <div class="x-input-group mb-2">
                        <div class="input-group-prepend">
                            <span class="input-group-text" style=""><?php echo $lang['data_export_tool_280']?></span>
                        </div>
                        <input type="text" class="form-control" id="filterLogic" placeholder="<?php echo $lang['data_export_tool_281']?>" autocomplete="new-password">
                    </div>
                    <div class="text-center mb-2">
                        <button class="btn btn-primary" id="submitButton" type="button"><?php echo $lang['survey_200']?></button>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://connectors.tableau.com/libs/tableauwdc-2.3.latest.js" type="text/javascript"></script>

        <script type="text/javascript">
            var errorText = '<?php echo $lang['data_export_tool_282']?>'+' https://connectors.tableau.com/libs/tableauwdc-2.3.latest.js';
            if (typeof tableau==='undefined') { alert(errorText); }
            (function(tableau) {

                var myConnector = tableau.makeConnector();

                // Define the schema
                myConnector.getSchema = function(schemaCallback) {
                    var connectionData = JSON.parse(tableau.connectionData);
                    $.ajax({
                        url: connectionData.url,
                        type: "POST",
                        data: {
                            token: connectionData.token,
                            content: 'project_xml',
                            returnFormat: 'json',
                            returnMetadataOnly: true,
                            exportSurveyFields: connectionData.surveyFields,
                            exportDataAccessGroups: connectionData.dags,
                            exportFiles: false
                        },
                        contentType: "application/x-www-form-urlencoded",
                        dataType: "xml",
                        success: function(response) {
                            try {
                                var redcapTable = buildDataSource(connectionData, response);
                                schemaCallback([redcapTable]);
                            }
                            catch (e) {
                                console.log(e);
                            }
                        }
                    });
                };

                // Download the data
                myConnector.getData = function(table, doneCallback) {
                    var connectionData = JSON.parse(tableau.connectionData);

                    // if subset of fields is specified, look for any checkbox columns
                    // and swap out for the checkbox field name
                    var fieldList = [];
                    if (connectionData.fieldList.length>0) {
                        connectionData.fieldList.forEach(function(f) {
                            if (f.indexOf('___')>0) {
                                var cbNameParts = f.split('___');
                                if ($.inArray(cbNameParts[0], fieldList)===-1) {
                                    fieldList.push(cbNameParts[0]);
                                }
                            } else {
                                fieldList.push(f);
                            }
                        });
                    }

                    var tableData = [];
                    $.ajax({
                        url: connectionData.url,
                        type: "POST",
                        data: {
                            token: connectionData.token,
                            content: 'record',
                            format: 'json',
                            returnFormat: 'json',
                            type: 'flat',
                            fields: fieldList,
                            rawOrLabel: connectionData.raworlabel,
                            varOrLabel: connectionData.varorlabel,
                            rawOrLabelHeaders: 'raw',
                            exportCheckboxLabel: false,
                            exportSurveyFields: connectionData.surveyfields,
                            exportDataAccessGroups: connectionData.dags,
                            filterLogic: connectionData.filterLogic
                        },
                        contentType: "application/x-www-form-urlencoded",
                        dataType: "json",
                        success: function(resp){
                            resp.forEach(function(record){
                                $.each(record, function(key, value) {
                                    if (value === "" || value === null) {
                                        delete record[key];
                                    }
                                });
                                tableData.push(record);
                            });
                            table.appendRows(tableData);
                            doneCallback();
                        }
                    });
                };

                tableau.registerConnector(myConnector);

                function buildDataSource(connectionData, response){
                    var $response = $(response);
                    var pName = $response.find('GlobalVariables').find( 'StudyName' ).text();
                    var isLong = $response.find('MetaDataVersion').find( 'StudyEventRef' ).length > 0;

                    var hasRepeatingEventsOrForms = false;
                    $response.find('GlobalVariables').children().each(function(){
                        if (this.nodeName==='redcap:RepeatingInstrumentsAndEvents') {
                            hasRepeatingEventsOrForms = true;
                        }
                    });

                    var filterFields = connectionData.fieldList.length>0;

                    var fields = [];
                    $response.find('MetaDataVersion').find( 'ItemRef' ).each(function(v){
                        var rcExportVarname = this.attributes['ItemOID'].value;
                        var varNode = $response.find( 'ItemDef[OID="'+rcExportVarname+'"]');
                        var rcFType = varNode.attr('redcap:FieldType');

                        if (rcFType !== 'descriptive') {
                            if (fields.length>0 && filterFields &&
                                $.inArray(rcExportVarname, connectionData.fieldList)===-1) {
                                if (rcFType==='checkbox') {
                                    // if the user has specified the checkbox variable name rather than full export names (with ___) then still allow
                                    var cbNameParts = rcExportVarname.split('___');
                                    if ($.inArray(cbNameParts[0], connectionData.fieldList)===-1) {
                                        return;
                                    }
                                } else {
                                    return; // if field list specified, skip if current field is not listed
                                }
                            }

                            var f = {};
                            f.id = rcExportVarname;

                            if (connectionData.varorlabel==='var') {
                                f.alias = rcExportVarname;
                            } else {
                                f.alias = varNode.find( 'TranslatedText' ).text();
                            }
                            f.description = varNode.find( 'TranslatedText' ).text();

                            var dataType = 'string';

                            if (connectionData.raworlabel==='raw') {
                                dataType = varNode.attr('DataType');
                            }

                            if (rcFType==='checkbox') {
                                if (connectionData.varorlabel==='label') {
                                    f.alias = f.description+' (choice='+getCheckboxChoiceLabel($response, rcExportVarname)+')';
                                }

                                f.description = getCheckboxChoiceLabel($response, rcExportVarname)+' | '+f.description;
                            }

                            f.dataType = odmTypeToTableauType(dataType);

                            fields.push(f);

                            if (fields.length === 1){ // i.e. directly after record id field...
                                if (filterFields && $.inArray(rcExportVarname, connectionData.fieldList)===-1) {
                                    connectionData.fieldList.unshift(rcExportVarname); // ensure record id is included in list of fields, when specified
                                }
                                if (isLong) {
                                    fields.push({
                                        id: "redcap_event_name",
                                        alias: "redcap_event_name",
                                        description: "Event Name",
                                        dataType: tableau.dataTypeEnum.string
                                    });
                                }
                                if (hasRepeatingEventsOrForms) {
                                    fields.push({
                                        id: "redcap_repeat_instrument",
                                        alias: "redcap_repeat_instrument",
                                        description: "Repeat Instrument",
                                        dataType: tableau.dataTypeEnum.string
                                    });
                                    fields.push({
                                        id: "redcap_repeat_instance",
                                        alias: "redcap_repeat_instance",
                                        description: "Repeat Instance",
                                        dataType: tableau.dataTypeEnum.int
                                    });
                                }
                                if (connectionData.dags) {
                                    fields.push({
                                        id: "redcap_data_access_group",
                                        alias: "redcap_data_access_group",
                                        description: "Data Access Group",
                                        dataType: tableau.dataTypeEnum.string
                                    });
                                }
                            }
                        }
                    });

                    var redcapTable = {
                        id: "redcap",
                        alias: pName,
                        columns: fields
                    };

                    tableau.connectionData = JSON.stringify(connectionData);

                    return redcapTable;
                };

                function odmTypeToTableauType(dtype) {

                    switch (dtype) {
                        case 'integer': return tableau.dataTypeEnum.int; break;
                        case 'text': return tableau.dataTypeEnum.string; break;
                        case 'float': return tableau.dataTypeEnum.float; break;
                        case 'date': return tableau.dataTypeEnum.date; break;
                        case 'datetime': return tableau.dataTypeEnum.datetime; break;
                        case 'partialDatetime': return tableau.dataTypeEnum.datetime; break;
                        case 'boolean': return tableau.dataTypeEnum.int; break;
                        default: return tableau.dataTypeEnum.string;
                    }
                };

                function getCheckboxChoiceLabel($response, rcExportVarname) {
                    var choiceOptionString = $response.find( 'CodeList[OID="'+rcExportVarname+'.choices"]' ).attr('redcap:CheckboxChoices');
                    var choiceVarVal = rcExportVarname.split('___');
                    var choiceLabel = choiceVarVal;
                    choiceOptionString.split(' | ').forEach(function(c) {
                        if (c.lastIndexOf(choiceVarVal[1]+', ', 0)===0) {
                            choiceLabel = c.replace(choiceVarVal[1]+', ', '');
                        }
                    });
                    return choiceLabel;
                }

                $(document).ready(function (){

                    $("#submitButton").click(function() {
                        var exportFormat = $("input[name=\"raworlabel\"]:checked").val();
                        exportFormat = (exportFormat==='label') ? exportFormat : 'raw';

                        var exportFieldFormat = $("input[name=\"varorlabel\"]:checked").val();
                        exportFieldFormat = (exportFieldFormat==='label') ? exportFieldFormat : 'var';

                        var includeDag = ("1" == $("input[name=\"incldag\"]:checked").val());

                        var fields = $("input#fieldList").val();
                        var fieldList = (fields.trim().length>0) ? fields.split(/[ ,\t]+/) : [];
                        /* Passing tableau.connectionData as an object works in the simulator but not in Tableau.
                         * Debugging shows tableau.connectionData = "[object Object]" i.e. that string, not the object!
                         * (https://tableau.github.io/webdataconnector/docs/wdc_debugging)
                         * Passing tableau.connectionData as a string is a workaround, hence:
                         * tableau.connectionData = JSON.stringify(connectionData);
                         */
                        var connectionData = {
                            raworlabel: exportFormat,
                            varorlabel: exportFieldFormat,
                            surveyfields: false, // can't yet tell from odm xml whiat instruments are surveys
                            dags: includeDag,
                            token: $("input#token").val(),
                            fieldList: fieldList,
                            filterLogic: $("input#filterLogic").val(),
                            url: $("input#url").val()
                        };
                        tableau.connectionData = JSON.stringify(connectionData);
                        tableau.connectionName = '<?php echo $lang['data_export_tool_283'] ?>';
                        try {
                            tableau.submit();
                        }
                        catch(err) {
                            console.log(err);
                        }
                    });
                });
            })(tableau);
        </script>
        <?php
    }
}
