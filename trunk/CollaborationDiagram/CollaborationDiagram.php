<?php
# Alert the user that this is not a valid entry point to MediaWiki if they try to access the special pages file directly.
if (!defined('MEDIAWIKI')) {
  echo <<<EOT
To install my extension, put the following line in LocalSettings.php:
  require_once( "\$IP/extensions/CollaborationDiagram/CollaborationDiagram.php" );
EOT;
  exit( 1 );
}
 $wgExtensionCredits['specialpage'][] = array(
  'name' => 'CollaborationDiagram',
  'author' => 'Yury Katkov',
  'url' => 'http://www.mediawiki.org/wiki/Extension:CollaborationDiagram',
  'description' => 'Shows graph that represents how much each user participated in a creation of the article',
  'descriptionmsg' => 'collaborationdiagram-desc',  
  'version' => '0.0.1',
);

$wgHooks['ParserFirstCallInit'][] = 'efSampleParserInit';
 
function efSampleParserInit( &$parser ) {
	$parser->setHook( 'collaborationdia', 'efRenderCollaborationDiagram' );
	return true;
}
/*!
 * \brief normalization function
 * This is a function for edges to be in the same order of thickness
 * to prevent situations when you have one edge with thickness=1 and other with thickness=155
 */
function getNorm($val, $sum)
{
  return ceil(($val*12)/$sum);
}


/*!
 * \brief This function gets list of Users
 *  that edited current page from database
 *  
 *  The function returns such array as
 *     MediaWiki default;  194.85.163.147; Ganqqwerty;  Ganqqwerty ; Ganqqwerty;  Ganqqwerty;
 *     Ganqqwerty; 92.62.62.48; Cheshirig; Cheshirig
 */
function getPageEditorsFromDb($thisPageTitle)
{
  $dbr =& wfGetDB( DB_SLAVE );

  $tbl_pag =  'page';
  $tbl_rev = 'revision';

  $sql = "
    SELECT
     rev_user_text
    FROM $tbl_pag
    INNER JOIN $tbl_rev on $tbl_pag.page_id=$tbl_rev.rev_page
    WHERE
    page_title=\"$thisPageTitle\";
  ";

  $res = $dbr->query($sql);
  return $res;
}

function getCategoryPagesFromDb($categoryName)
{
  $dbr =& wfGetDB( DB_SLAVE );
  $tbl_categoryLinks = 'categorylinks';
  $sql = "
      SELECT
      page_title
      from $tbl_categoryLinks 
      LEFT JOIN page on categorylinks.cl_from=page.page_id
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

/*!
 * \brief Function that evaluate hom much time each user edited the page
 * \return array : username -> how much time edited
 */
function getCountsOfEditing($res)
{
 
  $changesForUsers = array();//an array where we'll store how much time each user edited the page
  foreach ($res as $row)
  {
    $curName = $row->rev_user_text;
    if (!isset($changesForUsers[$curName]))
      $changesForUsers[$curName]=1;
    else
      $changesForUsers[$curName]++;
  }
  return $changesForUsers;
}

/*!
 * \brief Sums all edits for all users
 */
function evaluateCountOfAllEdits($changesForUsers)
{
  $sumEditing = 0;
  foreach($changesForUsers as $user)
    $sumEditing +=$user;
  return $sumEditing;
}

/*!
 * \brief generates graphviz text for all Users with thickness evaluated with getNorm()
 */
function getGraphvizNodes($changesForUsers, $numEditing, $sumEditing, $thisPageTitle)
{
  while (list($editorName,$numEditing)=each($changesForUsers))
    $text.= "\n" . '"User:' . $editorName . '"' . ' -> ' . '"' . $thisPageTitle . '"' . " " . "[penwidth=" . getNorm($numEditing, $sumEditing) . "label=".$numEditing ."]" . " ;";

  return $text;
}


/*!
 * \brief here is an old generation function. I'm refactoring it now
 * XXX
 */
function efRenderCollaborationDiagram( $input, $args, $parser, $frame ) 
{
  global $wgRequest;


  $pagesList = array();
  if (empty($args))
  {
    $pagesList = $wgRequest->getText('title');
  }
    
  if  (isset($args["page"]))
  {
    $pagesList = explode(";",$args["page"]);
  }
  if (isset($args["category"]))
  {
    $pagesFromCategory = array();
    $pagesFromCategory = getCategoryPagesFromDb($args["category"]);
    $pagesList = array_merge($pagesList, $pagesFromCategory) ;
  }

  $text = '<graphviz>
digraph W {
rankdir = LR ;
node [URL="' . $_SERVER['PHP_SELF'] . '/\N"] ;
node[fontsize=8, fontcolor="blue", shape="none", style=""] ;';

  foreach ($pagesList as $thisPageTitle )
  {
    $res = getPageEditorsFromDb($thisPageTitle);

    $changesForUsers = getCountsOfEditing($res);
    $sumEditing = evaluateCountOfAllEdits($changesForUsers);
    $text.=getGraphvizNodes($changesForUsers, $numEditing, $sumEditing, $thisPageTitle);

  }
  $text = $parser->recursiveTagParse($text, $frame); //this stuff just render my page
  $text.= "</graphviz>";
  return $text;
}

$wgHooks['SkinTemplateContentActions'][] = 'showCollaborationDiagramTab';

/*!
 * \brief function that show tab
 * very simple, see this extension : http://www.mediawiki.org/wiki/Extension:Tab0
 * and here is full explanation http://svn.wikimedia.org/viewvc/mediawiki/trunk/extensions/examples/Content_action.php?view=markup
 */
function showCollaborationDiagramTab( $content_actions ) 
{
  global $wgTitle, $wgScriptPath, $wgRequest;

  if( $wgTitle->exists() &&  ($wgTitle->getNamespace() != NS_SPECIAL) )
  {
    require_once("Title.php");
    $content_actions['CollaborationDiagram'] = array(
      'class' => false,
      'text' => 'CollaborationDiagram',
      //if ($wgTitle->getNamespace()==NS_CATEGORY) // XXX here I stopped
      'href' => $wgScriptPath . '?title=Special:CollaborationDiagram' . '&param=' . $wgRequest->getText('title') ,
    );    
  }
return true;
}

include_once("SpecialCollaborationDiagram.php");
