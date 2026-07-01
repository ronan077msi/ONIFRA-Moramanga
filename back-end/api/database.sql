-- ============================================
-- ONIFRA Moramanga — Base de données
-- MySQL / MariaDB
-- ============================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

CREATE DATABASE IF NOT EXISTS onifra_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE onifra_db;

-- ============================================
-- ADMINS
-- ============================================
CREATE TABLE IF NOT EXISTS admins (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  nom           VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at    DATETIME DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3 admins par défaut — mot de passe : Onifra2026!
-- A changer immediatement apres installation
INSERT INTO admins (nom, password_hash) VALUES
  ('Admin Principal',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
  ('Admin Scolarite',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
  ('Admin Enseignant', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi')
ON DUPLICATE KEY UPDATE id = id;

-- ============================================
-- ETUDIANT
-- ============================================
CREATE TABLE IF NOT EXISTS etudiants (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  nom        VARCHAR(100) NOT NULL,
  prenom     VARCHAR(100) NOT NULL,
  matricule  VARCHAR(50)  NOT NULL UNIQUE,
  mention    ENUM('gestion','droit','agronomie') NOT NULL,
  niveau     ENUM('L1','L2','L3') NOT NULL,
  tel        VARCHAR(20)  NOT NULL,
  pin_hash   VARCHAR(255) NOT NULL,
  actif      TINYINT(1)   DEFAULT 1,
  created_at DATETIME     DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- SESSIONS ETUDIANT
-- ============================================
CREATE TABLE IF NOT EXISTS sessions (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  etudiant_id INT          NOT NULL,
  token       VARCHAR(512) NOT NULL UNIQUE,
  ip          VARCHAR(45),
  user_agent  TEXT,
  created_at  DATETIME DEFAULT NOW(),
  last_seen   DATETIME DEFAULT NOW(),
  FOREIGN KEY (etudiant_id) REFERENCES etudiants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- ENSEIGNANTS
-- ============================================
CREATE TABLE IF NOT EXISTS enseignants (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  nom        VARCHAR(100) NOT NULL,
  prenom     VARCHAR(100) NOT NULL,
  grade      VARCHAR(50),
  pin_hash   VARCHAR(255) NOT NULL,
  actif      TINYINT(1)   DEFAULT 1,
  created_at DATETIME     DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sessions enseignants
CREATE TABLE IF NOT EXISTS sessions_enseignants (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  enseignant_id INT          NOT NULL,
  token         VARCHAR(512) NOT NULL UNIQUE,
  ip            VARCHAR(45),
  user_agent    TEXT,
  created_at    DATETIME DEFAULT NOW(),
  last_seen     DATETIME DEFAULT NOW(),
  FOREIGN KEY (enseignant_id) REFERENCES enseignants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- MODULES
-- ============================================
CREATE TABLE IF NOT EXISTS modules (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  nom_module    VARCHAR(150) NOT NULL,
  mention       ENUM('gestion','droit','agronomie') NOT NULL,
  niveau        ENUM('L1','L2','L3') NOT NULL,
  enseignant_id INT NOT NULL,
  introduction  TEXT,
  created_at    DATETIME DEFAULT NOW(),
  FOREIGN KEY (enseignant_id) REFERENCES enseignants(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- SUPPORTS DE COURS
-- ============================================
CREATE TABLE IF NOT EXISTS supports (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  module_id    INT          NOT NULL,
  titre        VARCHAR(255) NOT NULL,
  description  TEXT,
  fichier_path VARCHAR(500) NOT NULL,
  taille       INT,
  created_at   DATETIME DEFAULT NOW(),
  FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- EDT
-- ============================================
CREATE TABLE IF NOT EXISTS edt (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  mention    ENUM('gestion','droit','agronomie') NOT NULL,
  niveau     ENUM('L1','L2','L3') NOT NULL,
  image_path VARCHAR(500) NOT NULL,
  created_at DATETIME DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- ACTUALITES
-- ============================================
CREATE TABLE IF NOT EXISTS actualites (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  titre          VARCHAR(255) NOT NULL,
  contenu        TEXT         NOT NULL,
  type           ENUM('info','event','urgent') NOT NULL DEFAULT 'info',
  date_evenement DATE,
  image_path     VARCHAR(500),
  created_at     DATETIME DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- COMMENTAIRES
-- ============================================
CREATE TABLE IF NOT EXISTS commentaires (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  actualite_id INT  NOT NULL,
  etudiant_id  INT  NOT NULL,
  contenu      TEXT NOT NULL,
  created_at   DATETIME DEFAULT NOW(),
  FOREIGN KEY (actualite_id) REFERENCES actualites(id) ON DELETE CASCADE,
  FOREIGN KEY (etudiant_id)  REFERENCES etudiants(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- NOTIFICATIONS
-- ============================================
CREATE TABLE IF NOT EXISTS notifications (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  titre      VARCHAR(255) NOT NULL,
  message    TEXT         NOT NULL,
  type       ENUM('edt','annonce','admin','urgent','cours') NOT NULL DEFAULT 'admin',
  target     ENUM('tous','mention','niveau') NOT NULL DEFAULT 'tous',
  mention    ENUM('gestion','droit','agronomie'),
  niveau     ENUM('L1','L2','L3'),
  created_at DATETIME DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Suivi lecture par etudiant
CREATE TABLE IF NOT EXISTS notifications_lues (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  notification_id INT NOT NULL,
  etudiant_id     INT NOT NULL,
  lu_at           DATETIME DEFAULT NOW(),
  UNIQUE KEY unique_lu (notification_id, etudiant_id),
  FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
  FOREIGN KEY (etudiant_id)     REFERENCES etudiants(id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- INDEX
-- ============================================
CREATE INDEX idx_sessions_token     ON sessions(token);
CREATE INDEX idx_sessions_etudiant  ON sessions(etudiant_id);
CREATE INDEX idx_sessions_ens_token ON sessions_enseignants(token);
CREATE INDEX idx_sessions_ens_id    ON sessions_enseignants(enseignant_id);
CREATE INDEX idx_supports_module    ON supports(module_id);
CREATE INDEX idx_modules_mention    ON modules(mention, niveau);
CREATE INDEX idx_edt_mention        ON edt(mention, niveau);
CREATE INDEX idx_notifs_target      ON notifications(target, mention, niveau);
CREATE INDEX idx_notifs_lues        ON notifications_lues(etudiant_id);
CREATE INDEX idx_commentaires_actu  ON commentaires(actualite_id);

SET foreign_key_checks = 1;
