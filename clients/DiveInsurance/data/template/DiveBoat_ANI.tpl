{assign var=list value=$additionalNamedInsured|json_decode:true}
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
<link href= "{$smarty.current_dir}/css/divebtemplate_css.css" rel="stylesheet" type="text/css" />

</head>
<body>
	<div class ="body_div_ai">

			<div class = "ai_spacing"></div>
			{foreach from=$list item=$additional}
	    		<p class = "ai_list">
	    			{$additional.name},{$additional.address},{$additional.city},{$additional.state},{$additional.zip}
	    		</p>
    		{/foreach}
	</div>
</body>
</html>
