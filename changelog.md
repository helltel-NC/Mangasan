# Changelog

## [En cours]

### Ajouté
- ajout de `includes/theme.php` pour centraliser le thème du site
- ajout du module de gestion des éditions :
  - `admin/editions.php`
  - `admin/edition_edit.php`
  - `actions/edition_create.php`
  - `actions/edition_update.php`
  - `actions/edition_delete.php`
  - `actions/edition_toggle_active.php`

### Modifié
- application du thème global au site public et à l’administration
- amélioration de l’aperçu admin grâce au thème partagé
- clarification de l’organisation entre `includes/`, `admin/`, `actions/` et `assets/js/`

### Corrigé
- upload d’images non bloquant si la conversion WEBP n’est pas disponible
- fallback automatique vers le format d’origine si nécessaire
- identification du décalage entre le code et `editions.status`

### Notes techniques
- statuts SQL retenus pour `editions.status` :
  - `draft`
  - `active`
  - `closed`
  - `archived`
- une seule édition peut être active à la fois
- l’activation d’une édition désactive automatiquement les autres
- les dates de début et de fin sont obligatoires à la création
- la suppression d’une édition doit aussi gérer `edition_mangas` et `reviews`