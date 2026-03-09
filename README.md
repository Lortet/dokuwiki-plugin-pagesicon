# Pagesicon

J'aime beaucoup DokuWiki, mais je l'ai toujours trouvé un peu triste : il manquait une façon simple de mettre de belles icônes sur les pages.  
Avec **pagesicon**, c'est possible.

`pagesicon` est à la fois :
- un **plugin** (affichage et gestion des icônes),
- un **helper** (API réutilisable par d'autres plugins, comme `catmenu` et `visualindex`).

## Ce que fait le plugin

- Affiche une icône en haut de la page (`show`), si activé.
- Peut utiliser l'icône de la page comme favicon d'onglet (`show_as_favicon`).
- Fournit une page de gestion d'icône par page : `?do=pagesicon`.
- Gère les variantes `big` et `small`.
- Notifie les autres plugins quand une icône change via `PLUGIN_PAGESICON_UPDATED` (pour invalider leurs caches).

## Paramètres de configuration

Dans le `Configuration Manager` :

- `icon_name` : noms candidats pour l'icône `big` (séparés par `;`).  
  Supporte `~pagename~`.

- `icon_thumbnail_name` : noms candidats pour l'icône `small` (séparés par `;`).  
  Supporte `~pagename~`.

- `default_image` : image par défaut (mediaID) utilisée uniquement quand le fallback est explicitement activé dans l'API helper.

- `icon_size` : taille (px) de l'icône affichée en haut de page.

- `extensions` : extensions autorisées (séparées par `;`), par exemple `svg;png;jpg;jpeg`.

- `show_on_top` : activer/désactiver l'affichage en haut de page.

- `show_as_favicon` : utiliser l'icône de la page comme favicon.

## Usage

Depuis une page, utiliser l'action `Gérer l'icône` puis uploader/supprimer.

Le plugin travaille sur la **page courante** (`$ID`), pas sur une cible passée en paramètre.

## API helper

Charger le helper :

```php
$pagesicon = plugin_load('helper', 'pagesicon');
```

### Résolution en mediaID

- `getPageIconId(string $namespace, string $pageID, string $size = 'bigorsmall')`  
  Retourne un mediaID (`ns:file.ext`) ou `false`.

- `getMediaIconId(string $mediaID, string $size = 'bigorsmall')`  
  Retourne le mediaID d'icône pour un média, ou `false`.

`size` accepte : `big`, `small`, `bigorsmall`, `smallorbig`.

### Résolution en URL versionnée

- `getPageIconUrl(string $namespace, string $pageID, string $size = 'bigorsmall', array $params = ['width' => 55], ?int &$mtime = null, bool $withDefault = false)`  
  Retourne une URL d'icône (avec `pi_ts=<filemtime>`) ou `false`.  
  Renseigne aussi `$mtime`.

- `getMediaIconUrl(string $mediaID, string $size = 'bigorsmall', array $params = ['width' => 55], ?int &$mtime = null, bool $withDefault = false)`  
  Retourne une URL d'icône de média (avec `pi_ts=<filemtime>`) ou `false`.  
  Renseigne aussi `$mtime`.

- `getDefaultIconUrl(array $params = ['width' => 55], ?int &$mtime = null)`  
  Retourne l'URL de l'image par défaut configurée, ou `false`.

### URLs de gestion

- `getUploadIconPage(string $targetPage = '')`  
  Retourne l'URL `?do=pagesicon` d'une page, ou `null` si non autorisé.

- `getUploadMediaIconPage(string $mediaID = '')`  
  Retourne l'URL de gestion d'icône associée à un média.

### Notification

- `notifyIconUpdated(string $targetPage, string $action = 'update', string $mediaID = '')`

Effets :
- met à jour `purgefile`,
- déclenche l'événement `PLUGIN_PAGESICON_UPDATED`.

Payload :
- `target_page`,
- `action`,
- `media_id`.

Chaque plugin consommateur est responsable de sa propre invalidation de cache.

## Compatibilité des signatures

- Avant `09-03-2025` :
  - `getPageImage(string $namespace, string $pageID, string $size = 'bigorsmall')`
  - `getMediaImage(string $mediaID, string $size = 'bigorsmall')`
  - `getImageIcon(string $namespace, string $pageID, string $size = 'bigorsmall', array $params = ['width' => 55], ?int &$mtime = null)`
  - `getMediaIcon(string $mediaID, string $size = 'bigorsmall', array $params = ['width' => 55], ?int &$mtime = null)`

La compatibilité est conservée via des alias legacy :
- `getPageImage(...)` -> `getPageIconId(...)` (le paramètre legacy `$withDefault` est ignoré)
- `getMediaImage(...)` -> `getMediaIconId(...)` (le paramètre legacy `$withDefault` est ignoré)
- `getImageIcon(...)` -> `getPageIconUrl(...)`
- `getMediaIcon(...)` -> `getMediaIconUrl(...)`
- `getDefaultImageIcon(...)` -> `getDefaultIconUrl(...)`
