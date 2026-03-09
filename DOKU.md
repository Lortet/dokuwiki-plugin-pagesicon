====== Pagesicon Plugin ======

---- plugin ----
description: Gère et exposer des icônes
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

Installer le plugin depuis le [[fr:plugin:extension|Gestionnaire d'extensions]] à l'aide de l'URL de téléchargement (source) ci-dessus.

===== Description =====

Le plugin **pagesicon** permet :
  * d'afficher une icône en haut de la page ;
    * {{https://i.ibb.co/TBz1yVHB/Screenshot-2026-03-06-at-20-25-47-Welcome-to-your-new-Doku-Wiki-Wiki-BSPP.png?250}} 
    * {{https://i.ibb.co/XZd613sL/Screenshot-2026-03-06-at-20-25-39-Formatting-Syntax-Wiki-BSPP.png?250}}
  * d'utiliser l'icône de la page comme favicon (onglet navigateur) ;
  * de gérer l'icône depuis l'action de page ''?do=pagesicon'' ;
    * {{https://i.ibb.co/1JBtfvH9/Screenshot-2026-03-06-at-20-26-12-Welcome-to-your-new-Doku-Wiki-Wiki-BSPP.png?250}} 
  * d'exposer une API helper pour les autres plugins (ex. : catmenu, visualindex).
    * {{https://i.ibb.co/Mkv7RY8K/Screenshot-2026-03-06-at-20-26-00-Welcome-to-your-new-Doku-Wiki-Wiki-BSPP.png?250}} 

===== Paramètres =====

^ Nom ^ Description ^ Valeur par défaut ^
| icon_name | Noms candidats pour l'icône ''big'' (séparés par '';''). Supporte ''~pagename~''. | ''~pagename~;icon_thumbnail;icon'' |
| icon_thumbnail_name | Noms candidats pour l'icône ''small'' (séparés par '';''). Supporte ''~pagename~''. | ''~pagename~;icon'' |
| default_image | Image par défaut (mediaID), utilisée quand ''withDefault=true'' sur les méthodes URL. | '''' |
| icon_size | Taille de l'icône affichée en haut de page (px). | ''55'' |
| extensions | Extensions d'images autorisées (séparées par '';''). | ''svg;png;jpg;jpeg'' |
| show_on_top | Afficher l'icône en haut de page. | ''true'' |
| show_as_favicon | Utiliser l'icône comme favicon de la page. | ''true'' |

===== Utilisation =====

Depuis une page, utiliser l'action **Gérer l'icône** puis :
  * importer une icône ''big'' ou ''small'' ;
  * supprimer l'icône existante.

===== API Helper =====

Charger le helper :
''$pagesicon = plugin_load('helper', 'pagesicon');''

^ Méthode ^ Description ^
| ''getPageIconId($namespace, $pageID, $size = 'bigorsmall')'' | Retourne un mediaID (ou ''false''). |
| ''getMediaIconId($mediaID, $size = 'bigorsmall')'' | Retourne le mediaID d'icône d'un média (ou ''false''). |
| ''getPageIconUrl($namespace, $pageID, $size = 'bigorsmall', $params = ['width' => 55], &$mtime = null, $withDefault = false)'' | Retourne une URL d'icône versionnée (''pi_ts=<filemtime>'') ou ''false''. |
| ''getMediaIconUrl($mediaID, $size = 'bigorsmall', $params = ['width' => 55], &$mtime = null, $withDefault = false)'' | Retourne une URL d'icône média versionnée (''pi_ts=<filemtime>'') ou ''false''. |
| ''getDefaultIconUrl($params = ['width' => 55], &$mtime = null)'' | Retourne l'URL de l'image par défaut configurée, ou l'image interne ''default_image.png''. |
| ''getUploadIconPage($targetPage = "")'' | Retourne l'URL de gestion d'icône (ou ''null'' si non autorisé). |
| ''getUploadMediaIconPage($mediaID = "")'' | Retourne l'URL de gestion d'icône d'un média. |
| ''notifyIconUpdated($targetPage, $action = "update", $mediaID = "")'' | Déclenche l'événement d'invalidation de cache. |

===== Événement =====

Lors d'un upload/suppression, le plugin émet :
  * ''PLUGIN_PAGESICON_UPDATED''
    * pour permettre l'invalidation de cache dans les plugins consommateurs.

Payload :
  * ''target_page''
  * ''action''
  * ''media_id''

===== Compatibilité des signatures =====

Signatures historiques (avant ''09-03-2025'') :
  * ''getPageImage($namespace, $pageID, $size = "bigorsmall")''
  * ''getMediaImage($mediaID, $size = "bigorsmall")''
  * ''getImageIcon($namespace, $pageID, $size = "bigorsmall", $params = ['width' => 55], &$mtime = null)''
  * ''getMediaIcon($mediaID, $size = "bigorsmall", $params = ['width' => 55], &$mtime = null)''

La compatibilité est conservée via des alias legacy :
  * ''getPageImage(...)'' -> ''getPageIconId(...)'' (le paramètre legacy ''$withDefault'' est ignoré)
  * ''getMediaImage(...)'' -> ''getMediaIconId(...)'' (le paramètre legacy ''$withDefault'' est ignoré)
  * ''getImageIcon(...)'' -> ''getPageIconUrl(...)''
  * ''getMediaIcon(...)'' -> ''getMediaIconUrl(...)''
  * ''getDefaultImageIcon(...)'' -> ''getDefaultIconUrl(...)''
