<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
<link href= "./css/divebtemplate_css.css" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="{$smarty.current_dir}/AgentInfo.js"></script>
<script>
  function subst() {
      var vars = {};
      var data = {};
      var split = {};

      var query_strings_from_url = document.location.search.substring(1).split('&');
      for (var query_string in query_strings_from_url) {
          if (query_strings_from_url.hasOwnProperty(query_string)) {
              var temp_var = query_strings_from_url[query_string].split('=', 2);
              vars[temp_var[0]] = decodeURI(temp_var[1]);
          }
      }
      // document.getElementById("test").textContent = query_strings_from_url;
      var css_selector_classes = ['page', 'frompage', 'topage', 'webpage', 'section', 'subsection', 'date', 'isodate', 'time', 'title', 'doctitle', 'sitepage', 'sitepages','start_date','license_number','business_name','business_city','business_state','business_country','business_zip','group_certificate_no','business_padi','end_date','policy_id','business_address1','business_address2'];
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
<body onload="subst()" id = "doc_body">  
 <div class = "main_div1">
            <hr class="line1"></hr>
          <div class="spacer"></div>
          <hr class="line2"></hr>

          <center><b><div class="title1">DIVE BOAT MARINE CERTIFICATE</div></b></center>
          <div class="title2">
            <div class = "claims_title">
               <p class ="claims"><b>ADDITIONAL NAMED INSURED</b></p>
            </div>
            <div class = "page_data">
               <p class ="page_no1">Page <span class="page"></span> of <span class="topage"></span></p>
            </div>
          </div>
 
          <hr class="line1"></hr>
          <div class="spacer"></div>
          <hr class="line2"></hr>

    <div class = "cont">
      <div class ="content1">
          <b class = "caption">Agent Information</b>
          <div class = "caption1">
            <p class ="info" id = "nameVal"></p>
            <p class ="info" id="addressVal"></p>
           </div>
          <hr class ="hr_caption"></hr>
          <b class = "caption2">Insured's Name and Mailing Address:</b>
          <p class = "details"><span class ="business_name"></span></p>
          <p class = "details"><span class ="business_address1"></span></p>
          <p class = "details"><span class ="business_address2"></span></p>
          <p class = "details"><span class ="business_city"></span>,<span class ="business_state"></span></p>
          <p class = "details"><span class ="business_country"></span>,<span class ="business_zip"></span></p>
      </div>
      <div class ="content2">
        <div class = "certificate_data">
          <p class = "p_margin"><b>Certificate #:</b></p>
          <p class = "p_margin"><b>Member #:</b></p>
          <p class = "p_margin"><b>Effective Date:</b></p>
          <p class = "p_margin"><b>Expiration Date:</b></p>
          <p class = "p_margin"><b>Policy issued by:</b></p>
          <p class = "p_margin"><b>Policy #:</b></p>
        </div>
        <div class = "certificate1">
          <p class = "p_margin"><span class ="group_certificate_no"></span></p>
          <p class = "p_margin"><span class ="business_padi"></p>
          <p class = "p_margin"><span class ="start_date"></p>
          <p class = "p_margin"><span class ="end_date">&nbsp12:01:00 AM</p>
          <p class = "p_margin">U.S. SPECIALTY INSURANCE COMPANY</p>
          <p class = "p_margin"><span class ="policy_id"></p>
        </div>
      </div>
      </div>
    </div>
    <hr class =" ai_hrtag"></hr>
    <center><b><p class = "ai_title">Subject to all of the terms and conditions of this policy, the following are named as Additional Insured(s) under Section B of the policy.
Additional Insured(s) hereunder are covered only for vicarious liability which they incur as a result of the operations of the Vessel insured
on this policy and such Additional Insured(s) are not covered hereunder for liability arising out of their own negligence or other fault.</p></b></center>
<hr class =" ai_hrtag"></hr>
  </div>
</body>
</html>
