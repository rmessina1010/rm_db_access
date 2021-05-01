 <?
 include ('settings.php'); 
 include ('basic db-4.php'); 
  include ('RMLDO.php'); 
   ECHO 'MMMMM!!!<HR>';
 $test = new RMLDO( 'SELECT * FROM Categories WHERE CatPub =? and Parent = :?',array ('dbh'=> $h->connect(),'vars'=>array(1,0)));
  var_dump($test->dump());
 ?>