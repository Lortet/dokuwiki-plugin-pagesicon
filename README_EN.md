# Pagesicon

I really like DokuWiki, but I always found it a bit sad: there was no simple way to add nice icons to pages.  
With **pagesicon**, this is now possible.

`pagesicon` is both:
- a **plugin** (icon display and management),
- a **helper** (reusable API for other plugins like `catmenu` and `visualindex`).

## What The Plugin Does

- Displays an icon at the top of the page (`show`), when enabled.
- Can use the page icon as browser tab favicon (`show_as_favicon`).
- Provides a per-page icon management page: `?do=pagesicon`.
- Supports `big` and `small` icon variants.
- Notifies other plugins when an icon changes through `PLUGIN_PAGESICON_UPDATED`.

## Configuration

In the `Configuration Manager`:

- `icon_name`: candidate names for the `big` icon (separated by `;`).  
  Supports `~pagename~`.

- `icon_thumbnail_name`: candidate names for the `small` icon (separated by `;`).  
  Supports `~pagename~`.

- `icon_size`: size (px) of the icon shown at the top of pages.

- `extensions`: allowed file extensions (separated by `;`), for example `svg;png;jpg;jpeg`.

- `show_on_top`: enable/disable icon display at the top of pages.

- `show_as_favicon`: use the page icon as favicon.

## Usage

From a page, use the `Gérer l'icône` action, then upload/delete.

The plugin works on the **current page** (`$ID`), not on an external target parameter.

## Helper API

Load helper:

```php
$pagesicon = plugin_load('helper', 'pagesicon');
```

### mediaID Resolution

- `getPageImage(string $namespace, string $pageID, string $size = 'bigorsmall')`  
  Returns a mediaID (`ns:file.ext`) or `false`.

- `getMediaImage(string $mediaID, string $size = 'bigorsmall')`  
  Returns the icon mediaID for a media item, or `false`.

`size` supports: `big`, `small`, `bigorsmall`, `smallorbig`.

### Versioned URL Resolution

- `getImageIcon(string $namespace, string $pageID, string $size = 'bigorsmall', array $params = ['width' => 55], ?int &$mtime = null)`  
  Returns an icon URL (with `pi_ts=<filemtime>`) or `false`.  
  Also fills `$mtime`.

- `getMediaIcon(string $mediaID, string $size = 'bigorsmall', array $params = ['width' => 55], ?int &$mtime = null)`  
  Returns a media icon URL (with `pi_ts=<filemtime>`) or `false`.  
  Also fills `$mtime`.

### Management URLs

- `getUploadIconPage(string $targetPage = '')`  
  Returns the page URL with `?do=pagesicon`, or `null` when not authorized.

- `getUploadMediaIconPage(string $mediaID = '')`  
  Returns the icon management URL associated with a media item.

### Notification

- `notifyIconUpdated(string $targetPage, string $action = 'update', string $mediaID = '')`

Effects:
- updates `purgefile`,
- triggers `PLUGIN_PAGESICON_UPDATED`.

Payload:
- `target_page`,
- `action`,
- `media_id`.

Each consumer plugin remains responsible for its own cache invalidation strategy.
