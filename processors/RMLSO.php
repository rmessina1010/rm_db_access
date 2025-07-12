<?
require_once 'RMLDB.php';

class RMSO
{

	protected  	$mode	= 'r';
	protected  	$MOs	= array('c', 'r', 'u', 'd', 'p');
	protected  	$SQL	= '';
	protected  	$OBJ	= null;
	protected  	$conn	= null;
	protected  	$cname	= '_default';
	protected  	$data 	= array();


	function __construct($table, $cols = "*", $wher = false, $o = false, $args = array())
	{
		$this->conn = (isset($args['conn'])) ? $args['conn'] : DBNAME;
		$this->cname = (isset($args['cname'])) ? $args['cname'] : '_default';
		if ($table) {
			if ($cols && is_string($cols)) {
				$args['c'] = $cols;
			}
			if ($wher && is_string($wher)) {
				$args['w'] = $wher;
			}
			if ($args['lim'] && $args['lim'] > 0) {
				$args['l'] = $args['lim'];
			}
			if ($o && is_string($o)) {
				$args['o'] = $o;
			}
			if (isset($args['mode'])) {
				$this->SetMode($args['mode']);
				unset($args['mode']);
			}
			unset($args['conn']);
			if (isset($args['data']) && is_array($args['data'])) {
				$this->data = $args['data'];
				unset($args['data']);
			}
		} else {
			$this->data = is_array($wher) ? $wher : array();
		}
		$this->SQL = $table ? sql_str($this->mode, $table, $args) : $cols;
		$this->OBJ = new  DB_query($this->SQL, DB_hub::connect($this->conn, $this->cname), $this->data);
	}

	function SetMode($m)
	{
		if (!is_string($m)) {
			return;
		}
		$m = strtolower($m);
		if (in_array($m, $this->MOs)) {
			$this->mode = $m;
		}
	}

	function _doQ($data = null)
	{
		$data = $data === null ? $this->data :  (is_array($data) ? $data : array());
		return $this->OBJ->run($data);
	}
	function STMNT()
	{
		return $this->OBJ->STMNT();
	}
}

function doQ($q, $data = null, $ret = false, $cnn = DBNAME, $cname = '_default')
{
	$log = array();  														// intialize error log
	$prepped = false;
	if (!is_array($q)) {
		$Q[0] = $q;
	} else {
		$Q = $q;
	}																	// initialize array of queries, $Q
	$so = new  DB_query($Q[0], DB_hub::connect($cnn, $cname));   			//set up stament object
	//$dbh=$so->dbh();													//set up DB handle;
	//if ($trns){$dbh->beginTransaction();}								//intitiale transaction IF requested by user;
	$dataArray = is_array($data);


	if (count($Q) === 1) { //special case for when there is only one query to perform
		if ($dataArray) {
			$hasSubArr = array_filter($data, 'is_array');
			if (!$hasSubArr) {
				$data = array(key($Q) => array($data));
			} elseif (!is_array($hasSubArr)) {
				$data = array(key($Q) => $data);
			} // double nsested
		} else {
			$data = array(array(array($data)));
		}
		$prepped = true;
	}


	foreach ($Q as $key => $qr) {
		$so->prep($qr);
		$stmt = $so->STMNT();

		if ($prepped) {
			$dataShell = $data;
		} elseif ($dataArray && isset($data[$key])) {
			$dataShell =  (is_array($data[$key]) && array_filter($data[$key], 'is_array')) ?  $data[$key] : array($data[$key]);
		} else {
			$dataShell = array($data);
		}


		foreach ($dataShell as $dataRow) {
			$dataLines = (is_array($dataRow) && array_filter($dataRow, 'is_array')) ?  $dataRow : array($dataRow);
			foreach ($dataLines as  $vars) {
				try {
					$ovar = $vars;
					if (!is_array($vars)) {
						$vars = array();
					}
					$flg = $stmt->execute($vars);
					$err = ($flg === false) ?   'could not do : ' . $qr . ' [ data line: ' . implode(',', $vars) . ']' : $stmt->rowCount();
					$log[] = array($qr, $ovar, $err, NULL);
					if ($flg === false) {
						//if ($trns){$dbh->rollBack(); }
						return ($ret) ?  $log : $err;
					}
				} catch (PDOException $e) {
					$err = 'could not do : ' . $qr . ' [ data line: ' . implode(',', $vars) . ']';
					$log[] = array($qr, $ovar, $err, $e->getMessage());
					echo $e->getMessage();
					//if ($trns){$dbh->rollBack(); }
					return ($ret) ?  $log : $err;
				}
			}
		}
	}
	//if ($trns) {$dbh->commit();}
	return ($ret) ?  $log : false;
}
