<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
<link href= "{$smarty.current_dir}/css/card_css.css" rel="stylesheet" type="text/css" />

</head>
<body>
	<div class ="div_cover">
		<p>{$smarty.now|date_format:"%m/%d/%Y"}</p>
		<div class ="info_cover">
			<p class="name1">{$orgname}</p>
			<p class="name1">{$address1}</p>
			<p class="name1">{$address2}</p>
			<p class="name1">{$city},{$state}</p>
			<p class="name1">{$country},{$zip}</p>
		</div>

		<p class = "rgard">RE: PADI ENDORSED DIVE BOAT INSURANCE</p>

		<p>Dear <span class ="rgaard1">{$firstname}&nbsp{$lastname}</span></p>
		<div class = "line_space">
		<p>We are pleased to enclose the certificate of insurance and policy for your dive/snorkel vessel. The certificate lists the
coverage and policy limits applicable to your vessel.</p>
		<ul class ="order">
			<li>Please read these documents and advise us of any discrepancies or necessary changes.</li>
			<li>Read and understand the navigational and passenger limits on your certificate.</li>
			<li>Be sure to inform us of any new captains or crew members you hire.</li>
			<li>The insured hull value is determined by your latest survey report. This may differ from what is stated on your
application.</li>
			<li>If financing, your payments are due to AFS/IBEX Premium Finance Company. You will receive a monthly invoice.</li>
		</ul>

		<p class = "line_end">Thank you for your support of the PADI Endorsed Insurance Program, if you have any questions, please call or email me if you have any questions.</p>
</div>
		<p>Sincerely,</p>
		<p>Vicencia & Buckley A Division of HUB International</p>
		<p class="acc_name">{$manager_name},CISR, Account Manager</p>
		<p class ="footer_line">Vicencia & Buckley A Division of HUB International</p>
		<p class ="footer_line">(800) 223-9998 or (714) 739-3176</p>
		<p class ="footer_line">{$manager_email}</p>

	</div>
</body>
</html>

