<?
class RMLDO{
	protected $_TheData 	= array();
	protected $_pointer		= 0;
	protected $_theSize		= 0;
	protected $_columns		= array();
	protected $_STMNTobj	= false;
	protected $_current;
	protected $_isLooping	= false;
	protected $_table 		= false;
	protected $_pKey		= false;
	protected $_keyMap_inv	= false;
	protected $user_args	= array('sc'=>'apply_sc', 'ck1'=>'check1', 'ck2'=>'check2', 'opp'=>'opp',  'ndt'=>'indent', 'lp'=>'loop');
	
	protected $_keyMap		= false;
	protected $_hlev		= 0;
	var		  $loop 		= false;
	var		  $indent 		= 0;
	var 	  $apply_sc		= APPLY_SC; 
	var 	  $check1		= null;
	var 	  $check2		= null;
	var 	  $opp			= '==';

	function __construct($input, array $args=array()){
		foreach ($this->user_args as $arg_key=>$prop){ 
				if(isset($args[$arg_key])){
					$this->$prop = $args[$arg_key];
				}
		}
	    if (isset($args['map'])  && is_array($args['map'])) {$this->mapColsTo($args['map']);}
		$this->data_init($input, $args);
	}
	
	protected function data_init($input, $args =array()){
 		if(is_string($input) && isset($args['dbh'])){
			$dbh =$args['dbh'];
			$stmt = false;
			if (!($dbh instanceof PDO)){
				if (is_string($dbh)){ $dbh = explode(',', $dbh);}
				if(is_array($dbh)){
					$dbname = array_unshift($dbh) ;
					$dbconn = $dbh ? array_unshift($dbh) : (isset($args['dbi']) && is_string($args['dbi'])) ? $args['dbi'] : '_default';
					$dbh = DB_hub::connect($dbname,$dbconn);
				}else{
					$dbh = false;
				}
			}
 			if($dbh){
				$input =trim($input);
				if (substr($input, 0,7) === 'SELECT '){
					$stmt = new DB_query($input,  $dbh);
 				}
			}
			if ($stmt) { $input=$stmt;}
		}
		if($input instanceof DB_query)  {
			$input = $input->STMNT();
		}
		if($input instanceof PDOStatement){
			$this->_STMNTobj = $input;
			$this->setTable();
 			if ($this->_STMNTobj->errorCode() === ''){ 
				$vars = (isset($args['vars']) && is_array($args['vars'])) ?  $args['vars'] : array();
	 			$this->_STMNTobj->execute($vars);
	 		}
			$input = $this->tryFetch();
		}
 		$this->set_theData($input);
	}
	
	protected function tryFetch(){
		try  {
			$r= $this->_STMNTobj->fetchAll();   
			return  $r ?  $r : array();
			
 		}
		catch (Exception $e) {
			return array();
		}
	}
	protected function setTable(){
		preg_match_all('/(?:FROM +\(?`?)((?!SELECT )[\w$\x{00C0}-\x{00FF}]*)/i', $this->_STMNTobj->queryString,$matches);
		if(!isset($matches[1])){ 
			$this->_pKey=$this->_table = false; 
			return; 
		}
		$this->_table = array_shift(array_filter($matches[1]));
	    $this->_pKey=pKey::getK($this->_table);
	}
	
	protected function set_theData($data =array()){
		if (!is_array($data)){ $data = array(); }
		$this->_TheData = array_values($data);
		$this->setColumns();
		$this->calcSize();
		$this->resetPointer();
	}

 	
	protected function calcSize(){ 
		$this->_theSize	= count( $this->_TheData);
	}
	
	protected function setColumns(){ 
		$this->_columns = $this->_TheData ? array_keys($this->_TheData[0]) : array();
	}

	function pointerAt(){ 
		return $this->_pointer;
	}
	
	function resetPointer($rev=false){
		$this->_pointer 	= $rev ? $this->_theSize-1 : 0;
		$this->_isLooping 	=  false;
		$this->_current 	= isset($this->_TheData[$this->_pointer]) ? $this->_TheData[$this->_pointer] : null;
	}
 	function res($row=0,$key=null){ /// added for the sake of compatibility with RMCO
     	if ($row === null){ return $this->_TheData;}
     	if ($key !== null && isset($this->_TheData[$row][$key])){return $this->_TheData[$row][$key];}
     	if (isset($this->_TheData[$row])){return $this->_TheData[$row];}
 	}
 	
	function dump( array $data =array()){
		return $this->_TheData;
 	}

 	function Q(){ 
		return $this->_STMNTobj ? $this->_STMNTobj->queryString : null;
	}
 	
	function update( array $data =array()){
		if($this->_STMNTobj){
			$this->_STMNTobj->execute($data);
			$data = tryFetch();
		}
		$this->set_theData($data);
 	}
 	
	function mapColsTo($new=false, $mapTo=false){
		if ($new === false){// no map
			$this->_keyMap_inv=$this->_keyMap=array();
 			return;
		}
		if (is_scalar($new)){//change/set/delete specific key pair
			if ( $mapTo === NULL || $mapTo === false){
				$rev = $this->_keyMap[$new];
				unset ($this->_keyMap[$new],  $this->_keyMap_inv[$rev]);
				return ;
			}
			elseif( is_scalar($mapTo)  && in_array($mapTo, $this->_columns)){
				unset($this->_keyMap_inv[$this->_keyMap[$new]]);
				$this->_keyMap[$new]=$mapTo;
				$this->_keyMap_inv[$mapTo]=$new;
			}
		}
		elseif (is_array($new)){//new map from array
			if (is_array($mapTo)) { $new = array_combine($new, $mapTo);}
			$temp = array_flip($this->_columns);
			foreach( $new as $link=>$OK){
				if (isset($temp[$OK])){
					$this->_keyMap[$link] = $OK; 
					$this->_keyMap_inv[$OK]=$link;
				}
			}
 		}
	}
 }


?>