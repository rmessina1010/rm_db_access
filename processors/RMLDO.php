<?
require_once 'RMLDB.php';
require_once './includes/functions/sc_process_fn.php';
require_once './includes/functions/core_foos.php';

class RMLDO
{
	protected $_theData 	= array();
	protected $_pointer		= 0;
	protected $_theSize		= 0;
	protected $_columns		= array();
	protected $_STMNTobj	= false;
	protected $_current;
	protected $_isLooping	= false;
	protected $_table 		= false;
	protected $_pKey		= false;
	protected $_keyMap_inv	= false;
	protected $user_args	= array('sc' => 'apply_sc', 'ck1' => 'check1', 'ck2' => 'check2', 'opp' => 'opp',  'ndt' => 'indent', 'lp' => 'loop', 'ndx' => '_indexKey');
	protected $_indexMap 	= null;   	// added sept 2020
	protected $_indexKey 	= null;  	// added sept 2020

	protected $_keyMap		= array();
	protected $_hlev		= 0;
	var		  $loop 		= false;
	var		  $indent 		= 0;
	var 	  $apply_sc		= APPLY_SC;
	var 	  $check1		= null;
	var 	  $check2		= null;
	var 	  $opp			= '==';
	var 	  $def_row		= array();

	protected $l			= 0;
	protected $h			= 0;
	protected $clamp		= false;

	protected $error		= null;

	function __construct($input, array $args = array())
	{
		foreach ($this->user_args as $arg_key => $prop) {
			if (isset($args[$arg_key])) {
				$this->$prop = $args[$arg_key];
			}
		}
		if (isset($args['map'])  && is_array($args['map'])) {
			$this->mapColsTo($args['map']);
		}
		$this->data_init($input, $args);
	}

	protected function data_init($input, $args = array())
	{
		if (is_string($input)) {
			if (isset($args['dbh'])) {
				$dbh = $args['dbh'];
			} else {
				$dbh = new DB(DBNAME, UACC,  UPASS,  HOST);
				$dbh = $dbh->connect();
			}
			$stmt = false;
			if (!($dbh instanceof PDO)) {
				if (is_string($dbh)) {
					$dbh = explode(',', $dbh);
				}
				if (is_array($dbh)) {
					$dbname = array_unshift($dbh);
					$dbconn = $dbh ? array_unshift($dbh) : ((isset($args['dbi']) && is_string($args['dbi'])) ? $args['dbi'] : '_default');
					$dbh = DB_hub::connect($dbname, $dbconn);
				} else {
					$dbh = false;
				}
			}
			if ($dbh) {
				$input = trim($input);
				if (substr($input, 0, 7) === 'SELECT ') {
					$stmt = new DB_query($input,  $dbh);
				}
			}
			if ($stmt) {
				$input = $stmt;
			}
		}
		$vars = (isset($args['vars']) && is_array($args['vars'])) ?  $args['vars'] : array();
		$has_run = false;
		if ($input instanceof DB_query) {
			$input = $input->run($vars);
			$has_run = true;
		}
		if ($input instanceof PDOStatement) {
			$this->_STMNTobj = $input;
			$this->setTable($this->_indexKey);
			if (!$has_run && ($this->_STMNTobj->errorCode() === NULL  || $this->_STMNTobj->errorCode() === '00000')) {
				$this->_STMNTobj->execute($vars);
			}
			$input = $this->tryFetch();
		}
		$this->set_theData($input);
	}

	protected function tryFetch()
	{
		try {
			$r = $this->_STMNTobj->fetchAll(PDO::FETCH_ASSOC);
			$this->clear_err();
			return  $r ?  $r : array();
		} catch (Exception $e) {
			$this->set_err($e);
			return array();
		}
	}

	protected function clear_err()
	{
		$this->error = null;
	}
	protected function set_err($err)
	{
		$this->error = $err;
	}
	function has_err()
	{
		return $this->error || false;
	}

	protected function setTable($fallback_key = false)
	{
		preg_match_all('/(?:FROM +\(?`?)((?!SELECT )[\w$\x{00C0}-\x{00FF}]*)/i', $this->_STMNTobj->queryString, $matches);
		if (!isset($matches[1])) {
			$this->_pKey = $this->_table = false;
			return;
		}
		$this->_table = array_shift(array_filter($matches[1]));
		$this->_pKey = method_exists('pKey', 'getK')  ? pKey::getK($this->_table) : ((is_string($fallback_key)) ? $fallback_key : false);
	}

	protected function set_theData($data = array())
	{
		if (!is_array($data)) {
			$data = array();
		}
		$this->_theData = array_values($data);
		$this->setColumns();
		$this->calcSize();
		$this->resetPointer();
		$this->resetClamp();
	}


	protected function calcSize()
	{
		$this->_theSize	= count($this->_theData);
	}

	protected function setColumns()
	{
		$this->_columns = $this->_theData ? array_keys($this->_theData[0]) : array();
	}

