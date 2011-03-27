<?php
# Alert the user that this is not a valid entry point to MediaWiki if they try to access the special pages file directly.
if (!defined('MEDIAWIKI')) {
  echo <<<EOT
To install my extension, put the following line in LocalSettings.php:
  require_once( "\$IP/extensions/CollaborationDiagram/CollaborationDiagram.php" );
EOT;
  exit( 1 );
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

  public function setup($args) {
  }
}
