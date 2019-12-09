<!DOCTYPE html>
<html>

<head>
    <link href="{$smarty.current_dir}/css/divestemplate_css.css" rel="stylesheet" type="text/css" />
    <link href="./css/divestemplate_css.css" rel="stylesheet" type="text/css" />
    <!-- <script type="text/javascript" src="{$smarty.current_dir}/AgentInfo.js"></script> -->
</head>

<body>
    <p></p>
    <div>
        <div class="table_sec">
            <table class="proposal_table" cellspacing="0" cellpadding="0">
                <tbody>
                    <tr>
                        <th>Property Coverages</th>
                        <th>Limits</th>
                    </tr>
                    <tr>
                        <td>Contents Limit:</td>
                        <td>${$contentslimit}</td>
                    </tr>
                    <tr>
                        <td id="space_left">(Sign limited to : ${$signlimit})</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>Business Income:</td>
                        <td>${$businessincome}</td>
                    </tr>
                    <tr>
                        <td>Building Coverage:</td>
                        <td>{$buildingcoverage}</td>
                    </tr>
                    <tr>
                        <td>Equipment Breakdown:</td>
                        <td>{$equipbrkdown}</td>
                    </tr>
                    <tr>
                        <td>Business Income from dependant properties:</td>
                        <td>{$busiIncproperty}</td>
                    </tr>
                    <tr>
                        <td>Robbery (per Occurrence - Inside):</td>
                        <td>{$robberyinside}</td>
                    </tr>
                    <tr>
                        <td>Robbery (per Occurrence - Outside):</td>
                        <td>{$robberoutside}</td>
                    </tr>
                    <tr>
                        <td>Transit Coverage (Locked Vehicle):</td>
                        <td>{$transitcoverage}</td>
                    </tr>
                    <tr>
                        <td>EmployeeTheft Limit:</td>
                        <td>{$emptheftlimit}</td>
                    </tr>
                    <tr>
                        <td>Property of Others:</td>
                        <td>{$propertyodothers}</td>
                    </tr>
                    <tr>
                        <td>Off premises:</td>
                        <td>{$offpremises}</td>
                    </tr>
                    <tr>
                        <td>Glass:</td>
                        <td>{$glass}</td>
                    </tr>
                </tbody>
            </table>

            <table class="proposal_table" cellspacing="0" cellpadding="0">
                <tbody>
                    <tr>
                        <th>Liability Coverages</th>
                        <th>Limits</th>
                    </tr>
                    <tr>
                        <td>Commercial General Liability (Each Occurrence Limit):</td>
                        <td>${$commercialGLliability}</td>
                    </tr>
                    <tr>
                        <td>Personal Injury (per Occurence):</td>
                        <td>{$personalinjury}</td>
                    </tr>
                    <tr>
                        <td>General Liability Aggregate:</td>
                        <td>${$GLaggr}</td>
                    </tr>
                    <tr>
                        <td>Products and Completed Operations Aggregate:</td>
                        <td>${$prodNcompoperationaggr}</td>
                    </tr>
                    <tr>
                        <td>Damage to premises rented to you:</td>
                        <td>${$damagetopremisesrented}</td>
                    </tr>
                    <tr>
                        <td>Medical Expense:</td>
                        <td>${$medicalexps}</td>
                    </tr>
                    <tr>
                        <td>NON-Owned Auto:</td>
                        <td>${$nonownedauto}</td>
                    </tr>
                    <tr>
                        <td>NON-Diving Pool Use:</td>
                        <td>{$nondivingpool}</td>
                    </tr>
                    <tr>
                        <td>Travel Agent E&O (Each wrongful act & Aggregate):
                            <p class="info">(Claims made form)</p>
                        </td>
                        <td>{$travelagenteo}</td>
                    </tr>
                    <tr>
                        <td>Group Professional Liability:</td>
                        <td>{$grpprofliability}</td>
                    </tr>
                    <tr>
                        <td>Group Professional Liability Aggregate:</td>
                        <td>{$grpprofliabilityaggr}</td>
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
                            Mechanical breakdown is $2500. All other perils is ${$deductibleProperty}}.</td>
                    </tr>
                </tbody>
            </table>
            <hr class="line_divide">
            </hr>
            <hr class="line_divide">
            </hr>

            <!-- Report Header -->

            <!-- Alarm Calc -->
            <div style="margin: 2% 0;">
                {if not $alert}
                <center>
                    <b>
                        <p>Burglary Coverage is Excluded as there is no Central Station Alarm</p>
                    </b>
                </center>
                {/if}
            </div>
            <!-- Additional Location -->

            <!-- Surplus Lines -->

            {if $state == 'Alaska'}
            <center>
                <p class="notice">
                    <b>{include file = "{$smarty.current_dir}/IPLSurplus/AK.tpl"}</b>
                </p>
            </center>
            {elseif $state == 'Alabama'}
            <center>
                <p class="notice">
                    <b>{include file = "{$smarty.current_dir}/IPLSurplus/AL.tpl"}</b>
                </p>
            </center>
            {elseif $state == 'Arkansas'}
            <center>
                <p class="notice">
                    <b>{include file = "{$smarty.current_dir}/IPLSurplus/AR.tpl"}</b>
                </p>
            </center>
            {elseif $state == 'Arizona'}
            <center>
                <p class="notice">
                    <b>{include file = "{$smarty.current_dir}/IPLSurplus/AZ.tpl"}</b>
                </p>
            </center>
            {elseif $state == 'Colorado'}
            <center>
                <p class="notice">
                    <b>{include file = "{$smarty.current_dir}/IPLSurplus/CO.tpl"}</b>
                </p>
            </center>
            {elseif $state == 'Connecticut'}
            <center>
                <p class="notice">
                    <b>{include file = "{$smarty.current_dir}/IPLSurplus/CT.tpl"}</b>
                </p>
            </center>
            {elseif $state == 'District of Columbia'}
            <center>
                <p class="notice">
                    <b>{include file = "{$smarty.current_dir}/IPLSurplus/DC.tpl"}</b>
                </p>
            </center>
            {elseif $state == 'Delaware'}
            <center>
                <p class="notice">
                    <b>{include file = "{$smarty.current_dir}/IPLSurplus/DE.tpl"}</b>
                </p>
            </center>
            {elseif $state == 'Florida'}
            <center>
                <p class="notice">
                    <b>{include file = "{$smarty.current_dir}/IPLSurplus/FL.tpl"}</b>
                </p>
            </center>
            {elseif $state == 'Micronesia'}
            <center>
                <p class="notice">
                    <b>{include file = "{$smarty.current_dir}/IPLSurplus/FM.tpl"}</b>
                </p>
            </center>
            {elseif $state == 'Georgia'}
            <center>
                <p class="notice">
                    <b>{include file = "{$smarty.current_dir}/IPLSurplus/GA.tpl"}</b>
                </p>
            </center>
            {elseif $state == 'Hawaii'}
            <center>
                <p class="notice">
                    <b>{include file = "{$smarty.current_dir}/IPLSurplus/HI.tpl"}</b>
                </p>
            </center>
            {elseif $state == 'Iowa'}
            <center>
                <p class="notice">
                    <b>{include file = "{$smarty.current_dir}/IPLSurplus/IA.tpl"}</b>
                </p>
            </center>
            {elseif $state == 'Idaho'}
            <center>
                <p class="notice" style="color:red;">
                    <b>{include file = "{$smarty.current_dir}/IPLSurplus/ID.tpl"}</b>
                </p>
            </center>
            {elseif $state == 'Illinois'}
            <center>
                <p class="notice">
                    <b>{include file = "{$smarty.current_dir}/IPLSurplus/IL.tpl"}</b>
                </p>
            </center>
            {elseif $state == 'International'}
            <center>
                <p class="notice">
                    <b>{include file = "{$smarty.current_dir}/IPLSurplus/International.tpl"}</b>
                </p>
            </center>
            {elseif $state == 'Kansas'}
            <center>
                <p class="notice">
                    <b>{include file = "{$smarty.current_dir}/IPLSurplus/KS.tpl"}</b>
                </p>
            </center>
            {elseif $state == 'Kentucky'}
            <center>
                <p class="notice">
                    <b>{include file = "{$smarty.current_dir}/IPLSurplus/KY.tpl"}</b>
                </p>
            </center>
            {elseif $state == 'Louisiana'}
            <center>
                <p class="notice" style="color:red;">
                    <b>{include file = "{$smarty.current_dir}/IPLSurplus/LA.tpl"}</b>
                </p>
            </center>
            {elseif $state == 'Massachusetts'}
            <center>
                <p class="notice">
                    <b>{include file = "{$smarty.current_dir}/IPLSurplus/MA.tpl"}</b>
                </p>
            </center>
            {elseif $state == 'Maryland'}
            <center>
                <p class="notice">
                    <b>{include file = "{$smarty.current_dir}/IPLSurplus/MD.tpl"}</b>
                </p>
            </center>
            {elseif $state == 'Maine'}
            <center>
                <p class="notice">
                    <b>{include file = "{$smarty.current_dir}/IPLSurplus/ME.tpl"}</b>
                </p>
            </center>
            {elseif $state == 'Marshall Islands'}
            <center>
                <p class="notice">
                    <b>{include file = "{$smarty.current_dir}/IPLSurplus/MH.tpl"}</b>
                </p>
            </center>
            {elseif $state == 'Michigan'}
            <center>
                <p class="notice" style="color:red;">
                    <b>{include file = "{$smarty.current_dir}/IPLSurplus/MI.tpl"}</b>
                </p>
            </center>
            {elseif $state == 'Minnesota'}
            <center>
                <p class="notice" style="color:red;">
                    <b>{include file = "{$smarty.current_dir}/IPLSurplus/MN.tpl"}</b>
                </p>
            </center>
            {elseif $state == 'Missouri'}
            <center>
                <p class="notice">
                    <b>{include file = "{$smarty.current_dir}/IPLSurplus/MO.tpl"}</b>
                </p>
            </center>
            {elseif $state == 'Mississippi'}
            <center>
                <p class="notice">
                    <b>{include file = "{$smarty.current_dir}/IPLSurplus/MS.tpl"}</b>
                </p>
            </center>
            {elseif $state == 'Montana'}
            <center>
                <p class="notice">
                    <b>{include file = "{$smarty.current_dir}/IPLSurplus/MT.tpl"}</b>
                </p>
            </center>
            {elseif $state == 'North Carolina'}
            <center>
                <p class="notice">
                    <b>{include file = "{$smarty.current_dir}/IPLSurplus/NC.tpl"}</b>
                </p>
            </center>
            {elseif $state == 'North Dakota'}
            <center>
                <p class="notice">
                    <b>{include file = "{$smarty.current_dir}/IPLSurplus/ND.tpl"}</b>
                </p>
            </center>
            {elseif $state == 'Nebraska'}
            <center>
                <p class="notice">
                    <b>{include file = "{$smarty.current_dir}/IPLSurplus/NE.tpl"}</b>
                </p>
            </center>
            {elseif $state == 'New Hampshire'}
            <center>
                <p class="notice">
                    <b>{include file = "{$smarty.current_dir}/IPLSurplus/NH.tpl"}</b>
                </p>
            </center>
            {elseif $state == 'New Jersey'}
            <center>
                <p class="notice">
                    <b>{include file = "{$smarty.current_dir}/IPLSurplus/NJ.tpl"}</b>
                </p>
            </center>
            {elseif $state == 'New Mexico'}
            <center>
                <p class="notice">
                    <b>{include file = "{$smarty.current_dir}/IPLSurplus/NM.tpl"}</b>
                </p>
            </center>
            {elseif $state == 'Nevada'}
            <center>
                <p class="notice">
                    <b>{include file = "{$smarty.current_dir}/IPLSurplus/NV.tpl"}</b>
                </p>
            </center>
            {elseif $state == 'New York'}
            <center>
                <p class="notice" style="color:red;">
                    <b>{include file = "{$smarty.current_dir}/IPLSurplus/NY.tpl"}</b>
                </p>
            </center>
            {elseif $state == 'Ohio'}
            <center>
                <p class="notice">
                    <b>{include file = "{$smarty.current_dir}/IPLSurplus/OH.tpl"}</b>
                </p>
            </center>
            {elseif $state == 'Oklahoma'}
            <center>
                <p class="notice">
                    <b>{include file = "{$smarty.current_dir}/IPLSurplus/OK.tpl"}</b>
                </p>
            </center>
            {elseif $state == 'Oregon'}
            <center>
                <center>
                    <p class="notice">
                        <b>{include file = "{$smarty.current_dir}/IPLSurplus/OR.tpl"}</b>
                    </p>
                </center>
                {elseif $state == 'Pennsylvania'}
                <center>
                    <p class="notice">
                        <b>{include file = "{$smarty.current_dir}/IPLSurplus/PA.tpl"}</b>
                    </p>
                </center>
                {elseif $state == 'Palau'}
                <center>
                    <p class="notice">
                        <b>{include file = "{$smarty.current_dir}/IPLSurplus/PW.tpl"}</b>
                    </p>
                </center>
                {elseif $state == 'Rhode Island'}
                <center>
                    <p class="notice">
                        <b>{include file = "{$smarty.current_dir}/IPLSurplus/RI.tpl"}</b>
                    </p>
                </center>
                {elseif $state == 'South Carolina'}
                <center>
                    <p class="notice">
                        <b>{include file = "{$smarty.current_dir}/IPLSurplus/SC.tpl"}</b>
                    </p>
                </center>
                {elseif $state == 'South Dakota'}
                <center>
                    <p class="notice">
                        <b>{include file = "{$smarty.current_dir}/IPLSurplus/SD.tpl"}</b>
                    </p>
                </center>
                {elseif $state == 'Tennessee'}
                <center>
                    <p class="notice">
                        <b>{include file = "{$smarty.current_dir}/IPLSurplus/TN.tpl"}</b>
                    </p>
                </center>
                {elseif $state == 'Texas'}
                <center>
                    <p class="notice">
                        <b>{include file = "{$smarty.current_dir}/IPLSurplus/TX.tpl"}</b>
                    </p>
                </center>
                {elseif $state == 'Utah'}
                <center>
                    <p class="notice">
                        <b>{include file = "{$smarty.current_dir}/v/UT.tpl"}</b>
                    </p>
                </center>
                {elseif $state == 'Virginia'}
                <center>
                    <p class="notice">
                        <b>{include file = "{$smarty.current_dir}/IPLSurplus/VA.tpl"}</b>
                    </p>
                </center>
                {elseif $state == 'Virgin Islands'}
                <center>
                    <p class="notice" style="color:red;">
                        <b>{include file = "{$smarty.current_dir}/IPLSurplus/VT.tpl"}</b>
                    </p>
                </center>
                {elseif $state == 'Washington'}
                <center>
                    <p class="notice">
                        <b>{include file = "{$smarty.current_dir}/IPLSurplus/WA.tpl"}</b>
                    </p>
                </center>
                {elseif $state == 'Wisconsin'}
                <center>
                    <p class="notice">
                        <b>{include file = "{$smarty.current_dir}/IPLSurplus/WI.tpl"}</b>
                    </p>
                </center>
                {elseif $state == 'West Virginias'}
                <center>
                    <p class="notice">
                        <b>{include file = "{$smarty.current_dir}/IPLSurplus/WV.tpl"}</b>
                    </p>
                </center>
                {elseif $state == 'Wyoming'}
                <center>
                    <p class="notice">
                        <b>{include file = "{$smarty.current_dir}/IPLSurplus/WY.tpl"}</b>
                    </p>
                </center>
                {/if}

        </div>

        <!-- Second Page -->

        <div class="main" style="margin-top: 1%;">
            <div class="value_main">
                <p>${$divecenterGLpremium}</p>
                <p>&nbsp;</p>
                <p>${$divecenterPLpremium}</p>
                <p>${$surpluslinetax}</p>
                <p>${$padiadminfee}</p>
            </div>
            <div class="sub_main">
                <p>Dive Center General Liability Premium:</p>
                <p>(Based on estimated annual receipts of ${$annualreceipt})</p>
                <p>Dive Center Property Premium</p>
                <p>Dive Center Surplus Lines Tax:</p>
                <p>Dive Center PADI Administration Fee:</p>
            </div>
            <div class="clearfix"></div>
            <p class="hrtag sub_line"></p>
        </div>
        <div class="clearfix"></div>
        <div class="total_main">
            <div class="value_main">
                <p>${$storepremium}</p>
            </div>
            <div class="sub_main">
                <p>Total Store Premium:</p>
            </div>

            <div class="clearfix"></div>
            <p class="spacer2 sub_line"></p>
        </div>
        <div class="clearfix"></div>
        <!-- second section -->
        <div class="main">
            <div class="value_main">
                <p>${$divecentergrouppremium}</p>
                <p>&nbsp;</p>
                <p>${$groupsurpluslinetax}</p>
                <p>${$grouppadiadminfee}</p>
            </div>
            <div class="sub_main">
                <p>Dive Center Group Instructional Program Premium:</p>
                <p>(Based on estimated annual group receipts of {$groupannualreceipts})</p>
                <p>Dive Center Group Instructional Program Surplus Lines Tax:</p>
                <p>Dive Center Group Instructional Program PADI Administration Fee:</p>
            </div>

            <div class="clearfix"></div>
            <p class="hrtag sub_line"></p>
        </div>
        <div class="total_main">
            <div class="value_main">
                <p>${$totalgrouppremium}</p>
            </div>
            <div class="sub_main">
                <p>Total Group Premium:</p>
            </div>
        </div>
        <div class="clearfix"></div>
        <p class="hrtag" style="margin-top: 2px;"></p>
        <div class="total_main">
            <div class="value_main">
                <p>${$amtDueinFull}</p>
            </div>
            <div class="sub_main">
                <p>Amount due in full:</p>
            </div>
        </div>
        <div class="clearfix"></div>
        <p></p>
        <p class="spacer2"></p>
        <p></p>

        <!-- Payment Section -->
        <div>
            <div class="paymentSection">
                <p>PAYMENT AMOUNT:&nbsp;&nbsp;<span>______________________________________</span></p>
                <div>
                    <div class="payment_option">
                        <div class="checkbox"></div>
                        <div>
                            <p>CHECK or MONEY ORDER</p>
                            <p>Make payable to HUB International (U.S. funds only)</p>
                        </div>
                    </div>
                    <div class="payment_option">
                        <div class="checkbox"></div>
                        <div>
                            <p>CREDIT CARD</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="paymentSection">
                <center>
                    <p>CREDIT CARD INFORMATION (If paying by Credit Card)</p>
                </center>
                <center style="margin-left: 20%;">
                    <div class="clearfix"></div>
                    <div class="checkbox"></div>
                    <div class="check_p">VISA</div>
                    <div class="checkbox"></div>
                    <div class="check_p">MasterCard</div>
                    <div class="checkbox"></div>
                    <div class="check_p">American Express</div>
                    <div class="clearfix"></div>
                </center>

                <div style="margin-left: 10%;">
                    <p>Card Number:&nbsp;&nbsp;<span>______________________________________</span></p>
                    <div>
                        <span>
                            Expiration Date:&nbsp;&nbsp;<span>_________________</span>
                        </span>&nbsp;
                        <span>
                            CVV2:&nbsp;&nbsp;<span>___________________</span>
                        </span>
                    </div>
                    <p>Card Holder Name (Print):&nbsp;&nbsp;<span>_______________________________</span></p>
                    <p>Card Holder Signature:&nbsp;&nbsp;<span>_______________________________</span></p>
                </div>
            </div>
            <div class="clearfix"></div>
            <p></p>
            <div class="spacer2"></div>
            <center>
                <p class="info">
                    We (I) know and acknowledge that this policy does not provide any insurance coverage or defense for
                    snow-ski rentals or snow-ski binding adjustments. No
                    coverage is provided for firearms (this exclusion does not include spearguns). No coverage is
                    provided
                    under this policy for any professional liability except
                    under the terms of the designated services exclusion with exception coverage. Professional Liability
                    includes, but is not limited to, instruction of scuba diving,
                    snorkeling, swimming and freediving. The policy defines these and other exclusions as they apply.
                    Burglary Coverage is afforded only with an armed Central
                    Station Alarm (Policy Warranty). This policy does not provide coverage for Workers Compensation or
                    employer&apos;s liability.
                </p>
            </center>
            <p></p>
            <div class="spacer2"></div>

            <center>
                <p>I accept this proposal and understand this is a summary of coverage and the actual policy language
                    determines the coverage.</p>
            </center>

            <div>
                <table style="width: 100%;" cellpadding="0" cellspacing="1">
                    <tbody>
                        <tr>
                            <td style="width: 50%;text-align: center;">
                                <p>Signature:<span class="">______________________________________</span></p>
                            </td>
                            <td style="width: 20%;text-align: center;">
                                <p>Date:<span class="">______________________________________</span></p>
                            </td>
                            <td style="width: 30%;text-align: center;">
                                <p>Title:<span class="">______________________________________</span></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</body>

</html>