# Pagesicon

I really like DokuWiki, but I always found it a bit sad: there was no simple way to add nice icons to pages.  
With **pagesicon**, this is now possible.

The plugin can:
- display an icon at the top of the page (`show`);
- use this icon as the browser tab favicon (`show_as_favicon`);
- provide an icon management page with `?do=pagesicon`;
- handle `big` and `small` variants depending on the context;
- expose a helper (reusable API) for other plugins.

## Usage

From a page, use the `Manage icon` action to:
- upload a `big` icon;
- upload a `small` icon;
- delete the current icon.

## Configuration

In the configuration manager:
- `icon_name`: candidate filenames for the `big` icon, separated by `;`, with support for `~pagename~`
- `icon_thumbnail_name`: candidate filenames for the `small` icon, separated by `;`, with support for `~pagename~`
- `default_image`: default image used only when a helper method explicitly asks for a fallback
- `icon_size`: size of the icon displayed at the top of the page
- `extensions`: allowed extensions, for example `svg;png;jpg;jpeg`
- `show_on_top`: displays the icon in the page
- `show_as_favicon`: uses the icon as favicon
- `parent_fallback`: allows using a parent icon when the page has none

## What the helper provides

Load helper:

```php
$pagesicon = plugin_load('helper', 'pagesicon');
```

Main methods:

- `getPageIconId()`: returns the media ID of a page icon
- `getMediaIconId()`: returns the media ID of the icon associated with a media file
- `getPageIconUrl()`: returns the versioned URL of a page icon
- `getMediaIconUrl()`: returns the versioned URL of the icon associated with a media file
- `getDefaultIconUrl()`: returns the URL of the default image to use when no icon is found
- `getUploadIconPage()`: returns the icon management URL for a page
- `getUploadMediaIconPage()`: returns the icon management URL for a media file
- `notifyIconUpdated()`: notifies other plugins that an icon changed

## Cache and integrations

When an icon is modified, the plugin triggers the `PLUGIN_PAGESICON_UPDATED` event.

This allows other plugins to refresh or invalidate their own cache when needed.

## Icon inheritance

If no icon is found on the page itself, the plugin can also:
- inherit no icon;
- use the direct parent icon;
- use the first icon found while walking up parent namespaces.
