<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
<link href= "{$smarty.current_dir}/css/template_css.css" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="{$smarty.current_dir}/AgentInfo.js"></script>
</head>
<body onload = "agentInfo()">
	<div class ="body_div">
		<div>&nbsp</div>
		<div class = "content">
			<div class ="content1">
					<b class = "caption">Agent Information</b>
					<div class = "caption1">
						<p class ="info" id = "nameVal"></p>
						<p class ="info" id = "addressVal"></p>
						<p class ="info" style="margin-bottom:2px;"><span id= "phone1Val"></span>&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbspFAX <span id= "faxVal"></span></p>
						<p class ="info" id = "phone2Val" style="margin-bottom:2px;"></p>
						<p class = "info">License#: {$license_number}</p>
					</div>
					<b class = "caption2">Insured's Name and Mailing Address:</b>
					<p class = "details">{$firstname},{$initial},{$lastname}</p>
					<p class = "details">{$address1}</p>
					<p class = "details">{$address2}</p>
					<p class = "details">{$city},{$state}</p>
					<p class = "details">{$country},{$zip}</p>
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
					<p class = "p_margin">{$start_date}</p>
					<p class = "p_margin">{$end_date}</p>
					<p class = "p_margin">90 DAY DISCOVERY PERIOD</p>
				</div>
				<hr></hr>
				<p class = "policy">Policy issued by &nbsp{$carrier}</p>
				<p class = "policy2">Policy #: {$policy_id}</p>
				<hr></hr>
				<p class = "efr_title"><b>Named Insured</b></p>
					<p class ="efr_bold"><b>EFR</b></p>
					<p class ="efr_title2">Emergency First Response Corporation</p>
					<p class ="efr_title2">{$address1},{$address2}</p>
					<p class ="efr_title2">{$city},{$state},{$country},{$zip}</p>
			</div>
		</div>
		<div class="spacing_efr">&nbsp</div>
		<hr class="hrtag"></hr>
    	<div class="i_type">
      		<div class="i_type1">
        		<div class = "in-type" style="width: 40%">
		           <b class = "ins_type">Type of Insurance</b>
		           <div class = "ins_data" style="margin-top: 10px;">
			           <p class = "ins_font"><b>COMBINED SINGLE LIMIT:</b></p>
			           <p class = "ins_font"><b class = "space">ANNUAL AGGREGATE:</b></p>
		       	   </div>
	        	</div>
	        	<div class = "in-type1"> 
		            <p class = "ins_type"  style="margin-bottom: 10px;margin-left:1px;">Professional Liability</p>
			            <p class = "ins_font">$1,000,000&nbsp&nbsp&nbsp(per accident)</p>
			            <p class = "ins_font">$2,000,000</p>						
		        </div>
	     	</div>
	     	<div class="i_type2_efr">
		       &nbsp
     		</div>
    	</div>
		<br/>
    	
    	<hr class="hrtag_efr"></hr>
    	<center><p class = "policy_notice1">Retro Date: {$start_date}, or the first day 		of uninterrupted coverage,whichever is earlier (refer to section VI of the 			   policy). However, in the event of a claim which invokes a Retroactive Date prior 	   to {$start_date}, the Certificate Holder must submit proof of uninterrupted 		   insurance coverage dating prior
			   to the date that the alleged negligent act, error, or omission occurred.
		</p></center>
		<hr class = "spacing1"></hr>
		<div class = "second_content">
			{if isset($update)}
				<p class ="policy_update"><b>Endorsements & Upgrades:</b></p>
				<p class = "policy_status">Status of Insured : {$endrosement_status} as of {$update_date}</p>
			{/if}
			<hr class = "hr_efr"></hr>
			<p class = "policy_notice">
				The insurance afforded by this policy is a master policy issued to PADI Worldwide Corporation, 30151 Tomas Street, Rancho Santa Margarita, CA 92688. The insurance is provided under terms and conditions of the master policy which is enclosed with this certificate. Please read the policy for a full description of the terms, conditions and exclusions of the policy. This certificate does not amend, alter or extend the coverage afforded by the policy referenced on this certificate.
			</p>
			<p class = "policy_notice">
				Notice of cancelation: The premium and any taxes or fees are fully earned upon inception and no refund is granted unless cancelled by the company.If the company cancels this policy, 45 days notice will be given to the certificate holder unless cancellation is for nonpayment of premium, then 10 days notice will be provided, and any premium not earned will be returned to the certificate holder.
			</p>

			{if $state == 'Alaska'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/AK.tpl"}</b>
				</p></center>
			{elseif $state == 'Alabama'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/AL.tpl"}</b>
				</p></center>
			{elseif $state == 'Arkansas'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/AR.tpl"}</b>
				</p></center>
			{elseif $state == 'Arizona'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/AZ.tpl"}</b>
				</p></center>
			{elseif $state == 'Colorado'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/CO.tpl"}</b>
				</p></center>
			{elseif $state == 'Connecticut'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/CT.tpl"}</b>
				</p></center>
			{elseif $state == 'District of Columbia'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/DC.tpl"}</b>
				</p></center>
			{elseif $state == 'Delaware'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/DE.tpl"}</b>
				</p></center>
			{elseif $state == 'Florida'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/FL.tpl"}</b>
				</p></center>
			{elseif $state == 'Micronesia'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/FM.tpl"}</b>
				</p></center>
			{elseif $state == 'Georgia'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/GA.tpl"}</b>
				</p></center>
			{elseif $state == 'Hawaii'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/HI.tpl"}</b>
				</p></center>
			{elseif $state == 'Iowa'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/IA.tpl"}</b>
				</p></center>
			{elseif $state == 'Idaho'}
				<center><p class = "notice" style = "color:red;">
					<b>{include file = "{$smarty.current_dir}/EFR/ID.tpl"}</b>
				</p></center>
			{elseif $state == 'Illinois'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/IL.tpl"}</b>
				</p></center>
			{elseif $state == 'International'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/International.tpl"}</b>
				</p></center>
			{elseif $state == 'Kansas'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/KS.tpl"}</b>
				</p></center>
			{elseif $state == 'Kentucky'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/KY.tpl"}</b>
				</p></center>
			{elseif $state == 'Louisiana'}
				<center><p class = "notice" style = "color:red;">
					<b>{include file = "{$smarty.current_dir}/EFR/LA.tpl"}</b>
				</p></center>
			{elseif $state == 'Massachusetts'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/MA.tpl"}</b>
				</p></center>
			{elseif $state == 'Maryland'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/MD.tpl"}</b>
				</p></center>
			{elseif $state == 'Maine'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/ME.tpl"}</b>
				</p></center>
			{elseif $state == 'Marshall Islands'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/MH.tpl"}</b>
				</p></center>
			{elseif $state == 'Michigan'}
				<center><p class = "notice" style = "color:red;">
					<b>{include file = "{$smarty.current_dir}/EFR/MI.tpl"}</b>
				</p></center>
			{elseif $state == 'Minnesota'}
				<center><p class = "notice" style = "color:red;">
					<b>{include file = "{$smarty.current_dir}/EFR/MN.tpl"}</b>
				</p></center>
			{elseif $state == 'Missouri'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/MO.tpl"}</b>
				</p></center>
			{elseif $state == 'Mississippi'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/MS.tpl"}</b>
				</p></center>
			{elseif $state == 'Montana'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/MT.tpl"}</b>
				</p></center>
			{elseif $state == 'North Carolina'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/NC.tpl"}</b>
				</p></center>
			{elseif $state == 'North Dakota'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/ND.tpl"}</b>
				</p></center>
			{elseif $state == 'Nebraska'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/NE.tpl"}</b>
				</p></center>
			{elseif $state == 'New Hampshire'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/NH.tpl"}</b>
				</p></center>
			{elseif $state == 'New Jersey'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/NJ.tpl"}</b>
				</p></center>
			{elseif $state == 'New Mexico'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/NM.tpl"}</b>
				</p></center>
			{elseif $state == 'Nevada'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/NV.tpl"}</b>
				</p></center>
			{elseif $state == 'New York'}
				<center><p class = "notice" style = "color:red;">
					<b>{include file = "{$smarty.current_dir}/EFR/NY.tpl"}</b>
				</p></center>
			{elseif $state == 'Ohio'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/OH.tpl"}</b>
				</p></center>
			{elseif $state == 'Oklahoma'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/OK.tpl"}</b>
				</p></center>
			{elseif $state == 'Oregon'}
				<center><center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/OR.tpl"}</b>
				</p></center>
			{elseif $state == 'Pennsylvania'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/PA.tpl"}</b>
				</p></center>
			{elseif $state == 'Palau'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/PW.tpl"}</b>
				</p></center>
			{elseif $state == 'Rhode Island'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/RI.tpl"}</b>
				</p></center>
			{elseif $state == 'South Carolina'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/SC.tpl"}</b>
				</p></center>
			{elseif $state == 'South Dakota'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/SD.tpl"}</b>
				</p></center>
			{elseif $state == 'Tennessee'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/TN.tpl"}</b>
				</p></center>
			{elseif $state == 'Texas'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/TX.tpl"}</b>
				</p></center>
			{elseif $state == 'Utah'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/v/UT.tpl"}</b>
				</p></center>
			{elseif $state == 'Virginia'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/VA.tpl"}</b>
				</p></center>
			{elseif $state == 'Virgin Islands'}
				<center><p class = "notice" style = "color:red;">
					<b>{include file = "{$smarty.current_dir}/EFR/VT.tpl"}</b>
				</p></center>
			{elseif $state == 'Washington'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/WA.tpl"}</b>
				</p></center>
			{elseif $state == 'Wisconsin'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/WI.tpl"}</b>
				</p></center>
			{elseif $state == 'West Virginias'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/WV.tpl"}</b>
				</p></center>
			{elseif $state == 'Wyoming'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/EFR/WY.tpl"}</b>
				</p></center>
			{/if}
		</div>
	</div>
</body>
</html>