	protected function run_filters($value, $filt = array(), $filtArgs = array())
	{
		foreach ($filt as  $filtName) {
			$filter = 'rm_filt_' . $filtName;
			if (function_exists($filter)) {
				$filt_args 	= (isset($filtArgs[$filtName]) && is_array($filtArgs[$filtName])) ?  												  array_merge($value, $filtArgs[$filtName])  : array($value);
				$value 		= call_user_func_array($filter, $filt_args);
			}
		}
		return $value;
	}

	protected function run_sc($value, $argsForSC = array())
	{
		return (is_scalar($value) && $value) ? apply_sc($value, clone $this, $argsForSC) : $value;
	}

	protected function run_sc_row($row, $col_sc_args = array())
	{
		foreach ($row as $key => $value) {
			$argsForSC = isset($col_sc_args[$key]) ? $col_sc_args[$key] : array();
			if ($argsForSC === null) {
				continue;
			}
			$row[$key] = apply_sc($value, clone $this, $argsForSC);
		}
		return $row;
	}
	protected function getCheckVals($args)
	{
		if (!is_array($args)) {
			return array();
		}
		$arr['cond'] 		= (isset($args['opp'], $args['chk1']) && is_string($args['chk1'])) ? $args['opp'] :  false; // does it have a key and an operand? ( store the operand)
		$arr['chkey']		= $arr['cond'] ? $this->_keyMapCol($args['chk1'], $args['map']) : false;  //then store the key//////////////////
		$arr['check2']		= $arr['chval'] = isset($args['chk2']) ? $args['chk2'] : NULL; // $cheval acts a a buffer of original $check2
		$arr['chkOffset'] 	= (isset($args['chkfst']) && is_scalar($arr['check2']))  ?  $args['chkfst'] : false;
		$arr['chkLoop'] 	= isset($args['chklp']) ?  $args['chklp'] : false;
		$arr['neg']			= isset($args['neg']) 	?  $args['neg'] : false;
		return $arr;
	}
	protected function omit(array $data, array $omit, $map = false)
	{
		foreach ($omit as $o) {
			$o = $this->_keyMapCol($o, $map);
			$ko =  isset($this->_keyMap[$o]) ?  $this->_keyMap[$o] : $o;
			unset($data[$ko]);
		}
		return $data;
	}

	protected function adjustMeta($rstPointer = false, $offset = 0)
	{
		$this->calcSize();
		if ($rstPointer) {
			$this->resetPointer();
		} else {
			$newPointer = ($rstPointer === NULL) ? $this->_pointer : $this->_pointer + $offset;
			if (isset($this->_theData[$newPointer])) {
				$this->_pointer = $this->applyClamp($newPointer);
			}/*moves pointer to*/
		}
	}

	function pointerAt()
	{
		return $this->_pointer;
	}

	function resetPointer($rev = false)
	{
		$this->_pointer 	= $this->applyClamp($rev ? $this->_theSize - 1 : 0);
		$this->_isLooping 	=  false;
		$this->_current 	= isset($this->_theData[$this->_pointer]) ? $this->_theData[$this->_pointer] : null;
	}
	function res($row = 0, $key = null)
	{ /// added for the sake of compatibility with RMCO
		if ($row === null) {
			return $this->_theData;
		}
		if ($key !== null && isset($this->_theData[$row][$key])) {
			return $this->_theData[$row][$key];
		}
		if (isset($this->_theData[$row])) {
			return $this->_theData[$row];
		}
	}

	function dump($i = null, $l = null)
	{
		if ($i !== null) {
			return array_slice($this->_theData, $i, $l);
		}
		return $this->_theData;
	}
	function alter($key, $val, $at = null, $offset = false, $loop = null)
	{  ////
		$index = $this->indexHandler($at, $offset, $loop);
		if (isset($this->_theData[$index][$key])) {
			$this->_theData[$index][$key] = $val;
			if ($this->_indexKey === $key) {
				$this->updateIndex($this->_indexKey, $val);
			}
		}
	}
	function alterRow(array $row, $at = null, $offset = false, $loop = null)
	{  /////
		$index = $this->indexHandler($at, $offset, $loop);
		foreach ($row as $key => $val) {
			if (isset($this->_theData[$index][$key])) {
				$this->_theData[$index][$key] = $val;
				if ($this->_indexKey === $key) {
					$this->updateIndex($this->_indexKey, $val);
				}
			}
		}
	}

	function Q()
	{
		return $this->_STMNTobj ? $this->_STMNTobj->queryString : null;
	}

	function update(array $data = array())
	{
		if ($this->_STMNTobj) {
			$this->_STMNTobj->execute($data);
			$data = $this->tryFetch();
		}
		$this->set_theData($data);
	}

	function mapColsTo($new = false, $mapTo = false)
	{
		if ($new === false) { // no map
			$this->_keyMap_inv = $this->_keyMap = array();
			return;
		}
		if (is_scalar($new)) { //change/set/delete specific key pair
			if ($mapTo === NULL || $mapTo === false) {
				$rev = $this->_keyMap[$new];
				unset($this->_keyMap[$new],  $this->_keyMap_inv[$rev]);
				return;
			} elseif (is_scalar($mapTo)  && in_array($mapTo, $this->_columns)) {
				unset($this->_keyMap_inv[$this->_keyMap[$new]]);
				$this->_keyMap[$new] = $mapTo;
				$this->_keyMap_inv[$mapTo] = $new;
			}
		} elseif (is_array($new)) { //new map from array
			if (is_array($mapTo)) {
				$new = array_combine($new, $mapTo);
			}
			$temp = array_flip($this->_columns);
			foreach ($new as $link => $OK) {
				if (isset($temp[$OK])) {
					$this->_keyMap[$link] = $OK;
					$this->_keyMap_inv[$OK] = $link;
				}
			}
		}
	}



