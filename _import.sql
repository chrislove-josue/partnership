SET FOREIGN_KEY_CHECKS=0;
-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- HÃ´te : 127.0.0.1
-- GÃ©nÃ©rÃ© le : ven. 20 nov. 2026 Ã  00:28
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
-- Base de donnÃ©es : `partnership`
--

-- --------------------------------------------------------

--
-- Structure de la table `accounts`
--

CREATE TABLE `accounts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `provider` varchar(255) NOT NULL,
  `provider_account_id` varchar(255) NOT NULL,
  `type` varchar(255) DEFAULT NULL,
  `refresh_token` text DEFAULT NULL,
  `access_token` text DEFAULT NULL,
  `expires_at` int(11) DEFAULT NULL,
  `token_type` varchar(255) DEFAULT NULL,
  `scope` text DEFAULT NULL,
  `id_token` text DEFAULT NULL,
  `session_state` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- DÃ©chargement des donnÃ©es de la table `cache`
--

INSERT INTO `cache` (`key`, `value`, `expiration`) VALUES
('suivi-des-partenariats-cache-livewire-rate-limiter:056fc329aaaa757d31db450f525da23fde4d1b36', 'i:1;', 1795130661),
('suivi-des-partenariats-cache-livewire-rate-limiter:056fc329aaaa757d31db450f525da23fde4d1b36:timer', 'i:1795130661;', 1795130661),
('suivi-des-partenariats-cache-spatie.permission.cache', 'a:3:{s:5:\"alias\";a:5:{s:1:\"a\";s:2:\"id\";s:1:\"b\";s:4:\"name\";s:1:\"c\";s:10:\"guard_name\";s:1:\"r\";s:5:\"roles\";s:1:\"j\";s:9:\"is_system\";}s:11:\"permissions\";a:8:{i:0;a:4:{s:1:\"a\";i:1;s:1:\"b\";s:20:\"READ_OWN_CHURCH_DATA\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:5:{i:0;i:1;i:1;i:2;i:2;i:3;i:3;i:4;i:4;i:5;}}i:1;a:4:{s:1:\"a\";i:2;s:1:\"b\";s:24:\"READ_CHILD_CHURCHES_DATA\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:1;i:1;i:2;i:2;i:3;}}i:2;a:4:{s:1:\"a\";i:3;s:1:\"b\";s:23:\"MANAGE_SUBGROUP_PASTORS\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:3;a:4:{s:1:\"a\";i:4;s:1:\"b\";s:20:\"MANAGE_LOCAL_PASTORS\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:1;i:1;i:2;i:2;i:3;}}i:4;a:4:{s:1:\"a\";i:5;s:1:\"b\";s:12:\"MANAGE_STAFF\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:4:{i:0;i:1;i:1;i:2;i:2;i:3;i:3;i:4;}}i:5;a:4:{s:1:\"a\";i:6;s:1:\"b\";s:15:\"MANAGE_CHURCHES\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:6;a:4:{s:1:\"a\";i:7;s:1:\"b\";s:14:\"VIEW_DASHBOARD\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:5:{i:0;i:1;i:1;i:2;i:2;i:3;i:3;i:4;i:4;i:5;}}i:7;a:4:{s:1:\"a\";i:8;s:1:\"b\";s:18:\"MANAGE_OWN_PROFILE\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:5:{i:0;i:1;i:1;i:2;i:2;i:3;i:3;i:4;i:4;i:5;}}}s:5:\"roles\";a:5:{i:0;a:4:{s:1:\"a\";i:1;s:1:\"b\";s:11:\"SUPER_ADMIN\";s:1:\"c\";s:3:\"web\";s:1:\"j\";i:1;}i:1;a:4:{s:1:\"a\";i:2;s:1:\"b\";s:12:\"GROUP_PASTOR\";s:1:\"c\";s:3:\"web\";s:1:\"j\";i:1;}i:2;a:4:{s:1:\"a\";i:3;s:1:\"b\";s:15:\"SUBGROUP_PASTOR\";s:1:\"c\";s:3:\"web\";s:1:\"j\";i:1;}i:3;a:4:{s:1:\"a\";i:4;s:1:\"b\";s:12:\"LOCAL_PASTOR\";s:1:\"c\";s:3:\"web\";s:1:\"j\";i:1;}i:4;a:4:{s:1:\"a\";i:5;s:1:\"b\";s:5:\"STAFF\";s:1:\"c\";s:3:\"web\";s:1:\"j\";i:1;}}}', 1795213940);

-- --------------------------------------------------------

--
-- Structure de la table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `category_dons`
--

CREATE TABLE `category_dons` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `color` varchar(20) NOT NULL DEFAULT '#3B82F6',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- DÃ©chargement des donnÃ©es de la table `category_dons`
--

INSERT INTO `category_dons` (`id`, `name`, `color`, `created_at`, `updated_at`) VALUES
(1, 'DÃ®mes', '#10B981', '2026-10-17 10:02:35', '2026-10-17 10:02:35'),
(2, 'Offrandes', '#3B82F6', '2026-10-17 10:02:35', '2026-10-17 10:02:35'),
(3, 'Rhapsodie des RÃ©alitÃ©s', '#F59E0B', '2026-10-17 10:02:35', '2026-10-17 10:02:35'),
(4, 'Mission InnerCity', '#06B6D4', '2026-10-17 10:02:35', '2026-10-17 10:02:35'),
(5, 'Ã‰cole de GuÃ©risons', '#8B5CF6', '2026-10-17 10:02:35', '2026-10-17 10:02:35');

-- --------------------------------------------------------

--
-- Structure de la table `cellules`
--

CREATE TABLE `cellules` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `church_id` bigint(20) UNSIGNED NOT NULL,
  `leader_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `churches`
