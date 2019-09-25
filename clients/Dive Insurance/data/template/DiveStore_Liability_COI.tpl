<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
<link href= "{$smarty.current_dir}/css/divestemplate_css.css" rel="stylesheet" type="text/css" />

</head>
<body>
	<div class ="body_div">
	  <hr class = "s_hrtag"></hr>
      <center><b>Store Location:&nbsp<span class ="store_location"> {$address1},{$address2},{$city},{$state},{$country},{$zipcode}</span></b></center>
      <hr class = "s_hrtag"></hr>
      <br/>
		<div class="section_col">
			<div class ="sec1">
				<p class ="title">SECTION I: LIABILITY COVERAGES</p>
			</div>
			<div class ="sec2">
				<p class ="title">Limits</p>
			</div>
		</div>

			<div class = "sec_content">
				<div class = "sec3">
					<p class = "sec_title">Commercial General Liability (Each Occurrence Limit):</p>
					<p class = "sec_title">Personal Injury (per Occurrence):</p>
					<p class = "sec_title">General Liability Aggregate:</p>
					<p class = "sec_title">Products and Completed Operations Aggregate:</p>
					<p class = "sec_title">Damage to premises rented to you:</p>
					<p class = "sec_title">Medical Expense:</p>
					<p class = "sec_title">NON-Owned Auto:</p>
					<p class = "sec_title">NON-Diving Pool Use:</p>
					<p class = "sec_title">Travel Agent E&O (Each wrongful act & Aggregate):</p>
					<p class = "sec_title">&nbsp&nbsp&nbsp&nbsp(Claims made form)</p>
				</div>
				<b><div class = "sec4">
					<p class = "sec_title2">${$general_liaility}</p>
					<p class = "sec_title2">${$personal_injury}</p>
					<p class = "sec_title2">${$general_liability_aggregate}</p>
					<p class = "sec_title2">${$product_aggregate}</p>
					<p class = "sec_title2">${$damage}</p>
					<p class = "sec_title2">{if medical_expense}
							Included
					   {else}
					   		Not Included
					   {/if}
					</p>
					<p class = "sec_title2">{if owned_auto}
							Included
					   {else}
					   		Not Included
					   {/if}
					</p>
					<p class = "sec_title2">{if diving_pool_use}
							Included
					   {else}
					   		Not Included
					   {/if}
					</p>
					<p class = "sec_title2">{if travel_agent}
							Included
					   {else}
					   		Not Included
					   {/if}
					</p>
				</div></b>
			</div>
		<div class="spacing">&nbsp</div>

		<hr></hr>
		<b><center><p class="addIn">{if !empty(additionalInsured)}
			Certificate has Additional Insureds (See Attached)
			{else}
		Certificate Does Not Have Additional Insured.
			{/if}</p></center></b>
		<hr></hr>

		<p  class = "policy_notice">The insurance afforded by this policy is a master policy issued to PADI Worldwide Corporation, 30151 Tomas Street, Rancho Santa Margarita, CA 92688.
The insurance is provided under terms and conditions of the master policy which is enclosed with this certificate. Please read the policy for a full description
of the terms, conditions and exclusions of the policy. This certificate does not amend, alter or extend the coverage afforded by the policy referenced on this
certificate.</p>

<p  class = "policy_notice">Notice of cancelation: If the company cancels this policy, 45 days notice will be given to the certificate holder unless cancellation is for nonpayment of
premium, then 10 days notice will be provided, and any premium not earned will be returned to the certificate holder.</p>