	function _keyMapCol($tag, $ovrride_map = false)
	{
		if (is_array($ovrride_map)) {
			$map =     $ovrride_map;
		} //use temporary override map
		else {
			$map  = ($ovrride_map || !$this->_keyMap) ? false : $this->_keyMap;
		} //use keymap by default or bypass
		if ($map && isset($map[$tag])) {
			$tag = $map[$tag];
		}
		return $tag;
	}

	function _keyMapRow($row, $ovrride_map = false)
	{
		if (is_array($ovrride_map)) {
			$map =     $ovrride_map;
		} else {
			$map  = ($ovrride_map || !$this->_keyMap) ? false : $this->_keyMap;
		}
		if (is_array($map)) {
			foreach ($map as $a => $k) {
				if (isset($row[$k])) {
					$hold = $row[$k];
					unset($row[$k]);
					$row[$a] = $hold;
				}
			}
		}
		return $row;
	}

	function size()
	{
		return $this->_theSize;
	}

	function columns()
	{
		return $this->_columns;
	}

	function the_($key, $bef = '', $aft = '', $args = array())
	{ ///
		if (!is_array($args)) {
			$offset = $args;
			$args = array();
		} else {
			$offset = array_key_exists('offs', $args)  ? $args['offs'] : false;
		}
		$def_row = (array_key_exists('def', $args) && is_array($args['def'])) ? $args['def'] : $this->def_row;
		$fxo = (array_key_exists('fxo', $args) && !$offset) ?  $args['fxo'] : null; //offset as fixed index
		$loop = isset($args['loop']) && $args['loop'] ?  true : false;
		$shortCode = isset($args['sc'])  ? $args['sc'] : null;
		$echo = isset($args['ec']) && $args['ec'] ?   true : false;
		$mapped = isset($args['map'])  ? $args['map'] : ($this->_keyMap ? false : true);
		$filt = (isset($args['filt']) && isset($args['filt']))    ? $args['filt']  : array();
		$filtArgs = (isset($args['fArgs']) && isset($args['fArgs']))    ? $args['fArgs']  : array();
		$bef = isset($args['bef']) ? $args['bef'] : $bef;
		$aft = isset($args['aft']) ? $args['aft'] : $aft;
		$argsForSC = array();
		if (is_array($shortCode)) {
			$argsForSC = $shortCode;
			$shortCode = true;
		}
		$shortCode = ($shortCode !== NULL)  ? $shortCode : $this->apply_sc;  // user varable or (if NULL) instance default

		//$theRow =  $fxo ? $ofst : $this->_pointer+$offset;
		//$theRow =  $this->theLoop($theRow,$loop);
		$theRow = ($fxo !== null) ? $this->indexHandler($fxo, $offset, $loop) : $this->theLoop($this->_pointer + $offset, $loop);
		$key = $this->_keyMapCol($key, $mapped);
		$value = isset($this->_theData[$theRow][$key]) ? $this->_theData[$theRow][$key] : (isset($def_row[$key]) ? $def_row[$key] : NULL);
		if ($bef === null) {
			return $value;
		}
		if (is_scalar($value) && trim($value) !== '') {
			if ($shortCode) {
				$value = $this->run_sc($value, $argsForSC);
			}
			if (trim($bef) !== '' || trim($aft) !== '') {
				$value = $bef . $value . $aft;
			}
		}
		if (is_array($filt) &&  $filt) {
			$value = $this->run_filters($value, $filt, $filtArgs);
		}
		if ($echo) {
			echo $value;
		}
		return $value;
	}

	function prevRow($loop = NULL, $omit = false, $step = 1, $map = false, $sc = false)
	{
		$step = (!$step) ? -1 : abs($step) * -1;
		return $this->stepRow($loop, $omit, $step, $map, $sc);
	}

	function nextRow($loop = NULL, $omit = false, $step = 1, $map = false, $sc = false)
	{
		$step = (!$step) ? 1 : abs($step);
		return $this->stepRow($loop, $omit, $step, $map, $sc);
	}

	function stepRow($loop = NULL, $omit = false, $step = 1, $map = false, $sc = false)
	{
		$loop = ($loop !== NULL)  ? $loop : $this->loop;  // user varable or (if NULL) instance default
		$point = $this->theLoop($this->_pointer + $step, $loop, false);
		if ($this->_current  ||  $this->_theData[$point]) {
			$this->_pointer  = $point;
		}
		$this->_current =  $this->_theData[$point]  ? $this->_theData[$point] : false;
		return $this->thisRow($omit, $map, $sc);
	}

