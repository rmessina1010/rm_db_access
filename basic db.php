<?

class DB{

	protected $user 	= null;
	protected $password	= null;
	protected $db_name 	= null;
	protected $db_type 	= null;
	protected $db_host 	= null;
 	protected $db_dsn	= null;
 	protected $dbhs 	= array();


	function __construct( $db_name, $user  ,  $password ,     $db_host='localhost',  $db_type = 'mysql'){
		$this->user 	= $user;
		$this->password	= $password;
		$this->db_name 	= $db_name;
		$this->db_type 	= $db_type;
		$this->db_host 	= $db_host;
		$this->setup();
	}

	protected function setup(){
		$this->dbhs = array();
		$this->db_dsn = $this->db_type.":dbname=".$this->db_name.";host=".$this->db_host;
	}

	function connect($dbh='_default'){
 		return $this->open($dbh) ? $this->dbhs[$dbh] : null;
	}

	function open($dbh='_default'){
		if (is_scalar($dbh)){
			if (!isset($this->dbhs[$dbh])){
				try {
				    $this->dbhs[$dbh] = new PDO( $this->db_dsn, $this->user, $this->password);
	 			} catch (PDOException $e) {
				   	echo 'Connection failed: ' . $e->getMessage();
				   	return null;
				}
			}
		}
		return $this;
	}

	function close($dbh='_default'){
		if (is_scalar($dbh)){
			unset ($this->dbhs[$dbh]);
		}
		return $this;
	}

}


class DB_query{

	protected $dbh 		= null;
	protected $query	= 	'';
	protected $STMNT	= null;
	protected $args		= array();
	protected $holders	= array();
	protected $is_posit	= true;
	protected $hold_ct	= 0;
	protected $params	= null;


	function __construct($query,$dbh, array $attributes = array()){
 	  $this->dbh =  $dbh;
 	  $this->prep($query,$attributes);
	  return $this;
	}

	function prep($query, array $attributes = array()){
		$this->hold_ct 	= preg_match_all("/:\w+/", $query, $matches);
 		$this->is_posit	= !($this->hold_ct > 0 );
 		$holders		= ($matches && $matches[0]) ?  $matches[0] : array();
 		if ($this->is_posit) {
	 		$query = str_replace(':?', '?', $query,$count);
	 		$this->hold_ct =$count;
	 		$this->holders= $count >0 ? array_fill(1, $count, true) : array();
	 	}else{
	 		$this->holders	= ($matches && $matches[0]) ?  array_flip($matches[0]) : array();
	 	}
		$this->query  	= $query;
 		$this->STMNT  = $this->dbh->prepare($query,$attributes);

		return $this;
	}


	function bind_param(&$var,$col,$type='STR'){
		if ($this->STMNT){
			$col  =  $this->col_check($col);
			if($col){
				$type =  $this->type_check($type);
				$this->STMNT->bindParam($col,$var,$type);
			}
		}
		return $this;
	}

	function bind_val($val,$col,$type='STR'){
	 	if ($this->STMNT){
			$col  =  $this->col_check($col);
			if($col){
				$type =  $this->type_check($type);
				$this->STMNT->bindValue($col,$val,$type);
			}
		}
		return $this;
	}

	function list_holders(){
		return array_keys($this->holders);
	}

	function has_holder($key){
		return isset($this->holders[$key]);
	}

	function is_positional(){
		return $this->is_posit;
	}

	function set_params(array $params =array(),$type ='PARAM_STR'){////complete later
		foreach ($params as $col=>$val ) {
			$this->bindVal();
		}
		return $this;
	}

	function STMNT(){ return $this->STMNT;}

	function run($data = null){
		if ($this->STMNT){
			if (is_array($data)){    $this->STMNT->execute($data) ;}
			else{ $this->STMNT->execute();}
		}
		return $this->STMNT;
	}


	protected function col_check($col){
		if (is_string($col)) { $col = trim($col);}
 		if (!$this->is_posit){
			if (!is_string($col)) {return false;}
 			$col = $col[0] !==':' ? ':'.$col : $col;
			if (!isset($this->holders[$col])){return false;}
		}else{
			if (!is_numeric($col)) {return false;}
			if (is_string($col)) {$col = $col[0] ===':' ? substr($col, 1) : $col;}
			$col=$col+0;
		}
		return $col;
	}

	protected function type_check($type){
		if (!is_string($type) || !preg_match('/^ *(?:BOOL|NULL|INT|STR|LOB|STMT) *$/i', $type)){ $type = 'STR'; }
		$type = 'PARAM_'.strtoupper(trim($type));
		return constant('PDO::'.$type);
	}
}


function sql_str($operation, $table, $cols,$vals){
	$operation = is_scalar($operation) ? strtolower(trim($operation.'')) : 'r'; //sanitizes variable and/or defaults to 'r'
	$operation = $operation  ? $operation[0] : 'r'; //sanitizes variable and/or defaults to 'r'
	if (is_array($table)){

		 $key_map = array( 't'=>'table', 'c'=>'cols', 'v'=>'vals', 's'=>'set_list', 'l'=>'limit', 'o'=>'order','w'=>'where', 'j'=>'join',    'g'=>'group', 'h'=>'having');


	}
	$WHERE    = $where  ? ' WHERE '.$where : '';
	$WHERE    = $having ? ' HAVING '.$having : '';
	$LIMIT    = $limit  ? ' LIMIT '.$limit : '';
	$ORDERBY  = $order  ? ' ORDER BY '.$order : '';
	$GROUPBY  = $group  ? ' GROUP BY '.$group : '';
	//joins

 	switch ($operation){

		case 'p':
			$sql = $table;
		break;

		case 'c':
			$sql = "INSERT INTO $table SET ($cols) VALUES $values";
 		break;

		case 'u':
			// set-list
			$set_list_str = $comma ='';
			if (!is_string($set_list)){// the set_list has been pre parsed
				if(is_array($set_list)  && $set_list){ // the set_list has been passed as akey value pair array
					$vals  = array_values($set_list);
					$cols  = array_keys($set_list);
				}elseif ( is_array($cols))   {
					if ( is_scalar($vals)  ){
						 $vals  = $vals  ?  array_values($cols) : array_fill(0, count($sols),':?');
					}else{
						$cols= array_values($col);
					}
				}
				foreach ($cols as $i=>$col){ $set_list_str .=  $comma.$col.' = '.$values[$i]; $comma =' , ';}
			}else{ $set_list_str = $set_list;}
			$sql = "UPDATE $table SET $set_list_str $WHERE $ORDERBY $HAVING $LIMIT";
		break;

		case 'd':
			$sql= "DELETE FROM $table $WHERE $ORDERBY $LIMIT";
		break;
		default:
			$sql = "SELECT $cols FROM $table $JOIN $WHERE $ORDERBY  $GROUPBY $HAVING $LIMIT";

 	}

 	return $sql;
 }

function sql_C_str($table, $cols,$vals){

}
function sql_R_str($table, $cols,$vals){////

}
function sql_U_str($table, $cols,$vals){///

}
function sql_D_str($table, $where,$ord, $lim){

}
function sql_p_str($table){

}