--

CREATE TABLE `churches` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('GROUP','SUB_GROUP','LOCAL') NOT NULL,
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `manager_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- DÃ©chargement des donnÃ©es de la table `churches`
--

INSERT INTO `churches` (`id`, `name`, `type`, `parent_id`, `manager_id`, `created_at`, `updated_at`) VALUES
(1, 'CE SIKECODJI', 'GROUP', NULL, 2, '2026-10-17 10:02:34', '2026-11-19 17:16:24'),
(2, 'CE CALAVI', 'SUB_GROUP', 1, 3, '2026-11-19 16:56:22', '2026-11-19 17:07:07'),
(3, 'CE FIDJROSSE', 'LOCAL', 2, 4, '2026-11-19 16:56:22', '2026-11-19 17:06:43'),
(4, 'CE PARAKOU', 'LOCAL', 2, 5, '2026-11-19 16:56:22', '2026-11-19 17:05:30'),
(5, 'CE BOHICON', 'LOCAL', 1, 6, '2026-11-19 16:56:22', '2026-11-19 17:05:41'),
(6, 'CE HEVIE', 'LOCAL', 1, 7, '2026-11-19 16:56:22', '2026-11-19 17:05:50'),
(7, 'CE PAHOU', 'LOCAL', 1, 8, '2026-11-19 16:56:23', '2026-11-19 17:06:08'),
(8, 'CE COME 2', 'LOCAL', 2, 9, '2026-11-19 16:56:23', '2026-11-19 17:06:00'),
(9, 'CE SEME', 'LOCAL', 1, 10, '2026-11-19 16:56:23', '2026-11-19 16:58:36'),
(10, 'CE LOKOSSA', 'LOCAL', 2, 11, '2026-11-19 16:56:23', '2026-11-19 17:06:17'),
(11, 'CE POBE', 'LOCAL', 1, 12, '2026-11-19 16:56:24', '2026-11-19 17:06:31');

-- --------------------------------------------------------

--
-- Structure de la table `donations`
--

CREATE TABLE `donations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `date` datetime NOT NULL DEFAULT current_timestamp(),
  `partner_id` bigint(20) UNSIGNED NOT NULL,
  `church_id` bigint(20) UNSIGNED NOT NULL,
  `category_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `login_histories`
--

CREATE TABLE `login_histories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `event` varchar(16) NOT NULL,
  `source` varchar(16) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `reference` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- DÃ©chargement des donnÃ©es de la table `login_histories`
--

