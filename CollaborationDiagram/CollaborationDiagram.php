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
    global $wgCollaborationDiagramDiagramType, $wgCollDiaUseSocProfilePicture;

    switch($wgCollaborationDiagramDiagramType) {
      case 'pie':
        return new CDPieDrawer($changesForUsersForPage, $sumEditing, $thisPageTitle);
      case 'graphviz-thickness': {
        return new CDGraphVizDrawer($changesForUsersForPage, $sumEditing, $thisPageTitle);
      }
      case 'graphviz-figures':
        return new CDFiguresDrawer($changesForUsersForPage, $sumEditing, $thisPageTitle);
      default : {
          if ($wgCollDiaUseSocProfilePicture) {
            return new CDSocialProfileGraphVizDrawer($changesForUsersForPage, $sumEditing, $thisPageTitle);
        }
      	return new CDGraphVizDrawer($changesForUsersForPage, $sumEditing, $thisPageTitle);
      }
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
    $text .= $this->drawWikiLinkToArticle();
    $text .= $this->drawEdgesLogThinkness();
    return $text;
  }

  /**
  * \brief print usernames as links. Make links red if page doesn't exist
   * @return string description of nodes, strings like User:ganqqwerty [parameters]
   */
  protected function drawWikiLinksToUsers() {
    global $wgCollDiaUseSocProfilePicture;

    reset($this->changesForUsersForPage);
    $editors = array_unique(array_keys($this->changesForUsersForPage));
    $res = '';
    while (list($key,$editorName)=each($editors)) {
        $res .= $this->drawUserNode ($editorName);
        $res .= $this->drawTooltip($editorName);
        $res .= $this->drawRedLink($editorName);
        $res .= " \n";
    }
    return $res;
  }
    /**
     * Do nothing but will be implemented in children classes
     * @return string empty string
     */
    protected function drawWikiLinkToArticle() {
        return "\n\"" . Sanitizer::escapeId($this->thisPageTitle, 'noninitial') . "\"" .
        " [label = \"$this->thisPageTitle\"]" ;
    }

    /**
     * Draw only the identifier of a user
     * @param  $editorName: the name of user
     * @return string like "User:qwerty"
     */
    protected function drawUserNode ($editorName) {
        return  "\"User:" . Sanitizer::escapeId( $editorName, 'noninitial' ) . "\" ";
    }

    protected function drawPageNode() {
        return Sanitizer::escapeId($this->thisPageTitle, 'noninitial');
    }

    protected function drawTooltip($editorName) {
        return $this->drawUserNode($editorName) . "[tooltip=\"$editorName\"] ;";
    }
    /**
     * If the home page of the editor exists forms the blue link, otherwise the link will be red
     * @param  $editorName editor name without the User: prefix
     * @return string returns the red or blue link to the editor's page
     */
    protected function drawRedLink($editorName) {
        $text = '';
        $title = Title::newFromText("User:$editorName");
        if (!$title->exists()) {
            $text .= $this->drawUserNode($editorName) . "[fontcolor=\"#BA0000\"];";
            return $text;
        }
        return $text;
    }

 /**
  * \brief draw the edges with various thickness. Thickness is evaluated with getNorm()
  */
  protected function drawEdgesLogThinkness() {
    $text='';
    while (list($editorName,$numEditing)=each($this->changesForUsersForPage))
    {
      $text.= "\n" . $this->drawUserNode($editorName) . ' -> ' . '"' . $this->drawPageNode() . '"' . " " . " [ penwidth=" . getLogThickness($numEditing, $this->sumEditing,22) . " label=".$numEditing ."]" . " ;";
    }
    return $text;
  }



}

/**
 * A class for drawing graphviz  diagrams using the date from Extension:SocialProfile
 * There is an avatar of user instead of just username
 * This class inherits and uses many of the functions of the CDGraphvizDrawer, e.g. draw()
 */
class CDSocialProfileGraphVizDrawer extends CDGraphVizDrawer {
    public function __construct($changesForUsersForPage, $sumEditing, $thisPageTitle) {
        parent::__construct($changesForUsersForPage, $sumEditing, $thisPageTitle);
    }
  /**
  * \brief print usernames as links. Make links red if page doesn't exist
   * This method will also generate paths to the avatars of users.
   * @return string description of nodes, strings like User:ganqqwerty [parameters]
   */
    protected function drawWikiLinksToUsers() {
        reset($this->changesForUsersForPage);
        $editors = array_unique(array_keys($this->changesForUsersForPage));
        $res = '';
        while (list($key,$editorName)=each($editors)) {
            $res .= $this->drawUserNode ($editorName);
            $res .= $this->drawTooltip($editorName);
            $res .= $this->drawRedLink($editorName);
            $res .= $this->printGuyPicture($editorName);
        }
        return $res;
    }

   /**
     * search for avatar of the username $editorName.
     *
     * If the avatar is in jpg and the option $wgCollaborationDiagramConvertToPNG set to true then
     * all avatars that are not in PNG will be converted to PNG with ImageMagick program set in $wgImageMagickConvertCommand variable
     * @param  $editorName
     * @return string
     */
    private function printGuyPicture($editorName)
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
                exec($wgImageMagickConvertCommand . " " . $avatarWithPath . " " . substr_replace($avatarWithPath, 'png',-3)); ///probably code injection possible here!!!
                $avatarWithPath = substr_replace($avatarWithPath,'png',-3);
            }
            $pictureWithLabel = "[label=<
