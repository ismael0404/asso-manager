-- =====================================================
-- Association Manager - Database Schema (v3.0)
-- Fichier: database/db.sql
-- Inclut: participations avec workflow, comments nested,
--         favorites, messages privés, gestion utilisateurs
-- =====================================================

CREATE DATABASE IF NOT EXISTS association_manager
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE association_manager;

-- =====================================================
-- Table: users (v3.0 - ajout bio, is_active)
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT 'default.png',
    role ENUM('admin', 'user') DEFAULT 'user',
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- Table: activities (v3.0 - ajout max_participants, registration_status)
-- =====================================================
CREATE TABLE IF NOT EXISTS activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    image VARCHAR(255) DEFAULT NULL,
    activity_date DATE NOT NULL,
    location VARCHAR(200) DEFAULT NULL,
    status ENUM('upcoming', 'ongoing', 'completed') DEFAULT 'upcoming',
    publication_status ENUM('draft', 'published') DEFAULT 'published',
    registration_status ENUM('open', 'closed') DEFAULT 'open',
    max_participants INT DEFAULT NULL,
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- Table: posts
-- =====================================================
CREATE TABLE IF NOT EXISTS posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    image VARCHAR(255) DEFAULT NULL,
    category VARCHAR(50) DEFAULT 'general',
    author_id INT NOT NULL,
    is_published TINYINT(1) DEFAULT 1,
    publication_status ENUM('draft', 'published') DEFAULT 'published',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- Table: members
-- =====================================================
CREATE TABLE IF NOT EXISTS members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    membership_date DATE NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- Table: contacts (messages du formulaire)
-- =====================================================
CREATE TABLE IF NOT EXISTS contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- Table: participations (v3.0 - ajout status pour workflow)
-- Statuts: pending, accepted, rejected
-- =====================================================
CREATE TABLE IF NOT EXISTS participations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_participation (user_id, activity_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- Table: notifications
-- =====================================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) DEFAULT 'general',
    message VARCHAR(500) NOT NULL,
    link VARCHAR(255) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- Table: logs (historique des actions)
-- =====================================================
CREATE TABLE IF NOT EXISTS logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(500) NOT NULL,
    entity_type VARCHAR(50) DEFAULT NULL,
    entity_id INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- Table: comments (v3.0 - ajout parent_id pour nested)
-- =====================================================
CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    post_id INT DEFAULT NULL,
    activity_id INT DEFAULT NULL,
    parent_id INT DEFAULT NULL,
    content TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- Table: favorites (v3.0 - NOUVEAU)