{if $state == 'Alaska'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/AK.tpl"}</b>
				</p></center>
			{elseif $state == 'Alabama'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/AL.tpl"}</b>
				</p></center>
			{elseif $state == 'Arkansas'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/AR.tpl"}</b>
				</p></center>
			{elseif $state == 'Arizona'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/AZ.tpl"}</b>
				</p></center>
			{elseif $state == 'Colorado'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/CO.tpl"}</b>
				</p></center>
			{elseif $state == 'Connecticut'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/CT.tpl"}</b>
				</p></center>
			{elseif $state == 'District of Columbia'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/DC.tpl"}</b>
				</p></center>
			{elseif $state == 'Delaware'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/DE.tpl"}</b>
				</p></center>
			{elseif $state == 'Florida'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/FL.tpl"}</b>
				</p></center>
			{elseif $state == 'Micronesia'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/FM.tpl"}</b>
				</p></center>
			{elseif $state == 'Georgia'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/GA.tpl"}</b>
				</p></center>
			{elseif $state == 'Hawaii'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/HI.tpl"}</b>
				</p></center>
			{elseif $state == 'Iowa'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/IA.tpl"}</b>
				</p></center>
			{elseif $state == 'Idaho'}
				<center><p class = "notice" style = "color:red;">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/ID.tpl"}</b>
				</p></center>
			{elseif $state == 'Illinois'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/IL.tpl"}</b>
				</p></center>
			{elseif $state == 'International'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/International.tpl"}</b>
				</p></center>
			{elseif $state == 'Kansas'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/KS.tpl"}</b>
				</p></center>
			{elseif $state == 'Kentucky'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/KY.tpl"}</b>
				</p></center>
			{elseif $state == 'Louisiana'}
				<center><p class = "notice" style = "color:red;">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/LA.tpl"}</b>
				</p></center>
			{elseif $state == 'Massachusetts'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/MA.tpl"}</b>
				</p></center>
			{elseif $state == 'Maryland'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/MD.tpl"}</b>
				</p></center>
			{elseif $state == 'Maine'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/ME.tpl"}</b>
				</p></center>
			{elseif $state == 'Marshall Islands'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/MH.tpl"}</b>
				</p></center>
			{elseif $state == 'Michigan'}
				<center><p class = "notice" style = "color:red;">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/MI.tpl"}</b>
				</p></center>
			{elseif $state == 'Minnesota'}
				<center><p class = "notice" style = "color:red;">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/MN.tpl"}</b>
				</p></center>
			{elseif $state == 'Missouri'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/MO.tpl"}</b>
				</p></center>
			{elseif $state == 'Mississippi'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/MS.tpl"}</b>
				</p></center>
			{elseif $state == 'Montana'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/MT.tpl"}</b>
				</p></center>
			{elseif $state == 'North Carolina'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/NC.tpl"}</b>
				</p></center>
			{elseif $state == 'North Dakota'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/ND.tpl"}</b>
				</p></center>
			{elseif $state == 'Nebraska'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/NE.tpl"}</b>
				</p></center>
			{elseif $state == 'New Hampshire'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/NH.tpl"}</b>
				</p></center>
			{elseif $state == 'New Jersey'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/NJ.tpl"}</b>
				</p></center>
			{elseif $state == 'New Mexico'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/NM.tpl"}</b>
				</p></center>
			{elseif $state == 'Nevada'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/NV.tpl"}</b>
				</p></center>
			{elseif $state == 'New York'}
				<center><p class = "notice" style = "color:red;">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/NY.tpl"}</b>
				</p></center>
			{elseif $state == 'Ohio'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/OH.tpl"}</b>
				</p></center>
			{elseif $state == 'Oklahoma'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/OK.tpl"}</b>
				</p></center>
			{elseif $state == 'Oregon'}
				<center><center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/OR.tpl"}</b>
				</p></center>
			{elseif $state == 'Pennsylvania'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/PA.tpl"}</b>
				</p></center>
			{elseif $state == 'Palau'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/PW.tpl"}</b>
				</p></center>
			{elseif $state == 'Rhode Island'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/RI.tpl"}</b>
				</p></center>
			{elseif $state == 'South Carolina'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/SC.tpl"}</b>
				</p></center>
			{elseif $state == 'South Dakota'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/SD.tpl"}</b>
				</p></center>
			{elseif $state == 'Tennessee'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/TN.tpl"}</b>
				</p></center>
			{elseif $state == 'Texas'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/TX.tpl"}</b>
				</p></center>
			{elseif $state == 'Utah'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/v/UT.tpl"}</b>
				</p></center>
			{elseif $state == 'Virginia'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/VA.tpl"}</b>
				</p></center>
			{elseif $state == 'Virgin Islands'}
				<center><p class = "notice" style = "color:red;">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/VT.tpl"}</b>
				</p></center>
			{elseif $state == 'Washington'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/WA.tpl"}</b>
				</p></center>
			{elseif $state == 'Wisconsin'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/WI.tpl"}</b>
				</p></center>
			{elseif $state == 'West Virginias'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/WV.tpl"}</b>
				</p></center>
			{elseif $state == 'Wyoming'}
				<center><p class = "notice">
					<b>{include file = "{$smarty.current_dir}/DiveStoreSurplus/WY.tpl"}</b>
				</p></center>
			{/if}
	</div>
</body>
</html>

