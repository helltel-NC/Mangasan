# Mangasan – Plateforme Web Événementielle

## 1. Présentation du projet

Mangasan est une application web développée en PHP natif pour gérer un événement culturel autour du manga au sein d’un établissement scolaire.

Le projet a été conçu sans framework afin de rester :

- simple à maintenir
- lisible pour des profils techniques variés
- facilement déployable en environnement local (XAMPP / LAMP)
- modifiable en interne sans dépendre d’une architecture lourde

Mangasan repose sur trois espaces principaux :

- **espace public** : vitrine du site, consultation des éditions, mangas, archives, classement
- **espace membre** : consultation et saisie des reviews
- **espace administrateur** : gestion complète du contenu, des utilisateurs, des éditions, des mangas et des reviews

---

## 2. Version actuelle

**Version cible du dépôt / commit : `0.5`**

Cette version correspond à une base **déjà exploitable** :

- authentification fonctionnelle
- interface publique fonctionnelle
- administration fonctionnelle
- gestion des éditions
- gestion des mangas
- gestion des reviews
- classement général
- gestion des utilisateurs

Le projet est donc **utilisable en l’état**, mais il ne couvre pas encore l’ensemble des fonctionnalités avancées prévues.

---

## 3. Historique simplifié des versions

| Version | État | Résumé |
|---|---|---|
| `0.01` | fondation | structure initiale du projet, base publique, authentification de départ |
| `0.03` | évolution | premiers ajustements techniques et structuration du site |
| `0.1` | évolution majeure | consolidation du noyau, amélioration de la structure générale |
| `0.3` | version intermédiaire | mise en place d’une partie significative de l’admin et du contenu dynamique |
| `0.5` | version utilisable | site public branché, administration exploitable, éditions, mangas, reviews, classement, utilisateurs |

---

## 4. Stack technique

- PHP 8.0+
- MySQL / MariaDB
- HTML / CSS sans framework
- JavaScript léger

Environnements ciblés :

- XAMPP (Windows)
- LAMP (Linux)

---

## 5. Architecture actuelle du projet

```text
/mangasan
│
├── /includes
│   ├── auth.php
│   ├── db.php
│   ├── flash.php
│   ├── footer.php
│   ├── header.php
│   ├── log.php
│   ├── rankings.php
│   ├── session.php
│   ├── theme.php
│   └── upload.php
│
├── /actions
│   ├── login.php
│   ├── logout.php
│   ├── edition_create.php
│   ├── edition_update.php
│   ├── edition_delete.php
│   ├── edition_toggle_active.php
│   ├── manga_create.php
│   ├── manga_update.php
│   ├── manga_delete.php
│   ├── edition_manga_attach.php
│   ├── edition_manga_update.php
│   ├── edition_manga_detach.php
│   ├── review_create.php
│   ├── review_update.php
│   ├── review_update_user.php
│   ├── review_delete.php
│   ├── review_lock.php
│   ├── review_unlock.php
│   ├── user_create.php
│   ├── user_update.php
│   ├── user_toggle_status.php
│   ├── user_reset_password.php
│   └── profile_update.php
│
├── /public
│   ├── index.php
│   ├── review_edit.php
│   ├── account.php
│   └── /assets
│       ├── /css
│       │   ├── style.css
│       │   ├── home.css
│       │   ├── admin.css
│       │   ├── review.css
│       │   └── account.css
│       ├── /js
│       │   ├── home.js
│       │   ├── public-reviews.js
│       │   ├── review-form.js
│       │   ├── admin-preview.js
│       │   ├── admin-reviews.js
│       │   └── admin-users.js
│       └── /uploads
│
├── /admin
│   ├── index.php
│   ├── sections.php
│   ├── section_edit.php
│   ├── setting.php
│   ├── editions.php
│   ├── edition_edit.php
│   ├── mangas.php
│   ├── manga_edit.php
│   ├── reviews.php
│   ├── review_edit.php
│   ├── users.php
│   └── user_edit.php
│
├── .gitignore
└── README.md
```