-- =====================================================
CREATE TABLE IF NOT EXISTS favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_favorite (user_id, activity_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- Table: messages (v3.0 - NOUVEAU - messagerie privée)
-- =====================================================
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    subject VARCHAR(200) DEFAULT NULL,
    content TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- ALTER tables existantes (si migration depuis v2.0)
-- Exécuter uniquement si les tables existent déjà
-- =====================================================
-- ALTER TABLE participations ADD COLUMN IF NOT EXISTS status ENUM('pending','accepted','rejected') DEFAULT 'pending' AFTER activity_id;
-- ALTER TABLE participations ADD COLUMN IF NOT EXISTS updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
-- ALTER TABLE users ADD COLUMN IF NOT EXISTS bio TEXT DEFAULT NULL AFTER phone;
-- ALTER TABLE users ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1 AFTER role;
-- ALTER TABLE comments ADD COLUMN IF NOT EXISTS parent_id INT DEFAULT NULL AFTER activity_id;
-- ALTER TABLE activities ADD COLUMN IF NOT EXISTS registration_status ENUM('open','closed') DEFAULT 'open' AFTER publication_status;
-- ALTER TABLE activities ADD COLUMN IF NOT EXISTS max_participants INT DEFAULT NULL AFTER registration_status;
-- ALTER TABLE notifications ADD COLUMN IF NOT EXISTS type VARCHAR(50) DEFAULT 'general' AFTER user_id;

-- =====================================================
-- Données de test
-- =====================================================

-- Admin par défaut (mot de passe: admin123)
INSERT INTO users (username, email, password, full_name, phone, bio, role, is_active) VALUES
('admin', 'admin@association.com', '$2y$10$qZTVdEAo2Am8XT4HZAZAZ.udV97RYX03zuMaLVjkQaFCsOelzZGBS', 'Administrateur Principal', '+225 07 00 00 00', 'Administrateur de la plateforme AssocManager. Passionné par le développement communautaire.', 'admin', 1);

-- Utilisateurs de test (mot de passe: user123)
INSERT INTO users (username, email, password, full_name, phone, bio, role, is_active) VALUES
('jean.dupont', 'jean@association.com', '$2y$10$ye9xforbZMDsVrUVPi1lrOUeqljhgCODcKZ2RkUD7ERftSFOoY9A.', 'Jean Dupont', '+225 07 11 11 11', 'Membre actif de l\'association depuis 2024. Passionné par les actions communautaires.', 'user', 1),
('marie.kouassi', 'marie@association.com', '$2y$10$ye9xforbZMDsVrUVPi1lrOUeqljhgCODcKZ2RkUD7ERftSFOoY9A.', 'Marie Kouassi', '+225 07 22 22 22', 'Bénévole engagée. J\'aime participer aux activités de reboisement.', 'user', 1),
('paul.koffi', 'paul@association.com', '$2y$10$ye9xforbZMDsVrUVPi1lrOUeqljhgCODcKZ2RkUD7ERftSFOoY9A.', 'Paul Koffi', '+225 07 33 33 33', 'Étudiant en droit, engagé dans le bénévolat.', 'user', 1);

-- Activités de test
INSERT INTO activities (title, description, image, activity_date, location, status, publication_status, registration_status, max_participants, created_by) VALUES
('Journée de Reboisement', 'Grande journée de reboisement communautaire dans le parc national. Rejoignez-nous pour planter des arbres et contribuer à un avenir plus vert.', NULL, '2026-04-15', 'Parc National du Banco, Abidjan', 'upcoming', 'published', 'open', 50, 1),
('Formation en Leadership', 'Formation intensive sur le leadership et la gestion d\'équipe pour les jeunes membres de l\'association.', NULL, '2026-04-20', 'Centre Culturel, Cocody', 'upcoming', 'published', 'open', 30, 1),
('Tournoi de Football', 'Tournoi inter-associations de football. Venez supporter notre équipe et passer un moment convivial.', NULL, '2026-03-10', 'Stade Municipal, Plateau', 'completed', 'published', 'closed', NULL, 1),
('Distribution de Fournitures Scolaires', 'Distribution gratuite de fournitures scolaires aux enfants défavorisés du quartier.', NULL, '2026-05-01', 'École Primaire de Yopougon', 'upcoming', 'published', 'open', 100, 1),
('Soirée Culturelle', 'Grande soirée culturelle avec danses traditionnelles, musique live et exposition artisanale.', NULL, '2026-03-20', 'Palais de la Culture, Treichville', 'ongoing', 'published', 'open', NULL, 1);

-- Publications de test
INSERT INTO posts (title, content, image, category, author_id, is_published, publication_status) VALUES
('Bienvenue sur notre plateforme !', 'Nous sommes heureux de vous accueillir sur la nouvelle plateforme de gestion de notre association. Cette plateforme vous permettra de suivre toutes nos activités, actualités et événements. N\'hésitez pas à vous inscrire pour devenir membre et participer à nos actions.', NULL, 'general', 1, 1, 'published'),
('Résultats de la Campagne de Don', 'Notre dernière campagne de collecte de fonds a été un franc succès ! Grâce à votre générosité, nous avons pu récolter plus de 2 millions de FCFA qui seront utilisés pour financer nos projets communautaires. Merci à tous les donateurs !', NULL, 'actualite', 1, 1, 'published'),
('Appel à Bénévoles', 'Nous recherchons des bénévoles motivés pour participer à nos prochaines activités. Si vous souhaitez contribuer au développement de notre communauté, contactez-nous dès maintenant. Tous les profils sont les bienvenus !', NULL, 'annonce', 1, 1, 'published'),
('Nouveau Partenariat avec l\'ONG Espoir', 'Nous avons le plaisir de vous annoncer notre nouveau partenariat avec l\'ONG Espoir. Ce partenariat nous permettra d\'étendre nos actions dans les zones rurales et de toucher encore plus de bénéficiaires.', NULL, 'actualite', 1, 1, 'published');

-- Membres de test
INSERT INTO members (first_name, last_name, email, phone, address, membership_date, status) VALUES
('Amadou', 'Traoré', 'amadou.traore@email.com', '+225 07 44 44 44', 'Cocody, Abidjan', '2025-01-15', 'active'),
('Fatou', 'Diallo', 'fatou.diallo@email.com', '+225 07 55 55 55', 'Marcory, Abidjan', '2025-02-20', 'active'),
('Ibrahim', 'Koné', 'ibrahim.kone@email.com', '+225 07 66 66 66', 'Yopougon, Abidjan', '2025-03-10', 'active'),
('Aïcha', 'Bamba', 'aicha.bamba@email.com', '+225 07 77 77 77', 'Plateau, Abidjan', '2025-04-05', 'inactive'),
('Moussa', 'Ouattara', 'moussa.ouattara@email.com', '+225 07 88 88 88', 'Treichville, Abidjan', '2025-05-18', 'active'),
('Salimata', 'Touré', 'salimata.toure@email.com', '+225 07 99 99 99', 'Adjamé, Abidjan', '2025-06-25', 'active');

-- Messages de contact de test
INSERT INTO contacts (name, email, subject, message, is_read) VALUES
('Pierre Martin', 'pierre@email.com', 'Demande d\'information', 'Bonjour, je souhaiterais avoir plus d\'informations sur vos activités et comment rejoindre l\'association. Merci.', 0),
('Sophie Aka', 'sophie@email.com', 'Partenariat', 'Bonjour, notre entreprise serait intéressée par un partenariat avec votre association. Pouvons-nous en discuter ?', 1);

-- Participations de test (avec status)
INSERT INTO participations (user_id, activity_id, status) VALUES
(2, 1, 'accepted'), (3, 1, 'accepted'), (4, 1, 'pending'),
(2, 2, 'accepted'), (3, 2, 'pending');

-- Notifications de test
INSERT INTO notifications (user_id, type, message, link, is_read) VALUES
(2, 'activity', 'Nouvelle activité : Journée de Reboisement', '/user/activities.php', 0),
(3, 'activity', 'Nouvelle activité : Journée de Reboisement', '/user/activities.php', 0),
(4, 'activity', 'Nouvelle activité : Journée de Reboisement', '/user/activities.php', 1),
(2, 'participation', 'Votre participation à "Journée de Reboisement" a été acceptée !', '/user/my-activities.php', 0),
(3, 'participation', 'Votre participation à "Formation en Leadership" est en attente de validation.', '/user/my-activities.php', 0);

-- Logs de test
INSERT INTO logs (user_id, action, entity_type, entity_id) VALUES
(1, 'a ajouté l\'activité "Journée de Reboisement"', 'activity', 1),
(1, 'a ajouté la publication "Bienvenue sur notre plateforme !"', 'post', 1),
(1, 'a ajouté le membre "Amadou Traoré"', 'member', 1);

-- Commentaires de test (avec réponses nested)
INSERT INTO comments (user_id, activity_id, parent_id, content) VALUES
(2, 1, NULL, 'Super initiative ! J\'ai hâte d\'y participer.'),
(3, 1, NULL, 'Je serai présent avec ma famille.');

INSERT INTO comments (user_id, activity_id, parent_id, content) VALUES
(4, 1, 1, 'Moi aussi, on se retrouve là-bas !');

INSERT INTO comments (user_id, post_id, parent_id, content) VALUES
(2, 1, NULL, 'Excellente nouvelle pour l\'association !');

-- Favoris de test
INSERT INTO favorites (user_id, activity_id) VALUES
(2, 1), (2, 4), (3, 1), (3, 5);

-- Messages privés de test
INSERT INTO messages (sender_id, receiver_id, subject, content, is_read) VALUES
(2, 1, 'Question sur l\'activité', 'Bonjour Admin, je voudrais savoir si on peut venir avec des amis à la journée de reboisement ?', 0),
(1, 2, 'Re: Question sur l\'activité', 'Bonjour Jean, bien sûr ! Plus on est nombreux, mieux c\'est. N\'hésitez pas à inviter vos amis.', 1),
(3, 1, 'Suggestion d\'activité', 'Bonjour, serait-il possible d\'organiser un atelier de cuisine communautaire ?', 0);
