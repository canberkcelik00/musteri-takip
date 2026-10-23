-- Separate database for the test suite (see phpunit.xml).
CREATE DATABASE IF NOT EXISTS `musteri_takip_testing` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON `musteri_takip_testing`.* TO 'musteri_takip'@'%';
-- Per-process databases of the parallel test run (`musteri_takip_testing_test_1`, ...).
GRANT ALL PRIVILEGES ON `musteri\_takip\_testing\_%`.* TO 'musteri_takip'@'%';
FLUSH PRIVILEGES;
