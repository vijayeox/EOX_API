{assign var=additionalLocationDataItem value=$additionalLocationData|json_decode:true}
<!DOCTYPE html>
<html>

<head>
    <link href="{$smarty.current_dir}/css/divestemplate_css.css" rel="stylesheet" type="text/css" />
    <!-- <script type="text/javascript" src="{$smarty.current_dir}/AgentInfo.js"></script> -->
</head>

<body>
    <p></p>
    <div>
    <br>
    {if isset($additionalLocationDataItem.name) && $additionalLocationDataItem.name != ""} <p class = "info"><b>Store/Location Description : </b>{$additionalLocationDataItem.name}</p>{/if}

    {if isset($additional_named_insureds_option) && $additional_named_insureds_option =='yes'}
    {assign var=list value=$additionalNamedInsured|json_decode:true}
    <p class = "info"><b>Additional Named Insured:</b></p>
    {foreach from=$list item=$additional}
        <p class = "ai_list info">
            &nbsp&nbsp&nbsp {if isset($additional.name) && is_string($additional.name)}{$additional.name}{/if}{if isset($additional.address) && is_string($additional.address) && $additional.address !=""},{$additional.address}{/if}{if isset($additional.city)  && is_string($additional.city) && $additional.city !=""},{$additional.city}{/if}{if isset($additional.state) && is_string($additional.state)},{$additional.state}{/if}{if isset($additional.zip) && is_string($additional.zip) && $additional.city !=""}{$additional.zip}{/if}
        </p>
    {/foreach}
    {/if}
    {if isset($additionalLocationDataItem.padiNumberAL) && $additionalLocationDataItem.padiNumberAL != "" && $additionalLocationDataItem.padiNumberAL != null} <p class = "info"><b>Store/Location Number: </b>{$additionalLocationDataItem.padiNumberAL}</p>{/if}
    {if (isset($additionalLocationDataItem.address) && $additionalLocationDataItem.address != "") ||
    (isset($additionalLocationDataItem.country) && $additionalLocationDataItem.country != "") ||
    ( isset($additionalLocationDataItem.city)  && $additionalLocationDataItem.city != "" ) ||
    (isset($additionalLocationDataItem.state) && $additionalLocationDataItem.state != "" && is_string($additionalLocationDataItem.state)) ||
    (isset($additionalLocationDataItem.zip) && $additionalLocationDataItem.zip != "")}<p  class = "info"><b>
    Location Address: </b>{if isset($additionalLocationDataItem.address) && is_string($additionalLocationDataItem.address) && $additionalLocationDataItem.address !=""}{$additionalLocationDataItem.address},{/if}<br>
    {if isset($additionalLocationDataItem.city)  && is_string($additionalLocationDataItem.city) && $additionalLocationDataItem.city !=""}{$additionalLocationDataItem.city}{/if}{if isset($additionalLocationDataItem.state) && is_string($additionalLocationDataItem.state)},{$additionalLocationDataItem.state}{/if}{if isset($additionalLocationDataItem.zip) && is_string($additionalLocationDataItem.zip) && $additionalLocationDataItem.zip !=""} - {$additionalLocationDataItem.zip},{/if}<br>{if isset($additionalLocationDataItem.country) && is_string($additionalLocationDataItem.country) && $additionalLocationDataItem.country !=""}{$additionalLocationDataItem.country}{/if}</p>
    {/if}
        <div class="table_sec">
            <table class="proposal_table" cellspacing="0" cellpadding="0">
                <tbody>
                    <tr>
                        <th class = "table_hd">Property Coverages</th>
                        <th class = "table_hd">Limits</th>
                    </tr>
                    <tr><td   class = "info">Policy issued by {$property_carrier}</td></tr>
                    <tr><td   class = "info">Policy #: {$property_policy_id}</td></tr>
                    <tr>
                        <td class = "info">Contents Limit:</td>
                        {if isset($additionalLocationDataItem.ALpropertyCoverageSelect) && $additionalLocationDataItem.ALpropertyCoverageSelect == "yes"}
                        <td class = "info">${$additionalLocationDataItem.additionalLocationPropertyTotal|number_format}</td>{else}
                        <td class = "info">Not Included</td>
                        {/if}
                    </tr>
                    <tr>
                    {if isset($additionalLocationDataItem.ALpropertyCoverageSelect) && $additionalLocationDataItem.ALpropertyCoverageSelect == "yes"}
                        <td class = "info" id="space_left">(Sign limited to : $25,000)</td>
                        {else}
                      <td></td>
                          {/if}
                        <td></td>
                    </tr>
                    <tr>
                        <td class = "info">Business Income:</td>
                        {if isset($additionalLocationDataItem.ALpropertyCoverageSelect) && $additionalLocationDataItem.ALpropertyCoverageSelect == "yes"}
                        <td class = "info">${$additionalLocationDataItem.ALLossofBusIncome|number_format}</td>
                        {else}
                        <td class = "info">Not Included</td>
                        {/if}
                    </tr>
                    <tr>
                        <td class = "info">Building Coverage:</td>
                        {if isset($additionalLocationDataItem.ALpropertyCoverageSelect) && $additionalLocationDataItem.ALpropertyCoverageSelect == "yes"}
                        {if isset($additionalLocationDataItem.additionalLocationDoYouOwntheBuilding) && $additionalLocationDataItem.additionalLocationDoYouOwntheBuilding != "no"}
                            <td  class = "info" >${$additionalLocationDataItem.ALBuildingReplacementValue|number_format}</td>
                        {else}
                            <td class = "info">Not Included</td>
                        {/if}
                        {else}
                        <td class = "info">Not Included</td>
                        {/if}
                    </tr>
                    <tr>
                        <td class = "info">Equipment Breakdown:</td>
                        {if isset($additionalLocationDataItem.additionalLocationFurniturefixturesAndEquipment) && (int)$additionalLocationDataItem.additionalLocationFurniturefixturesAndEquipment != 0}
                            <td class = "info">Included</td>
                        {else}
                            <td class = "info">Not Included</td>
                        {/if}
                    </tr>
                    <tr>
                        <td class = "info">Business Income from dependant properties:</td>
                        {if isset($additionalLocationDataItem.ALpropertyCoverageSelect) && $additionalLocationDataItem.ALpropertyCoverageSelect == "yes"}
                        <td class = "info">$5,000</td>
                        {else}
                        <td class = "info">Not Included</td>
                        {/if}
                    </tr>
                    <tr>
                        <td class = "info">Robbery (per Occurrence - Inside):</td>
                        {if isset($additionalLocationDataItem.ALpropertyCoverageSelect) && $additionalLocationDataItem.ALpropertyCoverageSelect == "yes"}
                        <td class = "info">$2,500</td>
                        {else}
                        <td class = "info">Not Included</td>
                        {/if}
                    </tr>
                    <tr>
                        <td class = "info">Robbery (per Occurrence - Outside):</td>
                        {if isset($additionalLocationDataItem.ALpropertyCoverageSelect) && $additionalLocationDataItem.ALpropertyCoverageSelect == "yes"}
                        <td class = "info">$2,500</td>
                        {else}
                        <td class = "info">Not Included</td>
                        {/if}
                    </tr>
                    <tr>
                        <td class = "info">Transit Coverage (Locked Vehicle):</td>
                        {if isset($additionalLocationDataItem.ALpropertyCoverageSelect) && $additionalLocationDataItem.ALpropertyCoverageSelect == "yes"}
                        <td class = "info">$10,000</td>
                        {else}
                        <td class = "info">Not Included</td>
                        {/if}
                    </tr>
                    <tr>
                        <td class = "info">EmployeeTheft Limit:</td>
                        {if isset($additionalLocationDataItem.ALpropertyCoverageSelect) && $additionalLocationDataItem.ALpropertyCoverageSelect == "yes"}
                        <td class = "info">$5,000</td>
                        {else}
                        <td class = "info">Not Included</td>
                        {/if}
                    </tr>
                    <tr>
                        <td class = "info">Property of Others:</td>
                        {if isset($additionalLocationDataItem.ALpropertyCoverageSelect) && $additionalLocationDataItem.ALpropertyCoverageSelect == "yes"}
                        <td class = "info">$25,000</td>
                        {else}
                        <td class = "info">Not Included</td>
                        {/if}
                    </tr>
                    <tr>
                        <td class = "info">Off premises:</td>
                        {if isset($additionalLocationDataItem.ALpropertyCoverageSelect) && $additionalLocationDataItem.ALpropertyCoverageSelect == "yes"}
                        <td class = "info">$10,000</td>
                        {else}
                        <td class = "info">Not Included</td>
                        {/if}
                    </tr>
                    <tr>
                        <td class = "info">Glass:</td>
                        {if isset($additionalLocationDataItem.ALpropertyCoverageSelect) && $additionalLocationDataItem.ALpropertyCoverageSelect == "yes"}
                        <td class = "info">$5,000</td>
                        {else}
                        <td class = "info">Not Included</td>
                        {/if}
                    </tr>
                </tbody>
            </table>

            <table class="proposal_table" cellspacing="0" cellpadding="0">
                <tbody>
                    <tr>
                        <th class = "table_hd">Liability Coverages</th>
                        <th class = "table_hd">Limits</th>
                    </tr>
                    <tr><td   class = "info">Policy issued by {$liability_carrier}</td></tr>
                    <tr><td   class = "info">Policy #: {$liability_policy_id}</td></tr>
                    <tr>
                        <td class = "info">NON-Diving Pool Use:</td>
                        {if isset($additionalLocationDataItem.ALnonDivingPoolAmount) && (int)$additionalLocationDataItem.ALnonDivingPoolAmount > 0}
                                      <td class = "info">$1,000,000</td>
                                  {else}
                                      <td class = "info">Excluded</td>
                                  {/if}
                    </tr>
                    <tr>
                        <td class = "info">Travel Agent E&O (Each wrongful act & Aggregate):
                            <p class="info">(Claims made form)</p>
                        </td>
                        {if isset($travelAgentEoPL) && ($travelAgentEoPL === "true" || $travelAgentEoPL == true || $travelAgentEoPL == 1)}
                                <td class = "info">$1,000,000</td>
                        {else}
                                <td class = "info">Not Included</td>
                        {/if}
                    </tr>
                    <tr>
                        <td class = "info">Lake,quarry and pond:</td>
                        {if $additionalLocationDataItem.ALlakequarrypondContactVicenciaBuckleyforsupplementalformPL}
                        <td class = "info">Included</td>
                        {else}
                        <td class = "info">Not Included</td>
                        {/if}
                    </tr>
                </tbody>
            </table>
            <div class="clearfix"></div>
            <table class="deductible_table">
                <tbody>
                    <tr>
                        <td>Deductible:</td>
                        <td class="info">Wind/Hail is 5% of Insured Values per location, $5000 minimum, for Florida,
                            Hawaii, Puerto Rico, USVI, Guam and all Tier 1 locations
                            (coastal Counties) in Texas, Louisiana, Mississippi, Alabama, Georgia, South Carolina, North
                            Carolina and all Harris County Texas locations.
                            Mechanical breakdown is $2500. All other perils is {if $propertyDeductibles == "propertyDeductibles1000"}
                           $1,000
                        {elseif $propertyDeductibles == "propertyDeductibles2500"}
                           $2,500
                        {elseif $propertyDeductibles == "propertyDeductibles5000"}
                           $5,000
                        {else}
                           $1,000
                        {/if}
                            </td>
                    </tr>
                </tbody>
            </table>
            <hr class="line_divide">
            </hr>
            <hr class="line_divide">
            </hr>
            <!-- Report Header -->

            <!-- Alarm Calc -->

                {if isset($additionalLocationDataItem.ALcentralStationAlarm) && $additionalLocationDataItem.ALcentralStationAlarm != "yes"}
                    <div style="margin: 2% 0;">
                        {if isset($additionalLocationDataItem.centralStationAlarm) && $additionalLocationDataItem.centralStationAlarm != "yes"}
                        <center>
                            <b>
                                <p>Burglary Coverage is Excluded as there is no Central Station Alarm</p>
                            </b>
                        </center>
                        {/if}
                    </div>
                {/if}

        </div>
  </div>
</body>

</html>
