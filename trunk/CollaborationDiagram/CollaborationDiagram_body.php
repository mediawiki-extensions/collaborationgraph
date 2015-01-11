<?php
class CollaborationDiagram extends SpecialPage {
  function __construct() {
    parent::__construct( 'CollaborationDiagram' );
//    wfLoadExtensionMessages('CollaborationDiagram');
  }

  function execute( $par ) {
    global $wgRequest, $wgOut;

    $this->setHeaders();

    # Get request data from, e.g.
    $output= "";
    if ($wgRequest->getText('page')!="")
    {
      $param = $wgRequest->getText('page');
//      $param = mysql_real_escape_string($param);
      $output.="<collaborationdia page=\"$param\">";
    }
    else if ($wgRequest->getText('category')!="")
    {
      $category = $wgRequest->getText('category');
      //Here I delete Category: from the name of Category
      global $wgContLang;
      $namespace_labels = $wgContLang->getNamespaces();
      $category = str_replace($namespace_labels[NS_CATEGORY].":","",$category);

      $output="<collaborationdia category=\"$category\">";
    }
    $wgOut->addWikiText( $output );
  }
}

