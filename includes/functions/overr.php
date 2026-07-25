<?
 // This is a utility to fascilitate function overloading in PHP
 // Best used in combination with func_get_args()
 
 function _overload( array $args, array $matchers, bool $return_all= false) {

    $return_args = [];
    $arg_ct =  count($arg);
	$matched = false;
	$arg_ty= implode(",", array_map('typeof', $args));
	
	// get types
	// get vals
	
	foreach ($matchers as $match => $conditions){
		$values = false;
		$types = is_string($conditions) ? $conditions : '';
		if (is_array($conditions)){
			if (isset($conditions['ty'])){ $types = $conditions['ty']; }
			if (isset($conditions['vl']) && is_array($conditions['vl'])){ $values = $conditions['vl'];}
		}
		$types = preg_replace('/\s+/', '', $types);
		$count = (strlen($types)>2) + substr_count(',', $types); 
		// check  all types match in order
		if ($count != $arg_ct) {continue;}
		if ($types &&  $types != $arg_ty) {continue;}
		// check vals
		if ($values){
			$k = 0;
			foreach ($values as $v){
				if ((is_array($v) && !in_array($args[$k], $v)) || $args[$k] !== $v){ continue 2;}
				$k++;
			}
		}
		$matched =$match;
	}
	
	if ($matched && !$return_all){
		if (is_array($return_all)){
			$k =0 ;
			foreach ($return_all as $k){
				$return_args[$k]= $args[$k];
				$k++;
			}
		}else{
			$return_args= array_slice($args, 0, $arg_ct);
		}
	}

	return [
		'flag' => $matched,
		'args' => $return_args,
		];

}

  _overload( array(false,"SQL GoES HERE", [1,2,3,4], array ("sql"=>array("ty"=>"boolean,string,array")))

?>