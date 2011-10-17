<?php
# Alert the user that this is not a valid entry point to MediaWiki if they try to access the special pages file directly.
if (!defined('MEDIAWIKI')) {
  echo <<<EOT
To install my extension, put the following line in LocalSettings.php:
  require_once( "\$IP/extensions/CollaborationDiagram/CollaborationDiagram.php" );
EOT;
  exit( 1 );
}

function getCategoryPagesFromDb($categoryName)
{
  $dbr =& wfGetDB( DB_SLAVE );
  $categoryName = mysql_real_escape_string($categoryName);
  $queryResult = $dbr->select("categorylinks","cl_from","cl_to=\"$categoryName\"");

  $result = array();
  while ($row = $queryResult->fetchRow()) {
      $title = Title::newFromID($row['cl_from']);
      
      array_push($result,$title);
  }
    return $result;
}


class CDParameters {
  private $skin;
  private $pagesList = array(); //! list of Title objects for pages that collaboration diagram should be shown for
  private $diagramType;

  static private $instance = NULL;
/**
 * @static Singleton pattern function
 * @return object of CDParameter
 */
  public static function getInstance() {
    if (self::$instance == NULL) {
      self::$instance = new CDParameters();
    }
    return self::$instance;
  }

  private function __construct() {
  }

  private function __clone() {
  }

  /**
   * Read the parameters of collaborationdiagram tag and fill the pagesList
   * @param array $args arguments of the colaborationdia tag. For example in <collaborationdia page="ew" />
   * the page is an argument.
   * @return void return tohing
   */
  public function setup(array $args) {
      //FIXME везде тут в pagesList должен подсовываться объект Title
    global $wgCollaborationDiagramSkinFilename, $wgTitle;
    //if user asks for collaborationdia for the current page:
    if (!isset($args["page"])     &&
        !isset($args['category']))
    {
      $this->pagesList = array($wgTitle);
    }

    if  (isset($args["page"]))
    {
      $this->pagesList = array();
      $pageNames = explode(";",$args["page"]);
      foreach ($pageNames as $pageName) {
          array_push($this->pagesList, Title::newFromText($pageName));
      }

    }

    if (isset($args["category"]))
    {
      $this->pagesList = getCategoryPagesFromDb($args["category"]);//I need to dig here
    }

    $this->skin = 'default.dot';
    if (isset($wgCollaborationDiagramSkinFilename))
    {
      $this->skin = $wgCollaborationDiagramSkinFilename;
    }

    $this->diagramType = 'dot';
    if (isset($args['type']))
    {
      $this->diagramType= $args['type'];
    }
  }


  public function getSkin() {
    return $this->skin;        

  }

  public function getPagesList() {
    return $this->pagesList;
  }

  public function getDiagramType() {
    return $this->diagramType; 
  }
}