	function getRow($at = null, $offset = false, $loop = NULL, $move = false, $map = false, array $omit = array(), $sc = false)
	{ ////
		/**if ($at === null) {  $at =  $this->_pointer;}
 		$key = ($offset) ?  $this->_pointer + $at : $at;  // if offset-mode, add att to pointer
		$key = $this->theLoop($key,$loop);
		 **/
		$key = $this->indexHandler($at, $offset, $loop);
		if (isset($this->_theData[$key])) {
			if ($move) {
				$this->_pointer = $this->applyClamp($key);
				$this->_current = $this->_theData[$key];
			}

			$ROW = ($omit && is_array($omit)) ?  $this->omit($this->_theData[$key], $omit, $map) : $this->_theData[$key];
			if (is_array($sc)) {
				$ROW = $this->run_sc_row($ROW, $sc);
			}
			$ROW = $this->_keyMapRow($ROW, $map);
			return $ROW;
		}
	}

	function thisRow($omit = false, $map = false, $sc = false)
	{
		$ROW = ($omit && is_array($omit)) ?  $this->omit($this->_current, $omit, $map) : $this->_current;
		if (is_array($sc)) {
			$ROW = $this->run_sc_row($ROW, $sc);
		}
		$ROW = $this->_keyMapRow($ROW, $map);
		return $ROW;
	}

	function theRow($omit = false, $map = false, $step = 1, $loop = null, $sc = false)
	{  /////
		if ($this->_isLooping && $this->_current) {
			if ($step > 0) {
				return $this->nextRow($loop, $omit, $step, $map, $sc);
			}
			if ($step <= 0) {
				return $this->prevRow($loop, $omit, $step, $map, $sc);
			} ////check this line in othe vs
		}
		if (!$this->_current) {
			$this->resetPointer();
		} else {
			$this->_isLooping  =  true;
		}
		return $this->thisRow($omit, $map, $sc);
	}

	function theLoop($key, $loop = null, $peg = true)
	{
		$loop = ($loop !== NULL)  ? $loop : $this->loop;  // user varable or (if NULL) instance default
		$low  =  $this->clamp ?  $this->l : 0;
		$high =  $this->clamp ?  $this->h : $this->_theSize - 1;
		$size =  $high - $low + 1;
		$key = $this->clamp ? ($key < 0  ?  $this->h + $key    : $this->l + $key) : $key;
		if ($key < $low || $key > $high) {
			if (!$loop) {
				if (!$peg) {
					return $key;
				}
				return  $key < $low  ?  $low : $high;
			}
			if ($key < $low) {
				$key = $high + ($key - $low);
			}
			$key = abs($key % $size) + $low;
		}
		return $key;
	}

	function checkRow($line, $cheker = array(), $loop = NULL, $offset = false)
	{  /////
		if (is_integer($line)) { // if it's passed a #, infer it's a row location in _theData
			$line = $this->_theData[$this->indexHandler($line, $offset, $loop)]; //[$this->theLoop($line,$loop)];
		}
		$key = (isset($cheker['k'])) ? $cheker['k'] : $this->check1;
		$val = (isset($cheker['v'])) ? $cheker['v'] : $this->check2;
		$opp = (isset($cheker['o'])) ? $cheker['o'] : $this->opp;
		if (rm_compare($line[$key], $val, $opp)) {
			return ($line & 1);
		}
		return false;
	}

	function findData($col, $val, $Zero = false, $map = false, $rst = true, $opp = "==", $lim = false)
	{
		if (is_array($map)) {
			$col = $this->_keyMapCol($col, $map);
		}
		$this->check1 = $col;
		$this->check2 = $val;
		$this->opp = $opp;
		$finds = array_filter($this->_theData, array("RMLDO", "checkRow"));
		if (count($finds) == 1 && (!$Zero)) {
			$k = array_keys($finds);
			$temp = $finds[$k[0]];
			$finds = $temp;
		}
		if ($lim) {
			$finds = array_slice($finds, 0, $lim);
		}
		if ($rst) {
			$finds = array_merge($finds);
		}
		$this->check1 = $this->check2 = $this->opp = NULL;
		return $finds;
	}
	function output($outFoo, $fooArgs, $args = false, $pointer = NULL,  $xtra = null, $loop = NULL, $output = NULL)
	{
		$relroot = preg_replace("#processors.*#", "", dirname(__FILE__));
		$foo	 = "RM_" . str_replace(" ", "_", $outFoo);
		$meta	 =  $this->theMeta($pointer);
		$pointer = $meta['pointer'];
		if (!function_exists($foo)) {
			try {
				require_once($relroot . "includes/modules/RM_$outFoo.php");
			} catch (Exception $e) {
				return;
			}
		}
		if (is_array($args)) {
			$chkey = $check2 = $cond = $neg = false;
			extract($this->getCheckVals($args));
			$eval = rm_compare($this->the_($chkey), $check2, $cond);
			if (!($eval xor $neg)) {
				return;
			}
		}
		return $foo($this, $this->_theData[$pointer], $fooArgs, $meta, $output, $loop, $xtra);
	}

