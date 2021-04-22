
 if($h=DB_hub::store(new DB("RMCMS","root" ,"root" ))){
  	$qry_str 	="SELECT * FROM Categories WHERE CatOrd = :ord AND Parent = :pid" ;
  	$qry_str2 	="SELECT * FROM Entries WHERE   Category = :pid" ;
	$cat_list 	= new DB_query($qry_str,  $h->connect());
	$cat_list2 	= new DB_query($qry_str2, $h->connect());
	$ord=0;
	$data = $cat_list->bind_param($ord,'ord')->bind_val(0,'pid')->run()->fetchAll();
	$data2 = $cat_list2->bind_val(0,'pid') ;
	var_dump($data2->STMNT(),'<hr>');
	$data2 = $data2->run() ;
	var_dump($data2->errorCode(),'<hr>');
	$data2 = $data2->fetchAll();
 	var_dump($data);
 	var_dump($data2);
	
}else{ echo("no connection!");}

 echo sql_str('r','some_table', array('w'=>'`x`= 2', 'l'=>2, 'c'=>array('col1'=>1,'id_col'=>2,'attrib'=>3,'main'=>4) , 'v'=>true, 'j'=>'a_table ON y=x') );
 
	echo sql_R_str('my_table','*',null,'col1 DESC',5);