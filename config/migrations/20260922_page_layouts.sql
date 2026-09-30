-- Mangasan - Éditeur visuel de mise en page
-- À exécuter une seule fois sur la base locale avant d'utiliser l'éditeur.

CREATE TABLE IF NOT EXISTS `page_layouts` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_key` varchar(100) NOT NULL,
  `draft_layout` longtext DEFAULT NULL,
  `published_layout` longtext DEFAULT NULL,
  `previous_layout` longtext DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `published_by` int(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_page_layouts_page_key` (`page_key`),
  KEY `idx_page_layouts_updated_by` (`updated_by`),
  KEY `idx_page_layouts_published_by` (`published_by`),
  CONSTRAINT `fk_page_layouts_updated_by`
    FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_page_layouts_published_by`
    FOREIGN KEY (`published_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `page_layouts` (`page_key`)
VALUES ('home')
ON DUPLICATE KEY UPDATE `page_key` = VALUES(`page_key`);
