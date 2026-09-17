-- ============================================================================
-- Migration — index sur les codes-barres de Produit
--
-- Contexte : le lookup code-barre (scan à la vente, à l'approvisionnement,
-- au scanner mobile) filtre sur
--   Id_Entreprise = ? AND (Code_Barre_Unite = ? OR Code_Barre_Carton = ?)
-- Seul Id_Entreprise est indexé aujourd'hui. Un index sur chaque colonne de
-- code-barre (plutôt qu'un composite démarrant par Id_Entreprise, peu
-- sélectif ici) permet à MySQL de restreindre directement sur la valeur
-- scannée, qui est quasi unique par nature.
--
-- Non destructif : ADD INDEX uniquement, aucune donnée modifiée.
-- ============================================================================

USE `facturation`;

ALTER TABLE `Produit`
  ADD INDEX `idx_produit_code_barre_unite` (`Code_Barre_Unite`),
  ADD INDEX `idx_produit_code_barre_carton` (`Code_Barre_Carton`);
