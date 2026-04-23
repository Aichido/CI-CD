-- Création des bases de données
CREATE DATABASE IF NOT EXISTS `skillhub_auth`;
CREATE DATABASE IF NOT EXISTS `skillhub_catalog`;
CREATE DATABASE IF NOT EXISTS `skillhub_enrollment`;
-- Base de données dédiée au service TP-5 (Spring Boot)
CREATE DATABASE IF NOT EXISTS `auth_db`;

-- Autorisations pour ton utilisateur principal
GRANT ALL PRIVILEGES ON `skillhub_auth`.* TO 'skillhub_user'@'%';
GRANT ALL PRIVILEGES ON `skillhub_catalog`.* TO 'skillhub_user'@'%';
GRANT ALL PRIVILEGES ON `skillhub_enrollment`.* TO 'skillhub_user'@'%';
GRANT ALL PRIVILEGES ON `auth_db`.* TO 'skillhub_user'@'%';
FLUSH PRIVILEGES;