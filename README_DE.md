# Pagesicon

[🇫🇷 Français](README.md) | [🇬🇧 English](README_EN.md) | 🇩🇪 Deutsch | [🇪🇸 Español](README_ES.md)

Ich mag DokuWiki sehr, aber ich fand es immer etwas trist: Es gab keine einfache Möglichkeit, Seiten schöne Icons zu geben.
Mit **pagesicon** ist das jetzt möglich.

Das Plugin kann:
- ein Icon oben auf der Seite anzeigen (`show`);
- dieses Icon als Browser-Tab-Favicon verwenden (`show_as_favicon`);
- ein Icon vor internen Wiki-Links anzeigen;
- eine Icon-Verwaltungsseite mit `?do=pagesicon` bereitstellen;
- `big`- und `small`-Varianten je nach Kontext verwalten;
- einen Helper (wiederverwendbare API) für andere Plugins bereitstellen.

## Verwendung

Nutzen Sie auf einer Seite die Aktion `Icon verwalten`, um:
- ein `big`-Icon hochzuladen;
- ein `small`-Icon hochzuladen;
- das aktuelle Icon zu löschen.

## Konfiguration

Im Konfigurationsmanager:
- `icon_name`: Kandidaten-Dateinamen für das `big`-Icon, getrennt durch `;`, mit Unterstützung für `~pagename~`
- `icon_thumbnail_name`: Kandidaten-Dateinamen für das `small`-Icon, getrennt durch `;`, mit Unterstützung für `~pagename~`
- `default_image`: Standardbild, nur verwendet wenn eine Helper-Methode explizit einen Fallback anfordert
- `icon_size`: Größe des oben auf der Seite angezeigten Icons
- `extensions`: Erlaubte Erweiterungen, z. B. `svg;png;jpg;jpeg`
- `show_on_top`: Zeigt das Icon in der Seite an
- `show_as_favicon`: Verwendet das Icon als Favicon
- `parent_fallback`: Ermöglicht die Verwendung eines übergeordneten Icons, wenn die Seite kein eigenes hat
- `link_icons`: Zeigt ein Icon vor internen Links (`none` / `existing` / `all`)

## Was der Helper bietet

Helper laden:

```php
$pagesicon = plugin_load('helper', 'pagesicon');
```

Hauptmethoden:

- `getPageIconId()`: gibt die Media-ID des Icons einer Seite zurück
- `getMediaIconId()`: gibt die Media-ID des einer Mediendatei zugeordneten Icons zurück
- `getPageIconUrl()`: gibt die versionierte URL des Icons einer Seite zurück
- `getMediaIconUrl()`: gibt die versionierte URL des einer Mediendatei zugeordneten Icons zurück
- `getDefaultIconUrl()`: gibt die URL des Standardbilds zurück, wenn kein Icon gefunden wird
- `getUploadIconPage()`: gibt die Icon-Verwaltungs-URL für eine Seite zurück
- `getUploadMediaIconPage()`: gibt die Icon-Verwaltungs-URL für eine Mediendatei zurück
- `notifyIconUpdated()`: benachrichtigt andere Plugins, dass ein Icon geändert wurde

## Cache und Integrationen

Wenn ein Icon geändert wird, löst das Plugin das Ereignis `PLUGIN_PAGESICON_UPDATED` aus.

Dadurch können andere Plugins ihren eigenen Cache bei Bedarf aktualisieren oder invalidieren.

## Icon-Vererbung

Wenn auf der Seite kein Icon gefunden wird, kann das Plugin auch:
- kein Icon erben;
- das Icon des direkten Elternteils verwenden;
- das erste Icon verwenden, das beim Durchlaufen der übergeordneten Namensräume gefunden wird.