---

## 6. Fonctionnalités actuellement disponibles

### 6.1 Site public

Le site public permet :

- l’affichage du hero et des sections dynamiques
- l’affichage de l’édition active
- l’affichage des mangas liés à l’édition active
- l’affichage des archives d’éditions
- l’accès au classement général selon les règles de visibilité définies
- l’authentification depuis la page publique

### 6.2 Authentification

Le système d’authentification gère :

- la connexion via `login.php`
- la déconnexion via `logout.php`
- les sessions utilisateur
- le contrôle du rôle admin
- la vérification du statut actif
- le stockage en session de l’utilisateur connecté

### 6.3 Personnalisation du site

L’administration du site permet déjà :

- la modification des paramètres généraux du site
- la gestion du thème global
- la gestion des couleurs
- la gestion du hero
- la gestion des sections du site
- l’upload d’images avec conversion WebP si disponible, sans bloquer l’utilisateur si la conversion n’est pas possible

### 6.4 Gestion des éditions

Le module éditions permet :

- la création d’éditions
- la modification d’éditions
- l’activation d’une édition
- la désactivation automatique des autres éditions quand une édition devient active
- l’archivage logique selon le statut
- la suppression d’une édition avec nettoyage des données liées
- la configuration du classement général :
  - visibilité du classement
  - accès public ou membres
  - méthode de calcul
  - barème des notes (`5`, `10`, `20`)

### 6.5 Gestion des mangas

Le module mangas permet :

- la création d’un manga global
- la modification d’un manga
- la suppression d’un manga
- l’upload de deux visuels distincts :
  - image de card
  - image de couverture
- la liaison d’un manga à une ou plusieurs éditions
- la prévention des doublons dans une même édition
- la gestion de l’ordre d’affichage et de la visibilité dans chaque édition

### 6.6 Gestion des reviews

Le module reviews permet :

- la création d’une review par un membre pour un manga d’une édition active
- la modification d’une review tant qu’elle n’est pas verrouillée
- la consultation d’une review verrouillée
- l’administration complète des reviews côté admin
- le verrouillage / déverrouillage par l’administration
- la suppression d’une review par l’administration

La notation détaillée repose sur quatre axes :

- histoire
- style de dessin
- univers
- messages / thèmes

La note finale est calculée automatiquement à partir de la moyenne de ces sous-notes.

### 6.7 Classement général

Le classement général est basé sur :

- la moyenne des `reviews.score`

Le `personal_rank` n’influence pas le classement général. Il reste une donnée personnelle propre au membre.

Le classement prend en compte :

- la visibilité définie par l’édition
- l’accès défini par l’édition (`public` ou `members`)
- les mangas sans note, affichés avec une mention adaptée

### 6.8 Gestion des utilisateurs

Le module utilisateurs permet :

- la création d’utilisateurs membres et administrateurs
- la modification complète des utilisateurs par un admin
- la désactivation / réactivation par statut
- la réinitialisation du mot de passe
- l’obligation éventuelle de changer le mot de passe à la première connexion
- la gestion de la classe / du groupe pour les membres

Contraintes actuelles :

- la suppression réelle n’est pas la méthode par défaut
- un admin ne peut pas se désactiver lui-même
- un admin ne peut pas retirer son propre rôle admin
- `class_name` est obligatoire pour un membre
- `class_name` est inutile pour un administrateur

### 6.9 Compte utilisateur

Un utilisateur connecté peut actuellement :

- consulter son compte
- modifier son `display_name`

L’espace membre avancé n’est pas encore prioritaire dans cette version.

---

## 7. Base de données – principales tables utilisées

### `users`
Gestion des comptes utilisateurs.

Champs principaux :

- rôle
- identifiant
- informations personnelles
- mot de passe hashé
- statut
- obligation de changement de mot de passe
- dernière connexion

