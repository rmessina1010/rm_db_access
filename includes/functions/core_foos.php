<?

	function  rm_compare($a,$b=true,$op='==',$N=0){
	switch ($op){
			case 'prm':
				return  ($b) ? (pow(2, $a)%$a == 2) : 	!(pow(2, $a)%$a == 2);
			case 'str':
				return  ($b) ? is_string($a) 		: 	!is_string($a);
			case 'arr':
				return  ($b) ? is_array($a) 		: 	!is_array($a);
			case 'boo':
				return  ($b) ? is_bool($a) 			: 	!is_bool($a);
			case 'obj':
				return  ($b) ? is_object($a) 		: 	!is_object($a);
			case 'num':
				return  ($b) ? is_numeric($a) 		: 	!is_object($a);
			case 'flt':
				return  ($b) ? is_float($a) 		: 	!is_object($a);
			case 'int':
				return  ($b) ? is_int($a) 			: 	!is_object($a);
			case 'scl':
				return  ($b) ? is_scalar($a) 		: 	!is_object($a);
			case 'nll':
				return  ($b) ? ($a === null) 		: 	($a !== null);
			case 'tru':
				return  ($b) ? ($a === true) 		: 	($a === false);
			case 'tof':
				return  ($b) ? ($a) 				: 	(!$a);
 			case 'bte':
				return  ($a<$N && $N<$b);
 			case 'bti':
				return  ($a<=$N && $N<=$b);
 			case '===':
				return  ($a===$b);
			case '!==':
				return  ($a!==$b);
			case '!=':
				return  ($a!=$b);
			case '<':
				return  ($a<$b);
			case '>':
				return  ($a>$b);
			case '>=':
				return  ($a>=$b);
			case '<=':
				return  ($a<=$b);
			case 'fit':
				return  ($a%$b === 0);
			case 'has':
				return  (is_string($a) && (strpos($a,$b) !== false));
			case '!has':
				return  (is_string($a) && (strpos($a,$b) === false));
			case '&&':
				return  ($a && $b);
			case '||':
				return  ($a || $b);
			case 'xor':
				return  ($a xor $b);
			default:
				return  ($a==$b);
	}
}


 function implopdePath(array $arr,$root=false){
	 foreach ($arr as $key=>$val){
		 if  ( !trim($val)) { unset($arr[$key]);}
		 else{ $arr[$key]=trim($val, '/'); }
 	 }
	 return ($root ? '/' :'').implode('/', $arr);
 }

 function explodePath($str,$q=false, $noF=true, $noD=true){
	 $str = explode('?', $str);
	 $res = explode('://', $str[0]);
 	 $last = count($res)-1;
 	 $res =  explode('/', $res[$last]);
 	 $last = count($res)-1;
	 if ($noD && strpos( $res[0], '.') ){ unset($res[0]);  }
	 if ($noF && strpos( $res[$last], '.') ){ unset($res[$last]);  }
	 $res =array_values($res);
	 if ($q ) { $res['q'] = isset($str[1]) ? $str[1]  : false;   }
 	 return $res;
 }


function rm_fooArgs($args=array(), $defaults=array(),$minArgCt=0,$userArgs=array()){
	//$userArgs	= select arguments passed to function, usually this is a subset of func_get_args();
	//$minArgCt	= minimun nuber of argument required
	//$userArgs	= list of all arguments/default value for function
	//$args		= the data to parse through
 	if (count($userArgs)==1 && is_array($args)){					// array key user args
 		$foo_args=array_merge($defaults,$args);						// merges user args with defaults to creat a complete argument list
 		$args=array_intersect_key($foo_args, $defaults);			// sanitizing proecedure; makes sure no extra arguments are inserted. This is an OPTIONAL added level of security
 		return $args ;
 	}
 	else{															// ordered user args
 		try{
   		 	if (count($userArgs)<$minArgCt) { throw new Exception('missing arguments!!!');}
		 	while ($scanArgs= each($userArgs)){
			 	$defScan= each($defaults);
			 	$defaults[$defScan['key']]=$scanArgs['value'];
		 	}
	 		return $defaults ;
 		}
 		catch(Exception $e){  echo 'Caught exception: ',  $e->getMessage(), "\n"; return null;}
 	}
  	////
}

function nameMap($data,   $keyMap , $alias =false, $bef='',$aft=''){
                 if ( is_string($keyMap) ) {
                         $keyMap =   rm_keyMaps::getMap($keyMap) ;
                         if (!$keyMap){ return;}
                 }
                 if ($alias){
                         if ($data instanceof RMCO) { return $data->the_($keyMap[$alias],$bef, $aft); }// check the_ foo for eco, and  to offset
                         if  (is_array($data) && isset($data[$keyMap[$alias]])){ return $bef.$data[$keyMap[$alias]].$aft ; }
                 }
                 else{
                        if  (is_array($data)  ){
                                foreach($keyMap as $a=>$k){
                                        if (isset($data[$k])) {
                                                $hold = $data[$k];
                                                unset($data[$k]);
                                                $data[$a]  =$hold;
                                        }
                                }
                                return $data;
                        }
                        if ($data instanceof RMCO) { return $data->map($keyMap); return true;}
                 }
                 return ;
 }

 function rm_scrOffset($txt,$href=false){
 	if ( is_string($txt) && ROOT_OFFSET ){
		$src = $href ? '[Hh][Rr][Ee][Ff]' :'[Ss][Rr][Cc]';
		$rgx = '#('.$src.'\s*=\s*[\'"]/)(?!'.ROOT_OFFSET.'/)#';
		$txt = preg_replace($rgx, '${1}'.ROOT_OFFSET.'/', $txt);
	}
	return $txt;
}
