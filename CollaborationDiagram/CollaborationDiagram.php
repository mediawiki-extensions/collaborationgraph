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
  'author' => 'Yury Katkov, Yevgeny Patarakin, Irina Pochinok',
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
 * \param $norm - normalization value
 */
function getNorm($val, $sum, $norm)
{
  return ceil(($val*$norm)/$sum);
}

function getNormNotCeil($val, $sum, $norm)
{
  return ($val*$norm)/$sum;
}


function getLogThickness($val, $sum, $norm)
{
  return log($val/$sum+1)*$norm;
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
  global $wgDBprefix;
  $dbr =& wfGetDB( DB_SLAVE );

  $tbl_pag =  $wgDBprefix.'page';
  $tbl_rev = $wgDBprefix.'revision';
 // echo ($thisPageTitle); // почему  то тут для хлеба выводятия Диаграмма соучастяи а не хлеб
  $sql = sprintf("SELECT rev_user_text  FROM $tbl_pag  INNER JOIN $tbl_rev on $tbl_pag.page_id=$tbl_rev.rev_page  WHERE page_title=\"%s\";  ", mysql_escape_string($thisPageTitle));

  $rawUsers = $dbr->query($sql);
  $res=array();
  foreach ($rawUsers as $row)
  {
    array_push($res, $row->rev_user_text);
  }
  return $res;
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

/*!
 * \brief Function that evaluate hom much time each user edited the page
 * \return array : username -> how much time edited
 */
function getCountsOfEditing($names)
{
 
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
function getGraphvizNodes($changesForUsers,  $sumEditing, $thisPageTitle)
{
  $text = "";
  while (list($editorName,$numEditing)=each($changesForUsers))
  {
    $text.= "\n" . '"User:' . mysql_escape_string($editorName) . '"' . ' -> ' . '"' .mysql_escape_string( $thisPageTitle ). '"' . " " . " [ penwidth=" . getLogThickness($numEditing, $sumEditing,22) . " label=".$numEditing ."]" . " ;";

  }
  //here we'll make red links for pages that doesn't exist
  reset($changesForUsers);
  $editors = array_unique(array_keys($changesForUsers));
  while (list($key,$editorName)=each($editors))
  { 
    $title = Title::newFromText( "User:$editorName" );
    if (!$title->exists())
    {
      $text .="\n" . '"User:' . $editorName . '"' . '[fontcolor="#BA0000"] ;' . " \n"  ;
    }
  }
  return $text;
}

function getPie($changesForUsers,  $sumEditing, $thisPageTitle)
{
  $text = '<img src="http://chart.apis.google.com/chart?cht=p3&chs=750x300&';
  $text .= 'chd=t:';
  while (list($editorName,$numEditing)=each($changesForUsers))
  {
   $text .= $numEditing . ","  ;  
  }
  $text = substr_replace($text, '',-1);
  $text .= '&';
  $text .= 'chl=';
  reset($changesForUsers);
  while (list($editorName,$numEditing)=each($changesForUsers))
  {
    $text .=$editorName . "|" ;
  }
  $text = substr_replace($text, '',-1);
  $text .= '">';
  return $text;

}

function drawDiagram($settings, $parser, $frame) {
  global $wgTitle;
  $text = "<graphviz>";
  if (!is_file( dirname( __FILE__). "/" . $settings['skin']))
  {
    $text .= 'digraph W {
	rankdir = LR ;
	node [URL="' . 'ERROR' . '?title=\N"] ;
	node [fontsize=9, fontcolor="blue", shape="none", style=""] ;' ;

  }
  else
  {
    $text .= file_get_contents(dirname( __FILE__). "/" . $settings['skin']);
    $text .= "\n". 'node [URL="' . $_SERVER['SCRIPT_NAME'] . '?title=\N"] ;' . "\n";
  }  

  $changesForUsers = array();
  $sumEditing=0;
  foreach ($settings['pagesList'] as $thisPageTitle )
  {
    $names = getPageEditorsFromDb($thisPageTitle);

    $changesForUsersForPage = getCountsOfEditing($names);
    $pageWithChanges[$thisPageTitle]=$changesForUsersForPage;
    $changesForUsers = array_merge($changesForUsers, $changesForUsersForPage);
    $sumEditing+=evaluateCountOfAllEdits($changesForUsersForPage);

  }
  foreach ($pageWithChanges as $thisPageTitle=>$changesForUsersForPage)
  {
//    $sumEditing = evaluateCountOfAllEdits($changesForUsers);
    $text.=getGraphvizNodes($changesForUsersForPage, $sumEditing, $thisPageTitle);
  }
   $text.= "</graphviz>";
 // $text = getPie($changesForUsers, $sumEditing, $thisPageTitle);

  $parser->disableCache();
  $text = $parser->recursiveTagParse($text, $frame); //this stuff just render my page
  return $text;
}

/*!
 * \brief here is an old generation function. I'm refactoring it now
 * XXX
 */
function efRenderCollaborationDiagram( $input, $args, $parser, $frame ) 
{
  global $wgRequest, $wgCollaborationDiagramSkinFilename, $wgOut;
  $settings = array();
  print_r($settings);

  $settings['pagesList'] = array();
  if (!isset($args["page"])&&!isset($args['category']))
  {
    $settings['pagesList'] = array($wgRequest->getText('title'));
  }
  
  if  (isset($args["page"]))
  {
    $settings['pagesList'] = explode(";",$args["page"]);
  }

  if (isset($args["category"]))
  {
    $pagesFromCategory = array();
    $pagesFromCategory = getCategoryPagesFromDb($args["category"]);
    $settings['pagesList'] = array_merge($settings['pagesList'], $pagesFromCategory) ;
    $settings['category']=$args['category'];//XXX
  }
  
  $settings['skin'] = 'default.dot';
  if (isset($wgCollaborationDiagramSkinFilename))
  {
    $settings['skin'] = $wgCollaborationDiagramSkinFilename;
  }
  
  $settings['diagramType'] = 'dot';
  if (isset($args['type']))
  {
   $settings['diagramType']= $args['type'];
  }

  return drawDiagram($settings, $parser,$frame);
}

$wgHooks['SkinTemplateContentActions'][] = 'showCollaborationDiagramTab';
$wgHooks['SkinTemplateTabs'][] = 'showCollaborationDiagramTab';
$wgHooks['SkinTemplateNavigation'][] = 'showCollaborationDiagramTabInVector';
/*!
 * \brief function that show tab
 * very simple, see this extension : http://www.mediawiki.org/wiki/Extension:Tab0
 * and here is full explanation http://svn.wikimedia.org/viewvc/mediawiki/trunk/extensions/examples/Content_action.php?view=markup
 */
function showCollaborationDiagramTab( $obj , &$content_actions  ) 
{
  global $wgTitle, $wgScriptPath, $wgRequest, $wgArticle;

  if( $wgTitle->exists() &&  ($wgTitle->getNamespace() != NS_SPECIAL) )
  {
    wfLoadExtensionMessages('CollaborationDiagram');
    
    require_once("Title.php");
    $content_actions['CollaborationDiagram'] = array(
      'class' => false,
      'text' => wfMsgForContent('tabcollaboration'),
    );

	$pageName = $wgArticle->getTitle()->getDbKey();
    if ($wgTitle->getNamespace()==NS_CATEGORY)
    {
	$content_actions['CollaborationDiagram']['href'] = Title::newFromText("CollaborationDiagram", NS_SPECIAL)->getFullUrl(array('category'=>$pageName));
    }
    else
    {
     $content_actions['CollaborationDiagram']['href'] = Title::newFromText("CollaborationDiagram", NS_SPECIAL)->getFullUrl(array('page'=>$pageName));
    }
        
  }
return true;
}

function showCollaborationDiagramTabInVector( $obj, &$links )
{
  // the old '$content_actions' array is thankfully just a
  // sub-array of this one
  $views_links = $links['views'];
  showCollaborationDiagramTab( $obj, $views_links );
  $links['views'] = $views_links;
  return true;



}
include_once("SpecialCollaborationDiagram.php");
