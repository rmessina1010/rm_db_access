<?
	include('../RM_basic_DB.php');
	/**
	 testing obeject methods and props
	**/

	$db = new DB("SAMPLE_DB","user" ,"pass" );
	if($db){
	  	$qry_str 	="SELECT * FROM Categories WHERE CatOrd = :ord AND Parent = :pid" ;
	  	$qry_str2 	="SELECT * FROM Entries WHERE   Category = :pid" ;
		$cat_list 	= new DB_query($qry_str,$db->connect());
		$cat_list2 	= new DB_query($qry_str2,$db->connect());
		$ord=0;
		$data = $cat_list->bind_param($ord,'ord')->bind_val(0,'pid')->run()->fetchAll();
		$data2 = $cat_list2->bind_val(0,'pid')->run()->fetchAll();
	 	var_dump($data);
	 	var_dump($data2);

	}else{
		echo("no connection!");
	}
?>