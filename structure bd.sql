-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : ven. 07 août 2026 à 15:21
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
-- Base de données : `gestion_paiement_ecole`
--

-- --------------------------------------------------------

--
-- Structure de la table `annees_scolaires`
--

CREATE TABLE `annees_scolaires` (
  `idAnnee` int(11) NOT NULL,
  `libelle` varchar(20) NOT NULL,
  `annee` varchar(20) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `annees_scolaires`
--

INSERT INTO `annees_scolaires` (`idAnnee`, `libelle`, `annee`, `active`) VALUES
(1, '2026-2027', '2026-2027', 1);

-- --------------------------------------------------------

--
-- Structure de la table `classes`
--

CREATE TABLE `classes` (
  `idClasse` int(11) NOT NULL,
  `nomClasse` varchar(50) NOT NULL,
  `idNiveau` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `classes`
--

INSERT INTO `classes` (`idClasse`, `nomClasse`, `idNiveau`) VALUES
(1, '1?re Primaire', 2),
(2, '2?me Primaire', 2),
(3, '3?me Primaire', 2),
(4, '4?me Primaire', 2),
(5, '5?me Primaire', 2),
(6, '6?me Primaire', 2);

-- --------------------------------------------------------

--
-- Structure de la table `eleves`
--

CREATE TABLE `eleves` (
  `idEleve` int(11) NOT NULL,
  `matricule` varchar(30) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `postNom` varchar(100) DEFAULT NULL,
  `prenom` varchar(100) DEFAULT NULL,
  `lieuNaissance` varchar(100) DEFAULT NULL,
  `sexe` enum('M','F') DEFAULT NULL,
  `dateNaissance` date DEFAULT NULL,
  `adresse` varchar(255) DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `nomPere` varchar(100) DEFAULT NULL,
  `nomMere` varchar(100) DEFAULT NULL,
  `motDePasse` varchar(255) DEFAULT NULL,
  `idUtilisateur` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `eleves`
--

INSERT INTO `eleves` (`idEleve`, `matricule`, `nom`, `postNom`, `prenom`, `lieuNaissance`, `sexe`, `dateNaissance`, `adresse`, `telephone`, `nomPere`, `nomMere`, `motDePasse`, `idUtilisateur`) VALUES
(1, 'ELV-2026-9417', 'kalonji', 'mulumba', 'ben', 'kolwezi', 'M', '2005-03-12', 'Musonoie', '+243846874499', 'mulumba', 'kanjinga', '$2y$10$f32BTQTeNGRBMGSadxIos.fWFFVIggBhEp1QbzwHEjvfkgIFQOTV2', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `frais`
--

CREATE TABLE `frais` (
  `idFrais` int(11) NOT NULL,
  `libelle` varchar(100) NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `typeFrais` enum('Inscription','Mensualit?','Autre') NOT NULL,
  `idAnnee` int(11) NOT NULL,
  `idNiveau` int(11) DEFAULT NULL,
  `statut` enum('Actif','Inactif') DEFAULT 'Actif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `frais`
--

INSERT INTO `frais` (`idFrais`, `libelle`, `montant`, `typeFrais`, `idAnnee`, `idNiveau`, `statut`) VALUES
(1, 'Scolaire', 25.00, '', 1, 2, 'Actif'),
(2, 'Scolaire', 40.00, '', 1, 3, 'Actif');

-- --------------------------------------------------------

--
-- Structure de la table `inscriptions`
--

CREATE TABLE `inscriptions` (
  `idInscription` int(11) NOT NULL,
  `idEleve` int(11) NOT NULL,
  `idClasse` int(11) NOT NULL,
  `idAnnee` int(11) NOT NULL,
  `dateInscription` date NOT NULL,
  `statut` enum('Actif','Termin?') DEFAULT 'Actif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `inscriptions`
--

INSERT INTO `inscriptions` (`idInscription`, `idEleve`, `idClasse`, `idAnnee`, `dateInscription`, `statut`) VALUES
(1, 1, 6, 1, '2026-08-07', 'Actif');

-- --------------------------------------------------------

--
-- Structure de la table `journal_activite`
--

CREATE TABLE `journal_activite` (
  `idJournal` int(11) NOT NULL,
  `idUtilisateur` int(11) NOT NULL,
  `action` text NOT NULL,
  `dateAction` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `journal_activite`
--

INSERT INTO `journal_activite` (`idJournal`, `idUtilisateur`, `action`, `dateAction`) VALUES
(1, 3, 'Déconnexion de l\'utilisateur admin', '2026-08-07 08:10:36'),
(2, 4, 'Déconnexion de l\'utilisateur jonathan', '2026-08-07 08:36:33'),
(3, 4, 'Déconnexion de l\'utilisateur jonathan', '2026-08-07 08:37:24'),
(4, 3, 'Déconnexion de l\'utilisateur admin', '2026-08-07 15:03:21'),
(5, 5, 'Déconnexion de l\'utilisateur Elie', '2026-08-07 15:05:11'),
(6, 3, 'Déconnexion de l\'utilisateur admin', '2026-08-07 15:14:36'),
(7, 5, 'Déconnexion de l\'utilisateur Elie', '2026-08-07 15:19:39');

-- --------------------------------------------------------

--
-- Structure de la table `niveaux`
--

CREATE TABLE `niveaux` (
  `idNiveau` int(11) NOT NULL,
  `nomNiveau` varchar(50) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `niveaux`
--

INSERT INTO `niveaux` (`idNiveau`, `nomNiveau`, `description`) VALUES
(1, 'Maternel', 'maternel'),
(2, 'Primaire', NULL),
(3, 'Secondaire', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `notifications`
--

CREATE TABLE `notifications` (
  `idNotification` int(11) NOT NULL,
  `idUtilisateur` int(11) DEFAULT NULL,
  `titre` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) DEFAULT 'info',
  `est_lu` tinyint(1) NOT NULL DEFAULT 0,
  `dateCreation` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `paiements`
--

CREATE TABLE `paiements` (
  `idPaiement` int(11) NOT NULL,
  `idInscription` int(11) NOT NULL,
  `idFrais` int(11) NOT NULL,
  `mois` enum('Septembre','Octobre','Novembre','D?cembre','Janvier','F?vrier','Mars','Avril','Mai','Juin','Juillet') DEFAULT NULL,
  `montantPaye` decimal(10,2) NOT NULL,
  `datePaiement` datetime DEFAULT current_timestamp(),
  `idCaissier` int(11) NOT NULL,
  `numeroRecu` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `paiements`
--

INSERT INTO `paiements` (`idPaiement`, `idInscription`, `idFrais`, `mois`, `montantPaye`, `datePaiement`, `idCaissier`, `numeroRecu`) VALUES
(1, 1, 1, 'Septembre', 25.00, '2026-08-07 15:15:00', 5, 'REC-2026-0001');

-- --------------------------------------------------------

--
-- Structure de la table `permissions`
--

CREATE TABLE `permissions` (
  `idPermission` int(11) NOT NULL,
  `nomPermission` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `roles`
--

CREATE TABLE `roles` (
  `idRole` int(11) NOT NULL,
  `nomRole` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `roles`
--

INSERT INTO `roles` (`idRole`, `nomRole`) VALUES
(1, 'Administrateur'),
(3, 'Caissier'),
(2, 'Réceptionniste');

-- --------------------------------------------------------

--
-- Structure de la table `role_permission`
--

CREATE TABLE `role_permission` (
  `idRole` int(11) NOT NULL,
  `idPermission` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

CREATE TABLE `utilisateurs` (
  `idUtilisateur` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `postNom` varchar(100) DEFAULT NULL,
  `prenom` varchar(100) DEFAULT NULL,
  `sexe` enum('M','F') DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `motDePasse` varchar(255) NOT NULL,
  `idRole` int(11) NOT NULL,
  `statut` enum('Actif','Inactif') DEFAULT 'Actif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `utilisateurs`
--

INSERT INTO `utilisateurs` (`idUtilisateur`, `nom`, `postNom`, `prenom`, `sexe`, `telephone`, `email`, `username`, `motDePasse`, `idRole`, `statut`) VALUES
(3, 'Admin', 'Principal', 'Syst?me', 'M', '0000000000', 'admin@ecole.com', 'admin', '$2y$10$w3mE6qwx2KkuXWOiFGHbbOmx.atanNnxYvTGVzuLlHoFSDk6BKvp.', 1, 'Actif'),
(4, 'Cisuaka', 'kabamba', 'Jonathan', 'M', '+243846874422', 'joanathan@gmail.com', 'jonathan', '$2y$10$O84kVW6.2rrbnmLQ2tnQIOdeYwXga5kskTBxpl3cn6XBhKmD.jkte', 2, 'Actif'),
(5, 'elie', 'muteba', 'muteba', 'M', '+243846874005', 'elie@gmail.com', 'Elie', '$2y$10$I5D6eYKPS0d2mSXVYCNagudzvzu3ucJPwodtGjUv6P83wAdLxuUP2', 3, 'Actif');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `annees_scolaires`
--
ALTER TABLE `annees_scolaires`
  ADD PRIMARY KEY (`idAnnee`);

--
-- Index pour la table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`idClasse`),
  ADD KEY `idNiveau` (`idNiveau`);

--
-- Index pour la table `eleves`
--
ALTER TABLE `eleves`
  ADD PRIMARY KEY (`idEleve`),
  ADD UNIQUE KEY `matricule` (`matricule`),
  ADD KEY `idUtilisateur` (`idUtilisateur`);

--
-- Index pour la table `frais`
--
ALTER TABLE `frais`
  ADD PRIMARY KEY (`idFrais`),
  ADD KEY `idAnnee` (`idAnnee`);

--
-- Index pour la table `inscriptions`
--
ALTER TABLE `inscriptions`
  ADD PRIMARY KEY (`idInscription`),
  ADD KEY `idEleve` (`idEleve`),
  ADD KEY `idClasse` (`idClasse`),
  ADD KEY `idAnnee` (`idAnnee`);

--
-- Index pour la table `journal_activite`
--
ALTER TABLE `journal_activite`
  ADD PRIMARY KEY (`idJournal`),
  ADD KEY `idUtilisateur` (`idUtilisateur`);

--
-- Index pour la table `niveaux`
--
ALTER TABLE `niveaux`
  ADD PRIMARY KEY (`idNiveau`);

--
-- Index pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`idNotification`),
  ADD KEY `fk_notification_utilisateur` (`idUtilisateur`);

--
-- Index pour la table `paiements`
--
ALTER TABLE `paiements`
  ADD PRIMARY KEY (`idPaiement`),
  ADD UNIQUE KEY `numeroRecu` (`numeroRecu`),
  ADD KEY `idInscription` (`idInscription`),
  ADD KEY `idFrais` (`idFrais`),
  ADD KEY `idCaissier` (`idCaissier`);

--
-- Index pour la table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`idPermission`);

--
-- Index pour la table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`idRole`),
  ADD UNIQUE KEY `nomRole` (`nomRole`);

--
-- Index pour la table `role_permission`
--
ALTER TABLE `role_permission`
  ADD PRIMARY KEY (`idRole`,`idPermission`),
  ADD KEY `idPermission` (`idPermission`);

--
-- Index pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  ADD PRIMARY KEY (`idUtilisateur`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idRole` (`idRole`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `annees_scolaires`
--
ALTER TABLE `annees_scolaires`
  MODIFY `idAnnee` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `classes`
--
ALTER TABLE `classes`
  MODIFY `idClasse` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `eleves`
--
ALTER TABLE `eleves`
  MODIFY `idEleve` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `frais`
--
ALTER TABLE `frais`
  MODIFY `idFrais` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `inscriptions`
--
ALTER TABLE `inscriptions`
  MODIFY `idInscription` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `journal_activite`
--
ALTER TABLE `journal_activite`
  MODIFY `idJournal` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `niveaux`
--
ALTER TABLE `niveaux`
  MODIFY `idNiveau` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `idNotification` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `paiements`
--
ALTER TABLE `paiements`
  MODIFY `idPaiement` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `idPermission` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `roles`
--
ALTER TABLE `roles`
  MODIFY `idRole` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  MODIFY `idUtilisateur` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`idNiveau`) REFERENCES `niveaux` (`idNiveau`);

--
-- Contraintes pour la table `eleves`
--
ALTER TABLE `eleves`
  ADD CONSTRAINT `eleves_ibfk_1` FOREIGN KEY (`idUtilisateur`) REFERENCES `utilisateurs` (`idUtilisateur`);

--
-- Contraintes pour la table `frais`
--
ALTER TABLE `frais`
  ADD CONSTRAINT `frais_ibfk_1` FOREIGN KEY (`idAnnee`) REFERENCES `annees_scolaires` (`idAnnee`);

--
-- Contraintes pour la table `inscriptions`
--
ALTER TABLE `inscriptions`
  ADD CONSTRAINT `inscriptions_ibfk_1` FOREIGN KEY (`idEleve`) REFERENCES `eleves` (`idEleve`),
  ADD CONSTRAINT `inscriptions_ibfk_2` FOREIGN KEY (`idClasse`) REFERENCES `classes` (`idClasse`),
  ADD CONSTRAINT `inscriptions_ibfk_3` FOREIGN KEY (`idAnnee`) REFERENCES `annees_scolaires` (`idAnnee`);

--
-- Contraintes pour la table `journal_activite`
--
ALTER TABLE `journal_activite`
  ADD CONSTRAINT `journal_activite_ibfk_1` FOREIGN KEY (`idUtilisateur`) REFERENCES `utilisateurs` (`idUtilisateur`);

--
-- Contraintes pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notification_utilisateur` FOREIGN KEY (`idUtilisateur`) REFERENCES `utilisateurs` (`idUtilisateur`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `paiements`
--
ALTER TABLE `paiements`
  ADD CONSTRAINT `paiements_ibfk_1` FOREIGN KEY (`idInscription`) REFERENCES `inscriptions` (`idInscription`),
  ADD CONSTRAINT `paiements_ibfk_2` FOREIGN KEY (`idFrais`) REFERENCES `frais` (`idFrais`),
  ADD CONSTRAINT `paiements_ibfk_3` FOREIGN KEY (`idCaissier`) REFERENCES `utilisateurs` (`idUtilisateur`);

--
-- Contraintes pour la table `role_permission`
--
ALTER TABLE `role_permission`
  ADD CONSTRAINT `role_permission_ibfk_1` FOREIGN KEY (`idRole`) REFERENCES `roles` (`idRole`),
  ADD CONSTRAINT `role_permission_ibfk_2` FOREIGN KEY (`idPermission`) REFERENCES `permissions` (`idPermission`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
