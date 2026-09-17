-- ============================================================================
-- Migration — index composite sur Notification_B2B
--
-- Contexte : le badge de notifications non lues (NotificationApiController,
-- interrogé à chaque chargement de page côté header) et la page Notifications
-- filtrent systématiquement sur (Id_Entreprise_Destinataire, Est_Lue). Seul
-- Id_Entreprise_Destinataire est indexé aujourd'hui ; MySQL doit donc balayer
-- toutes les notifications de l'entreprise pour compter les non lues.
--
-- Non destructif : ADD INDEX uniquement, aucune donnée modifiée.
-- ============================================================================

USE `facturation`;

ALTER TABLE `Notification_B2B`
  ADD INDEX `idx_notif_dest_lue` (`Id_Entreprise_Destinataire`, `Est_Lue`);
