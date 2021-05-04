 <?
  include('settings.php');
  include('basic db-4.php');
  include('RMLDO.php');
  echo 'MMMMM!!!<HR>';
  $test = new RMLDO('SELECT * FROM Categories WHERE CatPub =? and Parent = :?', array('vars' => array(1, 0)));
  $datax[] = array('x' => 1, 'y' => true, 'z' => 'some string', 'a' => array(1, 2, 3));
  $datax[] = array('x' => 2, 'y' => false, 'z' => 's string', 'a' => array(10, 5, 3));
  $datax[] = array('x' => 3, 'y' => true, 'z' => 'some string', 'a' => array(11, 2, 33));
  $datax[] = array('x' => 4, 'y' => false, 'z' => 'some string', 'a' => array(1, 2, 3));
  $datax[] = array('x' => 5, 'y' => true, 'z' => '  string', 'a' => array(1, 2, 3));
  $test2 = new RMLDO($datax);
  var_dump($test->thisRow(), $test->thisRow());
  echo $test2->the_('z', '<b>', '</b>', array('offs' => -1, 'loop' => true));

  //added deafult connection object
  //fixed errorCode === '' value (5b)
  // moved settings

  ?>