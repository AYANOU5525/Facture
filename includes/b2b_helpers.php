<?php

/**
 * b2b_helpers.php — Fonctions utilitaires partagées pour le module B2B
 *
 * Inclus par toutes les pages et APIs B2B.
 * Centralise la logique métier pour éviter la duplication de code.
 *
 * @package FactuPro B2B v3 — Workflow complet + Historique + Carte
 */

const LABEL_VALIDEE = 'Validée';

// ============================================================
// NOTIFICATIONS
// ============================================================

/**
 * Crée une notification interne B2B et déclenche les canaux externes configurés.
 *
 * @param PDO    $pdo           Instance PDO
 * @param int    $id_entreprise ID de l'entreprise destinataire
 * @param string $type          Type (voir ENUM Notification_B2B.Type_Notif)
 * @param string $titre         Titre court de la notification
 * @param string $message       Corps du message
 * @param int|null $id_commande ID de la commande liée (optionnel)
 * @return int                  ID de la notification créée
 */
function creerNotificationB2b(PDO $pdo, int $id_entreprise, string $type, string $titre, string $message, ?int $id_commande = null): int
{
    $stmt = $pdo->prepare("
        INSERT INTO Notification_B2B
            (Id_Entreprise_Destinataire, Type_Notif, Titre, Message, Id_Commande_B2B)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$id_entreprise, $type, $titre, $message, $id_commande]);
    $id_notif = (int) $pdo->lastInsertId();

    // --- Déclencher les canaux externes ---

    // Récupérer l'email de l'entreprise destinataire
    $stmt_email = $pdo->prepare("SELECT Email_Entreprise, Nom_Entreprise FROM Entreprise WHERE Id_Entreprise = ?");
    $stmt_email->execute([$id_entreprise]);
    $entreprise = $stmt_email->fetch();

    if ($entreprise && !empty($entreprise['Email_Entreprise'])) {
        envoyerEmailB2b(
            $entreprise['Email_Entreprise'],
            "[FactuPro B2B] $titre",
            "Bonjour {$entreprise['Nom_Entreprise']},\n\n$message\n\nConnectez-vous sur FactuPro pour gérer cette notification.\n\nCordialement,\nFactuPro B2B"
        );
    }

    // SMS — Architecture prête (intégrer Twilio ici)
    // envoyerSmsB2b($id_entreprise, $message);

    // WhatsApp — Architecture prête (intégrer WhatsApp Business API ici)
    // envoyerWhatsappB2b($id_entreprise, $message);

    return $id_notif;
}

/**
 * Envoie un email via PHPMailer (SMTP) si configuré, sinon mail() natif.
 *
 * @param string $to       Adresse email destinataire
 * @param string $subject  Sujet de l'email
 * @param string $body     Corps HTML du message
 * @param string $altBody  Version texte brut (fallback)
 * @return bool            Succès ou échec
 */
function envoyerEmailB2b(string $to, string $subject, string $body, string $altBody = ''): bool
{
    $host     = $_ENV['MAIL_HOST']       ?? '';
    $username = $_ENV['MAIL_USERNAME']   ?? '';
    $password = $_ENV['MAIL_PASSWORD']   ?? '';
    $from     = $_ENV['MAIL_FROM']       ?? 'noreply@factupro.app';
    $fromName = $_ENV['MAIL_FROM_NAME']  ?? 'FactuPro';
    $port     = (int) ($_ENV['MAIL_PORT'] ?? 587);
    $encrypt  = $_ENV['MAIL_ENCRYPTION'] ?? 'tls';

    // Si SMTP configuré, utiliser PHPMailer
    if (!empty($host) && !empty($username) && !empty($password)
        && $username !== 'votre.email@gmail.com') {

        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $username;
            $mail->Password   = $password;
            $mail->SMTPSecure = $encrypt === 'ssl'
                ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $port;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom($from, $fromName);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = $altBody ?: strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

            return $mail->send();
        } catch (Exception $e) {
            error_log("[FactuPro] Échec SMTP vers $to : " . $e->getMessage());
            return false;
        }
    }

    // Fallback : mail() natif (fonctionne si sendmail est configuré sur le serveur)
    $headers  = "From: $fromName <$from>\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "X-Mailer: FactuPro\r\n";

    try {
        return mail($to, $subject, $body, $headers);
    } catch (Exception $e) {
        error_log("[FactuPro] Échec mail() vers $to : " . $e->getMessage());
        return false;
    }
}