	function iterate($outFoo, $fooArgs = false, array $args = array(), $move = true, $loop = false, $start = NULL, $end = NULL, $step = 1)
	{
		$step = $step + 0;
		if ($step == 0) {
			$step = 1;
		}
		$chkLoop = $chval = $chkOffset = $mode = $output = $chkey = $check2 = $cond = $neg = null; //default args
		extract($this->getCheckVals($args));
		$OLDpointer = $this->_pointer;
		$start =  (!$start  && $start !== 0) ?  $this->_pointer :  $this->theLoop($start, $loop);
		$end =  (!$end  && $end !== 0) ?  $this->_theSize :   $this->theLoop($end, $loop);
		if ($end < $start) {
			$end = $end + $this->_theSize;
		}
		for ($i = $start; $i < $end; $i += $step) {
			$key = $i % $this->_theSize;
			if ($cond) {
				if ($chval === '____'   && $mode == 'rec') {
					$check2 = $output;
				} elseif ($chkOffset ||  $chkOffset === 0  || $chkOffset === '0') {
					$check2 = $this->the_($chval, '', '', $chkOffset, $chkLoop, false);
				}
				$eval = rm_compare($this->the_($chkey), $check2, $cond);
				if (!($eval xor $neg)) {
					continue;
				}
			}
			if ($move !== null) {
				$this->_pointer = $this->applyClamp($i);
			}
			switch ($mode) {
				case 'rec':
					$output = $this->output($outFoo, $fooArgs, false, $key, null, $output);
					break;
				case 'arr':
					$output[$key] = $this->output($outFoo, $fooArgs, false, $key, null, null);
					break;
				default:
					$output .= $this->output($outFoo, $fooArgs, false, $key, null, null);
			}
		}
		if (!$move) {
			$this->_pointer = $this->applyClamp($OLDpointer);
		}/*resets pointer to*/
		return $output;
	}



	function appendRows(array $data, $before = false, $keep = false)
	{
		$data = array_values($data);
		if (array_keys($data[0]) === $this->_columns) {
			$ct = count($data);
			$keepOffset = ($keep  && $before) ? count($data) : 0;
			if ($ct == 1) {
				$data = $data[0];
			}
			if ($before) {
				array_unshift($this->_theData, $data);
			} else {
				array_push($this->_theData, $data);
			}
			$this->adjustMeta($keep, $keepOffset);
			return  true;
		}
		return   false;
	}

	function removeRows($key, $keep = false)
	{
		if ($key == 'last') {
			$key = $this->_theSize - 1;
		}
		if ($key == 'first') {
			$key = 0;
		}
		if (!is_array($key)) {
			$key = array($key);
		}
		$keepOffset = 0;
		foreach ($key as $k) {
			unset($this->_theData[$k]);
			if ($k < $this->_pointer) {
				$keepOffset--;
			}
		}
		$this->adjustMeta($keep, $keepOffset);
	}


	function isFirst()
	{
		return ($this->_pointer == 0);
	}
	function isLast()
	{
		return ($this->_pointer === $this->_theSize - 1);
	}
	function isSingle()
	{
		return ($this->_theSize === 1);
	}
	function isEmpty()
	{
		return empty($this->_theData);
	}
	function table()
	{
		return $this->_table;
	}
	function showMap()
	{
		return $this->_keyMap;
	}
	function isMappedTo($key, $flipped = false)
	{ /////
		if (!$this->_keyMap && !$flipped && isset($this->_theData[0][$key])) {
			return $key;
		}
		if (is_string($key) && (($flipped && isset($this->_keyMap_inv[$key]))  || (!$flipped && isset($this->_keyMap[$key])))) {
			return  $flipped ? $this->_keyMap_inv[$key] : $this->_keyMap[$key];
		}
		return  false;
	}
	function hasAlias($alias)
	{
		return isset($this->_keyMap[$alias]);
	}
	function hasCol($col)
	{
		return isset($this->_columns[$col]);
	}
	function getCol($col, $start = 0, $end = 0, array $args = array())
	{
		$theCol = array();
		$uniq = isset($args['uniq']) ? $args['uniq'] : false;
		$rdy = isset($args['rdy']) ? $args['rdy'] : false;
		$map = isset($args['map'])  ? $args['map'] : ($this->_keyMap ? false : true); ////////////////
		$col = $this->_keyMapCol($col, $map); /////////////////////////////
		if (!$col) {
			return array();
		}
		$end = $this->_theSize - abs($end);
		$start = $start < 0 ? 0 : $start;
		for ($i = $start; $i < $end; $i++) {
			if (isset($this->_theData[$i][$col])) {
				$theCol[] = $this->_theData[$i][$col];
			}
		}
		if ($uniq) {
			$theCol = array_unique($theCol);
		}
		if ($rdy) {
			foreach ($theCol as  $v) {
				$new[][$col] = $v;
			}
			$theCol = $new;
		}
		return $theCol;
	}
	function hLev($h = null)
	{
		if ($h === null) {
			return  $this->_hlev;
		}
		$h = ($h > 5) ? 5 : $h;
		$h = ($h < 0) ? 0 : $h;
		$this->_hlev = $h;
	}

