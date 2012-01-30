<?php 

	function smarty_modifier_urlForBinaryProperty($resource, $resourceApiCommonPath, $property_with_index, $width, $height, $aspectRatio, $cropPolicy=null, $contentDispositionType=null) {
	    $url = $resourceApiCommonPath . '/contentObject/' . $resource['systemName'] . '/' . $property_with_index;
	    
	    $parameters = array();
	    if ($width != null){
	    	$parameters[] = 'width=' . $width;
	    }
	    
	    if ($height != null){
    		$parameters[] = 'height=' . $height;
	    }
	    
		if ($aspectRatio != null){
	    	$parameters[] = 'aspectRatio=' . $aspectRatio;
	    }

		if ($cropPolicy != null){
	    	$parameters[] = 'cropPolicy=' . $cropPolicy;
	    }
	    
	    if ($contentDispositionType != null){
	    	$parameters[] = 'contentDispositionType=' . $contentDispositionType;
	    }
	    
	    if (count($parameters) > 0){
	    	return $url . '?' . join('&', $parameters);
	    }
	    
	    return $url;
	}
	
?>