-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : sam. 28 mars 2026 à 23:56
-- Version du serveur : 8.4.7
-- Version de PHP : 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `emploi_db`
--

-- --------------------------------------------------------

--
-- Structure de la table `abonnements`
--

DROP TABLE IF EXISTS `abonnements`;
CREATE TABLE IF NOT EXISTS `abonnements` (
  `id_abonnement` int NOT NULL AUTO_INCREMENT,
  `id_candidat` int DEFAULT NULL,
  `id_recruteur` int DEFAULT NULL,
  `date_abonnement` date DEFAULT NULL,
  PRIMARY KEY (`id_abonnement`),
  KEY `id_candidat` (`id_candidat`),
  KEY `id_recruteur` (`id_recruteur`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `abonnements`
--

INSERT INTO `abonnements` (`id_abonnement`, `id_candidat`, `id_recruteur`, `date_abonnement`) VALUES
(1, 1, 1, '2026-01-20'),
(10, 3, 2, '2026-03-09'),
(9, 2, 7, '2026-02-14');

-- --------------------------------------------------------

--
-- Structure de la table `candidats`
--

DROP TABLE IF EXISTS `candidats`;
CREATE TABLE IF NOT EXISTS `candidats` (
  `id_candidat` int NOT NULL AUTO_INCREMENT,
  `id_user` int DEFAULT NULL,
  `nom` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `prenom` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telephone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `date_naissance` date DEFAULT NULL,
  `niveau_etudes` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `adresse` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ville` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cv_numerique` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `bio` text COLLATE utf8mb4_general_ci,
  `domaine` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `photo_profil` varchar(55) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id_candidat`),
  UNIQUE KEY `idx_id_user` (`id_user`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `candidats`
--

INSERT INTO `candidats` (`id_candidat`, `id_user`, `nom`, `prenom`, `email`, `telephone`, `date_naissance`, `niveau_etudes`, `adresse`, `ville`, `cv_numerique`, `bio`, `domaine`, `photo_profil`) VALUES
(3, 6, 'Ouedraogo', 'Aminata', NULL, NULL, '1998-03-15', 'BAC+3', 'Secteur 22, Rue 22-104, Ouagadougou', 'Ouagadougou', NULL, 'Développeuse web passionnée, diplômée en Génie Logiciel à l\\\'Institut Supérieur d\\\'Informatique de Ouagadougou. Je recherche une opportunité pour mettre à profit mes compétences en développement web et contribuer à des projets innovants au Burkina Faso.', 'Informatique & Tech', NULL),
(2, 3, 'MAIGA', 'Yasmina', NULL, NULL, '2003-12-05', 'BAC+5 (Master, Ingénieur)', 'Marcoussis', 'Ouagadougou', NULL, 'Développeuse passionnée', 'Informatique & Tech', 'PHOTO_2_1771601522.png');

-- --------------------------------------------------------

--
-- Structure de la table `candidatures`
--

DROP TABLE IF EXISTS `candidatures`;
CREATE TABLE IF NOT EXISTS `candidatures` (
  `id_candidature` int NOT NULL AUTO_INCREMENT,
  `id_offre` int DEFAULT NULL,
  `id_candidat` int DEFAULT NULL,
  `date_candidature` date DEFAULT NULL,
  `lettre_motivation` text COLLATE utf8mb4_general_ci,
  `statut` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'En attente',
  PRIMARY KEY (`id_candidature`),
  KEY `id_offre` (`id_offre`),
  KEY `id_candidat` (`id_candidat`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `candidatures`
--

INSERT INTO `candidatures` (`id_candidature`, `id_offre`, `id_candidat`, `date_candidature`, `lettre_motivation`, `statut`) VALUES
(1, 1, 1, '2026-01-20', 'Motivé et disponible immédiatement', 'envoyée'),
(3, 4, 2, '2026-02-14', 'jghfdouiutyrttresdfghvbnjkhytrtyuiolk;,nbvfghilhjgfdjkkkjhfgfdfghjiuhdfghiljkhfvbnlkjhghfggghiuytdfghkjhnnjhgfghkjhhbnnjhhgbn,nbvfghj', 'En attente'),
(4, 5, 2, '2026-02-14', 'kjhgfdfghjjkhgfftyuiokjhgfdxcvbnkjhgfgftyjkhhcgfvbnnnbvcfgjjkbvcfhnhjgkfgcfvvvvvvvvvvvvjvbn;nvnbcn;bnbvb;vbnhghjjjhh,nbnbnbnnnb;,nbbhghbnbnn nbbjkhuhgbn,;nb', 'Acceptée');

-- --------------------------------------------------------

--
-- Structure de la table `candidat_competences`
--

DROP TABLE IF EXISTS `candidat_competences`;
CREATE TABLE IF NOT EXISTS `candidat_competences` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_candidat` int NOT NULL,
  `competence` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_candidat` (`id_candidat`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `candidat_competences`
--

INSERT INTO `candidat_competences` (`id`, `id_candidat`, `competence`) VALUES
(1, 2, 'Java'),
(2, 2, 'Php'),
(3, 2, 'react'),
(4, 3, 'Php'),
(5, 3, 'Javascript'),
(6, 3, 'MySQL'),
(8, 3, 'Communication'),
(9, 3, 'Anglais'),
(10, 3, 'React'),
(11, 3, 'Excel'),
(13, 3, 'Word');

-- --------------------------------------------------------

--
-- Structure de la table `candidat_experiences`
--

DROP TABLE IF EXISTS `candidat_experiences`;
CREATE TABLE IF NOT EXISTS `candidat_experiences` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_candidat` int NOT NULL,
  `poste` varchar(255) NOT NULL,
  `entreprise` varchar(255) NOT NULL,
  `date_debut` varchar(20) DEFAULT NULL,
  `date_fin` varchar(20) DEFAULT NULL,
  `en_cours` tinyint(1) DEFAULT '0',
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id_candidat` (`id_candidat`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `candidat_experiences`
--

INSERT INTO `candidat_experiences` (`id`, `id_candidat`, `poste`, `entreprise`, `date_debut`, `date_fin`, `en_cours`, `description`, `created_at`) VALUES
(4, 3, 'Développeuse Web Junior', 'Aziz Informatique', '2025-12', '2026-02', 0, 'Développement et maintenance de sites web clients en PHP/MySQL, Participation aux réunions client et rédaction de cahiers des charges.', '2026-03-09 22:13:16');

-- --------------------------------------------------------

--
-- Structure de la table `candidat_formations`
--

DROP TABLE IF EXISTS `candidat_formations`;
CREATE TABLE IF NOT EXISTS `candidat_formations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_candidat` int NOT NULL,
  `titre` varchar(255) NOT NULL,
  `etablissement` varchar(255) NOT NULL,
  `annee` varchar(20) DEFAULT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id_candidat` (`id_candidat`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `candidat_formations`
--

INSERT INTO `candidat_formations` (`id`, `id_candidat`, `titre`, `etablissement`, `annee`, `description`, `created_at`) VALUES
(1, 3, 'Licence en Génie Logiciel', 'Institut Supérieur d\\\'Informatique (ISI)', '2021', 'Spécialisation en développement web et base de données. Mention Bien', '2026-03-09 22:15:13'),
(2, 3, 'BTS Informatique de Gestion', 'Lycée Technique de Ouagadougou', '2018', 'Option développement d\\\'applications', '2026-03-09 22:16:52');

-- --------------------------------------------------------

--
-- Structure de la table `demandes_emploi`
--

DROP TABLE IF EXISTS `demandes_emploi`;
CREATE TABLE IF NOT EXISTS `demandes_emploi` (
  `id_demande_emploi` int NOT NULL AUTO_INCREMENT,
  `id_candidat` int DEFAULT NULL,
  `poste_recherche` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description_demande` text COLLATE utf8mb4_general_ci,
  `type_contrat_souhaite` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `localisation_souhaitee` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `salaire_souhaite` int DEFAULT NULL,
  `date_demande` date DEFAULT NULL,
  `date_modification` date DEFAULT NULL,
  PRIMARY KEY (`id_demande_emploi`),
  KEY `id_candidat` (`id_candidat`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `demandes_emploi`
--

INSERT INTO `demandes_emploi` (`id_demande_emploi`, `id_candidat`, `poste_recherche`, `description_demande`, `type_contrat_souhaite`, `localisation_souhaitee`, `salaire_souhaite`, `date_demande`, `date_modification`) VALUES
(1, 1, 'Développeur Web', 'Je recherche un poste en développement web', 'CDI', 'Ouagadougou', 250000, '2026-01-20', '2026-01-20'),
(2, 2, 'Assistante Agricole', 'Travail dans le domaine agricole', 'CDD', 'Bobo-Dioulasso', 200000, '2026-01-20', '2026-01-20');

-- --------------------------------------------------------

--
-- Structure de la table `domaines`
--

DROP TABLE IF EXISTS `domaines`;
CREATE TABLE IF NOT EXISTS `domaines` (
  `id_domaine` int NOT NULL AUTO_INCREMENT,
  `nom_domaine` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description_domaine` text COLLATE utf8mb4_general_ci,
  `nombre_offre` int DEFAULT NULL,
  PRIMARY KEY (`id_domaine`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `domaines`
--

INSERT INTO `domaines` (`id_domaine`, `nom_domaine`, `description_domaine`, `nombre_offre`) VALUES
(1, 'Informatique & Tech', 'Développement, réseaux, systèmes et cybersécurité', 0),
(2, 'Finance & Comptabilité', 'Comptabilité, audit, finance et contrôle de gestion', 0),
(3, 'Ressources Humaines', 'Recrutement, formation, paie et administration RH', 0),
(4, 'Commerce & Marketing', 'Vente, marketing digital, communication', 0),
(5, 'Santé & Médecine', 'Médecine, pharmacie, soins infirmiers', 0),
(6, 'Agriculture & Élevage', 'Agronomie, production agricole, élevage', 0);

-- --------------------------------------------------------

--
-- Structure de la table `entreprises`
--

DROP TABLE IF EXISTS `entreprises`;
CREATE TABLE IF NOT EXISTS `entreprises` (
  `id_entreprise` int NOT NULL AUTO_INCREMENT,
  `nom_entreprise` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `secteur` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `site_web` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email_entreprise` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telephone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `adresse` text COLLATE utf8mb4_general_ci,
  `ville` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nombre_employes` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `logo` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `statut` enum('active','inactive') COLLATE utf8mb4_general_ci DEFAULT 'active',
  `id_recruteur` int DEFAULT NULL,
  PRIMARY KEY (`id_entreprise`),
  KEY `id_recruteur` (`id_recruteur`),
  KEY `idx_ville` (`ville`),
  KEY `idx_secteur` (`secteur`),
  KEY `idx_statut` (`statut`)
) ENGINE=MyISAM AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `entreprises`
--

INSERT INTO `entreprises` (`id_entreprise`, `nom_entreprise`, `secteur`, `description`, `site_web`, `email_entreprise`, `telephone`, `adresse`, `ville`, `nombre_employes`, `logo`, `date_creation`, `statut`, `id_recruteur`) VALUES
(1, 'SONABEL', 'Énergie', 'Société Nationale Burkinabè d\'Electricité, créée en 1954. SONABEL assure la production, le transport et la distribution de l\'électricité sur l\'ensemble du territoire burkinabè. Elle gère un réseau couvrant les principales villes et s\'engage dans des projets d\'énergie solaire pour électrifier les zones rurales.', 'https://www.sonabel.bf', 'contact@sonabel.bf', '+226 25 30 61 00', 'Avenue de l\'Indépendance, Secteur 4', 'Ouagadougou', '1000+', NULL, '2026-02-20 18:08:52', 'active', 1),
(2, 'ONATEL SA', 'Télécommunications', 'Office National des Télécommunications du Burkina Faso. Filiale du groupe Maroc Telecom, ONATEL est l\'opérateur historique des télécommunications au Burkina Faso. Il propose des services de téléphonie fixe, d\'internet haut débit (fibre optique, ADSL) et de téléphonie mobile via sa marque Telmob.', 'https://www.onatel.bf', 'contact@onatel.bf', '+226 25 33 40 00', 'Avenue de la Liberté, Secteur 4', 'Ouagadougou', '500-1000', NULL, '2026-02-20 18:08:52', 'active', 2),
(3, 'Coris Bank International', 'Banque & Finance', 'Banque panafricaine fondée en 2008 à Ouagadougou, dont le siège social est au Burkina Faso. Coris Bank International est présente dans 9 pays d\'Afrique de l\'Ouest et du Centre. Elle offre une gamme complète de services bancaires aux particuliers, PME et grandes entreprises, avec un fort ancrage dans le financement des économies locales.', 'https://www.corisbank.bf', 'info@corisbank.bf', '+226 25 30 08 08', 'Avenue Kwame N\'Krumah, Secteur 4', 'Ouagadougou', '500-1000', NULL, '2026-02-20 18:08:52', 'active', 3),
(4, 'SOFITEX', 'Agriculture', 'Société Burkinabè des Fibres Textiles, créée en 1979. SOFITEX est le principal acteur de la filière coton au Burkina Faso, premier pays producteur de coton en Afrique subsaharienne. Elle encadre plus de 200 000 producteurs, exploite des usines d\'égrenage et commercialise la fibre de coton à l\'international.', 'https://www.sofitex.bf', 'info@sofitex.bf', '+226 20 97 00 14', 'Avenue de la Nation, Secteur 21', 'Bobo-Dioulasso', '1000+', NULL, '2026-02-20 18:08:52', 'active', 4),
(5, 'BRAKINA', 'Agroalimentaire', 'Brasserie du Burkina Faso, filiale du groupe Castel depuis 1998. BRAKINA est la principale brasserie du pays et produit les marques Brakina, Beaufort, Bière So.b et des boissons non alcoolisées. Ses produits sont distribués sur tout le territoire national et dans certains pays voisins.', 'https://www.brakina.bf', 'contact@brakina.bf', '+226 25 30 70 70', 'Zone industrielle de Kossodo', 'Ouagadougou', '200-500', NULL, '2026-02-20 18:08:52', 'active', 5),
(6, 'Groupe Yiriwa Conseil', 'Conseil & Formation', 'Cabinet burkinabè de conseil en management et en ressources humaines fondé en 2005. Yiriwa Conseil intervient dans les domaines de l\'audit organisationnel, du recrutement et de la chasse de tête, de la formation professionnelle et du conseil RH. Il accompagne des entreprises privées, des ONG et des institutions publiques en Afrique de l\'Ouest.', 'https://www.yiriwa.bf', 'contact@yiriwa.bf', '+226 25 36 12 00', 'Rue 15-67, Gounghin Nord', 'Ouagadougou', '50-100', NULL, '2026-02-20 18:08:52', 'active', 6),
(7, 'FASO CORIS', 'Microfinance', 'Institution de microfinance burkinabè proposant des services financiers accessibles aux populations à faibles revenus, artisans, commerçants et petits entrepreneurs. FASO CORIS dispose d\'un réseau d\'agences dans les principales villes et dans les zones rurales du pays, et contribue activement à l\'inclusion financière au Burkina Faso.', 'https://www.fasocoris.bf', 'info@fasocoris.bf', '+226 25 31 00 50', 'Rue du Commerce, Secteur 1', 'Ouagadougou', '100-200', NULL, '2026-02-20 18:08:52', 'active', NULL),
(8, 'EBOMAF', 'BTP & Construction', 'Entreprise burkinabè leader dans le secteur du BTP et des travaux publics, EBOMAF réalise des routes, ponts, bâtiments et infrastructures hydrauliques. Elle intervient au Burkina Faso et dans plusieurs pays de la sous-région ouest-africaine. Avec ses propres équipements lourds, elle est l\'une des entreprises de génie civil les plus importantes du pays.', 'https://www.ebomaf.com', 'contact@ebomaf.bf', '+226 25 37 52 00', 'Secteur 15, Zone Industrielle', 'Ouagadougou', '500-1000', NULL, '2026-02-20 18:08:52', 'active', NULL),
(9, 'Canal+ Burkina', 'Médias & Audiovisuel', 'Filiale du groupe Canal+ en Afrique, Canal+ Burkina propose des services de télévision payante par satellite avec des contenus sportifs, cinématographiques et de divertissement. Elle distribue ses offres aux particuliers et entreprises et participe au développement de la production audiovisuelle locale burkinabè.', 'https://www.canalplus-afrique.com', 'client@canalplus.bf', '+226 25 50 11 00', 'Avenue Babanguida, Koulouba', 'Ouagadougou', '100-200', NULL, '2026-02-20 18:08:52', 'active', NULL),
(10, 'SITARAIL', 'Transport & Logistique', 'Société internationale de transport sur Rail, SITARAIL exploite la ligne ferroviaire Abidjan-Ouagadougou longue de 1 260 km. Elle assure le transport de marchandises et de voyageurs entre la Côte d\'Ivoire et le Burkina Faso, jouant un rôle clé dans le désenclavement et le commerce régional de l\'Afrique de l\'Ouest.', 'https://www.sitarail.com', 'contact@sitarail.bf', '+226 25 30 55 00', 'Avenue de la Gare, Secteur 1', 'Ouagadougou', '200-500', NULL, '2026-02-20 18:08:52', 'active', NULL),
(11, 'PharmaBF', 'Santé & Pharmacie', 'Grossiste-répartiteur pharmaceutique burkinabè, PharmaBF assure l\'importation, le stockage et la distribution de médicaments, dispositifs médicaux et produits de santé aux officines, cliniques et hôpitaux du Burkina Faso. La société s\'engage à garantir l\'accessibilité des médicaments essentiels sur tout le territoire national.', 'https://www.pharmabf.bf', 'contact@pharmabf.bf', '+226 25 34 10 20', 'Zone Industrielle de Gounghin', 'Ouagadougou', '100-200', NULL, '2026-02-20 18:08:52', 'active', NULL),
(12, 'Aziz Informatique', 'Informatique & Tech', 'Société informatique burkinabè spécialisée dans la vente de matériels informatiques, la maintenance, le développement de logiciels sur mesure et la formation professionnelle en informatique. Aziz Informatique accompagne les PME, administrations et particuliers dans leur transformation numérique depuis plus de 15 ans à Ouagadougou.', 'https://www.azizinformatique.bf', 'contact@azizinformatique.bf', '+226 25 33 78 00', 'Avenue Loudun, Secteur 4', 'Ouagadougou', '20-50', NULL, '2026-02-20 18:08:52', 'active', NULL),
(13, 'Digital Solutions BF', 'Informatique & Tech', 'Société burkinabè spécialisée dans le développement de solutions numériques pour les entreprises et institutions. Digital Solutions BF intervient dans la conception d\\\'applications web et mobiles, l\\\'intégration de systèmes ERP, la cybersécurité et la formation en informatique. Partenaire de confiance des PME et administrations depuis 2015.', 'https://www.digitalsolutionsbf.com', 'contact@digitalsolutionsbf.com', '+226 25 41 78 90', 'Avenue Kwame N\\\'Krumah, Secteur 4', 'Ouagadougou', '11-50', NULL, '2026-02-20 21:34:36', 'active', 7);

-- --------------------------------------------------------

--
-- Structure de la table `favoris`
--

DROP TABLE IF EXISTS `favoris`;
CREATE TABLE IF NOT EXISTS `favoris` (
  `id_favoris` int NOT NULL AUTO_INCREMENT,
  `id_candidat` int DEFAULT NULL,
  `id_offre` int DEFAULT NULL,
  `date_ajout` date DEFAULT NULL,
  PRIMARY KEY (`id_favoris`),
  KEY `id_candidat` (`id_candidat`),
  KEY `id_offre` (`id_offre`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `favoris`
--

INSERT INTO `favoris` (`id_favoris`, `id_candidat`, `id_offre`, `date_ajout`) VALUES
(1, 1, 1, '2026-01-20'),
(2, 2, 2, '2026-01-20'),
(5, 3, 12, '2026-03-09');

-- --------------------------------------------------------

--
-- Structure de la table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `id_notification` int NOT NULL AUTO_INCREMENT,
  `id_user` int DEFAULT NULL,
  `type_notification` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `message` text COLLATE utf8mb4_general_ci,
  `date_envoi` date DEFAULT NULL,
  PRIMARY KEY (`id_notification`),
  KEY `id_user` (`id_user`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `offres_emploi`
--

DROP TABLE IF EXISTS `offres_emploi`;
CREATE TABLE IF NOT EXISTS `offres_emploi` (
  `id_offre` int NOT NULL AUTO_INCREMENT,
  `id_recruteur` int DEFAULT NULL,
  `id_secteur` int DEFAULT NULL,
  `id_domaine` int DEFAULT NULL,
  `titre_emploi` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description_offre` text COLLATE utf8mb4_general_ci,
  `competences_requises` text COLLATE utf8mb4_general_ci,
  `localisation` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `date_publication` date DEFAULT NULL,
  `statut_recherche` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `date_limite` date DEFAULT NULL,
  `salaire_min` int DEFAULT NULL,
  `salaire_max` int DEFAULT NULL,
  `profil_recherche` text COLLATE utf8mb4_general_ci,
  `avantages` text COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id_offre`),
  KEY `id_recruteur` (`id_recruteur`),
  KEY `id_secteur` (`id_secteur`),
  KEY `id_domaine` (`id_domaine`)
) ENGINE=MyISAM AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `offres_emploi`
--

INSERT INTO `offres_emploi` (`id_offre`, `id_recruteur`, `id_secteur`, `id_domaine`, `titre_emploi`, `description_offre`, `competences_requises`, `localisation`, `date_publication`, `statut_recherche`, `date_limite`, `salaire_min`, `salaire_max`, `profil_recherche`, `avantages`) VALUES
(1, 1, 5, 1, 'Ingénieur Électricien - Production & Distribution', 'SONABEL recrute un Ingénieur Électricien pour sa Direction Technique chargée de l\'exploitation du réseau électrique national. Vous assurerez la supervision des ouvrages de production (centrales thermiques et solaires), participerez aux projets d\'extension du réseau HTA/BT dans les zones périurbaines et rurales, rédigerez les rapports techniques d\'exploitation et proposerez des améliorations pour optimiser la performance du réseau.', 'Génie électrique, Réseaux HTA/BT, Centrales thermiques et solaires, AutoCAD Électrique, Normes IEC, Gestion de chantier, Rapport technique', 'Ouagadougou', '2026-02-10', 'CDI', '2026-05-01', 400000, 650000, 'Diplôme d\'Ingénieur en Génie Électrique (Bac+5). Minimum 2 ans d\'expérience dans l\'exploitation de réseaux électriques ou centrales de production. Permis B.', 'Véhicule de service, Assurance maladie groupe, Indemnité logement, Formation technique internationale, Retraite complémentaire'),
(2, 1, 5, 3, 'Chargé de Ressources Humaines et de la Formation', 'La Direction des Ressources Humaines de SONABEL recrute un Chargé RH pour renforcer son équipe. Vous gérerez l\'administration du personnel (contrats, absences, congés, paie), piloterez le plan de formation annuel, assurerez le suivi des indicateurs sociaux et veillerez au respect du Code du Travail burkinabè et des conventions collectives en vigueur dans le secteur de l\'énergie.', 'Droit du travail burkinabè, Administration RH, Gestion de la paie, SIRH, Gestion de la formation, Excel, Communication', 'Ouagadougou', '2026-02-15', 'CDI', '2026-04-30', 280000, 430000, 'Bac+4/5 en Ressources Humaines, Droit ou Gestion. Minimum 3 ans d\'expérience en RH. Maîtrise du droit social burkinabè obligatoire.', 'Assurance maladie famille, 13ème mois, Prise en charge transport, Formation continue'),
(3, 2, 1, 1, 'Ingénieur Systèmes & Réseaux Télécom', 'ONATEL SA recrute un Ingénieur Systèmes et Réseaux pour sa Direction des Infrastructures Télécoms. Vous assurerez l\'exploitation, la maintenance et l\'évolution des équipements du réseau national (backbone fibre optique, nœuds de commutation, équipements IP/MPLS), coordonnerez les interventions techniques en cas de pannes et participerez aux projets de modernisation du réseau.', 'Réseaux IP/MPLS, Fibre optique, Cisco/Huawei, BGP/OSPF, Supervision réseau, Linux, Documentation technique', 'Ouagadougou', '2026-02-12', 'CDI', '2026-05-15', 380000, 580000, 'Ingénieur Télécoms ou Réseaux (Bac+5). 2 ans d\'expérience minimum en exploitation de réseaux télécoms. Certification Cisco ou Huawei appréciée.', 'Téléphone et internet illimités, Assurance maladie, Véhicule de service, Formation et certifications constructeurs, Prime annuelle'),
(4, 2, 1, 4, 'Chargé Marketing & Offres Commerciales', 'ONATEL recrute un Chargé Marketing chargé de concevoir et promouvoir ses offres commerciales (internet, mobile, fixe, entreprises). Vous analyserez le marché et la concurrence, développerez de nouvelles offres adaptées aux segments cibles, coordonnerez les actions promotionnelles avec les équipes commerciales et suivrez les KPI marketing.', 'Marketing des télécoms, Analyse de marché, Développement d\'offres, CRM, Microsoft Office, Présentation, Rédaction', 'Ouagadougou', '2026-02-18', 'CDI', '2026-04-30', 250000, 400000, 'Bac+4/5 en Marketing, Commerce ou Télécoms. 2 à 3 ans d\'expérience en marketing dans un secteur concurrentiel. Connaissance du marché burkinabè.', 'Forfait internet, Téléphone professionnel, Assurance maladie, Prime sur objectifs'),
(5, 3, 2, 2, 'Analyste Financier - Département Crédit Entreprises', 'Coris Bank International recherche un Analyste Financier pour son Département Crédit Entreprises. Vous analyserez les demandes de financement des PME et grandes entreprises (études de faisabilité, analyse des états financiers, scoring de risque), préparerez les mémos de crédit pour les comités compétents et participerez au suivi des engagements.', 'Analyse financière, SYSCOHADA révisé, Modélisation financière, Excel avancé, Droit des affaires OHADA, Crédit scoring, Rigueur analytique', 'Ouagadougou', '2026-02-08', 'CDI', '2026-03-31', 350000, 550000, 'Bac+4/5 en Finance, Comptabilité ou Économie. 2 à 4 ans d\'expérience en analyse crédit ou audit financier. Maîtrise de l\'analyse des états financiers SYSCOHADA.', 'Assurance groupe famille, Prêt bancaire préférentiel, 13ème mois, Possibilité évolution rapide'),
(6, 3, 2, 4, 'Chargé de Clientèle Particuliers - Agence', 'Coris Bank recrute des Chargés de Clientèle Particuliers pour renforcer ses agences de Ouagadougou. Vous accueillerez et conseillerez les clients, commercialiserez les produits bancaires (comptes, épargne, crédits consommation, assurances), atteindrez les objectifs de collecte et de cross-selling, et assurerez la fidélisation de votre portefeuille client.', 'Techniques de vente bancaire, Connaissance produits bancaires, Relation client, Sens commercial, Réglementation BCEAO de base', 'Ouagadougou', '2026-02-20', 'CDI', '2026-04-15', 200000, 320000, 'Bac+3 en Banque, Finance, Commerce ou équivalent. Expérience en relation client appréciée. Très bon sens du contact et orientation résultats.', 'Mutuelle santé, Produits bancaires préférentiels, Formation interne, Prime sur objectifs commerciaux'),
(7, 4, 4, 6, 'Ingénieur Agronome - Encadrement Producteurs Coton', 'SOFITEX recrute un Ingénieur Agronome pour encadrer ses producteurs de coton dans la région des Hauts-Bassins. Vous formerez les agriculteurs aux bonnes pratiques culturales du coton (préparation des sols, semis, fertilisation, traitements phytosanitaires), superviserez les essais variétaux, collecterez les données de rendement et rédigerez des rapports de campagne pour la Direction Technique.', 'Agronomie tropicale, Culture cotonnière, Phytotechnie, Vulgarisation agricole, Rédaction de rapports, GPS/SIG, Permis B', 'Bobo-Dioulasso', '2026-02-14', 'CDI', '2026-05-31', 300000, 480000, 'Ingénieur Agronome (Bac+5) spécialité cultures tropicales. Expérience en encadrement de producteurs agricoles souhaitée. Disponibilité impérative pour déplacements fréquents en zone rurale.', 'Véhicule de mission, Per diem déplacements, Logement de service, Assurance maladie, Formation technique'),
(8, 4, 4, 2, 'Responsable Comptabilité et Contrôle Budgétaire', 'La Direction Financière de SOFITEX recrute un Responsable Comptabilité pour superviser la tenue de la comptabilité générale et analytique de la société. Vous piloterez la clôture mensuelle des comptes, coordonnerez les équipes comptables, assurerez les relations avec les commissaires aux comptes, préparerez les états financiers annuels et veillerez au respect des obligations fiscales.', 'Comptabilité générale et analytique, SYSCOHADA révisé, Fiscalité burkinabè, Sage Comptabilité, Management d\'équipe, Rigueur, Reporting', 'Bobo-Dioulasso', '2026-02-17', 'CDI', '2026-04-30', 400000, 600000, 'Bac+5 en Comptabilité, Finance ou DESCOGEF. Minimum 5 ans d\'expérience dont 2 ans en poste similaire. Maîtrise de SYSCOHADA révisé et fiscalité burkinabè.', 'Logement de fonction ou indemnité, Véhicule de service, Assurance famille, 13ème mois, Retraite complémentaire'),
(9, 5, 6, 4, 'Responsable Commercial Zone Centre-Ouest', 'BRAKINA recrute un Responsable Commercial pour gérer et développer son réseau de distributeurs et de points de vente sur la zone Centre-Ouest (Ouagadougou, Koudougou et provinces). Vous animerez les distributeurs, négocierez les accords commerciaux, planifierez et exécuterez les actions promotionnelles terrain (dégustations, visibilité), et reporterez vos performances à la Direction Commerciale.', 'Développement commercial, Animation réseau distributeurs, Négociation, Merchandising, Reporting commercial, Permis B, CRM', 'Ouagadougou', '2026-02-16', 'CDI', '2026-04-30', 280000, 430000, 'Bac+3/4 en Commerce, Marketing ou équivalent. Minimum 3 ans d\'expérience en force de vente terrain dans le secteur des biens de grande consommation (FMCG). Permis B obligatoire.', 'Véhicule de service, Carburant, Commissions sur chiffre d\'affaires, Prime trimestrielle, Assurance maladie'),
(10, 5, 6, 1, 'Technicien de Maintenance Électromécanique', 'BRAKINA recherche un Technicien de Maintenance Électromécanique pour son site de production de Kossodo. Vous assurerez la maintenance préventive et corrective des équipements de brassage et d\'embouteillage (lignes de conditionnement bouteilles verre et canettes), diagnostiquerez les pannes mécaniques et électriques, gérerez les pièces de rechange et contribuerez à l\'amélioration continue du taux de disponibilité machines.', 'Mécanique industrielle, Électrotechnique, Pneumatique et hydraulique, GMAO, Lecture de plans mécaniques et électriques, Diagnostic de pannes', 'Ouagadougou', '2026-02-19', 'CDI', '2026-05-31', 250000, 380000, 'BTS ou Licence Professionnelle en Maintenance Industrielle, Électromécanique ou MEI. Minimum 2 ans d\'expérience en maintenance d\'équipements industriels (agroalimentaire apprécié).', 'Cantine d\'entreprise, EPI fournis, Assurance maladie, Heures supplémentaires rémunérées, Prime de production mensuelle'),
(11, 6, 1, 3, 'Consultant Senior en Ressources Humaines', 'Le Groupe Yiriwa Conseil recrute un Consultant Senior RH pour renforcer son pôle conseil. Vous réaliserez des missions d\'audit organisationnel, d\'évaluation des compétences, de conception de politiques RH et de définition de référentiels métiers pour des clients variés (entreprises privées, ONG, institutions publiques). Vous contribuerez également au développement commercial du cabinet.', 'Conseil RH, Audit organisationnel, Gestion des compétences, Droit social burkinabè, Rédaction de rapports, Animation de comités, Développement commercial', 'Ouagadougou', '2026-02-17', 'CDD', '2026-04-15', 350000, 550000, 'Bac+5 en RH, Management ou équivalent. Minimum 4 ans d\'expérience dont une expérience significative en cabinet de conseil. Excellente maîtrise du français écrit et oral.', 'Véhicule pour missions terrain, Per diem déplacements, Formation continue, Prime de performance, Réseau professionnel panafricain'),
(12, 6, 1, 1, 'Stagiaire Développeur Web - PFE', 'Le Groupe Yiriwa Conseil offre un stage de fin d\'études à un développeur web junior. Vous participerez au développement de la nouvelle plateforme numérique interne du cabinet (gestion des missions, portail candidats, tableau de bord RH) sous la supervision d\'un développeur senior. Ce stage est une réelle opportunité de monter en compétences sur un projet concret.', 'HTML5/CSS3, JavaScript (ES6+), PHP ou Python, MySQL, Git/GitHub, Autonomie, Curiosité et rigueur', 'Ouagadougou', '2026-02-20', 'Stage', '2026-03-25', 60000, 80000, 'Étudiant(e) en Bac+3/4 en Informatique ou Génie Logiciel. Projets personnels ou académiques sur GitHub appréciés. Lettre de motivation + CV + relevés de notes requis.', 'Indemnité mensuelle, Encadrement professionnel dédié, Attestation + rapport d\'évaluation détaillé, Possibilité d\'embauche selon profil');

-- --------------------------------------------------------

--
-- Structure de la table `recruteurs`
--

DROP TABLE IF EXISTS `recruteurs`;
CREATE TABLE IF NOT EXISTS `recruteurs` (
  `id_recruteur` int NOT NULL AUTO_INCREMENT,
  `id_user` int DEFAULT NULL,
  `nom_entreprise` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ville_entreprise` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `adresse_professionnelle` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nombre_employes` int DEFAULT NULL,
  `description_entreprise` text COLLATE utf8mb4_general_ci,
  `secteur` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id_recruteur`),
  UNIQUE KEY `idx_id_user_recruteur` (`id_user`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `recruteurs`
--

INSERT INTO `recruteurs` (`id_recruteur`, `id_user`, `nom_entreprise`, `ville_entreprise`, `adresse_professionnelle`, `nombre_employes`, `description_entreprise`, `secteur`) VALUES
(1, NULL, 'SONABEL', 'Ouagadougou', 'Avenue de l\'Indépendance, Secteur 4', 2500, 'Société Nationale Burkinabè d\'Electricité. Principal fournisseur d\'énergie électrique au Burkina Faso.', 'Énergie'),
(2, NULL, 'ONATEL SA', 'Ouagadougou', 'Avenue de la Liberté, Secteur 4', 1000, 'Opérateur historique des télécommunications au Burkina Faso (Telmob, internet, fixe).', 'Télécommunications'),
(3, NULL, 'Coris Bank International', 'Ouagadougou', 'Avenue Kwame N\'Krumah, Secteur 4', 800, 'Banque panafricaine basée à Ouagadougou, présente dans 9 pays d\'Afrique.', 'Banque & Finance'),
(4, NULL, 'SOFITEX', 'Bobo-Dioulasso', 'Avenue de la Nation, Secteur 21', 3000, 'Leader de la filière coton au Burkina Faso, premier pays producteur en Afrique subsaharienne.', 'Agriculture'),
(5, NULL, 'BRAKINA', 'Ouagadougou', 'Zone Industrielle de Kossodo', 600, 'Principale brasserie du Burkina Faso, filiale du groupe Castel. Producteur de Brakina, Beaufort, So.b.', 'Agroalimentaire'),
(6, NULL, 'Groupe Yiriwa Conseil', 'Ouagadougou', 'Rue 15-67, Gounghin Nord', 150, 'Cabinet burkinabè de conseil en management, recrutement et formation professionnelle.', 'Conseil & Formation'),
(7, 5, 'Digital Solutions BF', 'Ouagadougou', 'Avenue Kwame N\\\'Krumah, Secteur 4', 11, 'Société burkinabè spécialisée dans le développement de solutions numériques pour les entreprises et institutions. Digital Solutions BF intervient dans la conception d\\\'applications web et mobiles, l\\\'intégration de systèmes ERP, la cybersécurité et la formation en informatique. Partenaire de confiance des PME et administrations depuis 2015.', 'Informatique & Tech');

-- --------------------------------------------------------

--
-- Structure de la table `secteurs`
--

DROP TABLE IF EXISTS `secteurs`;
CREATE TABLE IF NOT EXISTS `secteurs` (
  `id_secteur` int NOT NULL AUTO_INCREMENT,
  `nom_secteur` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description_secteur` text COLLATE utf8mb4_general_ci,
  `date_creation` date DEFAULT NULL,
  PRIMARY KEY (`id_secteur`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `secteurs`
--

INSERT INTO `secteurs` (`id_secteur`, `nom_secteur`, `description_secteur`, `date_creation`) VALUES
(1, 'Technologie & Numérique', 'Entreprises du secteur IT, télécoms et numérique', '2024-01-01'),
(2, 'Banque & Finance', 'Institutions financières, banques et assurances', '2024-01-01'),
(3, 'Santé & Pharmaceutique', 'Hôpitaux, cliniques, laboratoires et pharmacies', '2024-01-01'),
(4, 'Agro-industrie', 'Agriculture, élevage et transformation agroalimentaire', '2024-01-01'),
(5, 'Énergie & BTP', 'Mines, énergie, construction et travaux publics', '2024-01-01'),
(6, 'Commerce & Distribution', 'Grande distribution, commerce de gros et détail', '2024-01-01');

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

DROP TABLE IF EXISTS `utilisateurs`;
CREATE TABLE IF NOT EXISTS `utilisateurs` (
  `id_user` int NOT NULL AUTO_INCREMENT,
  `nom_user` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('candidat','recruteur','admin') COLLATE utf8mb4_general_ci NOT NULL,
  `tel_user` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `derniere_connexion` timestamp NULL DEFAULT NULL,
  `statut` enum('actif','inactif','suspendu') COLLATE utf8mb4_general_ci DEFAULT 'actif',
  PRIMARY KEY (`id_user`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_email` (`email`),
  KEY `idx_role` (`role`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `utilisateurs`
--

INSERT INTO `utilisateurs` (`id_user`, `nom_user`, `email`, `password`, `role`, `tel_user`, `date_creation`, `derniere_connexion`, `statut`) VALUES
(6, 'OUEDRAOGO', 'aminataouedraogo@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'candidat', '+226 70 12 34 56', '2026-03-09 21:54:43', NULL, 'actif'),
(5, 'DIGITAL SOLUTIONS BF', 'contact@digitalsolutionsbf.com', '$2y$10$rqxY0.Dp7qQ1.3qsD0w.UO1G6ftc4/T9YCAVEffn3Fea/5iyVh2mK', 'recruteur', '+226 25 41 78 90', '2026-02-20 21:34:36', NULL, 'actif');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