<table border=\"0\">
 <tr>
  <td><img src=\"$avatarWithPath\" /></td>
 </tr>
 <tr>
  <td>$editorName</td>
 </tr>
</table>>]";
            return $this->drawUserNode($editorName) . $pictureWithLabel;
        }
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

class CDFiguresDrawer extends CDGraphVizDrawer {
  public function drawWikiLinksToUsers() {
      reset($this->changesForUsersForPage);
      $editors = array_unique(array_keys($this->changesForUsersForPage));
      $res = '';
      while (list($key,$editorName)=each($editors)) {
          $res .= $this->drawTooltip($editorName);
          $res .= $this->drawUserIcon($editorName);
      }
      return $res;
  }
  private function drawUserIcon($editorName) {
      global $wgCollaborationDiagramUserIcon, $IP;
      if (!isset($wgCollaborationDiagramUserIcon)) {
          $wgCollaborationDiagramUserIcon = "$IP/extensions/CollaborationDiagram/user_icon.png";
      }
      return $this->drawUserNode($editorName) . "[label =\"\", image=\"$wgCollaborationDiagramUserIcon\" ];\n";

  }
    /**
     * Draw the article icon
     * @return description of the article node with an image and tooltip
     */
    protected function drawWikiLinkToArticle() {

        global $wgCollaborationDiagramArticleIcon, $IP;
        if (!isset ($wgCollaborationDiagramArticleIcon)) {
            $wgCollaborationDiagramArticleIcon="$IP/extensions/CollaborationDiagram/article_icon.png";
        }
        $res = "\"$this->thisPageTitle\"";
        $res .= " [image=\"$wgCollaborationDiagramArticleIcon\"";
        $res .= " tooltip=\"$this->thisPageTitle\" label=\"\"];\n";
        return $res;
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
/**
 * Throws small contributions from the array. The size of small contribution is set via global variable
 * called $wgCollaborationDiagramMinEdit in LocalSettings.php
 * @param  $changesForUsers array with pairs USER=>her contribution
 * @return the same array but filtered
 */
function filterTinyEdits($changesForUsers) {
    global $wgCollaborationDiagramMinEdit;
    foreach ($changesForUsers as $key => $val) {
        if ($val<=$wgCollaborationDiagramMinEdit) {
            unset ($changesForUsers[$key]);
        }
    }
    return $changesForUsers;
}

/*!
 * \brief Function that evaluate hom much time each user edited the page
 * \return array : username -> how much time edited
 */
function getCountsOfEditing($names) {
  global $wgCollaborationDiagramMinEdit;
  $changesForUsers = array();//an array where we'll store how much time each user edited the page
  foreach ($names as $curName)
  {
    if (!isset($changesForUsers[$curName]))
      $changesForUsers[$curName]=1;
    else
      $changesForUsers[$curName]++;
  }
  if (isset($wgCollaborationDiagramMinEdit)) {
      $changesForUsers = filterTinyEdits($changesForUsers);
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
    $text = "";
    $text .= "<graphviz>\n";

  if (!is_file( dirname( __FILE__). "/" . CDParameters::getInstance()->getSkin())) {
    $text .= 'digraph W {
      rankdir = LR ;
      node [URL="' . 'ERROR' . '?title=\N"] ;
      node [fontsize=9, fontcolor="blue", shape="plaintext", style=""] ;' ;

    }
    else {
        /**
         * @var WebRequest
         */
        global $wgRequest;
      $text .= file_get_contents(dirname( __FILE__). "/" . CDParameters::getInstance()->getSkin());
      $text .= "\n". 'node [URL="' . $wgRequest->detectServer() . $_SERVER["SCRIPT_NAME"] . '?title=\N"] ;' . "\n";
    } 
    return $text;
}

function drawDiagram(Parser $parser, PPFrame $frame) {
  global $wgTitle;
 
  $sumEditing=0;
  $pagesList = CDParameters::getInstance()->getPagesList();
  $pageWithChanges=array();
  foreach ($pagesList as $thisPageTitle )
  {
    $names = getPageEditorsFromDb($thisPageTitle);
    $changesForUsersForPage = getCountsOfEditing($names);
    $thisPageTitleKey=$thisPageTitle->getText();
      if ($thisPageTitle->getNsText()!="") {
        $thisPageTitleKey = $thisPageTitle->getNsText(). ":" . $thisPageTitleKey; // we can't use Title object this is a key with an array so we generate the Ns:Name key
      }


    $pageWithChanges[$thisPageTitleKey]=$changesForUsersForPage;
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

  $parser->setTitle(Title::newFromText("Main_page"));
  $frame->getTitle(Title::newFromText("Main_page"));
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
//    wfLoadExtensionMessages('CollaborationDiagram');
    
    $content_actions['CollaborationDiagram'] = array(
      'class' => false,
      'text' => wfMsgForContent('tabcollaboration'),
    );

	$pageName = $wgTitle->getDbKey();
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