	function deleteCol($col)
	{
		$col = isset($this->_keyMap[$col]) ? $this->_keyMap[$col] : $col;
		for ($i = 0; $i < $this->_theSize; $i++) {
			unset($this->_theData[$i][$col]);
		}
		$colk = array_search($col, $this->_keyMap);
		unset($this->_columns[$colk]);
		$aliases = array_keys($this->_keyMap, $col);
		foreach ($aliases as $alias) {
			unset($this->_keyMap[$alias]);
		}
	}
	function appendCol($col, $data, $nColOffset = 0, $tDtaOffset = 0, $loop = null, $fill = false, $plc1 = false, $plc2 = false)
	{
		$loop = ($loop !== NULL)  ? $loop : $this->loop;  // user varable or (if NULL) instance default
		$data = array_values($data);
		if ($plc1 || $plc1 === 0) {
			$data = ($plc2 || $plc2 === 0)  ?  array_splice($inputm, $plc1) : array_splice($input, $plc1, $plc2);
		}
		$nColOffset -= $tDtaOffset;
		$dataSize = count($data);
		for ($i = 0; $i < $this->_theSize; $i++) {
			if (!$loop) {
				$v =  ($i < ($dataSize + $tDtaOffset) && ($i >=  $tDtaOffset  &&  $i <= $this->_theSize + $tDtaOffset)) ?  ($i + (($nColOffset) + $dataSize)) % $dataSize : $fill;
			} else {
				$v =  ($i + (($nColOffset) + $dataSize)) % $dataSize;
			}
			$this->_theData[$i][$col] = $data[$v];
		}
		$this->_columns[] = $col;
	}
	function asTable($hed = false, $omit = false, array $args = array(), array $cfoos = array())
	{
		foreach (array('thb', 'tha', 'futh') as $varkey) {
			$$varkey = isset($args[$varkey]) ?  str_replace(array('<td ', '</td>'), array('<th ', '</th>'), $args[$varkey]) : '';
		}
		$fut 	= isset($args['fut']) 		? $args['fut'] 		: '';
		$sideH 	= isset($args['sideh']) 	? $args['sideh'] 	: false;
		$alias 	= isset($args['alias']) 	? $args['alias'] 	: false;
		$thClass = isset($args['class']) 	? 'class="' . $args['class'] . '" ' 	: false;
		$output = '';
		if ($hed || $thb) {
			$output .= "\t<thead $thClass>\n$thb\t\t<tr>\n";
			if ($hed) {
				if (!is_array($hed)) {
					if ($alias) {
						$heds = array_unique($this->_columns + array_values(array_unique($this->_keyMap)));
					} else {
						$heds = $this->_columns;
					}
				}
				foreach ($heds as $th) {
					if (is_array($omit) && in_array($th, $omit)) {
						continue;
					}
					$output .= "\t\t\t<th>$th</th>\n";
				}
			}
			$output .= "\t\t</tr>\n$tha\t</thead>\n";
		}
		if ($fut || $futh) {
			$output .= "<tfoot>$fut$futh</tfoot>";
		}
		$output .= $hed ? "\t<tbody>\n" : '';
		foreach ($this->_theData as $theRow) {
			$output .= "\t\t<tr>\n";
			$cTag =  ($sideH !== false && $sideH !== null && trim($sideH) !== '')  ? 'th' : 'td';
			foreach ($theRow as $cellKey => $theCell) {
				if (is_array($omit) && in_array($cellKey, $omit)) {
					continue;
				}
				if (isset($cfoos[$cellKey]) && function_exists($cfoos[$cellKey])) {
					$theCell = $cfoos[$cellKey]($theCell);
				}
				$output .= "\t\t\t<$cTag>$theCell</$cTag>\n";
				if ($cellKey === $sideH) {
					$cTag = 'td';
				}
			}
			$output .= "\t\t</tr>\n";
		}
		$output .= $hed ? "\t</tbody>\n" : '';
		return $output;
	}
	function theMeta($pointer = NULL, $loop = NULL)
	{
		$pointer = ($pointer === NULL) ? $this->_pointer : $this->theLoop($pointer, $loop);
		$loop = ($loop !== NULL)  ? $loop : $this->loop;  // user varable or (if NULL) instance default
		return array('pointer' => $pointer, 'isFirst' => ($pointer === 0), 'isLast' => ($pointer === $this->_theSize - 1), 'size' => $this->_theSize, 'isOnly' => ($this->_theSize === 1), 'isEmpty' => ($this->_theSize  < 1), 'inNull' => (!$this->_theData), 'curr' => $this->_current, 'indnt' => $this->indent);
	}

	function tableKey()
	{
		return $this->_pKey;
	}

	function setApply_sc($value = APPLY_SC)
	{
		$this->apply_sc = $value ? true : false;
	}

	function currentRow()
	{
		return $this->_current;
	}

