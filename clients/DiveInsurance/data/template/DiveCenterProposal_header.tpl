<!DOCTYPE html
    PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>

<head>
    <link href="./css/divestemplate_css.css" rel="stylesheet" type="text/css" />
    <!-- <script type="text/javascript" src="{$smarty.current_dir}/AgentInfo.js"></script> -->
    <script type="text/javascript" src="./AgentInfo.js"></script>
    <script>
        function subst() {
            var vars = {};
            var data = {};
            var split = {};

            var query_strings_from_url = document.location.search.substring(1).split('&');
            for (var query_string in query_strings_from_url) {
                if (query_strings_from_url.hasOwnProperty(query_string)) {
                    var temp_var = query_strings_from_url[query_string].split('=', 2);
                    vars[temp_var[0]] = unescape(temp_var[1]);
                }
            }
            var css_selector_classes = ['page', 'frompage', 'topage', 'webpage', 'section', 'subsection', 'date',
                'isodate', 'time', 'title', 'doctitle', 'sitepage', 'sitepages', 'start_date', 'license_number',
                'firstname', 'lastname', 'city', 'state', 'country', 'zip', 'certificate_no', 'padi',
                'end_date', 'policy_id', 'address1', 'address2', 'membernumber', 'dba'
            ];
            for (var css_class in css_selector_classes) {
                if (css_selector_classes.hasOwnProperty(css_class)) {
                    var element = document.getElementsByClassName(css_selector_classes[css_class]);
                    for (var j = 0; j < element.length; ++j) {
                        element[j].textContent = vars[css_selector_classes[css_class]];
                    }
                }
            }
            agentInfo();
        }
    </script>
</head>

<body onload="subst()" id="doc_body">
    <div class="main_div_ai">
        <hr class="line1">
        </hr>
        <div class="spacer"></div>
        <hr class="line2">
        </hr>
        <center>
            <div class="title1"><b>DIVE CENTER PROPOSAL</div>
            <div class="title2">SUMMARY OF COVERAGES</b></div>
        </center>
        <hr class="line1">
        </hr>
        <div class="spacer"></div>
        <hr class="line2">
        </hr>

        <div class="content">
            <div class="content1">
                <b class="caption">Agent Information</b>
                <div class = "caption1">
                    <p class ="info" id = "nameVal"></p>
                    <p class ="info" id = "addressLineVal"></p>
                    <p class ="info" id = "addressLine2Val"></p>
                    <p class = "info">License#: {$license_number}</p>
                </div>
                    <b class = "caption2">Insured's Name and Mailing Address:</b>
                    <p class = "details">{$lastname},{$firstname} {if isset($initial)},{$initial}{/if}</p>
                    <p class = "details">{$address1}</p>
                    <p class = "details">{$address2}</p>
                    <p class = "details">{$city}, {$state_in_short} {$zip}</p>
                    <p class = "details">{$country}</p>
            </div>
            <div class="content2">
                <b class="p_margin">Agent Contact Information</b>
                <div class="caption1"></div>
                <p class="info">
                    <span id="phone1Val"></span>&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp
                    FAX: <span id="faxVal"></span>
                </p>
                <p class="info" id="phone2Val"></p>
                <p class="info">www.diveinsurance.com</p>
                <p class="info">diveboat@diveinsurance.com</p>
                <p class="info">Policy period: <span class="start_date"></span> thru
                    <span class="end_date"></span> 12:01:00 AM
                </p>
                <b class="p_margin">Member #: <span class="p_margin normal padi"></span></b>
                <hr>
            </div>
        </div>

    </div>
    </div>
    </div>
    <div>&nbsp</div>
    <div class="spacer2"></div>
    <center>
        <b>
            <!-- <p style="margin-top: 5px;" class="info">Store Location: <span class="storeLocation uppercase">{$address1},
                    {$address2}, {$city}, {$state},
                    {$zip}</span></p> -->
            <p style="margin-top: 5px;" class="info">Store Location:
                <span class="uppercase">{$address1}</span>,
                <span class="uppercase">{$address2}</span>,
                <span class="uppercase">{$city}</span>,
                <span class="uppercase">{$state}</span>,
                <span class="uppercase">{$zip}</span>
            </p>
        </b>
    </center>
    <div class="spacer2"></div>
    <p></p>
    <div class="clearfix"></div>

</body>

</html>