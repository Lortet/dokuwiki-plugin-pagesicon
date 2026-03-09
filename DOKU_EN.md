====== Pagesicon Plugin ======

---- plugin ----
description: Manage and expose page/media icons
author     : Lortet
email      : valentin@lortet.io
type       : Action, Helper
lastupdate : 2026-03-09
compatible : Librarian
depends    :
conflicts  :
similar    :
tags       : Media, UI, Navigation, Helper, Icons

downloadurl: https://github.com/Lortet/dokuwiki-plugin-pagesicon/zipball/master
bugtracker : https://github.com/Lortet/dokuwiki-plugin-pagesicon/issues
sourcerepo : https://github.com/Lortet/dokuwiki-plugin-pagesicon/
donationurl:
screenshot_img :
----

===== Installation =====

Install the plugin from the [[plugin:extension|Extension Manager]] using the source download URL above.

===== Description =====

The **pagesicon** plugin allows you to:
  * display an icon at the top of the page;
    * {{https://i.ibb.co/TBz1yVHB/Screenshot-2026-03-06-at-20-25-47-Welcome-to-your-new-Doku-Wiki-Wiki-BSPP.png?250}} 
    * {{https://i.ibb.co/XZd613sL/Screenshot-2026-03-06-at-20-25-39-Formatting-Syntax-Wiki-BSPP.png?250}} 
  * use the page icon as favicon (browser tab icon);
  * manage the icon from the page action ''?do=pagesicon'';
    * {{https://i.ibb.co/1JBtfvH9/Screenshot-2026-03-06-at-20-26-12-Welcome-to-your-new-Doku-Wiki-Wiki-BSPP.png?250}} 
  * expose a helper API for other plugins (e.g. catmenu, visualindex).
    * {{https://i.ibb.co/Mkv7RY8K/Screenshot-2026-03-06-at-20-26-00-Welcome-to-your-new-Doku-Wiki-Wiki-BSPP.png?250}} 

===== Settings =====

^ Name ^ Description ^ Default value ^
| icon_name | Candidate names for the ''big'' icon (separated by '';''). Supports ''~pagename~''. | ''~pagename~;icon_thumbnail;icon'' |
| icon_thumbnail_name | Candidate names for the ''small'' icon (separated by '';''). Supports ''~pagename~''. | ''~pagename~;icon'' |
| default_image | Default image (mediaID), used when ''withDefault=true'' on URL methods. | '''' |
| icon_size | Size of the icon displayed at the top of the page (px). | ''55'' |
| extensions | Allowed image extensions (separated by '';''). | ''svg;png;jpg;jpeg'' |
| show_on_top | Show icon at the top of the page. | ''true'' |
| show_as_favicon | Use the icon as page favicon. | ''true'' |

===== Usage =====

From a page, use the **Manage icon** action, then:
  * upload a ''big'' or ''small'' icon;
  * delete the current icon.

===== Helper API =====

Load helper:
''$pagesicon = plugin_load('helper', 'pagesicon');''

^ Method ^ Description ^
| ''getPageIconId($namespace, $pageID, $size = "bigorsmall")'' | Returns a mediaID (or ''false''). |
| ''getMediaIconId($mediaID, $size = "bigorsmall")'' | Returns the media icon mediaID (or ''false''). |
| ''getPageIconUrl($namespace, $pageID, $size = "bigorsmall", $params = ['width' => 55], &$mtime = null, $withDefault = false)'' | Returns a versioned icon URL (''pi_ts=<filemtime>'') or ''false''. |
| ''getMediaIconUrl($mediaID, $size = "bigorsmall", $params = ['width' => 55], &$mtime = null, $withDefault = false)'' | Returns a versioned media icon URL (''pi_ts=<filemtime>'') or ''false''. |
| ''getDefaultIconUrl($params = ['width' => 55], &$mtime = null)'' | Returns the configured default image URL, or bundled ''default_image.png''. |
| ''getUploadIconPage($targetPage = "")'' | Returns the icon management URL (or ''null'' if unauthorized). |
| ''getUploadMediaIconPage($mediaID = "")'' | Returns the icon management URL for a media. |
| ''notifyIconUpdated($targetPage, $action = "update", $mediaID = "")'' | Triggers the cache invalidation event. |

===== Event =====

On upload/delete, the plugin emits:
  * ''PLUGIN_PAGESICON_UPDATED''
    * to allow cache invalidation in consumer plugins.

Payload:
  * ''target_page''
  * ''action''
  * ''media_id''

===== Signature compatibility =====

Historical signatures (before ''09-03-2025''):
  * ''getPageImage($namespace, $pageID, $size = "bigorsmall")''
  * ''getMediaImage($mediaID, $size = "bigorsmall")''
  * ''getImageIcon($namespace, $pageID, $size = "bigorsmall", $params = ['width' => 55], &$mtime = null)''
  * ''getMediaIcon($mediaID, $size = "bigorsmall", $params = ['width' => 55], &$mtime = null)''

Compatibility is preserved through legacy aliases:
  * ''getPageImage(...)'' -> ''getPageIconId(...)'' (legacy ''$withDefault'' argument is ignored)
  * ''getMediaImage(...)'' -> ''getMediaIconId(...)'' (legacy ''$withDefault'' argument is ignored)
  * ''getImageIcon(...)'' -> ''getPageIconUrl(...)''
  * ''getMediaIcon(...)'' -> ''getMediaIconUrl(...)''
  * ''getDefaultImageIcon(...)'' -> ''getDefaultIconUrl(...)''
