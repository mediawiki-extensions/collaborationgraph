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
  'description' => 'Show graph that represent how much each user participated in a creation of the article',
  'descriptionmsg' => 'CollaborationDiagram-desc',
  'version' => '0.0.0',
);
 
$dir = dirname(__FILE__) . '/';
 
$wgAutoloadClasses['CollaborationDiagram'] = $dir . 'CollaborationDiagram_body.php'; # Location of the CollaborationDiagram class (Tell MediaWiki to load this file)
$wgExtensionMessagesFiles['CollaborationDiagram'] = $dir . 'CollaborationDiagram.i18n.php'; # Location of a messages file (Tell MediaWiki to load this file)
$wgExtensionAliasesFiles['CollaborationDiagram'] = $dir . 'CollaborationDiagram.alias.php'; # Location of an alias file (Tell MediaWiki to load this file)
$wgSpecialPages['CollaborationDiagram'] = 'CollaborationDiagram'; # Tell MediaWiki about the new special page and its class name

