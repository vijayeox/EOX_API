{assign var=list value=$lossPayees|json_decode:true}
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
<link href= "{$smarty.current_dir}/css/divestemplate_css.css" rel="stylesheet" type="text/css" />

</head>
<body>
	<div class ="body_div_lp">
		<b><p class ="lp_name">Name & Address</p></b>
	    	{foreach from=$list.name item=$value}
	  			<p class = "ai_list">
	    			&nbsp&nbsp&nbsp{$value}
	  			</p>
			{/foreach}
	</div>
</body>
</html>

