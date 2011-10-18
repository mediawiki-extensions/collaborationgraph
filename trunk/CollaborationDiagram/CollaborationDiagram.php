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
  'version' => '0.1.0',
);

$wgHooks['ParserFirstCallInit'][] = 'efSampleParserInit';

require_once('CDParameters.php');
 
function efSampleParserInit( &$parser ) {
  $parser->setHook( 'collaborationdia', 'efRenderCollaborationDiagram' );
	return true;
}


interface CDDrawer { 
  public function __construct($changesForUsersForPage, $sumEditing,  $thisPageTitle);
  public function draw();
}

abstract class CDAbstractDrawer implements CDDrawer {
  protected $changesForUsersForPage;
  protected $sumEditing;
  protected $thisPageTitle;

  public function __construct($changesForUsersForPage, $sumEditing,  $thisPageTitle) {
    $this->changesForUsersForPage = $changesForUsersForPage;
    $this->sumEditing=$sumEditing;
    $this->thisPageTitle=$thisPageTitle;

  }
}


class CDDrawerFactory
{
  public static function getDrawer($changesForUsersForPage, $sumEditing,  $thisPageTitle) {
    global $wgCollaborationDiagramDiagramType;

    switch($wgCollaborationDiagramDiagramType) {
      case 'pie':
        return new CDPieDrawer($changesForUsersForPage, $sumEditing, $thisPageTitle);
      case 'graphviz-thickness':
        return new CDGraphVizDrawer($changesForUsersForPage, $sumEditing, $thisPageTitle);
      case 'graphviz-figures':
        return new CDFiguresDrawer($changesForUsersForPage, $sumEditing, $thisPageTitle);
      default :
      	return new CDGraphVizDrawer($changesForUsersForPage, $sumEditing, $thisPageTitle);
    }

  }
}

/*
   Это лажовый класс. Рисовальщик должен быть всего графа, а этот класс рисует только мясо
 
 */
class CDGraphVizDrawer extends CDAbstractDrawer{

   public function draw() {
    $text ='';

    $text .= $this->drawWikiLinksToUsers();
    $text .= $this->drawEdgesLogThinkness();
    //here we'll make red links for pages that doesn't exist
    return $text;
  }

  /**
  * \brief print usernames as links. Make links red if page doesn't exist
   *
   * If global configuration variable $wgCollDiaUseSocProfilePicture set to true
   * the method will also generate paths to the avatars of users.
   * @return string description of nodes, strings like User:ganqqwerty [parameters]
   */
  protected function drawWikiLinksToUsers() {
    global $wgCollDiaUseSocProfilePicture;

    reset($this->changesForUsersForPage);
    $editors = array_unique(array_keys($this->changesForUsersForPage));
    $res = '';
    while (list($key,$editorName)=each($editors)) {
        $res .= $this->makeRedOrBlueLink($editorName);
      if ($wgCollDiaUseSocProfilePicture==true) {
          $res .= $this->printGuyPicture($editorName);
      }
      $res .= "; \n";
    }
    return $res;
  }

    /**
     * If the home page of the editor exists forms the blue link, otherwise the link will be red
     * @param  $editorName editor name without the User: prefix
     * @return string returns the red or blue link to the editor's page
     */
    protected function makeRedOrBlueLink($editorName) {
        $text = '';
        $title = Title::newFromText("User:$editorName");
        //red links for authors with empty pages

        if (!$title->exists()) {
            $text .= "\n" . '"User:' . $editorName . '"' . "[fontcolor=\"#BA0000\", tooltip=\"$editorName\" ]  ";
            return $text;
        }
        else {
            $text .= "\n" . '"User:' . $editorName . '"' . "[tooltip=\"$editorName\"]";
            return $text;
        }
    }

    /*!
    * \brief draw the edges with various thickness. Thickness is evaluated with getNorm()
    */
  protected function drawEdgesLogThinkness() {
    $text='';


    while (list($editorName,$numEditing)=each($this->changesForUsersForPage))
    {
      $text.= "\n" . '"User:' . mysql_real_escape_string($editorName) . '"' . ' -> ' . '"' . $this->thisPageTitle . '"' . " " . " [ penwidth=" . getLogThickness($numEditing, $this->sumEditing,22) . " label=".$numEditing ."]" . " ;";
    }
    return $text;
  }

    /**
     * search for avatar of the username $editorName.
     *
     * If the avatar is in jpg and the option $wgCollaborationDiagramConvertToPNG set to true then
     * all avatars that are not in PNG will be converted to PNG with ImageMagick program set in $wgImageMagickConvertCommand variable
     * @param  $editorName
     * @return string
     */
    protected function printGuyPicture($editorName)
    {
        $user = User::newFromName($editorName);
        if ($user==false) {
            return '';
        }
        else {
            global $IP, $wgCollaborationDiagramConvertToPNG, $wgImageMagickConvertCommand, $wgUseImageMagick;
            $avatar = new wAvatar( $user->getId(), 'l' );
            $tmpArr = explode ('?r=',$avatar->getAvatarImage());
            $avatarImage = $tmpArr[0];
            $avatarWithPath = "$IP/images/avatars/$avatarImage";
            if ($wgCollaborationDiagramConvertToPNG==true && $wgUseImageMagick==true && isset($wgImageMagickConvertCommand)) {
                exec("$wgImageMagickConvertCommand $avatarWithPath ");
            }
            return " [image=\"$avatarWithPath\"]";
        }
    }

}


