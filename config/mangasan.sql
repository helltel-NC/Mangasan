-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost
-- Généré le : dim. 19 avr. 2026 à 09:19
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `mangasan`
--

-- --------------------------------------------------------

--
-- Structure de la table `drawing_contests`
--

CREATE TABLE `drawing_contests` (
  `id` int(10) UNSIGNED NOT NULL,
  `edition_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `rules` text DEFAULT NULL,
  `status` enum('draft','published','closed','archived') NOT NULL DEFAULT 'draft',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `editions`
--

CREATE TABLE `editions` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(150) NOT NULL,
  `year` smallint(5) UNSIGNED NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('draft','active','closed','archived') NOT NULL DEFAULT 'draft',
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `general_ranking_visibility` enum('hidden','visible') NOT NULL DEFAULT 'hidden',
  `general_ranking_access` enum('public','members') NOT NULL DEFAULT 'members',
  `ranking_calculation_method` varchar(100) NOT NULL DEFAULT 'average_score',
  `score_max` int(10) UNSIGNED NOT NULL DEFAULT 20,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `editions`
--

INSERT INTO `editions` (`id`, `title`, `year`, `description`, `status`, `is_active`, `general_ranking_visibility`, `general_ranking_access`, `ranking_calculation_method`, `score_max`, `start_date`, `end_date`, `created_at`, `updated_at`) VALUES
(4, 'MANGASAN - EDITION DE TEST', 2026, 'EDITION DE TEST - MANGASAN 2026', 'active', 1, 'visible', 'members', 'average', 20, '2026-04-16', '2026-04-20', '2026-04-17 19:28:55', '2026-04-17 19:43:02');

-- --------------------------------------------------------

--
-- Structure de la table `edition_mangas`
--

CREATE TABLE `edition_mangas` (
  `id` int(10) UNSIGNED NOT NULL,
  `edition_id` int(10) UNSIGNED NOT NULL,
  `manga_id` int(10) UNSIGNED NOT NULL,
  `display_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `edition_mangas`
--

INSERT INTO `edition_mangas` (`id`, `edition_id`, `manga_id`, `display_order`, `is_visible`, `created_at`) VALUES
(3, 4, 5, 0, 1, '2026-04-17 19:29:09'),
(4, 4, 4, 0, 1, '2026-04-17 19:29:18'),
(5, 4, 3, 0, 1, '2026-04-17 19:29:26');

-- --------------------------------------------------------

--
-- Structure de la table `logs`
--

CREATE TABLE `logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action_type` varchar(100) NOT NULL,
  `target_type` varchar(100) DEFAULT NULL,
  `target_id` int(10) UNSIGNED DEFAULT NULL,
  `message` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `logs`
--

INSERT INTO `logs` (`id`, `user_id`, `action_type`, `target_type`, `target_id`, `message`, `ip_address`, `created_at`) VALUES
(1, 1, 'section_update', 'site_section', 1, 'Mise à jour de la section \"Présentation\".', '127.0.0.1', '2026-04-15 09:25:25'),
(2, 1, 'settings_update', 'site_settings', 1, 'Mise à jour des paramètres globaux du site.', '127.0.0.1', '2026-04-15 09:26:33'),
(3, 1, 'settings_update', 'site_settings', 1, 'Mise à jour des paramètres globaux du site.', '127.0.0.1', '2026-04-15 09:27:04'),
(4, 1, 'settings_update', 'site_settings', 1, 'Mise à jour des paramètres globaux du site.', '127.0.0.1', '2026-04-15 09:28:20'),
(5, 1, 'settings_update', 'site_settings', 1, 'Mise à jour des paramètres globaux du site.', '127.0.0.1', '2026-04-15 09:28:23'),
(6, 1, 'section_update', 'site_section', 2, 'Mise à jour de la section \"Édition actuelle\".', '127.0.0.1', '2026-04-15 09:28:59'),
(7, 1, 'settings_update', 'site_settings', 1, 'Mise à jour des paramètres globaux du site.', '127.0.0.1', '2026-04-15 09:29:37'),
(8, 1, 'settings_update', 'site_settings', 1, 'Mise à jour des paramètres globaux du site.', '127.0.0.1', '2026-04-15 09:29:40'),
(9, 1, 'settings_update', 'site_settings', 1, 'Mise à jour des paramètres globaux du site.', '127.0.0.1', '2026-04-15 09:29:53'),
(10, 1, 'edition_create', 'edition', 1, 'Création de l’édition \"Mangasan\".', '127.0.0.1', '2026-04-15 15:37:18'),
(11, 1, 'edition_update', 'edition', 1, 'Mise à jour de l’édition \"Mangasan\".', '127.0.0.1', '2026-04-15 15:38:31'),
(12, 1, 'edition_update', 'edition', 1, 'Mise à jour de l’édition \"Mangasan\".', '127.0.0.1', '2026-04-15 15:47:58'),
(13, 1, 'edition_update', 'edition', 1, 'Mise à jour de l’édition \"Mangasan\".', '127.0.0.1', '2026-04-15 15:48:04'),
(14, 1, 'edition_activate', 'edition', 1, 'Activation de l’édition \"Mangasan\".', '127.0.0.1', '2026-04-15 15:48:13'),
(15, 1, 'edition_deactivate', 'edition', 1, 'Désactivation de l’édition \"Mangasan\".', '127.0.0.1', '2026-04-15 15:48:20'),
(16, 1, 'edition_activate', 'edition', 1, 'Activation de l’édition \"Mangasan\".', '127.0.0.1', '2026-04-15 15:48:22'),
(17, 1, 'edition_deactivate', 'edition', 1, 'Désactivation de l’édition \"Mangasan\".', '127.0.0.1', '2026-04-15 15:48:24'),
(18, 1, 'edition_delete', 'edition', 1, 'Suppression de l’édition \"Mangasan\".', '127.0.0.1', '2026-04-15 15:49:14'),
(19, 1, 'section_update', 'site_section', 2, 'Mise à jour de la section \"Édition actuelle\".', '127.0.0.1', '2026-04-15 15:49:55'),
(20, 1, 'manga_create', 'manga', 1, 'Création du manga \"test\".', '127.0.0.1', '2026-04-16 08:19:46'),
(21, 1, 'edition_create', 'edition', 2, 'Création de l’édition \"test\".', '127.0.0.1', '2026-04-16 08:20:31'),
(22, 1, 'edition_manga_attach', 'edition_manga', 0, 'Rattachement du manga \"test\" à l’édition \"test 2026\".', '127.0.0.1', '2026-04-16 08:20:51'),
(23, 1, 'edition_activate', 'edition', 2, 'Activation de l’édition \"test\".', '127.0.0.1', '2026-04-16 08:21:02'),
(24, 1, 'edition_update', 'edition', 2, 'Mise à jour de l’édition \"test\".', '127.0.0.1', '2026-04-16 08:21:34'),
(25, 1, 'edition_deactivate', 'edition', 2, 'Désactivation de l’édition \"test\".', '127.0.0.1', '2026-04-16 08:46:02'),
(26, 1, 'manga_delete', 'manga', 1, 'Suppression du manga \"test\".', '127.0.0.1', '2026-04-16 08:46:42'),
(27, 1, 'edition_delete', 'edition', 2, 'Suppression de l’édition \"test\".', '127.0.0.1', '2026-04-16 08:46:54'),
(28, 1, 'settings_update', 'site_settings', 1, 'Mise à jour des paramètres globaux du site.', '127.0.0.1', '2026-04-16 09:29:47'),
(29, 1, 'manga_create', 'manga', 2, 'Création du manga \"Berserk\".', '127.0.0.1', '2026-04-16 09:52:42'),
(30, 1, 'edition_create', 'edition', 3, 'Création de l’édition \"édition test\".', '127.0.0.1', '2026-04-16 09:54:07'),
(31, 1, 'edition_manga_attach', 'edition_manga', 0, 'Rattachement du manga \"Berserk\" à l’édition \"édition test 2026\".', '127.0.0.1', '2026-04-16 09:54:28'),
(32, 1, 'review_create_user', 'review', 1, 'Création de la review utilisateur pour le manga \"Berserk\".', '127.0.0.1', '2026-04-16 10:03:19'),
(33, 1, 'review_update_user', 'review', 1, 'Mise à jour de la review utilisateur pour le manga \"Berserk\".', '127.0.0.1', '2026-04-16 10:03:22'),
(34, 1, 'review_lock', 'review', 1, 'Verrouillage de la review du manga \"Berserk\".', '127.0.0.1', '2026-04-16 10:03:58'),
(35, 1, 'edition_update', 'edition', 3, 'Mise à jour de l’édition \"édition test\".', '127.0.0.1', '2026-04-17 10:52:33'),
(36, 1, 'edition_update', 'edition', 3, 'Mise à jour de l’édition \"édition test\".', '127.0.0.1', '2026-04-17 10:53:45'),
(37, 1, 'edition_update', 'edition', 3, 'Mise à jour de l’édition \"édition test\".', '127.0.0.1', '2026-04-17 11:01:37'),
(38, 1, 'edition_update', 'edition', 3, 'Mise à jour de l’édition \"édition test\".', '127.0.0.1', '2026-04-17 11:07:10'),
(39, 1, 'edition_update', 'edition', 3, 'Mise à jour de l’édition \"édition test\".', '127.0.0.1', '2026-04-17 11:15:41'),
(40, 1, 'user_create', 'user', 2, 'Création de l’utilisateur \"test\".', '127.0.0.1', '2026-04-17 12:01:34'),
(41, 2, 'review_create_user', 'review', 2, 'Création de la review utilisateur pour le manga \"Berserk\".', '127.0.0.1', '2026-04-17 12:03:59'),
(42, 1, 'user_update', 'user', 2, 'Mise à jour de l’utilisateur \"test\".', '127.0.0.1', '2026-04-17 12:06:48'),
(43, 1, 'edition_delete', 'edition', 3, 'Suppression de l’édition \"édition test\".', '127.0.0.1', '2026-04-17 12:10:05'),
(44, 1, 'manga_delete', 'manga', 2, 'Suppression du manga \"Berserk\".', '127.0.0.1', '2026-04-17 12:10:12'),
(45, 1, 'section_toggle_visibility', 'site_section', 3, 'Section \"Anciennes éditions\" désormais masquée.', '127.0.0.1', '2026-04-17 18:54:46'),
(46, 1, 'section_toggle_visibility', 'site_section', 4, 'Section \"Concours dessin\" désormais masquée.', '127.0.0.1', '2026-04-17 18:54:48'),
(47, 1, 'section_toggle_visibility', 'site_section', 5, 'Section \"Vidéos\" désormais masquée.', '127.0.0.1', '2026-04-17 18:54:49'),
(48, 1, 'settings_update', 'site_settings', 1, 'Mise à jour des paramètres globaux du site.', '127.0.0.1', '2026-04-17 18:59:16'),
(49, 1, 'manga_create', 'manga', 3, 'Création du manga \"Manga A\".', '127.0.0.1', '2026-04-17 19:26:02'),
(50, 1, 'manga_create', 'manga', 4, 'Création du manga \"Manga B\".', '127.0.0.1', '2026-04-17 19:27:12'),
(51, 1, 'manga_create', 'manga', 5, 'Création du manga \"Manga C\".', '127.0.0.1', '2026-04-17 19:27:57'),
(52, 1, 'edition_create', 'edition', 4, 'Création de l’édition \"MANGASAN - EDITION DE TEST\".', '127.0.0.1', '2026-04-17 19:28:55'),
(53, 1, 'edition_manga_attach', 'edition_manga', 0, 'Rattachement du manga \"Manga C\" à l’édition \"MANGASAN - EDITION DE TEST 2026\".', '127.0.0.1', '2026-04-17 19:29:09'),
(54, 1, 'edition_manga_attach', 'edition_manga', 0, 'Rattachement du manga \"Manga B\" à l’édition \"MANGASAN - EDITION DE TEST 2026\".', '127.0.0.1', '2026-04-17 19:29:18'),
(55, 1, 'edition_manga_attach', 'edition_manga', 0, 'Rattachement du manga \"Manga A\" à l’édition \"MANGASAN - EDITION DE TEST 2026\".', '127.0.0.1', '2026-04-17 19:29:26'),
(56, 1, 'review_create_user', 'review', 3, 'Création de la review utilisateur pour le manga \"Manga A\".', '127.0.0.1', '2026-04-17 19:34:04'),
(57, 1, 'review_create_user', 'review', 4, 'Création de la review utilisateur pour le manga \"Manga B\".', '127.0.0.1', '2026-04-17 19:35:31'),
(58, 1, 'review_create_user', 'review', 5, 'Création de la review utilisateur pour le manga \"Manga C\".', '127.0.0.1', '2026-04-17 19:35:57'),
(59, 1, 'review_update_user', 'review', 5, 'Mise à jour de la review utilisateur pour le manga \"Manga C\".', '127.0.0.1', '2026-04-17 19:36:49'),
(60, 2, 'review_create_user', 'review', 6, 'Création de la review utilisateur pour le manga \"Manga A\".', '127.0.0.1', '2026-04-17 19:37:34'),
(61, 2, 'review_create_user', 'review', 7, 'Création de la review utilisateur pour le manga \"Manga B\".', '127.0.0.1', '2026-04-17 19:38:14'),
(62, 2, 'review_create_user', 'review', 8, 'Création de la review utilisateur pour le manga \"Manga C\".', '127.0.0.1', '2026-04-17 19:38:52'),
(63, 1, 'edition_update', 'edition', 4, 'Mise à jour de l’édition \"MANGASAN - EDITION DE TEST\".', '127.0.0.1', '2026-04-17 19:43:02'),
(64, 1, 'review_lock', 'review', 6, 'Verrouillage de la review du manga \"Manga A\".', '127.0.0.1', '2026-04-17 19:43:12'),
(65, 1, 'review_lock', 'review', 4, 'Verrouillage de la review du manga \"Manga B\".', '127.0.0.1', '2026-04-17 19:43:14'),
(66, 1, 'review_lock', 'review', 8, 'Verrouillage de la review du manga \"Manga C\".', '127.0.0.1', '2026-04-17 19:43:16'),
(67, 1, 'review_lock', 'review', 3, 'Verrouillage de la review du manga \"Manga A\".', '127.0.0.1', '2026-04-17 19:43:18'),
(68, 1, 'review_lock', 'review', 7, 'Verrouillage de la review du manga \"Manga B\".', '127.0.0.1', '2026-04-17 19:43:20'),
(69, 1, 'review_lock', 'review', 5, 'Verrouillage de la review du manga \"Manga C\".', '127.0.0.1', '2026-04-17 19:43:22');

-- --------------------------------------------------------

--
-- Structure de la table `mangas`
--

CREATE TABLE `mangas` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `author` varchar(255) DEFAULT NULL,
  `illustrator` varchar(255) DEFAULT NULL,
  `publisher` varchar(255) DEFAULT NULL,
  `summary` text DEFAULT NULL,
  `card_image` varchar(255) DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `video_url` varchar(500) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `mangas`
--

INSERT INTO `mangas` (`id`, `title`, `subtitle`, `author`, `illustrator`, `publisher`, `summary`, `card_image`, `cover_image`, `video_url`, `status`, `created_at`, `updated_at`) VALUES
(3, 'Manga A', 'L\'épopée de A', 'ABD', 'ADB', 'Mangasan', 'L\'histoire de A qui parcours le continent de l\'alphabet', NULL, NULL, NULL, 'active', '2026-04-17 19:26:02', '2026-04-17 19:26:02'),
(4, 'Manga B', 'La revanche de B', 'BDA', 'BAD', 'Mangasan', 'La sombre histoire de B qui parcours le continent de Alphabet pour sa vengeance', NULL, NULL, NULL, 'active', '2026-04-17 19:27:12', '2026-04-17 19:27:12'),
(5, 'Manga C', 'La Comédie divine de C', 'CAD', 'CDA', 'Mangasan', 'Vivez les histoires rocambolesque de C', NULL, NULL, NULL, 'active', '2026-04-17 19:27:57', '2026-04-17 19:27:57');

-- --------------------------------------------------------

--
-- Structure de la table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `edition_id` int(10) UNSIGNED NOT NULL,
  `manga_id` int(10) UNSIGNED NOT NULL,
  `story_score` decimal(5,2) NOT NULL,
  `art_score` decimal(5,2) NOT NULL,
  `universe_score` decimal(5,2) NOT NULL,
  `message_score` decimal(5,2) NOT NULL,
  `score` decimal(5,2) NOT NULL,
  `personal_rank` int(10) UNSIGNED NOT NULL,
  `review_text` text DEFAULT NULL,
  `status` enum('editable','locked') NOT NULL DEFAULT 'editable',
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `locked_at` datetime DEFAULT NULL,
  `locked_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `reviews`
--

INSERT INTO `reviews` (`id`, `user_id`, `edition_id`, `manga_id`, `story_score`, `art_score`, `universe_score`, `message_score`, `score`, `personal_rank`, `review_text`, `status`, `is_locked`, `created_at`, `updated_at`, `locked_at`, `locked_by`) VALUES
(3, 1, 4, 3, 14.00, 12.00, 16.00, 15.00, 14.25, 2, 'L\'histoire reste du vu et revus, mais est efficace\r\n\r\nle dessin n\'est pas le fort mais reste très lisibles,\r\nl\'univers est incroyable,  facile à s\'y plonger !\r\nles dialogues et thème aborder sont interessant !', 'locked', 1, '2026-04-17 19:34:04', '2026-04-17 19:43:18', '2026-04-17 19:43:18', 1),
(4, 1, 4, 4, 18.00, 16.00, 16.00, 14.00, 16.00, 1, 'Histoire se déroulant dans le même univers de Manga A\r\nle dessin est nettement mieux, voir même incroyable\r\n\r\ncela reste une histoire de vengeance classique, mais finement bien menée \r\nles combats sont incroyable', 'locked', 1, '2026-04-17 19:35:31', '2026-04-17 19:43:14', '2026-04-17 19:43:14', 1),
(5, 1, 4, 5, 10.00, 18.00, 12.00, 15.00, 13.75, 3, 'Un manga accès sur la comédie, le style de dessin aide grandement au mangas, les visuelles sont efficaces, mais l\'univers existe que pour la blague', 'locked', 1, '2026-04-17 19:35:57', '2026-04-17 19:43:21', '2026-04-17 19:43:21', 1),
(6, 2, 4, 3, 20.00, 15.00, 18.00, 16.00, 17.25, 1, 'meilleur manga de la seleciton', 'locked', 1, '2026-04-17 19:37:34', '2026-04-17 19:43:12', '2026-04-17 19:43:12', 1),
(7, 2, 4, 4, 5.00, 20.00, 14.00, 5.00, 11.00, 3, 'trop dark , le dessin est incroyable parcontre \r\n\r\nmême continent que manga A', 'locked', 1, '2026-04-17 19:38:14', '2026-04-17 19:43:20', '2026-04-17 19:43:20', 1),
(8, 2, 4, 5, 16.00, 18.00, 19.00, 14.00, 16.75, 2, 'Trop drôle\r\n\r\nles blagues et scène sont bien trouvé \r\nde bonne rigolade', 'locked', 1, '2026-04-17 19:38:52', '2026-04-17 19:43:16', '2026-04-17 19:43:16', 1);

-- --------------------------------------------------------

--
-- Structure de la table `roles`
--

CREATE TABLE `roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `label` varchar(100) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `roles`
--

INSERT INTO `roles` (`id`, `name`, `label`, `created_at`) VALUES
(1, 'visitor', 'Visiteur', '2026-03-23 12:56:01'),
(2, 'member', 'Membre', '2026-03-23 12:56:01'),
(3, 'admin', 'Administrateur', '2026-03-23 12:56:01');

-- --------------------------------------------------------

--
-- Structure de la table `site_sections`
--

CREATE TABLE `site_sections` (
  `id` int(10) UNSIGNED NOT NULL,
  `section_key` varchar(100) NOT NULL,
  `section_type` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `subtilte` varchar(255) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `media_type` enum('none','image','video') NOT NULL DEFAULT 'none',
  `media_value` varchar(255) DEFAULT NULL,
  `display_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `site_sections`
--

INSERT INTO `site_sections` (`id`, `section_key`, `section_type`, `title`, `subtilte`, `content`, `media_type`, `media_value`, `display_order`, `is_visible`, `updated_at`, `updated_by`) VALUES
(1, 'homepage_intro', '', 'Présentation', NULL, 'Ceci est un test', 'none', NULL, 1, 1, '2026-04-15 09:25:25', 1),
(2, 'current_edition', '', 'Édition actuelle', NULL, 'Découvrez la sélection de l’édition en cours.', 'none', NULL, 2, 1, '2026-04-15 15:49:55', 1),
(3, 'past_editions', '', 'Anciennes éditions', NULL, 'Consultez les anciennes sélections et résultats.', 'none', NULL, 3, 0, '2026-04-17 18:54:46', 1),
(4, 'drawing_contest', '', 'Concours dessin', NULL, 'Découvrez le concours dessin Mangasan.', 'none', NULL, 4, 0, '2026-04-17 18:54:48', 1),
(5, 'videos', '', 'Vidéos', NULL, 'Vidéos de présentation et contenus associés.', 'none', NULL, 5, 0, '2026-04-17 18:54:49', 1),
(6, 'rules', '', 'Règlement', NULL, 'Règlement et modalités de participation.', 'none', NULL, 6, 1, '2026-03-23 12:56:01', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `site_settings`
--

CREATE TABLE `site_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `site_title` varchar(255) NOT NULL DEFAULT 'Mangasan',
  `site_title_type` enum('text','image') NOT NULL DEFAULT 'text',
  `primary_color` varchar(20) DEFAULT NULL,
  `secondary_color` varchar(20) DEFAULT NULL,
  `background_color` varchar(20) DEFAULT NULL,
  `text_color` varchar(20) DEFAULT NULL,
  `accent_color` varchar(20) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `hero_background_type` enum('color','image','video') NOT NULL DEFAULT 'image',
  `hero_background_value` varchar(255) DEFAULT NULL,
  `homepage_intro` text DEFAULT NULL,
  `hero_text_color` varchar(20) DEFAULT NULL,
  `hero_login_position` enum('left','right') NOT NULL DEFAULT 'right',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `site_settings`
--

INSERT INTO `site_settings` (`id`, `site_title`, `site_title_type`, `primary_color`, `secondary_color`, `background_color`, `text_color`, `accent_color`, `logo_path`, `hero_background_type`, `hero_background_value`, `homepage_intro`, `hero_text_color`, `hero_login_position`, `updated_at`, `updated_by`) VALUES
(1, 'Mangasan', 'text', '#c2410c', '#3b2a20', '#12100e', '#f5f1ec', '#f97316', NULL, 'color', NULL, 'Bienvenue sur le site de Mangasan', '#fff7ed', 'right', '2026-04-17 18:59:16', 1);

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `username` varchar(100) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `display_name` varchar(150) DEFAULT NULL,
  `class_name` varchar(100) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `role_id`, `username`, `first_name`, `last_name`, `display_name`, `class_name`, `password_hash`, `status`, `must_change_password`, `created_at`, `updated_at`, `last_login_at`) VALUES
(1, 3, 'admin', 'admin', 'admin', 'Admin Rnt', NULL, '$2y$10$TaSY5qG62LK5aIF3LgsQ2usKcAoZBP1yhmRvgdrEKpGlqt65YIV4.', 'active', 0, '2026-03-23 14:01:37', '2026-03-23 14:01:37', NULL),
(2, 2, 'test', 'test', 'test', 'test', 'test', '$2y$10$nXGESMkGnHbx6doToLTBDut7H3CGe/bK/9lIF94trcoxTtdugY78.', 'active', 1, '2026-04-17 12:01:34', '2026-04-17 12:06:48', NULL);

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `drawing_contests`
--
ALTER TABLE `drawing_contests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_drawing_contests_edition_id` (`edition_id`),
  ADD KEY `idx_drawing_contests_status` (`status`);

--
-- Index pour la table `editions`
--
ALTER TABLE `editions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_editions_year` (`year`),
  ADD KEY `idx_editions_status` (`status`),
  ADD KEY `idx_editions_is_active` (`is_active`);

--
-- Index pour la table `edition_mangas`
--
ALTER TABLE `edition_mangas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_edition_mangas_edition_manga` (`edition_id`,`manga_id`),
  ADD UNIQUE KEY `uq_edition_manga` (`edition_id`,`manga_id`),
  ADD KEY `idx_edition_mangas_edition_id` (`edition_id`),
  ADD KEY `idx_edition_mangas_manga_id` (`manga_id`),
  ADD KEY `idx_edition_mangas_display_order` (`display_order`);

--
-- Index pour la table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_logs_user_id` (`user_id`),
  ADD KEY `idx_logs_action_type` (`action_type`),
  ADD KEY `idx_logs_target_type_target_id` (`target_type`,`target_id`),
  ADD KEY `idx_logs_created_at` (`created_at`);

--
-- Index pour la table `mangas`
--
ALTER TABLE `mangas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mangas_status` (`status`),
  ADD KEY `idx_mangas_title` (`title`);

--
-- Index pour la table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_reviews_user_edition_manga` (`user_id`,`edition_id`,`manga_id`),
  ADD UNIQUE KEY `uq_review_user_edition_manga` (`user_id`,`edition_id`,`manga_id`),
  ADD KEY `idx_reviews_user_id` (`user_id`),
  ADD KEY `idx_reviews_edition_id` (`edition_id`),
  ADD KEY `idx_reviews_manga_id` (`manga_id`),
  ADD KEY `idx_reviews_score` (`score`),
  ADD KEY `idx_reviews_is_locked` (`is_locked`),
  ADD KEY `idx_reviews_locked_by` (`locked_by`);

--
-- Index pour la table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_roles_name` (`name`);

--
-- Index pour la table `site_sections`
--
ALTER TABLE `site_sections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_site_sections_section_key` (`section_key`),
  ADD KEY `idx_site_sections_display_order` (`display_order`),
  ADD KEY `idx_site_sections_updated_by` (`updated_by`);

--
-- Index pour la table `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_site_settings_updated_by` (`updated_by`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_username` (`username`),
  ADD KEY `idx_users_role_id` (`role_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `drawing_contests`
--
ALTER TABLE `drawing_contests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `editions`
--
ALTER TABLE `editions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `edition_mangas`
--
ALTER TABLE `edition_mangas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `logs`
--
ALTER TABLE `logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT pour la table `mangas`
--
ALTER TABLE `mangas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `site_sections`
--
ALTER TABLE `site_sections`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `site_settings`
--
ALTER TABLE `site_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `drawing_contests`
--
ALTER TABLE `drawing_contests`
  ADD CONSTRAINT `fk_drawing_contests_edition_id` FOREIGN KEY (`edition_id`) REFERENCES `editions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `edition_mangas`
--
ALTER TABLE `edition_mangas`
  ADD CONSTRAINT `fk_edition_mangas_edition_id` FOREIGN KEY (`edition_id`) REFERENCES `editions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_edition_mangas_manga_id` FOREIGN KEY (`manga_id`) REFERENCES `mangas` (`id`) ON UPDATE CASCADE;

--
-- Contraintes pour la table `logs`
--
ALTER TABLE `logs`
  ADD CONSTRAINT `fk_logs_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `fk_reviews_edition_id` FOREIGN KEY (`edition_id`) REFERENCES `editions` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_reviews_locked_by` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_reviews_manga_id` FOREIGN KEY (`manga_id`) REFERENCES `mangas` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_reviews_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Contraintes pour la table `site_sections`
--
ALTER TABLE `site_sections`
  ADD CONSTRAINT `fk_site_sections_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `site_settings`
--
ALTER TABLE `site_settings`
  ADD CONSTRAINT `fk_site_settings_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role_id` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;


ALTER TABLE editions
ADD COLUMN review_form_type varchar(100) NOT NULL DEFAULT 'classic_score' AFTER ranking_calculation_method;

ALTER TABLE reviews
ADD COLUMN review_data longtext DEFAULT NULL AFTER review_text;

UPDATE editions
SET review_form_type = 'mangasan_reading_sheet_v1',
    ranking_calculation_method = 'rank_points'
WHERE is_active = 1;