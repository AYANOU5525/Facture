<?php

namespace App;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class Chat implements MessageComponentInterface
{
    protected $clients;
    protected $userConnections; // Map company ID to connections
    protected $pdo;

    public function __construct($pdo)
    {
        $this->clients = new \SplObjectStorage;
        $this->userConnections = [];
        $this->pdo = $pdo;
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);

        if (!$data) return;

        // Message types: 'auth' to identify, 'message' to send
        switch ($data['type']) {
            case 'auth':
                $companyId = $data['companyId'];
                $this->userConnections[$companyId][] = $from;
                $from->companyId = $companyId;
                echo "Company $companyId authenticated on connection {$from->resourceId}\n";
                break;

            case 'message':
                if (!isset($from->companyId)) {
                    $from->send(json_encode(['type' => 'error', 'message' => 'Not authenticated']));
                    return;
                }

                $senderId = $from->companyId;
                $receiverId = $data['receiverId'];
                $content = trim($data['content']);

                if (empty($content)) return;

                // Save to DB
                try {
                    $stmt = $this->pdo->prepare("INSERT INTO Message (Id_Expediteur, Id_Destinataire, Contenu) VALUES (?, ?, ?)");
                    $stmt->execute([$senderId, $receiverId, $content]);
                    $messageId = $this->pdo->lastInsertId();

                    $response = [
                        'type' => 'new_message',
                        'senderId' => $senderId,
                        'receiverId' => $receiverId,
                        'content' => $content,
                        'date' => date('H:i'),
                        'messageId' => $messageId
                    ];

                    // Send to receiver if online
                    if (isset($this->userConnections[$receiverId])) {
                        foreach ($this->userConnections[$receiverId] as $client) {
                            $client->send(json_encode($response));
                        }
                    }

                    // Send back to sender (to confirm display in other tabs for example)
                    foreach ($this->userConnections[$senderId] as $client) {
                        $client->send(json_encode($response));
                    }
                } catch (\Exception $e) {
                    echo "Database Error: " . $e->getMessage() . "\n";
                    $from->send(json_encode(['type' => 'error', 'message' => 'Database error']));
                }
                break;
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);

        if (isset($conn->companyId) && isset($this->userConnections[$conn->companyId])) {
            $key = array_search($conn, $this->userConnections[$conn->companyId]);
            if ($key !== false) {
                unset($this->userConnections[$conn->companyId][$key]);
            }
        }

        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
}
