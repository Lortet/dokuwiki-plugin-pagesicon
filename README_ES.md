# Pagesicon

[🇫🇷 Français](README.md) | [🇬🇧 English](README_EN.md) | [🇩🇪 Deutsch](README_DE.md) | 🇪🇸 Español

Me gusta mucho DokuWiki, pero siempre lo encontré un poco triste: no había una forma sencilla de añadir iconos bonitos a las páginas.
Con **pagesicon**, ahora es posible.

El plugin puede:
- mostrar un icono en la parte superior de la página (`show`);
- usar este icono como favicon de la pestaña del navegador (`show_as_favicon`);
- mostrar un icono antes de los enlaces internos del wiki;
- proporcionar una página de gestión de iconos con `?do=pagesicon`;
- gestionar variantes `big` y `small` según el contexto;
- exponer un helper (API reutilizable) para otros plugins.

## Uso

Desde una página, usa la acción `Gestionar icono` para:
- subir un icono `big`;
- subir un icono `small`;
- eliminar el icono actual.

## Configuración

En el gestor de configuración:
- `icon_name`: nombres de archivo candidatos para el icono `big`, separados por `;`, con soporte para `~pagename~`
- `icon_thumbnail_name`: nombres de archivo candidatos para el icono `small`, separados por `;`, con soporte para `~pagename~`
- `default_image`: imagen por defecto utilizada solo cuando un método helper solicita explícitamente un fallback
- `icon_size`: tamaño del icono mostrado en la parte superior de la página
- `extensions`: extensiones permitidas, por ejemplo `svg;png;jpg;jpeg`
- `show_on_top`: muestra el icono en la página
- `show_as_favicon`: usa el icono como favicon
- `parent_fallback`: permite usar el icono del padre si la página no tiene ninguno
- `link_icons`: muestra un icono antes de los enlaces internos (`none` / `existing` / `all`)

## Lo que proporciona el helper

Cargar el helper:

```php
$pagesicon = plugin_load('helper', 'pagesicon');
```

Métodos principales:

- `getPageIconId()`: devuelve el mediaID del icono de una página
- `getMediaIconId()`: devuelve el mediaID del icono asociado a un archivo multimedia
- `getPageIconUrl()`: devuelve la URL versionada del icono de una página
- `getMediaIconUrl()`: devuelve la URL versionada del icono asociado a un archivo multimedia
- `getDefaultIconUrl()`: devuelve la URL de la imagen por defecto cuando no se encuentra ningún icono
- `getUploadIconPage()`: devuelve la URL de gestión de iconos para una página
- `getUploadMediaIconPage()`: devuelve la URL de gestión de iconos para un archivo multimedia
- `notifyIconUpdated()`: notifica a otros plugins que un icono ha cambiado

## Caché e integraciones

Cuando se modifica un icono, el plugin lanza el evento `PLUGIN_PAGESICON_UPDATED`.

Esto permite que otros plugins recarguen o invaliden su propio caché cuando sea necesario.

## Herencia de iconos

Si no se encuentra ningún icono en la página, el plugin también puede:
- no heredar ningún icono;
- usar el icono del padre directo;
- usar el primer icono encontrado al recorrer los espacios de nombres padre.
