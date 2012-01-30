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
				{if !empty($resources)}
					<h2>Lastest blog postings</h2>
					<hr style="color: #A2C613; background-color: #A2C613; border: 0; height: 1px;"/>
					{foreach $resources as $resource}
						<span style="font-size: 1.2em;">
							{$resource.webPublication.webPublicationStartDate|date_format:"%d %B %Y"} 
						</span>

						<h2>
							<a href="/blogpostings/{$resource.systemName}">{$resource.profile.title}</a>
						</h2>
						{if ! empty($resource.profile.publisher)}
							<h3>by {$resource.profile.publisher}</h3>
						{/if}

						<div>{$resource.body}</div>
						
						<hr style="color: #A2C613; background-color: #A2C613; border: 0; height: 1px;"/>
					{/foreach}
				{else}
					No Results Found
				{/if}
			</div>
			
		</div>
			
		{include file="footer.tpl"}
	
	</body>
		
</html>