/**
 * Stub SMS — Architecture prête pour Twilio / Africa's Talking.
 *
 * Pour activer :
 * 1. Installer le SDK Twilio : composer require twilio/sdk
 * 2. Ajouter dans .env : TWILIO_SID, TWILIO_TOKEN, TWILIO_FROM
 * 3. Décommenter et compléter la logique ci-dessous.
 *
 * @param int    $id_entreprise ID entreprise destinataire
 * @param string $message       Message court (max 160 caractères)
 */
function envoyerSmsB2b(int $id_entreprise, string $message): void
{
    error_log("envoyerSmsB2b stub: $id_entreprise - $message");
    // Action requise : Récupérer le numéro de l'entreprise
    // Action requise : Initialiser le client Twilio avec les variables .env
    // Action requise : Envoyer le SMS
    //
    // Exemple avec Twilio :
    // $client = new Twilio\Rest\Client($_ENV['TWILIO_SID'], $_ENV['TWILIO_TOKEN']);
    // $client->messages->create($tel, [
    //     'from' => $_ENV['TWILIO_FROM'],
    //     'body' => substr($message, 0, 160)
    // ]);
}

/**
 * Stub WhatsApp — Architecture prête pour WhatsApp Business API.
 *
 * Pour activer :
 * 1. Obtenir accès à l'API WhatsApp Business (Meta)
 * 2. Ajouter dans .env : WHATSAPP_TOKEN, WHATSAPP_PHONE_ID
 * 3. Décommenter et compléter la logique ci-dessous.
 *
 * @param int    $id_entreprise ID entreprise destinataire
 * @param string $message       Message à envoyer
 */
function envoyerWhatsappB2b(int $id_entreprise, string $message): void
{
    error_log("envoyerWhatsappB2b stub: $id_entreprise - $message");
    // Action requise : Récupérer le numéro WhatsApp de l'entreprise
    // Action requise : Appeler l'API WhatsApp Business via cURL
    //
    // Exemple :
    // $url = "https://graph.facebook.com/v18.0/{$_ENV['WHATSAPP_PHONE_ID']}/messages";
    // $data = json_encode([
    //     'messaging_product' => 'whatsapp',
    //     'to' => $tel,
    //     'type' => 'text',
    //     'text' => ['body' => $message]
    // ]);
    // $ch = curl_init($url);
    // curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer '.$_ENV['WHATSAPP_TOKEN'], 'Content-Type: application/json']);
    // curl_setopt($ch, CURLOPT_POST, true);
    // curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    // curl_exec($ch);
    // curl_close($ch);
}

// ============================================================
// GÉOLOCALISATION — Formule de Haversine
// ============================================================

/**
 * Calcule la distance en kilomètres entre deux points GPS.
 * Utilise la formule de Haversine (sphère, pas d'ellipsoïde).
 *
 * @param float $lat1 Latitude point 1 (degrés décimaux)
 * @param float $lon1 Longitude point 1
 * @param float $lat2 Latitude point 2
 * @param float $lon2 Longitude point 2
 * @return float Distance en km (arrondie à 1 décimale)
 */
function calculDistanceHaversine(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $rayon_terre = 6371.0; // km

    $dlat = deg2rad($lat2 - $lat1);
    $dlon = deg2rad($lon2 - $lon1);

    $a = sin($dlat / 2) ** 2
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dlon / 2) ** 2;

    $c = 2 * asin(sqrt($a));

    return round($rayon_terre * $c, 1);
}

