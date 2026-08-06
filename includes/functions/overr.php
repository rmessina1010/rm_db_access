<?
 // This is a utility to fascilitate function overloading in PHP
 // Best used in combination with func_get_args()
 
 function _overload( array $args, array $matchers,  $return_part= false) {

    $return_args = $args;
    $arg_ct  	 = count($args);
	$matched 	 = false;
	$arg_ty		 = implode(",", array_map('gettype', $args));
	echo 	$arg_ty."<br>";
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
		echo $types."<br>";
		$count = (strlen($types)>2) + substr_count( $types,','); 
				echo "$count = $arg_ct<br>";

		// check  all types match and in order
		if ($count != $arg_ct) {continue;}
		if ($types &&  $types != $arg_ty) {continue;}
		// check values match
		if ($values){
			$k = 0;
			foreach ($values as $v){
				if ((is_array($v) && !in_array($args[$k], $v)) || $args[$k] !== $v){ continue 2;}
				$k++;
			}
		}
		$matched =$match;
	}
	
	if ($matched && $return_part){
		$return_args= array();
		$temp = array_values($args);
		if (is_array($return_part)){
			$k = 0 ;
			foreach ($return_part as $kv){
				$return_args[$kv]= $temp[$k];
				$k++;
			}
		}else{
			$return_args = array_slice($args, 0, $arg_ct);
		}
	}

	return [
		'flag' => $matched,
		'args' => $return_args,
		];

}
 
var_dump( _overload( 
 	array(false,"SQL GoES HERE", [1,2,3,4]), 
 	array ("sql"=>array("ty"=>"boolean,string,array")),
 	array('arg1','arg2')
));


var_dump( _overload( 
	array(false,"SQL GoES HERE", [1,2,3,4]), 
	array ("sql"=>array("ty"=>"boolean,string,array")),
	array('arg1','arg2')
));
		
var_dump( _overload( 
	array(true,"SQL GoES HERE", [1,2,3,4]), 
	array (
		"sql"=>array("ty"=>"boolean,string,array"),
		"nosql"=>array("ty"=>"boolean,string,array", "vl"=>[true])
	),
	array('arg1','arg2')
));
 
var_dump( _overload( 
	array("more string","SQL GoES HERE", [1,2,3,4]), 
	array (
		"sql"=>array("ty"=>"boolean,string,array"),
		"nosql"=>array("ty"=>"boolean,string,array", "vl"=>[true]),
		"diff"=>array("ty"=>"string,string,array" ),
	),
	array('arg1','arg2')
));
			  
?>