### `roles`
Référentiel des rôles :

- `visitor`
- `member`
- `admin`

### `site_settings`
Paramètres globaux du site.

### `site_sections`
Sections dynamiques du site public.

### `editions`
Gestion des éditions Mangasan.

### `mangas`
Catalogue global des mangas.

### `edition_mangas`
Liaison entre mangas et éditions.

### `reviews`
Reviews des membres sur les mangas d’une édition.

### `logs`
Journalisation des actions importantes.

---

## 8. Installation (local)

### Étapes

1. Cloner le dépôt

```bash
git clone <repo>
```

2. Placer le projet dans :

```text
/xampp/htdocs/mangasan
```

3. Configurer l’accès base de données dans :

```text
includes/db.php
```

4. Créer la base MySQL / MariaDB

5. Importer le schéma SQL du projet

6. Vérifier les tables nécessaires :

- users
- roles
- site_settings
- site_sections
- editions
- mangas
- edition_mangas
- reviews
- logs

7. Accéder au site :

```text
http://localhost/mangasan/public/
```

---

## 9. Sécurité et bonnes pratiques

### Déjà en place

- requêtes préparées
- `password_hash` / `password_verify`
- contrôles de session
- contrôle d’accès admin
- séparation claire entre affichage, logique métier et actions
- gestion d’uploads encadrée

### À renforcer plus tard

- protections CSRF
- durcissement session en production
- politique mot de passe plus explicite
- gestion plus stricte de la première connexion
- validation centralisée des enums et constantes métier

---

## 10. État du projet en version `0.5`

La version `0.5` correspond à un projet :

- **cohérent techniquement**
- **déjà utilisable**
- **administrable**
- **suffisamment avancé pour de vrais tests métier**

En revanche, cette version ne couvre pas encore l’ensemble des fonctionnalités avancées prévues. Elle doit être considérée comme une **version intermédiaire stable**, pas comme une version finale.

---

## 11. Objectifs de la version `0.5`

La version `0.5` vise à stabiliser une base fonctionnelle couvrant :

- le site public
- l’administration du contenu
- la gestion des utilisateurs
- la gestion des éditions
- la gestion des mangas
- la gestion des reviews
- le classement général

Autrement dit, la version `0.5` doit fournir un **socle exploitable, testable et maintenable**.

---

## 12. Fonctionnalités avancées restant à développer après `0.5`

Parmi les éléments non encore finalisés ou volontairement laissés à plus tard :

- véritable espace membre avancé
- changement de mot de passe forcé à la première connexion
- tableau de bord membre plus complet
- suppression réelle contrôlée des utilisateurs
- gestion plus fine des concours / modules annexes
- exploitation plus poussée des anciennes éditions
- amélioration du rendu du classement
- centralisation des enums / constantes métier
- renforcement sécurité / validation / robustesse UX
- documentation technique complémentaire

Cette liste justifie pleinement que la version `0.5` soit considérée comme **utilisable mais encore partiellement incomplète sur les fonctionnalités avancées**.

---

## 13. Philosophie du projet

Mangasan est conçu pour rester :

- compréhensible sans framework
- maintenable sur le long terme
- exploitable en environnement interne
- modifiable sans complexité inutile

L’objectif n’est pas de faire une architecture lourde, mais de produire un projet :

> simple, propre, lisible et maîtrisé

---

## 14. Auteur

Projet développé par :

**Cheminard Romain**  
Technicien informatique / développement web

---

## 15. Remarques finales

Toute évolution future doit respecter :

- la lisibilité du code
- la séparation des responsabilités
- la cohérence entre la base de données et les valeurs métier utilisées dans le code
- la simplicité d’exploitation pour les futurs intervenants

L’un des points de vigilance du projet reste la cohérence des valeurs `enum` entre la base et le code. Toute nouvelle évolution doit impérativement vérifier cet alignement avant intégration.

