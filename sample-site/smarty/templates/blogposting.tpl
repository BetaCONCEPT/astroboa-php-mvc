<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" 
		  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	
	<head>
		<title>
			Blog Postings
		</title>
		
		{include file="head-common-metatags.tpl"}
		
		<link href="/css/main.css" rel="stylesheet" type="text/css" />
		
	</head>
				
	<body>
		{include file="header.tpl"}
		
		<div id="container">
		
			<div id="main-column" class="one-column-layout">
				<h2>{$blogposting.profile.title}</h2>
				
				{if ! empty($blogposting.profile.publisher)}
					<h3>by {$blogposting.profile.publisher}</h3>
				{/if}
				<img src="{$blogposting|urlForBinaryProperty:$resourceApiCommonPath:'image':'600':null:'1.2'}"/>
				
				<p>
					{$blogposting.body}
				</p>
			</div>
			
		</div>
			
		{include file="footer.tpl"}
	
	</body>
		
</html>