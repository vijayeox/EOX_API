{assign var=list value=$additionalInsured|json_decode:true}
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
					<p class = "details">{$lastname},{$firstname} {if isset($initial)},{$initial}{/if}</p>
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
					<p class = "p_margin">{$start_date|date_format:"%m/%d/%Y"}</p>
					<p class = "p_margin">{$end_date|date_format:"%m/%d/%Y"}&nbsp12:01:00 AM</p>
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
	    	<b><p class = "ai_title">Additional Insured (See Additional Insured - Blanket Form):</p></b>
	    		{foreach from=$list item=$additional}
		    		<p class = "ai_list">
		    			{$additional.name}
		    		</p>
	    		{/foreach}
    	</div>
	</div>
</body>
</html>

