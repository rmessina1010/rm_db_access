 <?
   include( 'processors/RMLDO.php');
   require_once ( 'processors/RMLDB.php');
   echo 'MMMMM!!!<HR>';
   $test = new RMLDO('SELECT * FROM `GLists` WHERE `GLID` = :?  AND `GLOwner` = :?', array('vars' => array(1, 1)));
   $datax[] = array('x' => 1, 'y' => true, 'z' => 'some string', 'a' => array(1, 2, 3));
   $datax[] = array('x' => 2, 'y' => false, 'z' => 's string', 'a' => array(10, 5, 3));
   $datax[] = array('x' => 3, 'y' => true, 'z' => 'some string', 'a' => array(11, 2, 33));
   $datax[] = array('x' => 4, 'y' => false, 'z' => 'some string', 'a' => array(1, 2, 3));
   $datax[] = array('x' => 5, 'y' => true, 'z' => '  string', 'a' => array(1, 2, 3));
   
   $DB = new DB(DBNAME, UACC,  UPASS,  HOST);
   $DB = $DB->connect();
   $DBQ = new DB_query('SELECT * FROM `GLists` WHERE `GLID` = :?  AND `GLOwner` = :?', $DB);
   // $DBQ->run( array(1, 1));
   // $test4 = new RMLDO($DBQ, array('vars' => array(2, 1)));
   $test4 = new RMLDO($DBQ, array('vars' => array(2, 1)));
   $test5 = new RMLDO($DBQ, array('vars' => array(1, 1)));
   $test4->refresh();
   
   var_dump($test4);
   echo "<br><br>";
   var_dump($test5);
   echo "<h3> test4 ^^^</h3>";


	$dbh= new PDO("mysql:host=".HOST.";dbname=".DBNAME, UACC, UPASS,array(
            PDO::ATTR_DEFAULT_FETCH_MODE =>PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'
        ));
        
    $PDO = $dbh->prepare('SELECT * FROM `GLists` WHERE `GLID` = ?  AND `GLOwner` = ?', [PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY]);
    
    
    //TODO tweak: so that executed PDO can run
    // $PDO->execute( array(1,1));
    // $test3 = new RMLDO($PDO, array('vars' => array(1, 1)));
    $test3 = new RMLDO($PDO, array('vars' => array(1, 1)));

   var_dump($test3);
   echo "<h3> test3 ^^^</h3>";
   var_dump($test);
   echo "<hr/>";
   $test2 = new RMLDO($datax);
   var_dump($test->thisRow(), $test->thisRow());
   echo "<hr/>";
   echo $test2->the_('z', '<b>', '</b>', array('offs' => -1, 'loop' => true));

   //added deafult connection object
   //fixed errorCode === '' value (5b)
   // moved settings

   ?>