/**
 * Formate une distance en km en texte lisible.
 *
 * @param float $km Distance en kilomètres
 * @return string   "à 500 m" | "à 5,3 km" | "à 1 250 km"
 */
function formaterDistance(float $km): string
{
    if ($km < 1) {
        return 'à ' . round($km * 1000) . ' m';
    } elseif ($km < 10) {
        return 'à ' . number_format($km, 1, ',', ' ') . ' km';
    } else {
        return 'à ' . number_format(round($km), 0, ',', ' ') . ' km';
    }
}

// ============================================================
// RÉACTIVITÉ VENDEURS (Point 3)
// ============================================================

/**
 * Calcule et formate le temps moyen de réponse d'un vendeur.
 * Basé sur l'historique réel des commandes (Date_Commande → Date_Validation).
 *
 * @param PDO $pdo          Instance PDO
 * @param int $id_entreprise ID de l'entreprise vendeuse
 * @return array            ['label' => string, 'minutes' => float, 'classe' => string]
 */
function getTempsReponseMoyen(PDO $pdo, int $id_entreprise): array
{
    $stmt = $pdo->prepare("
        SELECT AVG(TIMESTAMPDIFF(MINUTE, Date_Commande, Date_Validation)) AS moy_minutes
        FROM Commande_B2B
        WHERE Id_Entreprise_Vendeuse = ?
          AND Date_Validation IS NOT NULL
          AND Statut IN ('validee', 'expediee', 'livree')
        LIMIT 50
    ");
    $stmt->execute([$id_entreprise]);
    $moy = (float) ($stmt->fetchColumn() ?? 0);

    if ($moy <= 0) {
        $result = ['label' => 'Nouveau vendeur', 'minutes' => 0, 'classe' => 'reaction-neutre'];
    } elseif ($moy <= 60) {
        $result = [
            'label'   => '⚡ Répond en moins d\'1h',
            'minutes' => $moy,
            'classe'  => 'reaction-excellent'
        ];
    } elseif ($moy <= 120) {
        $result = [
            'label'   => '✅ Répond en moins de 2h',
            'minutes' => $moy,
            'classe'  => 'reaction-bon'
        ];
    } elseif ($moy <= 480) {
        $heures = round($moy / 60);
        $result = [
            'label'   => "🕐 Répond en ~{$heures}h",
            'minutes' => $moy,
            'classe'  => 'reaction-moyen'
        ];
    } else {
        $result = [
            'label'   => '🐢 Répond sous 24h',
            'minutes' => $moy,
            'classe'  => 'reaction-lent'
        ];
    }

    return $result;
}

// ============================================================
// LIGNES DE COMMANDE NORMALISÉES (Point 5)
// ============================================================

/**
 * Récupère les lignes d'une commande B2B (depuis Ligne_Commande_B2B si disponible,
 * sinon fallback sur Articles_JSON pour la rétrocompatibilité).
 *
 * @param PDO $pdo          Instance PDO
 * @param int $id_commande  ID de la commande
 * @return array            Tableau de lignes [nom, quantite, prix_unitaire, sous_total, id_produit]
 */
function getLignesCommande(PDO $pdo, int $id_commande): array
{
    // Essayer d'abord la table normalisée
    $stmt = $pdo->prepare("
        SELECT
            l.Id_Ligne,
            l.Id_Produit,
            l.Nom_Produit     AS nom,
            l.Quantite        AS quantite,
            l.Prix_Unitaire   AS prix,
            l.Sous_Total      AS sous_total
        FROM Ligne_Commande_B2B l
        WHERE l.Id_Commande_B2B = ?
        ORDER BY l.Id_Ligne ASC
    ");
    $stmt->execute([$id_commande]);
    $lignes = $stmt->fetchAll();

    if (!empty($lignes)) {
        $result = $lignes;
    } else {
        // Fallback sur Articles_JSON (commandes antérieures à la migration)
        $stmt2 = $pdo->prepare("SELECT Articles_JSON FROM Commande_B2B WHERE Id_Commande_B2B = ?");
        $stmt2->execute([$id_commande]);
        $json = $stmt2->fetchColumn();

        $result = [];
        if ($json) {
            $articles = json_decode($json, true);
            if (is_array($articles)) {
                foreach ($articles as $a) {
                    $prix = floatval($a['prix'] ?? 0);
                    $qte  = intval($a['quantite'] ?? 1);
                    $result[] = [
                        'Id_Ligne'    => null,
                        'Id_Produit'  => $a['id_produit'] ?? $a['id'] ?? null,
                        'nom'         => $a['nom'] ?? 'Article inconnu',
                        'quantite'    => $qte,
                        'prix'        => $prix,
                        'sous_total'  => floatval($a['total'] ?? ($prix * $qte)),
                    ];
                }
            }
        }
    }

    return $result;
}

// ============================================================
// CONTRÔLE DU STOCK (Point 6)
// ============================================================

/**
 * Vérifie que le stock est suffisant pour toutes les lignes d'une commande.
 * Utilise FOR UPDATE pour éviter les race conditions (à appeler dans une transaction).
 *
 * @param PDO $pdo         Instance PDO (doit être dans une transaction active)
 * @param int $id_commande ID de la commande à valider
 * @return array           Tableau d'erreurs. Vide = tout est OK.
 *                         Format : [['nom' => string, 'requis' => int, 'disponible' => int], ...]
 */
function verifierStockAvantValidation(PDO $pdo, int $id_commande): array
{
    $lignes = getLignesCommande($pdo, $id_commande);
    $erreurs = [];

    foreach ($lignes as $ligne) {
        if (!$ligne['Id_Produit']) {
            continue;
        }

        // Verrouillage pessimiste pour éviter les lectures fantômes
        $stmt = $pdo->prepare("
            SELECT Nom_Produit, Quantite_En_Stock
            FROM Produit
            WHERE Id_Produit = ?
            FOR UPDATE
        ");
        $stmt->execute([$ligne['Id_Produit']]);
        $produit = $stmt->fetch();

        if (!$produit) {
            $erreurs[] = [
                'nom'         => $ligne['nom'],
                'requis'      => $ligne['quantite'],
                'disponible'  => 0,
                'message'     => "Le produit \"{$ligne['nom']}\" n'existe plus dans le catalogue.",
            ];
            continue;
        }

        if ($produit['Quantite_En_Stock'] < $ligne['quantite']) {
            $erreurs[] = [
                'nom'         => $produit['Nom_Produit'],
                'requis'      => $ligne['quantite'],
                'disponible'  => $produit['Quantite_En_Stock'],
                'message'     => "Stock insuffisant pour \"{$produit['Nom_Produit']}\" : "
                    . "requis {$ligne['quantite']}, disponible {$produit['Quantite_En_Stock']}.",
            ];
        }
    }

    return $erreurs;
}

/**
 * Décrémente le stock pour toutes les lignes d'une commande.
 * À appeler APRÈS verifierStockAvantValidation() dans la même transaction.
 *
 * @param PDO $pdo         Instance PDO (dans une transaction active)
 * @param int $id_commande ID de la commande
 */
function decrementerStockCommande(PDO $pdo, int $id_commande): void
{
    $lignes = getLignesCommande($pdo, $id_commande);
    $upd = $pdo->prepare("
        UPDATE Produit
        SET Quantite_En_Stock = Quantite_En_Stock - ?
        WHERE Id_Produit = ?
    ");
    foreach ($lignes as $ligne) {
        if ($ligne['Id_Produit'] && $ligne['quantite'] > 0) {
            $upd->execute([$ligne['quantite'], $ligne['Id_Produit']]);
        }
    }
}

// ============================================================
// UTILITAIRES GÉNÉRAUX
// ============================================================

/**
 * Retourne un badge HTML coloré selon le statut d'une commande B2B.
 *
 * @param string $statut   Statut de la commande
 * @param bool   $urgente  La commande est-elle urgente ?
 * @return string          HTML du badge
 */
function badgeStatutCommande(string $statut, bool $urgente = false): string
{
    $map = [
        'en_attente'     => ['label' => 'En attente',     'classe' => 'badge-warning',   'icon' => 'fa-clock'],
        'validee'        => ['label' => LABEL_VALIDEE,    'classe' => 'badge-info',      'icon' => 'fa-check-circle'],
        'en_preparation' => ['label' => 'En préparation', 'classe' => 'badge-purple',    'icon' => 'fa-box-open'],
        'prete'          => ['label' => 'Prête',          'classe' => 'badge-teal',      'icon' => 'fa-check-double'],
        'expediee'       => ['label' => 'Expédiée',       'classe' => 'badge-primary',   'icon' => 'fa-shipping-fast'],
        'livree'         => ['label' => 'Livrée',         'classe' => 'badge-success',   'icon' => 'fa-check-circle'],
        'refusee'        => ['label' => 'Refusée',        'classe' => 'badge-danger',    'icon' => 'fa-times-circle'],
    ];

    $info = $map[$statut] ?? ['label' => strtoupper($statut), 'classe' => 'badge-secondary', 'icon' => 'fa-question'];
    $html = "<span class=\"badge {$info['classe']}\"><i class=\"fas {$info['icon']}\"></i> {$info['label']}</span>";

    if ($urgente && in_array($statut, ['en_attente', 'validee', 'en_preparation', 'prete'])) {
        $html .= ' <span class="badge badge-urgent badge-pulse">⚡ URGENT</span>';
    }

    return $html;
}

/**
 * Retourne le libellé lisible d'un statut de commande.
 */
function getLabelStatut(string $statut): string
{
    $labels = [
        'en_attente'     => 'En attente de validation',
        'validee'        => LABEL_VALIDEE,
        'en_preparation' => 'En cours de préparation',
        'prete'          => 'Prête à expédier',
        'expediee'       => 'Expédiée — En livraison',
        'livree'         => 'Livrée ✅',
        'refusee'        => 'Refusée ❌',
    ];
    return $labels[$statut] ?? ucfirst(str_replace('_', ' ', $statut));
}

/**
 * Calcule les secondes restantes avant la date limite de réponse d'une commande urgente.
 *
 * @param string|null $date_limite Date limite (format SQL DATETIME)
 * @return int                     Secondes restantes (négatif si dépassé)
 */
function getSecondesRestantes(?string $date_limite): int
{
    if (!$date_limite) {
        return -1;
    }
    return strtotime($date_limite) - time();
}

// ============================================================
// HISTORIQUE DES STATUTS (v3)
// ============================================================

/**
 * Enregistre un changement de statut dans Historique_Commande_B2B.
 * À appeler APRÈS chaque UPDATE de Commande_B2B.
 *
 * @param PDO    $pdo              Instance PDO
 * @param int    $id_commande      ID de la commande
 * @param string $ancien_statut    Statut précédent
 * @param string $nouveau_statut   Nouveau statut
 * @param string $note             Note optionnelle (motif de refus, message vendeur…)
 * @param int    $id_entreprise    Entreprise qui effectue l'action
 */
function enregistrerHistoriqueCommande(
    PDO $pdo,
    int $id_commande,
    string $ancien_statut,
    string $nouveau_statut,
    string $note = '',
    int $id_entreprise = 0
): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO Historique_Commande_B2B
                (Id_Commande_B2B, Ancien_Statut, Nouveau_Statut, Note, Id_Entreprise_Action)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $id_commande,
            $ancien_statut,
            $nouveau_statut,
            $note ?: null,
            $id_entreprise ?: null
        ]);
    } catch (PDOException $e) {
        // Ne pas bloquer si la table n'existe pas encore (migration en attente)
        error_log("[FactuPro] Historique non enregistré : " . $e->getMessage());
    }
}

