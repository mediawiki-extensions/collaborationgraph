<?php
class CollaborationDiagram extends SpecialPage {
  function __construct() {
    parent::__construct( 'CollaborationDiagram' );
    wfLoadExtensionMessages('CollaborationDiagram');
  }

  function execute( $par ) {
    global $wgRequest, $wgOut;

    $this->setHeaders();

    # Get request data from, e.g.
    $param = $wgRequest->getText('param');

    # Do stuff
    # ...
    $output="<collaborationdia page=\"$param\">";
    $wgOut->addWikiText( $output );
  }
}

