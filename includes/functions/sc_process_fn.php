<?
	function apply_sc($str,$obj=NULL, array $xtra=array()) {
  	$useKey=false;
	if (($str instanceof RMCO ||  $str instanceof RMLDO||  $str instanceof RMCDO || is_array($str)) && is_scalar($obj)){
			$useKey=true;
			$swap=$str;
			$str=$obj;
			$obj=$swap;
	}
 	if($useKey){			 
 			if (is_array($obj)) {
				 if (isset($obj[$str]) && is_scalar($obj[$str])){ $str= $obj[$str]; }
				 else { return false;}
		}
		elseif( $obj instanceof RMCO  || $obj instanceof RMCDO || $obj instanceof RMLDO){
				 $counter= $obj->pointerAt();
				 if ( $obj->rawGet($counter,$str) !== null ){ 
					 	$str= $obj->rawGet($counter,$str);
					 	if (!is_scalar($str)){return false;}
				 }
				 else { return false;}
		}
	}
  	// find short codes in string;
   	 preg_match_all('#<\!--((\w+-)?[\w_]+):((?!-->).*?)?-->((?!<\!--\/\1-->).*?)?<\!--\/\1-->#is', $str,$codez);
  	 $path= realpath(__DIR__ . '/..').'/shortcodes/';
  	 
  	 if (count($codez[0])){ 
	  	  	 $shortCodez=	$codez[0];
		  	 $code 		=	$codez[1];
		  	 $docName 	=	$codez[2];
		  	 $attrs 	=	$codez[3];
		  	 $contents 	=	$codez[4];
	    	 $ckey = array_keys(array_unique($shortCodez));// determine unique shortcode substring;
	    	// replace  said shortcode in string with result from function
	    	foreach  ($ckey as $k){
		  	$fn=$dn='';
		  	if ($docName[$k]) { $dn = substr(trim($docName[$k]), 0, -1); $fn=$dn.'_'; }
		  	$fooName='RMCSC_'.$fn.trim($code[$k]);
	 	  	if (!function_exists($fooName)){ // if  function not available, attempt  to load function
				if ($dn && file_exists($path.$dn.'.php')){include_once($path.$dn.'.php');}
				elseif(file_exists($path.$fooName.'.php')) { include_once($path.$fooName.'.php');}
				elseif (file_exists($path.SHORTCODES)) { include_once($path.SHORTCODES);}
			}
	  		if (function_exists($fooName)){ // if functin exist  run; pass attrs, content,  xtra, obj(obj|| row)
		  		parse_str(trim($attrs[$k]),$attrArray); // parse shortcode arguments, $attrs[$k] must be written in PHP's parse_string() function format!!!
		  		$replacement=$fooName($attrArray, $contents[$k] ,  $xtra, $obj);
		  	}
	  		else{ $replacement=$contents[$k];} // else return content;
	  		$str = str_replace($shortCodez[$k], $replacement, $str);
	  		
	  	}
	}
    return !$useKey ? $str : false;
}
?>