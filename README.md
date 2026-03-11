# Pagesicon

J'aime beaucoup DokuWiki, mais je l'ai toujours trouvé un peu triste : il manquait un moyen simple d'ajouter de belles icônes aux pages.
Avec **pagesicon**, c'est maintenant possible.

Le plugin peut :
- afficher une icône en haut de la page (show) ;
- utiliser cette icône comme favicon de l'onglet (show_as_favicon) ;
- proposer une page de gestion d'ïcone avec `?do=pagesicon` ;
- gèrer les variantes `big` et `small` suivant le contexte.
- exposer un helper (API réutilisable) pour les autres plugins.

## Utilisation

Depuis une page, utilisez l'action `Gerer l'icône` pour
- importer une icône `big` ;
- importer une icône `small` ;
- supprimer l'icône actuelle.

## Configuration

Dans le gestionnaire de configuration :
- `icon_name` : noms de fichiers candidats pour l'icône `big`, séparés par `;`, avec support de `~pagename~`
- `icon_thumbnail_name` : noms de fichiers candidats pour l'icône `small`, séparés par `;`, avec support de `~pagename~`
- `default_image` : image par défaut utilisée seulement quand une méthode helper demande explicitement un fallback
- `icon_size` : taille de l'icône affichée en haut de page
- `extensions` : extensions autorisées, par exemple `svg;png;jpg;jpeg`
- `show_on_top` : affiche l'icône dans la page
- `show_as_favicon` : utilise l'icône comme favicon

## Ce que le helper fournit

Charger le helper :

```php
$pagesicon = plugin_load('helper', 'pagesicon');
```

Methodes principales :

- `getPageIconId()` : retourne le mediaID de l'icône d'une page
- `getMediaIconId()` : retourne le mediaID de l'icône associée à un média
- `getPageIconUrl()` : retourne l'URL versionnée de l'icône d'une page
- `getMediaIconUrl()` : retourne l'URL versionnée de l'icône associée à un média
- `getDefaultIconUrl()` : retourne l'URL de l'image par défaut à utiliser quand aucune icône n'est trouvée
- `getUploadIconPage()` : retourne l'URL de gestion d'icône pour une page
- `getUploadMediaIconPage()` : retourne l'URL de gestion d'icône pour un média
- `notifyIconUpdated()` : notifie les autres plugins qu'une icône a changé

## Cache et integrations

Quand une icône est modifiée, le plugin déclenche l'événement `PLUGIN_PAGESICON_UPDATED`.

Cela permet aux autres plugins de recharger ou invalider leur propre cache si besoin.
