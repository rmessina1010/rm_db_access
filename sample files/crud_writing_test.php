/**
$db = new DB("RMCMS","root" ,"root" );
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
	
}else{ echo("no connection!");}
**/
 /**echo sql_str('r','some_table', array('w'=>'`x`= 2', 'l'=>2, 'c'=>array('col1'=>1,'id_col'=>2,'attrib'=>3,'main'=>4) , 'v'=>true, 'j'=>'a_table ON y=x') );
	 **/
	echo sql_R_str('my_table','*',null,'col1 DESC',5);