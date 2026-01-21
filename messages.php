<?php
require_once 'auth.php';
require_once 'db.php';

$page_title = "Messagerie B2B";
include 'header.php';

// ID de mon entreprise
$stmt = $pdo->prepare("SELECT Id_Entreprise FROM Utilisateur WHERE Id_Utilisateur = ?");
$stmt->execute([$_SESSION['user_id']]);
$mon_id = $stmt->fetchColumn();

$destinataire_id = isset($_GET['destinataire']) ? (int)$_GET['destinataire'] : 0;

// Envoyer un message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && $destinataire_id) {
    // Vérif CSRF simple ou juste auth
    $msg = trim($_POST['message']);
    if (!empty($msg)) {
        $stmt = $pdo->prepare("INSERT INTO Message (Id_Expediteur, Id_Destinataire, Contenu) VALUES (?, ?, ?)");
        $stmt->execute([$mon_id, $destinataire_id, $msg]);
    }
    // Si c'est une requête AJAX (fetch), on ne redirige pas, on laisse le script recharger
    // Mais pour faire simple : on redirige toujours, le fetch gérait le GET
    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header("Location: messages.php?destinataire=$destinataire_id");
        exit();
    }
}

// 1. Lister les conversations
$sql_conversations = "
    SELECT DISTINCT e.Id_Entreprise, e.Nom_Entreprise, e.Secteur_Activite
    FROM Entreprise e
    JOIN Message m ON (m.Id_Expediteur = e.Id_Entreprise OR m.Id_Destinataire = e.Id_Entreprise)
    WHERE (m.Id_Expediteur = ? OR m.Id_Destinataire = ?)
    AND e.Id_Entreprise != ?
";
$stmt = $pdo->prepare($sql_conversations);
$stmt->execute([$mon_id, $mon_id, $mon_id]);
$conversations = $stmt->fetchAll();

// Ajout du destinataire temporaire si nouveau
if ($destinataire_id && !in_array($destinataire_id, array_column($conversations, 'Id_Entreprise'))) {
    $stmt = $pdo->prepare("SELECT Id_Entreprise, Nom_Entreprise, Secteur_Activite FROM Entreprise WHERE Id_Entreprise = ?");
    $stmt->execute([$destinataire_id]);
    $new_contact = $stmt->fetch();
    if ($new_contact) array_unshift($conversations, $new_contact);
}

