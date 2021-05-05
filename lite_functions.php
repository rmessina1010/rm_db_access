<?
	function rm_whitelist( array $arr, array $allowed, $def = false){
		return $def  ?  	array_intersect_key($arr, array_flip($allowed)): array_intersect_key($arr,  $allowed) + $allowed ;
	 }

 	function rm_base_array($default,$fill=false, $data =false){
	 	if (is_string($default)){  
		 	$default = explode(',', $default);
		}
		$default = array_values($default);
		$baseArr = array();
		for ($i=0, $l=count($default); $i<$l;$i++){ 
			$baseArr[trim($default[$i])] = $fill; 
		}
 	 	if (is_array($data)){ 
	 	 	$baseArr = rm_base_array_fill( $baseArr, $data);
	 	}
	 	return $baseArr;
 	}
 	
 	function rm_base_array_fill(array $base, array $fill){
	 	return array_intersect_key($fill, $base) + $base ;
 	}
 	

	function rm_ph_replace($str, array $data=array(),$o='{{',$c='}}' ){ // lite version of insertFromField()
		  $firstDel=strstr($str, $o);
		  if ( $firstDel=== false || strstr($str, $c) <= $firstDel){return $str;}
		  $o= (strpos($o.$o,"#") !== false) ? str_replace("#", "\#", $o) : $o;
		  $c= (strpos($c.$c,"#") !== false) ? str_replace("#", "\#", $c) : $c;
		  $regex='#'.$o.'([\w-@\#\$\!\|&~\^\+\*\\\/]*)'.$c.'#';
  		  preg_match_all($regex, $str, $holders);
		  foreach ($holders[1] as $k=>$v){  
			  if (isset($data[$holders[1][$k]])) { $str=str_replace($holders[0][$k], $data[$holders[1][$k]] ,$str );}
		  }
		  return $str;
 	}