/**
 * Récupère l'historique complet d'une commande.
 *
 * @param PDO $pdo          Instance PDO
 * @param int $id_commande  ID de la commande
 * @return array            Tableau d'entrées d'historique (Date_Changement DESC)
 */
function getHistoriqueCommande(PDO $pdo, int $id_commande): array
{
    try {
        $stmt = $pdo->prepare("
            SELECT
                h.Id_Historique,
                h.Ancien_Statut,
                h.Nouveau_Statut,
                h.Note,
                h.Date_Changement,
                e.Nom_Entreprise
            FROM Historique_Commande_B2B h
            LEFT JOIN Entreprise e ON h.Id_Entreprise_Action = e.Id_Entreprise
            WHERE h.Id_Commande_B2B = ?
            ORDER BY h.Date_Changement ASC
        ");
        $stmt->execute([$id_commande]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return []; // Table pas encore créée
    }
}

/**
 * Génère la configuration de la timeline pour une commande.
 * Chaque étape a un état : 'done', 'active', 'pending'.
 *
 * @param string $statut_actuel  Statut actuel de la commande
 * @return array                 Tableau d'étapes ordonnées
 */
function getTimelineSteps(string $statut_actuel): array
{
    $ordre = [
        'en_attente'     => 0,
        'validee'        => 1,
        'en_preparation' => 2,
        'prete'          => 3,
        'expediee'       => 4,
        'livree'         => 5,
        'refusee'        => -1, // cas spécial
    ];

    if ($statut_actuel === 'refusee') {
        return [
            ['statut' => 'en_attente',     'label' => 'Créée',          'icon' => 'fa-file-alt',        'etat' => 'done'],
            ['statut' => 'refusee',        'label' => 'Refusée',        'icon' => 'fa-times-circle',    'etat' => 'error'],
        ];
    }

    $rang_actuel = $ordre[$statut_actuel] ?? 0;

    $etapes = [
        ['statut' => 'en_attente',     'label' => 'Commande créée',    'icon' => 'fa-file-alt',        'desc' => 'En attente de validation'],
        ['statut' => 'validee',        'label' => LABEL_VALIDEE,       'icon' => 'fa-check-circle',    'desc' => 'Commande acceptée par le vendeur'],
        ['statut' => 'en_preparation', 'label' => 'En préparation',   'icon' => 'fa-box-open',        'desc' => 'Le vendeur prépare la commande'],
        ['statut' => 'prete',          'label' => 'Prête',             'icon' => 'fa-check-double',    'desc' => 'Prête à être expédiée'],
        ['statut' => 'expediee',       'label' => 'Expédiée',         'icon' => 'fa-shipping-fast',   'desc' => 'En cours de livraison'],
        ['statut' => 'livree',         'label' => 'Livrée',            'icon' => 'fa-box',             'desc' => 'Livraison confirmée'],
    ];

    foreach ($etapes as &$e) {
        $rang_e = $ordre[$e['statut']] ?? 0;
        if ($rang_e < $rang_actuel) {
            $e['etat'] = 'done';
        } elseif ($rang_e === $rang_actuel) {
            $e['etat'] = 'active';
        } else {
            $e['etat'] = 'pending';
        }
    }
    unset($e);

    return $etapes;
}

/**
 * Retourne le nom d'une entreprise par son ID.
 *
 * @param PDO $pdo          Instance PDO
 * @param int $id_entreprise ID de l'entreprise
 * @return string           Nom de l'entreprise ou 'Entreprise inconnue'
 */
function getNomEntrepriseLocale(PDO $pdo, int $id_entreprise): string
{
    $stmt = $pdo->prepare("SELECT Nom_Entreprise FROM Entreprise WHERE Id_Entreprise = ?");
    $stmt->execute([$id_entreprise]);
    return $stmt->fetchColumn() ?: 'Entreprise inconnue';
}
/**
 * Exception dédiée aux erreurs du module B2B.
 *
 * Permet de différencier les erreurs métier B2B des autres exceptions
 * et de les capturer de façon granulaire dans le code appelant.
 */
class B2BException extends Exception
{
    // Vous pouvez ajouter des propriétés ou méthodes spécifiques si besoin.
}