// 2. Lire les messages
$messages = [];
$destinataire_info = null;
if ($destinataire_id) {
    foreach ($conversations as $c) {
        if ($c['Id_Entreprise'] == $destinataire_id) {
            $destinataire_info = $c;
            break;
        }
    }
    $stmt = $pdo->prepare("
        SELECT * FROM Message 
        WHERE (Id_Expediteur = ? AND Id_Destinataire = ?) 
           OR (Id_Expediteur = ? AND Id_Destinataire = ?)
        ORDER BY Date_Envoi ASC
    ");
    $stmt->execute([$mon_id, $destinataire_id, $destinataire_id, $mon_id]);
    $messages = $stmt->fetchAll();

    // Marquer comme lus
    $pdo->prepare("UPDATE Message SET Lu = 1 WHERE Id_Expediteur = ? AND Id_Destinataire = ?")->execute([$destinataire_id, $mon_id]);
}
?>

<div class="container fade-in" style="height: calc(100vh - 140px); min-height: 500px; display: flex; gap: 20px;">

    <!-- GAUCHE : LISTE -->
    <div class="conversations-list card" id="convList" style="flex: 1; min-width: 250px; overflow-y: auto; padding: 0;">
        <div style="padding: 20px; border-bottom: 1px solid #eee; background: #f8fafc;">
            <h3 style="margin:0;"><i class="fas fa-comments"></i> Discussions</h3>
        </div>
        <div class="list-group">
            <?php if (empty($conversations)): ?>
                <div style="padding: 20px; text-align: center; color: #999;">Aucune conversation.<br><a href="reseau_b2b.php">Trouver un partenaire</a></div>
            <?php else: ?>
                <?php foreach ($conversations as $conv): ?>
                    <a href="?destinataire=<?= $conv['Id_Entreprise'] ?>" class="conversation-item <?= $destinataire_id == $conv['Id_Entreprise'] ? 'active' : '' ?>">
                        <div class="avatar-circle"><?= strtoupper(substr($conv['Nom_Entreprise'], 0, 1)) ?></div>
                        <div class="conv-details">
                            <div class="conv-name"><?= htmlspecialchars($conv['Nom_Entreprise']) ?></div>
                            <div class="conv-sector"><?= htmlspecialchars($conv['Secteur_Activite']) ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- DROITE : CHAT -->
    <div class="chat-area card" style="flex: 2; display: flex; flex-direction: column; padding: 0; overflow: hidden;">
        <?php if ($destinataire_id && $destinataire_info): ?>
            <div class="chat-header" style="padding: 15px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; background: #fff;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div class="avatar-circle small"><?= strtoupper(substr($destinataire_info['Nom_Entreprise'], 0, 1)) ?></div>
                    <h3 style="margin: 0;"><?= htmlspecialchars($destinataire_info['Nom_Entreprise']) ?></h3>
                </div>
                <a href="reseau_b2b.php" class="btn btn-sm btn-secondary"><i class="fas fa-times"></i> Fermer</a>
            </div>

            <div class="messages-content" id="messagesBox" style="flex: 1; overflow-y: auto; padding: 20px; background: #f1f5f9;">
                <?php foreach ($messages as $msg): ?>
                    <?php $is_me = ($msg['Id_Expediteur'] == $mon_id); ?>
                    <div class="message-bubble <?= $is_me ? 'me' : 'other' ?>">
                        <div class="message-text"><?= nl2br(htmlspecialchars($msg['Contenu'])) ?></div>
                        <div class="message-time"><?= date('H:i', strtotime($msg['Date_Envoi'])) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="chat-input" style="padding: 20px; background: #fff; border-top: 1px solid #eee;">
                <form method="POST" style="display: flex; gap: 10px;">
                    <input type="text" name="message" class="form-control" placeholder="Écrivez votre message..." autocomplete="off" required>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i></button>
                </form>
            </div>
        <?php else: ?>
            <div style="flex: 1; display: flex; align-items: center; justify-content: center; flex-direction: column; color: #aaa;">
                <i class="fas fa-comments" style="font-size: 4rem; margin-bottom: 20px; color: #e2e8f0;"></i>
                <p>Sélectionnez une conversation.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .conversation-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 15px 20px;
        border-bottom: 1px solid #f1f5f9;
        cursor: pointer;
        transition: background 0.2s;
    }

    .conversation-item:hover {
        background: #f8fafc;
    }

    .conversation-item.active {
        background: #eff6ff;
        border-left: 4px solid var(--primary);
    }

    .avatar-circle {
        width: 40px;
        height: 40px;
        background: var(--primary);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
    }

    .avatar-circle.small {
        width: 32px;
        height: 32px;
        font-size: 0.9em;
    }

    .conv-name {
        font-weight: 600;
        color: var(--text-main);
    }

    .conv-sector {
        font-size: 0.8rem;
        color: var(--text-muted);
    }

    .message-bubble {
        max-width: 70%;
        margin-bottom: 10px;
        padding: 10px 15px;
        border-radius: 12px;
        position: relative;
        animation: popIn 0.3s;
    }

    .message-bubble.me {
        background: var(--primary);
        color: white;
        align-self: flex-end;
        margin-left: auto;
        border-bottom-right-radius: 2px;
    }

    .message-bubble.other {
        background: white;
        color: var(--text-main);
        align-self: flex-start;
        margin-right: auto;
        border-bottom-left-radius: 2px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }

    .message-time {
        font-size: 0.7rem;
        text-align: right;
        opacity: 0.8;
        margin-top: 4px;
    }

    @keyframes popIn {
        from {
            transform: scale(0.8);
            opacity: 0;
        }

        to {
            transform: scale(1);
            opacity: 1;
        }
    }
</style>

<script>
    // Auto-Scroll au chargement
    var msgBox = document.getElementById('messagesBox');
    if (msgBox) msgBox.scrollTop = msgBox.scrollHeight;

    // --- AUTO ACTUALISATION (AJAX) ---
    setInterval(() => {
        // On récupère l'URL actuelle
        fetch(window.location.href)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');

                // 1. Mettre à jour la liste des conversations (si nouveau message reçu d'un tiers)
                const newConvList = doc.getElementById('convList');
                const oldConvList = document.getElementById('convList');
                if (newConvList && oldConvList && newConvList.innerHTML !== oldConvList.innerHTML) {
                    oldConvList.innerHTML = newConvList.innerHTML;
                }

                // 2. Mettre à jour les messages (si on est dans une conversation)
                const newMsgBox = doc.getElementById('messagesBox');
                const oldMsgBox = document.getElementById('messagesBox');

                if (newMsgBox && oldMsgBox) {
                    // Si le contenu a changé (nouveau message)
                    if (newMsgBox.innerHTML.length !== oldMsgBox.innerHTML.length) {
                        oldMsgBox.innerHTML = newMsgBox.innerHTML;
                        // Auto-scroll vers le bas uniquement si on était déjà en bas ou presque
                        oldMsgBox.scrollTop = oldMsgBox.scrollHeight;
                    }
                }
            })
            .catch(err => console.error("Erreur actualisation:", err));
    }, 2000); // Toutes les 2 secondes
</script>

</body>

</html>