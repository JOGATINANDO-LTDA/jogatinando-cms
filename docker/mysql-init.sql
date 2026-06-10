CREATE DATABASE IF NOT EXISTS cms_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'cms_user'@'%' IDENTIFIED BY 'cms_pass2024';
-- Necessário ON *.* para o install.php poder criar o database (CREATE DATABASE)
-- Em produção, usar MySQL gerenciado (RDS/CloudSQL) com permissões manuais
GRANT ALL PRIVILEGES ON *.* TO 'cms_user'@'%' WITH GRANT OPTION;
FLUSH PRIVILEGES;
