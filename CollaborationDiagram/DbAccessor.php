<?
/**
 * Data access objects. 
 * Here areall objects that take data from database
 */
# Alert the user that this is not a valid entry point to MediaWiki if they try to access the special pages file directly.
if (!defined('MEDIAWIKI')) {
  echo <<<EOT
To install my extension, put the following line in LocalSettings.php:
  require_once( "\$IP/extensions/CollaborationDiagram/CollaborationDiagram.php" );
EOT;
  exit( 1 );
}
include "Page.php";

class DbAccessor {
  private static $instance;
  private function __construct() { /* ... */ }
  private function __clone() { /* ... */ }
  public static function getInstance() {
    if (self::$instance === null)
      $instance = new self();
    return $instance;
  }

  /**
   * \brief This function gets list of Users
   *  that edited current page from database
   *  
   *  The function returns such array as
   *     MediaWiki default;  194.85.163.147; Ganqqwerty;  Ganqqwerty ; Ganqqwerty;  Ganqqwerty;
   *     Ganqqwerty; 92.62.62.48; Cheshirig; Cheshirig
   */
  public function getPageEditorsFromDb($pageTitle) {
    $dbr =& wfGetDB( DB_SLAVE );

    $tbl_pag =  'page';
    $tbl_rev = 'revision';

    $sql = "
      SELECT
      rev_user_text
      FROM $tbl_pag
      INNER JOIN $tbl_rev on $tbl_pag.page_id=$tbl_rev.rev_page
      WHERE
      page_title=\"$pageTitle\";
    ";

    $rawUsers = $dbr->query($sql);
    $userList=array();
    foreach ($rawUsers as $row) {
      array_push($userList, $row->rev_user_text);
    }

    $contributions= $this->getCountsOfEditing($userList);

    $page = new Page($pageTitle,$contributions);
    return $page;
  }

  /*!
   * \brief Function that evaluate hom much time each user edited the page
   * \return array : username -> how much time edited
   */
  private function getCountsOfEditing($names) {

    $changesForUsers = array();//an array where we'll store how much time each user edited the page
    foreach ($names as $curName)
    {
      if (!isset($changesForUsers[$curName]))
	$changesForUsers[$curName]=1;
      else
	$changesForUsers[$curName]++;
    }
    return $changesForUsers;
  }
}
