<?
/**
 * Object that store information needed for diagrams
 */
if (!defined('MEDIAWIKI')) {
  echo <<<EOT
To install my extension, put the following line in LocalSettings.php:
  require_once( "\$IP/extensions/CollaborationDiagram/CollaborationDiagram.php" );
EOT;
  exit( 1 );
}
/**
 * Class that contains info about wikipage and it contributors
 */
class Page {
  private $title; /// page title
  private $contributions = array(); /// map from username to his contribution
  public function __construct($pageTitle, $contributions0)
  {
    $this->title = $pageTitle;
    $this->contributions = $contributions0;
  }
  public function getTitle()
  {
    return $this->title;
  }
  public function getContribution()
  {
    return $this->contributions;
  }
}