class CDSocialProfileGraphVizDrawer extends CDGraphVizDrawer {
    protected function drawWikiLinksToUsers(){
    global $wgCollDiaUseSocProfilePicture;
    $text = '';
    reset($this->changesForUsersForPage);
    $editors = array_unique(array_keys($this->changesForUsersForPage));
    while (list($key,$editorName)=each($editors)) {
        $text = $this->makeRedOrBlueLink($editorName);
      if ($wgCollDiaUseSocProfilePicture==true) {
          $text .= $this->printGuyPicture($editorName);
      }
      $text .= "; \n";
    }
    return $text;
  }

}



class CDPieDrawer extends CDAbstractDrawer {
  public function draw()
  {
    $text = '<img src="http://chart.apis.google.com/chart?cht=p3&chs=750x300&';
    $text .= 'chd=t:';
    while (list($editorName,$numEditing)=each($this->changesForUsersForPage))
    {
      $text .= $numEditing . ","  ;  
    }
    $text = substr_replace($text, '',-1);
    $text .= '&';
    $text .= 'chl=';
    reset($this->changesForUsersForPage);
    while (list($editorName,$numEditing)=each($this->changesForUsersForPage))
    {
      $text .=$editorName . "|" ;
    }
    $text = substr_replace($text, '',-1);
    $text .= '">';
    return $text;

  }
}

class CDFiguresDrawer extends CDAbstractDrawer {
  public function draw() {
    return '';
  }

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

  $table = array("page", "revision");
  $vars = array ("rev_user_text", "rev_page");
  $conds = array( "page_id=\"". $thisPageTitle->getArticleID() . "\"" ,
                  "page_id=rev_page");
  $rawUsers = $dbr->select($table,$vars, $conds);

  $res=array();
  foreach ($rawUsers as $row)
  {
    array_push($res, $row->rev_user_text);
  }
  return $res;
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
function evaluateCountOfAllEdits($changesForUsers) {
  $sumEditing = 0;
  foreach($changesForUsers as $user)
    $sumEditing +=$user;
  return $sumEditing;
}

function drawPreamble() {
//  $text = "<pre>";
  $text .= "<graphviz>\n";
  if (!is_file( dirname( __FILE__). "/" . CDParameters::getInstance()->getSkin())) {
    $text .= 'digraph W {
      rankdir = LR ;
      node [URL="' . 'ERROR' . '?title=\N"] ;
      node [fontsize=9, fontcolor="blue", shape="plaintext", style=""] ;' ;

    }
    else {
      $text .= file_get_contents(dirname( __FILE__). "/" . CDParameters::getInstance()->getSkin());
      $text .= "\n". 'node [URL="' . $_SERVER['SCRIPT_NAME'] . '?title=\N"] ;' . "\n";
    } 
    return $text;
}

function drawDiagram($parser, $frame) {
  global $wgTitle;
 

  $changesForUsers = array();
  $sumEditing=0;
  $pagesList = CDParameters::getInstance()->getPagesList();
  $pageWithChanges=array();
  foreach ($pagesList as $thisPageTitle )
  {
    $names = getPageEditorsFromDb($thisPageTitle);//FIXME тут будут Title-объекты

    $changesForUsersForPage = getCountsOfEditing($names);
    $thisPageTitleKey = $thisPageTitle->getNsText(). ":" . $thisPageTitle->getText(); // we can't use Title object this is a key with an array so we generate the Ns:Name key
    $pageWithChanges[$thisPageTitleKey]=$changesForUsersForPage;

    $changesForUsers = array_merge($changesForUsers, $changesForUsersForPage);
    $sumEditing+=evaluateCountOfAllEdits($changesForUsersForPage);
  }

  $text = drawPreamble();
  foreach ($pageWithChanges as $thisPageTitleKey=>$changesForUsersForPage)
  {
    $drawer = CDDrawerFactory::getDrawer($changesForUsersForPage, $sumEditing, $thisPageTitleKey);
    $text.=$drawer->draw();
  }
   $text.= "}</graphviz>";
 // $text = getPie($changesForUsers, $sumEditing, $thisPageTitle);

  $parser->disableCache();
  $text = $parser->recursiveTagParse($text, $frame); //this stuff just render my page
  return $text;
}

function efRenderCollaborationDiagram( $input, $args, $parser, $frame )
{
  CDParameters::getInstance()->setup($args); //not used yet
  return drawDiagram($parser,$frame);
}

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
