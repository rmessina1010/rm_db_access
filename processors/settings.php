<?
error_reporting  (E_ERROR); 
ini_set ("display_errors", true); 
ini_set ("mysql.connect_timeout", 1200); 
ini_set ("default_socket_timeout", 1200); 

define('DB_TYP_DEF','mysql');		//Default DB type.. new as of 5.0
define('SCHEME', isset($_SERVER['HTTPS']) ? 'https://' : 'http://');
define('DBNAME','dbname');
define('HOST','localhost');
define('UPASS','userpassword');
define('UACC','username');

define ('APPLY_SC', false);
define('ADMIN_MAIL','email@email.com');// Your Email address
define('ERR_404','_404.php');// It's handy to define ERRs as Constants
define('MERID', 0); // number of hours  from chief admin time  to server hostime
define('T_OFFSET',MERID*3600);
define('SHORTCODES', 'RM_shortcodes.php');
?>
