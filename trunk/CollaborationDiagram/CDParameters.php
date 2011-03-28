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
  global $wgDBprefix;
  //and go!
  $dbr =& wfGetDB( DB_SLAVE );
  $tbl_categoryLinks = $wgDBprefix.'categorylinks';
  $pageTable = $wgDBprefix.'page';
  $categoryName = mysql_real_escape_string($categoryName);
  $sql = "
    SELECT
    page_title
    from $tbl_categoryLinks 
    LEFT JOIN $pageTable on $tbl_categoryLinks.cl_from=$pageTable.page_id
    WHERE cl_to=\"$categoryName\";
  ";
  $res = $dbr->query($sql);

  $result = array();
  //formatting output array of names:
  foreach ($res as $row)
  {
    array_push($result, $row->page_title);
  }
  return $result;
}


class CDParameters {
  private $skin;
  private $pagesList;
  private $category;
  private $diagramType;

  static private $instance = NULL;

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

  public function setup(array $args) {
    global $wgRequest, $wgCollaborationDiagramSkinFilename, $wgOut;
    $this->pagesList = array();
    if (!isset($args["page"])&&!isset($args['category']))
    {
      $this->pagesList = array($wgRequest->getText('title'));
    }

    if  (isset($args["page"]))
    {
      $this->pagesList = explode(";",$args["page"]);
    }

    if (isset($args["category"]))
    {
      $pagesFromCategory = array();
      $pagesFromCategory = getCategoryPagesFromDb($args["category"]);
      $this->pagesList = array_merge($this->pagesList, $pagesFromCategory) ;
      $this->category=$args['category'];//XXX
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
  public function getCategory() {
    return $this->category;    
  }
  public function getDiagramType() {
    return $this->diagramType; 
  }
}