	function rawGet($row = null, $col = null, $map = false, $args = array())
	{  ////???
		//if ($row === null) { $row == $this->_pointer ;}
		if (($row !== null && !is_scalar($row)) || ($col !== null && !is_scalar($col))) {
			return;
		}
		$loop 	 = isset($args['loop'])   ? $args['loop'] : null;
		$offset = array_key_exists('off', $args)  ? $args['off'] : false;
		$rowDefault = isset($args['def']) ? $args['def'] : null;
		$row = $this->indexHandler($row, $offset, $loop, array('err' => -2)); // calculates _theData index or returns an index that will evaluate  to false ( so as to return $rowDefault)
		if ($col === null || $col === false || $col ==  '') {
			return (isset($this->_theData[$row])) ? $this->_theData[$row] : $rowDefault;
		}
		if ($map) {
			$col = $this->_keyMapCol($col, $map);
		}
		return (isset($this->_theData[$row][$col])) ? $this->_theData[$row][$col] : $rowDefault;
	}

	function editRow(array $data, $row = null, $ovrride_map = null, array $args = array())
	{ /////
		$loop 	 = isset($args['loop'])   ? $args['loop'] : null;
		$offset = array_key_exists('off', $args)  ? $args['off'] : false;
		// if ($row === null || $row === false){   $row = $this->_pointer;}
		$row = $this->indexHandler($row, $offset, $loop, array('err' => -2)); // calculates _theData index or returns an index that will evaluate  to false ( so as to return $rowDefault)
		if (!isset($this->_theData[$row])) {
			return;
		}
		$oldRow = $this->_theData[$row];
		$toChange = array_intersect_key($data, $oldRow);
		foreach ($toChange as $key => $val) {
			if ($ovrride_map !== null) {
				$key = $this->_keyMapCol($key, $ovrride_map);
			} ////////
			$this->_theData[$row][$key] = $val;
		}
	}

	function editField($data, $key, $row = null, $preserve = true, $ovrride_map = null)
	{
		if ($ovrride_map !== null) {
			$key = $this->_keyMapCol($key, $ovrride_map);
		} ////////
		if ($row === null || $row === false) {
			$row = $this->_pointer;
		}
		if ($preserve && !isset($this->_theData[$row][$key])) {
			return;
		}
		$this->_theData[$row][$key] = $data;
	}


	//// RMCO graft
	function foundData($col, $val, $opp = "=", $ovrride_map = false)
	{
		$hold1 = $this->check1;
		$hold2 = $this->check2;
		$hold3 = $this->opp;
		$x = $this->findData($col, $val, array('z' => true,  'opp' => $opp, 'map' => $ovrride_map));
		$this->check1 = $hold1;
		$this->check2 = $hold2;
		$this->opp = $hold3;
		return new FD($x);
	}
	/// RMCO aliases
	function countIsOn()
	{
		return $this->pointerAt();
	}
	function getTable()
	{
		return $this->table();
	}

	//deprecate
	function SELECTstmt(array $userSetting = array())
	{
		$commands = array('S' => ' SELECT ', 'F' => ' FROM ', 'J' => ' JOIN ', 'W' => ' WHERE ', 'G' => ' GROUP BY ', 'H' => ' HAVING ', 'O' => ' ORDER BY ', 'L' => ' LIMIT ', 'U' => ' UNION ');
		$defaults = array('S' => '*', 'F' => '`Categories`', 'J' => '', 'W' => '', 'G' => '', 'H' => '', 'O' => '', 'L' => '', 'U' => '');
		$SQL = '';
		foreach ($defaults as $key => $default) {
			if (isset($userSetting[$key])) {
				$default = $userSetting[$key];
			}
			if ($default) {
				if ($key == 'G' || $key == 'O' || $key == 'L') {
					if (is_array($default)) {
						$default =  array_values($default);
						if (count($default) < 2) {
							$default = $default[0];
						} else {
							if ($key == 'L') {
								$default = ' ' . $default[0] . ', ' . $default[1] . ' ';
							} else {
								$default[1] = trim($default[1]);
								if ($default[1] !== 'ASC' && $default[1] !== 'DESC') {
									$default[1] = $default[1] ? 'ASC' : 'DESC';
								}
								$default = ' ' . $default[0] . ' ' . $default[1] . ' ';
							}
						}
					}
				}
				$SQL .= $commands[$key] . $default;
			}
		}
		return  $SQL;
	}