INSERT INTO `login_histories` (`id`, `user_id`, `event`, `source`, `ip_address`, `user_agent`, `reference`, `created_at`) VALUES
(1, 1, 'login', 'web', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36', NULL, '2026-11-19 22:28:46'),
(2, 1, 'login', 'web', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36', NULL, '2026-11-19 22:28:46'),
(3, 1, 'logout', 'mobile', '172.16.206.64', 'okhttp/4.12.0', '8', '2026-11-19 22:32:54'),
(4, 1, 'login', 'mobile', '172.16.206.64', 'okhttp/4.12.0', '9', '2026-11-19 22:33:24'),
(5, 1, 'login', 'mobile', '172.16.206.66', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.9711', '10', '2026-11-19 22:42:01'),
(6, 1, 'login', 'web', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) OpenCode/2.3.2 Chrome/152.0.7977.167 Electron/44.9.1 Safari/537.36', NULL, '2026-11-19 22:45:23'),
(7, 1, 'login', 'web', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) OpenCode/2.3.2 Chrome/152.0.7977.167 Electron/44.9.1 Safari/537.36', NULL, '2026-11-19 22:45:23'),
(8, 1, 'login', 'mobile', '172.16.206.64', 'okhttp/4.12.0', '11', '2026-11-19 22:49:41'),
(9, 1, 'login', 'mobile', '172.16.206.66', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.9711', '12', '2026-11-19 22:57:33'),
(10, 1, 'login', 'mobile', '172.16.206.66', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.9711', '13', '2026-11-19 22:57:41'),
(11, 1, 'login', 'mobile', '172.16.206.64', 'okhttp/4.12.0', '14', '2026-11-19 22:59:18'),
(12, 1, 'login', 'mobile', '172.16.206.66', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.9711', '15', '2026-11-19 23:02:30'),
(13, 1, 'login', 'mobile', '172.16.206.64', 'okhttp/4.12.0', '16', '2026-11-19 23:19:11'),
(14, 1, 'logout', 'web', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36', NULL, '2026-11-19 23:20:54'),
(15, 1, 'logout', 'web', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36', NULL, '2026-11-19 23:20:54'),
(16, 2, 'login', 'web', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36', NULL, '2026-11-19 23:21:01'),
(17, 2, 'login', 'web', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36', NULL, '2026-11-19 23:21:01'),
(18, 1, 'logout', 'web', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) OpenCode/2.3.2 Chrome/152.0.7977.167 Electron/44.9.1 Safari/537.36', NULL, '2026-11-19 23:23:14'),
(19, 1, 'logout', 'web', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) OpenCode/2.3.2 Chrome/152.0.7977.167 Electron/44.9.1 Safari/537.36', NULL, '2026-11-19 23:23:14'),
(20, 2, 'login', 'web', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) OpenCode/2.3.2 Chrome/152.0.7977.167 Electron/44.9.1 Safari/537.36', NULL, '2026-11-19 23:23:21'),
(21, 2, 'login', 'web', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) OpenCode/2.3.2 Chrome/152.0.7977.167 Electron/44.9.1 Safari/537.36', NULL, '2026-11-19 23:23:21');

-- --------------------------------------------------------

--
-- Structure de la table `magic_tokens`
--

CREATE TABLE `magic_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `token` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- DÃ©chargement des donnÃ©es de la table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2026_10_16_173212_create_permission_tables', 1),
(5, '2026_10_16_200001_create_churches_table', 1),
(6, '2026_10_16_200002_add_church_columns_to_users_table', 1),
(7, '2026_10_16_200003_create_cellules_table', 1),
(8, '2026_10_16_200004_create_partners_table', 1),
(9, '2026_10_16_200005_create_category_dons_table', 1),
(10, '2026_10_16_200006_create_donations_table', 1),
(11, '2026_10_16_200007_create_notifications_table', 1),
(12, '2026_10_16_200008_create_magic_tokens_table', 1),
(13, '2026_10_16_200009_create_accounts_table', 1),
(14, '2026_11_19_000000_rename_donation_categories_to_french', 2),
(15, '2026_11_19_000001_normalize_donation_categories_accents', 2),
(16, '2026_11_19_183926_create_personal_access_tokens_table', 3),
(17, '2026_11_19_230000_create_login_histories_table', 4);

-- --------------------------------------------------------

--
-- Structure de la table `model_has_permissions`
--

CREATE TABLE `model_has_permissions` (
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `model_has_roles`
--

CREATE TABLE `model_has_roles` (
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- DÃ©chargement des donnÃ©es de la table `model_has_roles`
--

INSERT INTO `model_has_roles` (`role_id`, `model_type`, `model_id`) VALUES
(1, 'App\\Models\\User', 1),
(2, 'App\\Models\\User', 2),
(3, 'App\\Models\\User', 2),
(3, 'App\\Models\\User', 3),
(4, 'App\\Models\\User', 2),
(4, 'App\\Models\\User', 3),
(4, 'App\\Models\\User', 4),
(4, 'App\\Models\\User', 5),
(4, 'App\\Models\\User', 6),
(4, 'App\\Models\\User', 7),
(4, 'App\\Models\\User', 8),
(4, 'App\\Models\\User', 9),
(4, 'App\\Models\\User', 10),
(4, 'App\\Models\\User', 11),
(4, 'App\\Models\\User', 12);

-- --------------------------------------------------------

--
-- Structure de la table `notifications`
--

CREATE TABLE `notifications` (
  `id` char(36) NOT NULL,
  `type` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `partners`
--

CREATE TABLE `partners` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `handle_kc` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `cellule_id` bigint(20) UNSIGNED DEFAULT NULL,
  `church_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `permissions`
--

CREATE TABLE `permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- DÃ©chargement des donnÃ©es de la table `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES
(1, 'READ_OWN_CHURCH_DATA', 'web', '2026-10-17 10:02:33', '2026-10-17 10:02:33'),
(2, 'READ_CHILD_CHURCHES_DATA', 'web', '2026-10-17 10:02:33', '2026-10-17 10:02:33'),
(3, 'MANAGE_SUBGROUP_PASTORS', 'web', '2026-10-17 10:02:33', '2026-10-17 10:02:33'),
(4, 'MANAGE_LOCAL_PASTORS', 'web', '2026-10-17 10:02:34', '2026-10-17 10:02:34'),
(5, 'MANAGE_STAFF', 'web', '2026-10-17 10:02:34', '2026-10-17 10:02:34'),
(6, 'MANAGE_CHURCHES', 'web', '2026-10-17 10:02:34', '2026-10-17 10:02:34'),
(7, 'VIEW_DASHBOARD', 'web', '2026-10-17 10:02:34', '2026-10-17 10:02:34'),
(8, 'MANAGE_OWN_PROFILE', 'web', '2026-10-17 10:02:34', '2026-10-17 10:02:34');

-- --------------------------------------------------------

--
-- Structure de la table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- DÃ©chargement des donnÃ©es de la table `personal_access_tokens`
--

INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES
(16, 'App\\Models\\User', 1, 'mobile', '72c6ced20bce653bc8c0ed5ca126e925a172c0a02c2922e3c31bcde946a85947', '[\"*\"]', '2026-11-19 23:27:58', NULL, '2026-11-19 23:19:11', '2026-11-19 23:27:58');

-- --------------------------------------------------------

--
-- Structure de la table `roles`
--

CREATE TABLE `roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- DÃ©chargement des donnÃ©es de la table `roles`
--

INSERT INTO `roles` (`id`, `name`, `guard_name`, `is_system`, `created_at`, `updated_at`) VALUES
(1, 'SUPER_ADMIN', 'web', 1, '2026-10-17 10:02:34', '2026-10-17 10:02:34'),
(2, 'GROUP_PASTOR', 'web', 1, '2026-10-17 10:02:34', '2026-10-17 10:02:34'),
(3, 'SUBGROUP_PASTOR', 'web', 1, '2026-10-17 10:02:34', '2026-10-17 10:02:34'),
(4, 'LOCAL_PASTOR', 'web', 1, '2026-10-17 10:02:34', '2026-10-17 10:02:34'),
(5, 'STAFF', 'web', 1, '2026-10-17 10:02:34', '2026-10-17 10:02:34');

-- --------------------------------------------------------

--
-- Structure de la table `role_has_permissions`
--

CREATE TABLE `role_has_permissions` (
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- DÃ©chargement des donnÃ©es de la table `role_has_permissions`
--

INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(2, 1),
(2, 2),
(2, 3),
(3, 1),
(3, 2),
(4, 1),
(4, 2),
(4, 3),
(5, 1),
(5, 2),
(5, 3),
(5, 4),
(6, 1),
(6, 2),
(7, 1),
(7, 2),
(7, 3),
(7, 4),
(7, 5),
(8, 1),
(8, 2),
(8, 3),
(8, 4),
(8, 5);

-- --------------------------------------------------------

--
-- Structure de la table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- DÃ©chargement des donnÃ©es de la table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('6xuSf1jmtVOgsK68I8xK7fnbYIImXseWwSWeGHQd', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) OpenCode/2.3.2 Chrome/152.0.7977.167 Electron/44.9.1 Safari/537.36', 'eyJfdG9rZW4iOiJ5M20yTzNvRWdzVFVFczlpWGpDQzhWVnVEejVXWjRMQlRjZHNhOTVyIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2xvY2FsaG9zdDo4MDAwXC9sb2dpbiIsInJvdXRlIjoibG9naW4ifSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==', 1795128323),
('jAk32WeO6ZepvQ9LqnUif8K82FYhNVs6qT5rsRGV', NULL, '172.16.206.66', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.26100.9711', 'eyJfdG9rZW4iOiI2dWZWeG1pRFdGNHk4alAwYWJOUmhtRExRdlJmS3IxbEoxRGpNVWtQIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzE3Mi4xNi4yMDYuNjY6ODAwMFwvbG9naW4iLCJyb3V0ZSI6ImxvZ2luIn0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=', 1795126673),
('PUcP3DwwbkcxTv5MPCX7DQt0sXtqxaxPGDXQq7DO', 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) OpenCode/2.3.2 Chrome/152.0.7977.167 Electron/44.9.1 Safari/537.36', 'eyJfdG9rZW4iOiJFNlg1MndMTlYycnl0VlJRZmtMQjRGWWVXQUhzRzVIUTdvMkJWQWlWIiwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119LCJfcHJldmlvdXMiOnsidXJsIjoiaHR0cDpcL1wvbG9jYWxob3N0OjgwMDBcL2Rhc2hib2FyZFwvcGFydG5lcnNoaXAtcmVwb3J0Iiwicm91dGUiOiJmaWxhbWVudC5hZG1pbi5wYWdlcy5wYXJ0bmVyc2hpcC1yZXBvcnQifSwibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiOjIsInBhc3N3b3JkX2hhc2hfd2ViIjoiZGQ5MTMwMjY0NzcwODQ0ZTk4YzUyOTFjM2JiODU0OWZiMTliOWM2NGI3NGQ5MWNiNTk0MGUxZTlkODEwOGFkMyIsInRhYmxlcyI6eyI1YTQ3MjU2NDRlNmE2MWE5ODM1ZWRhZjQ4NTJiNWU2ZV9jb2x1bW5zIjpbeyJ0eXBlIjoiY29sdW1uIiwibmFtZSI6Im5hbWUiLCJsYWJlbCI6Ilx1MDBjOWdsaXNlIiwiaXNIaWRkZW4iOmZhbHNlLCJpc1RvZ2dsZWQiOnRydWUsImlzVG9nZ2xlYWJsZSI6ZmFsc2UsImlzVG9nZ2xlZEhpZGRlbkJ5RGVmYXVsdCI6bnVsbH0seyJ0eXBlIjoiY29sdW1uIiwibmFtZSI6InR5cGUiLCJsYWJlbCI6IlR5cGUiLCJpc0hpZGRlbiI6ZmFsc2UsImlzVG9nZ2xlZCI6dHJ1ZSwiaXNUb2dnbGVhYmxlIjpmYWxzZSwiaXNUb2dnbGVkSGlkZGVuQnlEZWZhdWx0IjpudWxsfSx7InR5cGUiOiJjb2x1bW4iLCJuYW1lIjoicGFydG5lcnNfY291bnQiLCJsYWJlbCI6IlBhcnRlbmFpcmVzIiwiaXNIaWRkZW4iOmZhbHNlLCJpc1RvZ2dsZWQiOnRydWUsImlzVG9nZ2xlYWJsZSI6ZmFsc2UsImlzVG9nZ2xlZEhpZGRlbkJ5RGVmYXVsdCI6bnVsbH0seyJ0eXBlIjoiY29sdW1uIiwibmFtZSI6InRvdGFsX2RvbmF0aW9ucyIsImxhYmVsIjoiTW9udGFudCB0b3RhbCIsImlzSGlkZGVuIjpmYWxzZSwiaXNUb2dnbGVkIjp0cnVlLCJpc1RvZ2dsZWFibGUiOmZhbHNlLCJpc1RvZ2dsZWRIaWRkZW5CeURlZmF1bHQiOm51bGx9XSwiYzQ0MzQzMTA3MGJlMWZiOWQyMTY2ZDg3YjJiYTRlMjlfY29sdW1ucyI6W3sidHlwZSI6ImNvbHVtbiIsIm5hbWUiOiJkYXRlIiwibGFiZWwiOiJEYXRlIiwiaXNIaWRkZW4iOmZhbHNlLCJpc1RvZ2dsZWQiOnRydWUsImlzVG9nZ2xlYWJsZSI6ZmFsc2UsImlzVG9nZ2xlZEhpZGRlbkJ5RGVmYXVsdCI6bnVsbH0seyJ0eXBlIjoiY29sdW1uIiwibmFtZSI6InBhcnRuZXIuZnVsbF9uYW1lIiwibGFiZWwiOiJQYXJ0ZW5haXJlIiwiaXNIaWRkZW4iOmZhbHNlLCJpc1RvZ2dsZWQiOnRydWUsImlzVG9nZ2xlYWJsZSI6ZmFsc2UsImlzVG9nZ2xlZEhpZGRlbkJ5RGVmYXVsdCI6bnVsbH0seyJ0eXBlIjoiY29sdW1uIiwibmFtZSI6ImNodXJjaC5uYW1lIiwibGFiZWwiOiJcdTAwYzlnbGlzZSIsImlzSGlkZGVuIjpmYWxzZSwiaXNUb2dnbGVkIjp0cnVlLCJpc1RvZ2dsZWFibGUiOmZhbHNlLCJpc1RvZ2dsZWRIaWRkZW5CeURlZmF1bHQiOm51bGx9LHsidHlwZSI6ImNvbHVtbiIsIm5hbWUiOiJjYXRlZ29yeS5uYW1lIiwibGFiZWwiOiJDYXRcdTAwZTlnb3JpZSIsImlzSGlkZGVuIjpmYWxzZSwiaXNUb2dnbGVkIjp0cnVlLCJpc1RvZ2dsZWFibGUiOmZhbHNlLCJpc1RvZ2dsZWRIaWRkZW5CeURlZmF1bHQiOm51bGx9LHsidHlwZSI6ImNvbHVtbiIsIm5hbWUiOiJhbW91bnQiLCJsYWJlbCI6Ik1vbnRhbnQiLCJpc0hpZGRlbiI6ZmFsc2UsImlzVG9nZ2xlZCI6dHJ1ZSwiaXNUb2dnbGVhYmxlIjpmYWxzZSwiaXNUb2dnbGVkSGlkZGVuQnlEZWZhdWx0IjpudWxsfV19LCJmaWxhbWVudCI6W119', 1795130759),
('T1RuPBKLDHovlFmHYgKrLMls0mS4DcAETohXnomq', 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36', 'eyJfdG9rZW4iOiJOaVp4eG9xR2hHdU1nRFlXMzZmZDVmbXlGcnFBVEFHMmlSZ1FtNnVCIiwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119LCJfcHJldmlvdXMiOnsidXJsIjoiaHR0cDpcL1wvbG9jYWxob3N0OjgwMDBcL2Rhc2hib2FyZFwvcGFydG5lcnNoaXAtcmVwb3J0Iiwicm91dGUiOiJmaWxhbWVudC5hZG1pbi5wYWdlcy5wYXJ0bmVyc2hpcC1yZXBvcnQifSwibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiOjIsInBhc3N3b3JkX2hhc2hfd2ViIjoiZGQ5MTMwMjY0NzcwODQ0ZTk4YzUyOTFjM2JiODU0OWZiMTliOWM2NGI3NGQ5MWNiNTk0MGUxZTlkODEwOGFkMyIsInRhYmxlcyI6eyI1YTQ3MjU2NDRlNmE2MWE5ODM1ZWRhZjQ4NTJiNWU2ZV9jb2x1bW5zIjpbeyJ0eXBlIjoiY29sdW1uIiwibmFtZSI6Im5hbWUiLCJsYWJlbCI6Ilx1MDBjOWdsaXNlIiwiaXNIaWRkZW4iOmZhbHNlLCJpc1RvZ2dsZWQiOnRydWUsImlzVG9nZ2xlYWJsZSI6ZmFsc2UsImlzVG9nZ2xlZEhpZGRlbkJ5RGVmYXVsdCI6bnVsbH0seyJ0eXBlIjoiY29sdW1uIiwibmFtZSI6InR5cGUiLCJsYWJlbCI6IlR5cGUiLCJpc0hpZGRlbiI6ZmFsc2UsImlzVG9nZ2xlZCI6dHJ1ZSwiaXNUb2dnbGVhYmxlIjpmYWxzZSwiaXNUb2dnbGVkSGlkZGVuQnlEZWZhdWx0IjpudWxsfSx7InR5cGUiOiJjb2x1bW4iLCJuYW1lIjoicGFydG5lcnNfY291bnQiLCJsYWJlbCI6IlBhcnRlbmFpcmVzIiwiaXNIaWRkZW4iOmZhbHNlLCJpc1RvZ2dsZWQiOnRydWUsImlzVG9nZ2xlYWJsZSI6ZmFsc2UsImlzVG9nZ2xlZEhpZGRlbkJ5RGVmYXVsdCI6bnVsbH0seyJ0eXBlIjoiY29sdW1uIiwibmFtZSI6InRvdGFsX2RvbmF0aW9ucyIsImxhYmVsIjoiTW9udGFudCB0b3RhbCIsImlzSGlkZGVuIjpmYWxzZSwiaXNUb2dnbGVkIjp0cnVlLCJpc1RvZ2dsZWFibGUiOmZhbHNlLCJpc1RvZ2dsZWRIaWRkZW5CeURlZmF1bHQiOm51bGx9XSwiYzQ0MzQzMTA3MGJlMWZiOWQyMTY2ZDg3YjJiYTRlMjlfY29sdW1ucyI6W3sidHlwZSI6ImNvbHVtbiIsIm5hbWUiOiJkYXRlIiwibGFiZWwiOiJEYXRlIiwiaXNIaWRkZW4iOmZhbHNlLCJpc1RvZ2dsZWQiOnRydWUsImlzVG9nZ2xlYWJsZSI6ZmFsc2UsImlzVG9nZ2xlZEhpZGRlbkJ5RGVmYXVsdCI6bnVsbH0seyJ0eXBlIjoiY29sdW1uIiwibmFtZSI6InBhcnRuZXIuZnVsbF9uYW1lIiwibGFiZWwiOiJQYXJ0ZW5haXJlIiwiaXNIaWRkZW4iOmZhbHNlLCJpc1RvZ2dsZWQiOnRydWUsImlzVG9nZ2xlYWJsZSI6ZmFsc2UsImlzVG9nZ2xlZEhpZGRlbkJ5RGVmYXVsdCI6bnVsbH0seyJ0eXBlIjoiY29sdW1uIiwibmFtZSI6ImNodXJjaC5uYW1lIiwibGFiZWwiOiJcdTAwYzlnbGlzZSIsImlzSGlkZGVuIjpmYWxzZSwiaXNUb2dnbGVkIjp0cnVlLCJpc1RvZ2dsZWFibGUiOmZhbHNlLCJpc1RvZ2dsZWRIaWRkZW5CeURlZmF1bHQiOm51bGx9LHsidHlwZSI6ImNvbHVtbiIsIm5hbWUiOiJjYXRlZ29yeS5uYW1lIiwibGFiZWwiOiJDYXRcdTAwZTlnb3JpZSIsImlzSGlkZGVuIjpmYWxzZSwiaXNUb2dnbGVkIjp0cnVlLCJpc1RvZ2dsZWFibGUiOmZhbHNlLCJpc1RvZ2dsZWRIaWRkZW5CeURlZmF1bHQiOm51bGx9LHsidHlwZSI6ImNvbHVtbiIsIm5hbWUiOiJhbW91bnQiLCJsYWJlbCI6Ik1vbnRhbnQiLCJpc0hpZGRlbiI6ZmFsc2UsImlzVG9nZ2xlZCI6dHJ1ZSwiaXNUb2dnbGVhYmxlIjpmYWxzZSwiaXNUb2dnbGVkSGlkZGVuQnlEZWZhdWx0IjpudWxsfV19LCJmaWxhbWVudCI6W119', 1795130855),
('YKLHicAfAhzVO0GepHhFGGmjRi56cyocCKunisoK', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) OpenCode/2.3.2 Chrome/152.0.7977.167 Electron/44.9.1 Safari/537.36', 'eyJfdG9rZW4iOiJhRFFQR29NY0VtNzZRVzFoMlJwVUQxSmsyVnd1OUlKUERoNDZkREwzIiwidXJsIjp7ImludGVuZGVkIjoiaHR0cDpcL1wvbG9jYWxob3N0OjgwMDBcL2Rhc2hib2FyZFwvcGFydG5lcnNoaXAtcmVwb3J0In0sIl9wcmV2aW91cyI6eyJ1cmwiOiJodHRwOlwvXC9sb2NhbGhvc3Q6ODAwMFwvZGFzaGJvYXJkXC9wYXJ0bmVyc2hpcC1yZXBvcnQiLCJyb3V0ZSI6ImZpbGFtZW50LmFkbWluLnBhZ2VzLnBhcnRuZXJzaGlwLXJlcG9ydCJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19', 1795130602);

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `assigned_church_id` bigint(20) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `phone` varchar(255) DEFAULT NULL,
  `handle_kc` varchar(255) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- DÃ©chargement des donnÃ©es de la table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `assigned_church_id`, `is_active`, `phone`, `handle_kc`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'Nestor JosuÃ© ATCHOUGO', 'sounesatchougo@gmail.com', '2026-10-17 10:02:34', '$2y$12$UnpeROFP3dB6iLa80maYoOHOt0ldtZX8UtdvbYI2G9RiiGRWBGvA6', 1, 1, '0198123353', '@nestor229', NULL, '2026-10-17 10:02:34', '2026-11-19 23:27:05'),
(2, 'Pasteur Nicolas Yao Adjaka', 'yaoadjaka@yahoo.fr', NULL, '$2y$12$vKnkmH0gZtSv7CS3G7WtzuTxsluS0cbSoKFlIGp6D/Ar8zjgtIHOq', 1, 1, NULL, NULL, NULL, '2026-11-19 16:56:22', '2026-11-19 16:56:22'),
(3, 'Pasteur Wisdom Washington', 'wwashington@gmail.com', NULL, '$2y$12$hcn30L0xD09r/c7waqCWSekLwS3mwKlGgYR111jQ.7CyIF2nsEptK', 2, 1, NULL, NULL, NULL, '2026-11-19 16:56:22', '2026-11-19 16:56:22'),
(4, 'Pasteur Boris Aihin', 'baihin@gmail.com', NULL, '$2y$12$OeNYTJ6wOLU.1.nbDoOSMO13i1JdjvNUi3M.Q9Mif31PPik7Nf7b2', 3, 1, NULL, NULL, NULL, '2026-11-19 16:56:22', '2026-11-19 16:56:22'),
(5, 'Diacre Caleb Dossa', 'cdossa@gmail.com', NULL, '$2y$12$DFNoiOUJDAFKOHKXntta2.X3iVeFw.84CM.u50BP9s/RFHZ/xQ0Dq', 4, 1, NULL, NULL, NULL, '2026-11-19 16:56:22', '2026-11-19 16:56:22'),
(6, 'Pasteur Ives Gogan', 'igogan@gmail.com', NULL, '$2y$12$wsSZsBnofata8Hz5TqIWFeoVSrsnxu7v/SxIc0HzoqijEBTC6eK6q', 5, 1, NULL, NULL, NULL, '2026-11-19 16:56:22', '2026-11-19 16:56:22'),
(7, 'FrÃ¨re Sebastian Monkoun', 'smonkoun@gmail.com', NULL, '$2y$12$IJBF8DjGJkHKd4Cr/l/mBeORW5h/1Qb6p1v5flOjHqCRAvtxYVI0i', 6, 1, NULL, NULL, NULL, '2026-11-19 16:56:23', '2026-11-19 16:56:23'),
(8, 'FrÃ¨re Franck Gbemenou', 'fgbemenou@gmail.com', NULL, '$2y$12$/XE5TbEtztGjbYii9fN78OX1yIFXl5VQ.cDzzSTdk0kRIvdmFAPEG', 7, 1, NULL, NULL, NULL, '2026-11-19 16:56:23', '2026-11-19 16:56:23'),
(9, 'FrÃ¨re Roger Kouton', 'rkouton@gmail.com', NULL, '$2y$12$YOSKhzNCRhthLIDveuNW5eXtqRp5BLU1p9fXgMSgIHnqXAtz036cO', 8, 1, NULL, NULL, NULL, '2026-11-19 16:56:23', '2026-11-19 16:56:23'),
(10, 'FrÃ¨re Felix Gnadjihounde', 'fgnadjihounde@gmail.com', NULL, '$2y$12$8Preil0.idhc/gBfPu06PeOeupfZxBBwn61bSgYwnRQdqeGx9TSYe', 9, 1, NULL, NULL, NULL, '2026-11-19 16:56:23', '2026-11-19 16:56:23'),
(11, 'FrÃ¨re Sebastian Aguidi', 'saguidi@gmail.com', NULL, '$2y$12$wo0U7UAYIe3JrVk9CmIExe77gUrITz6Lxzum1sRTiAyHNo0Ss45jK', 10, 1, NULL, NULL, NULL, '2026-11-19 16:56:24', '2026-11-19 16:56:24'),
(12, 'FrÃ¨re Albert Alovokpinhoun', 'aalovokpinhoun@gmail.com', NULL, '$2y$12$lGDMdgPrdGlsBwVWVUh7VOylOd87ztip.ZGEqj.jLnarztsSAAVB.', 11, 1, NULL, NULL, NULL, '2026-11-19 16:56:24', '2026-11-19 16:56:24');

--
-- Index pour les tables dÃ©chargÃ©es
--

--
-- Index pour la table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `accounts_provider_provider_account_id_unique` (`provider`,`provider_account_id`),
  ADD KEY `accounts_user_id_foreign` (`user_id`);

--
-- Index pour la table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_expiration_index` (`expiration`);

--
-- Index pour la table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_locks_expiration_index` (`expiration`);

--
-- Index pour la table `category_dons`
--
ALTER TABLE `category_dons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `category_dons_name_unique` (`name`);

--
-- Index pour la table `cellules`
--
ALTER TABLE `cellules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cellules_leader_id_unique` (`leader_id`),
  ADD KEY `cellules_church_id_foreign` (`church_id`);

--
-- Index pour la table `churches`
--
ALTER TABLE `churches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `churches_name_unique` (`name`),
  ADD UNIQUE KEY `churches_manager_id_unique` (`manager_id`),
  ADD KEY `churches_parent_id_foreign` (`parent_id`);

--
-- Index pour la table `donations`
--
ALTER TABLE `donations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `donations_partner_id_foreign` (`partner_id`),
  ADD KEY `donations_category_id_foreign` (`category_id`),
  ADD KEY `donations_church_id_date_index` (`church_id`,`date`),
  ADD KEY `donations_date_index` (`date`);

--
-- Index pour la table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Index pour la table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Index pour la table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `login_histories`
--
ALTER TABLE `login_histories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `login_histories_user_id_created_at_index` (`user_id`,`created_at`);

--
-- Index pour la table `magic_tokens`
--
ALTER TABLE `magic_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `magic_tokens_token_unique` (`token`);

--
-- Index pour la table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `model_has_permissions`
--
ALTER TABLE `model_has_permissions`
  ADD PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  ADD KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`);

--
-- Index pour la table `model_has_roles`
--
ALTER TABLE `model_has_roles`
  ADD PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  ADD KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`);

--
-- Index pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notifications_user_id_is_read_index` (`user_id`,`is_read`),
  ADD KEY `notifications_user_id_created_at_index` (`user_id`,`created_at`);

--
-- Index pour la table `partners`
--
ALTER TABLE `partners`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `partners_email_unique` (`email`),
  ADD UNIQUE KEY `partners_phone_unique` (`phone`),
  ADD UNIQUE KEY `partners_handle_kc_unique` (`handle_kc`),
  ADD KEY `partners_cellule_id_foreign` (`cellule_id`),
  ADD KEY `partners_church_id_foreign` (`church_id`);

--
-- Index pour la table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Index pour la table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`);

--
-- Index pour la table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  ADD KEY `personal_access_tokens_expires_at_index` (`expires_at`);

--
-- Index pour la table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`);

--
-- Index pour la table `role_has_permissions`
--
ALTER TABLE `role_has_permissions`
  ADD PRIMARY KEY (`permission_id`,`role_id`),
  ADD KEY `role_has_permissions_role_id_foreign` (`role_id`);

--
-- Index pour la table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD UNIQUE KEY `users_phone_unique` (`phone`),
  ADD UNIQUE KEY `users_handle_kc_unique` (`handle_kc`),
  ADD KEY `users_assigned_church_id_foreign` (`assigned_church_id`);

--
-- AUTO_INCREMENT pour les tables dÃ©chargÃ©es
--

--
-- AUTO_INCREMENT pour la table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `category_dons`
--
ALTER TABLE `category_dons`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `cellules`
--
ALTER TABLE `cellules`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `churches`
--
ALTER TABLE `churches`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT pour la table `donations`
--
ALTER TABLE `donations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `login_histories`
--
ALTER TABLE `login_histories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT pour la table `magic_tokens`
--
ALTER TABLE `magic_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT pour la table `partners`
--
ALTER TABLE `partners`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT pour la table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- Contraintes pour les tables dÃ©chargÃ©es
--

--
-- Contraintes pour la table `accounts`
--
ALTER TABLE `accounts`
  ADD CONSTRAINT `accounts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `cellules`
--
ALTER TABLE `cellules`
  ADD CONSTRAINT `cellules_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `churches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cellules_leader_id_foreign` FOREIGN KEY (`leader_id`) REFERENCES `partners` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `churches`
--
ALTER TABLE `churches`
  ADD CONSTRAINT `churches_manager_id_foreign` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `churches_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `churches` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `donations`
--
ALTER TABLE `donations`
  ADD CONSTRAINT `donations_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `category_dons` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `donations_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `churches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `donations_partner_id_foreign` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `login_histories`
--
ALTER TABLE `login_histories`
  ADD CONSTRAINT `login_histories_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `model_has_permissions`
--
ALTER TABLE `model_has_permissions`
  ADD CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `model_has_roles`
--
ALTER TABLE `model_has_roles`
  ADD CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `partners`
--
ALTER TABLE `partners`
  ADD CONSTRAINT `partners_cellule_id_foreign` FOREIGN KEY (`cellule_id`) REFERENCES `cellules` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `partners_church_id_foreign` FOREIGN KEY (`church_id`) REFERENCES `churches` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `role_has_permissions`
--
ALTER TABLE `role_has_permissions`
  ADD CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_assigned_church_id_foreign` FOREIGN KEY (`assigned_church_id`) REFERENCES `churches` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

SET FOREIGN_KEY_CHECKS=1;