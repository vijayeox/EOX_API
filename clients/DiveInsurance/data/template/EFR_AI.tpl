{assign var=list value=$additionalInsured|json_decode:true}
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
<link href= "{$smarty.current_dir}/css/template_css.css" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="{$smarty.current_dir}/AgentInfo.js"></script>

</head>
<body onload = "agentInfo()">
    <div class = "content">
      <div class ="content1">
          <b class = "caption">Agent Information</b>
          <div class = "caption1">
            <p class ="info" id = "nameVal"></p>
            <p class ="info" id = "addressLineVal"></p>
            <p class ="info" id = "addressLine2Val"></p>
            <p class ="info" style="margin-bottom:2px;"><span id= "phone1Val"></span>&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbspFAX <span id= "faxVal"></span></p>
            <p class ="info" id = "phone2Val" style="margin-bottom:2px;"></p>
            <p class = "info">License#: {$license_number}</p>
          </div>
          <b class = "caption2">Insured's Name and Mailing Address:</b>
          <p class = "details">{$lastname}, {$firstname}{if isset($initial)}, {$initial} {/if}</p>
          <p class = "details">{$address1}</p>
          <p class = "details">{$address2}</p>
          <p class = "details">{$city}, {$state_in_short} {$zip}</p>
          <p class = "details">{$country}</p>
      </div>
      <div class ="content2">
        <div class = "certificate_data">
          <p class = "p_margin"><b>Certificate #:</b></p>
          <p class = "p_margin"><b>Member #:</b></p>
          <p class = "p_margin"><b>Effective Date:</b></p>
          <p class = "p_margin"><b>Expiration Date:</b></p>
        </div>
        <div class = "certificate_data1">
          <p class = "p_margin">{$certificate_no}</p>
          <p class = "p_margin">{$padi}</p>
          <p class = "p_margin">{$start_date|date_format:"%d %B %Y"}&nbsp12:01:00 AM</p>
          <p class = "p_margin">{$end_date|date_format:"%d %B %Y"}&nbsp12:01:00 AM</p>
          <p class = "p_margin">90 DAY DISCOVERY PERIOD</p>
        </div>
        <hr></hr>
        <p class = "policy">Policy issued by &nbsp{$carrier}</p>
        <p class = "policy2">Policy #: {$policy_id}</p>
        <hr></hr>
      </div>
    </div>
    <div class="spacing">&nbsp</div>
    <hr class="hrtag"></hr>
    <div class = "ai_margin">
        <b><p class = "ai_title">Additional Insured (Additional Insured status only applies when required by written contract per attached Additional Insured - Blanket Form PI-MANU-1 (01/100)):</p></b>
          {assign var = result value = []}
	
          {foreach $list as $additional}
            <p>
              {if (isset($additional.effective_date) && $additional.effective_date != "")}
                {$result[$additional['effective_date']][] = $additional}
              {/if}
            </p>
          {/foreach}
          {foreach $result as $key =>$newList}
            <p class = "ai_list" style="text-transform:none;font-size:15px;margin-bottom:5px;">Effective 
                {$key|date_format:"%d %B %Y"}
            </p> 
          {foreach $result[$key] as $additional}
            <p class = "ai_list" style="font-size: 13px;">
              {$additional.name} {if (isset($additional.businessRelation) && $additional.businessRelation != "")} (
              {if $additional.businessRelation == "confinedWaterTrainingLocation"}
                Confined Water Training Location 
              {elseif $additional.businessRelation == "openWaterTrainingLocation"} 
                Open Water Training Location 
              {elseif $additional.businessRelation == "diveBoatOwner"} 
                Dive Boat Owner
              {elseif $additional.businessRelation == "mortgageeLossPayee"} 
                Mortgagee / Loss Payee
              {elseif $additional.businessRelation == "landlord"}
                Landlord
              {elseif $additional.businessRelation == "governmentEntityPermitRequirement"} 
                  Government Entity - Permit Requirement
              {elseif $additional.businessRelation == "diveStore"} 
                 Dive Store
              {elseif $additional.businessRelation == "trainingAgency"} 
                 Training Agency
              {elseif $additional.businessRelation == "cruiseLine"} 
                 Cruise Line
              {elseif $additional.businessRelation == "landOwner"} 
                 Land Owner
              {elseif $additional.businessRelation == "bookingAgent"} 
                 Booking Agent
              {elseif $additional.businessRelation == "other"}                                           {$additional.businessRelationOther}
              {/if})
              {/if}
            </p>
          {/foreach}<br/>
        {/foreach}
      </div>
  </div>
</body>
</html>