	//// Added Aug. 2020
	function retrieve_row($at, array $addTo = array(),  array $aux_args = array())
	{  ////////////9-20
		$loop 	= isset($aux_args['loop'])  					?  $aux_args['loop'] : null;
		$offset = array_key_exists('off', $aux_args)			?  $aux_args['off']	 : false;
		$only 	= isset($aux_args['only']) && $aux_args['only'] ?  true : false;
		$no_sc 	= isset($aux_args['nosc']) && $aux_args['nosc'] ?  true : false;
		$map_to = isset($aux_args['map'])  && $aux_args['map']  ?  true : false;
		$method = (isset($aux_args['ndx']) && $aux_args['ndx'])	?  null	: $offset;
		//$key = ($offset || $at === null) ?  $this->_pointer + $at :  $at ;
		$key = $this->indexHandler($at, $method, $loop); ////$this->theLoop($key,$loop);
		if (!isset($this->_theData[$key])) {
			return array();
		}
		$loopThrough =  $only ? $addTo : $this->_theData[$key];
		if (!$no_sc && !$only) {
			return $loopThrough;
		}
		$ROW = array();
		foreach ($loopThrough as $col => $val) {
			$col = $this->_keyMapCol($col, $map_to);
			if (isset($this->_theData[$key][$col])) {
				if (isset($addTo[$col]['false']) && $ROW[$col] === false) {
					$ROW[$col] = $addTo[$col]['false'];
				}
				if (isset($addTo[$col]['true'])  && $ROW[$col] === true) {
					$ROW[$col] = $addTo[$col]['true'];
				}
				if (is_scalar($ROW[$col]) && trim($ROW[$col]) !== '') {
					$sc_args = isset($addTo[$col]['sca']) ?  $addTo[$col]['sca'] : array();
					$ROW[$col] = $no_sc ? $this->_theData[$key][$col] : $this->run_sc($this->_theData[$key][$col], $sc_args);
					if (isset($addTo[$col]['aft'])) {
						$ROW[$col] =  $ROW[$col] . $addTo[$col]['aft'];
					}
					if (isset($addTo[$col]['bef'])) {
						$ROW[$col] =  $addTo[$col]['bef'] . $ROW[$col];
					}
				}
				if (isset($addTo[$col]['filt']) && is_array($addTo[$col]['filt'])) {
					$filtArgs = (isset($addTo[$col]['flta']) && is_array($addTo[$col]['flta'])) ? $addTo[$col]['flta'] : array();
					$ROW[$col]  = $this->run_filters($ROW[$col], $addTo[$col]['filt'], $filtArgs);
				}
			}
		}
		return $ROW;
	}

	// added sept 2020
	protected function mapIndex()
	{
		if ((!$this->_indexKey === '0' && !$this->_indexKey) || !$this->_theData || !array_key_exists($this->_indexKey, $this->_theData[0])) {
			return;
		}
		$this->_indexMap =  array_flip(array_column($this->_theData, $this->_indexKey));
	}

	function setIndexKey($key = null)
	{
		if (!is_scalar($key)) {
			$key =  $this->_pKey ? $this->_pKey :  null;
		}
		$this->_indexKey = $key . '';
		if ($this->_indexKey === '0' || $this->_indexKey) {
			$this->mapIndex();
		}
	}

	function getIndexKey()
	{
		return $this->_indexKey;
	}

	function jumpTo($i)
	{
		$i = $this->indexPos($i);
		if ($i === null) {
			return false;
		}
		$this->_pointer = $this->applyClamp($i);
		return  true;
	}
	function indexPos($i, $err = null)
	{
		if ($i ===  null || ($this->_indexKey !== '0' && !$this->_indexKey) || !isset($this->_indexMap[$i])) {
			return $err;
		}
		return $this->_indexMap[$i];
	}
	protected function updateIndex($old, $new)
	{
		if ($old == $new  || !isset($this->_indexMap[$old])) {
			return;
		}
		$this->_indexMap[$new] = $this->_indexMap[$old];
		unset($this->_indexMap[$old]);
		return true;
	}

	function indexHandler($i =  null, $offset = false, $loop = null, array $args = array())
	{
		$set = isset($args['set']) && $args['set'] ? true : false;
		if ($offset) {
			$i = $this->theLoop($i + $this->_pointer, $loop);
		} elseif ($offset === null) {
			$ik = $this->indexPos($i);
			$i = (($this->_indexKey === '0' || $this->_indexKey) &&  $ik !== null && isset($this->_theData[$ik])) ? $ik : -1;
		} else {
			$i = ($i === null || $i === false) ? $this->_pointer  : $i + 0;
			$i = isset($this->_theData[$i]) ? $i : (isset($args['err']) ? $args['err'] :  $this->_pointer);
		}
		if ($set && (!isset($args['err']) || $i !== $args['err'])) {
			$this->_pointer = $this->applyClamp($i);
		}
		return $i;
	}

	//july 2021
	function setClamp($l = 0, $h = 0, $apply = true)
	{
		if ($h <= 0) {
			$h = $this->_theSize + $h - 1;
			$h = $h < 0 ? 0 : $h;
		}
		if ($h >= $this->_theSize) {
			$h = $this->_theSize - 1;
		}
		if ($l < 0) {
			$l = 0;
		}
		if ($l >= $this->_theSize) {
			$l = $this->_theSize - 1;
		}
		if ($l > $h) {
			$temp = $l;
			$l = $h;
			$h = $temp;
		}
		$this->l = $l;
		$this->h = $h;
		$this->clamp = $apply ? true : false;
		if ($this->clamp) {
			$this->_pointer = $this->applyClamp($this->_pointer);
		}
	}
	function toggleClamp()
	{
		$this->clamp = !$this->clamp;
	}
	function unclamp()
	{
		$this->clamp = false;
	}
	function clamp()
	{
		$this->clamp = true;
	}
	function resetClamp()
	{
		$this->clamp = false;
		$this->l = 0;
		$this->h = $this->_theSize - 1;
	}
	protected function applyClamp($pointer)
	{
		if ($this->clamp) {
			if ($pointer < $this->l) {
				return $this->l;
			}
			if ($pointer > $this->h) {
				return $this->h;
			}
		}
		return $pointer;
	}
}

class  FD extends RMLDO
{
	function __construct($t)
	{
		$this->_theData = $t;
		$this->size(true);
	}
}
