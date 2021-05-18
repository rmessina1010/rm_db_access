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
 
    function rm_vsprintf_assoc($string, $array){
	   if (!is_scalar($string)) { return  $string;  }
	   if (is_object($array)) { $data = get_object_vars($data);  }
	   if (!is_array($array)) { return  $string;  }
	   preg_match_all('#%([\w\d-_]+@[^\\$]*)\\$#', $string,$dates);
	   if (isset($dates[1])){$dates = array_unique($dates[1]);}
	   foreach($dates as $date){
		   $split = strpos($date, '@');
		   $pattern = substr($date, $split+1);
 		   $key = substr($date, 0, $split );
 		   $string =str_replace("%$date$", date($pattern, $array[$key] ), $string);
 	   }
	   $data = array();
	   $count= 1;
	   foreach ($array  as $key=> $val){
		   $data[$count] =$val;
		   $string =str_replace("%$key\$", "%$count\$", $string);
		   $count++;
	   }
	   return vsprintf($string, $data);
	}
	
function rm_scrub_empty_tags($html,$args =false){
    $nbsp  = !isset($args['nbsp']) || $args['nbsp'] ? '\s*(?:&nbsp;\s*)*':'\s*' ;
    $attrs = isset($args['attr']) && $args['attr'] ?    '' : '[^>]*' ;
    $regex = '#<(\w[\w\d]*)\s*'.$attrs.'>'.$nbsp.'</\1>#i';
  	$htm = preg_filter( $regex, '', $html);
  	$html = $htm ? : $html;
	return $html;
}

function rm_scrub_tags($html,$tag='style|script'){
    $regex = '#<('.$tag.')[^>]*>.*</\1>#i';
  	$htm  = preg_filter( $regex, '', $html);
  	$html = $htm ? :$html;
 	return $html;
}

function rm_sani_attrs($html,array $allow =array()){
	
	$opts = array (
			'css'=> array (  'class'=>1 ,'id'=>1, 'style'=>1),
			'mouse'=> array ( 'up'=>1 ,'down'=>1, 'enter'=>1, 'leave'=>1, 'over'=>1, 'out'=>1),
			'key'=> array ( 'up'=>1 ,'down'=>1, 'press'=>1),
			'gen'=> array ( 'blur'=>1 ,'focus'=>1, 'change'=>1, 'load'=>1, 'click'=>1)
	);
	foreach ($allow as $group=>$type){
		if (!is_array($type)){ continue;}
 		foreach ($type as $attr){
 			unset($opts[$group][$attr]);
		}
	}
	foreach ($opts as $opt_key=>$opt){
		$opts[$opt_key] =  $opts[$opt_key] ? '(?:'.implode('|',array_keys($opts[$opt_key])).')' :'';
	}
 	$on=$close_on='';
	if(($opts['mouse']||$opts['key']||$opts['gen'])){
		if ($opts['css'] ) { $opts['css'].='|';}
		$on='(?:on';
 		$close_on=')';
 		if ($opts['gen']){
	 		$on.=substr($opts['gen'],0, -1).(($opts['mouse']||$opts['key']) ? '|':'');
	 		$close_on.=')';
 		}
 		if ($opts['key']){
	 		$on.='(?:key'.$opts['key'].')'.($opts['mouse'] ? '|':'');
	 		$close_on.=')';
 		}
 		if ($opts['mouse']){
	 		$on.='(?:mouse'.$opts['mouse'].')';
  		}
	}
    $regex = '#(?:'.$opts['css'].$on.$close_on.'\s*=\s*([\'"]).*(?<!\\\\)\1#iU';
   	$htm  = preg_filter( $regex, '', $html);
  	$html = $htm ? :$html;
 	return $html;
}
