-- ============================================================================
-- Migration — localisation de livraison choisie par l'acheteur (commandes B2B)
--
-- Contexte : Commande_B2B a deja Adresse_Retrait pour le mode "retrait sur
-- place" (l'acheteur precise ou il vient chercher la marchandise), mais rien
-- d'equivalent pour le mode "livraison" : l'adresse de livraison retombait
-- silencieusement sur l'adresse enregistree de l'entreprise acheteuse
-- (Entreprise.Adresse_Entreprise), sans que l'acheteur ne puisse preciser un
-- point de livraison different pour cette commande precise (un depot, un
-- chantier, une autre adresse que le siege).
--
-- Cette migration ajoute le symetrique de Adresse_Retrait pour le mode
-- livraison, avec des coordonnees (le texte seul ne suffit pas au livreur
-- qui navigue via la carte Leaflet/OSRM deja en place).
--
-- Non destructif : ADD COLUMN uniquement, aucune donnee existante modifiee.
-- ============================================================================

USE `facturation`;

ALTER TABLE `Commande_B2B`
  ADD COLUMN `Adresse_Livraison` text DEFAULT NULL COMMENT 'Point de livraison choisi par l acheteur (mode livraison) ; NULL = adresse de l entreprise en secours' AFTER `Adresse_Retrait`,
  ADD COLUMN `Latitude_Livraison` decimal(10,7) DEFAULT NULL AFTER `Adresse_Livraison`,
  ADD COLUMN `Longitude_Livraison` decimal(10,7) DEFAULT NULL AFTER `Latitude_Livraison`;
