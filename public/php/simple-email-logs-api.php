<?php
// public/php/simple-email-logs-api.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

class SimpleEmailLogsAPI
{
    private string $logPath;

    public function __construct()
    {
        $this->logPath = __DIR__ . '/../logs/email-notifications.log';
    }

    public function handleRequest(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        try {
            switch ($method) {
                case 'GET':
                    if ($path === '/api/email-logs') {
                        $this->getEmailLogs();
                    } else {
                        $this->sendError('Route non trouvée', 404);
                    }
                    break;
                case 'POST':
                    if ($path === '/api/log-email') {
                        $this->logEmail();
                    } else {
                        $this->sendError('Route non trouvée', 404);
                    }
                    break;
                default:
                    $this->sendError('Méthode non autorisée', 405);
            }
        } catch (Exception $e) {
            error_log("Erreur SimpleEmailLogsAPI: " . $e->getMessage());
            $this->sendError('Erreur serveur: ' . $e->getMessage(), 500);
        }
    }

    private function getEmailLogs(): void
    {
        if (!file_exists($this->logPath)) {
            $this->sendSuccess([
                'logs' => [],
                'total' => 0,
                'message' => 'Aucun log trouvé'
            ]);
            return;
        }

        $logContent = file_get_contents($this->logPath);
        $logs = $this->parseLogContent($logContent);

        // Trier par date (plus récent en premier)
        usort($logs, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        $this->sendSuccess([
            'logs' => $logs,
            'total' => count($logs)
        ]);
    }

    private function logEmail(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            $this->sendError('Données JSON invalides', 400);
            return;
        }

        // Logger l'email côté client TypeScript
        $logMessage = "=== EMAIL NOTIFICATION (TYPESCRIPT) ===\n";
        $logMessage .= "To: " . ($input['to'] ?? 'Unknown') . " (" . ($input['name'] ?? 'Unknown') . ")\n";
        $logMessage .= "Subject: " . ($input['subject'] ?? 'Unknown') . "\n";
        $logMessage .= "Date: " . ($input['timestamp'] ?? date('Y-m-d H:i:s')) . "\n";
        $logMessage .= "Template: " . ($input['template'] ?? 'Unknown') . "\n";
        $logMessage .= "Tracking Code: " . ($input['trackingCode'] ?? 'N/A') . "\n";
        $logMessage .= "Status: " . ($input['status'] ?? 'logged') . "\n";
        $logMessage .= "Content: Email géré par TypeScript\n\n";
        
        $logDir = dirname($this->logPath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($this->logPath, $logMessage, FILE_APPEND | LOCK_EX);
        
        $this->sendSuccess(['message' => 'Log enregistré']);
    }

    private function parseLogContent(string $content): array
    {
        $logs = [];
        $entries = preg_split('/=== EMAIL NOTIFICATION.*? ===/', $content);
        
        foreach ($entries as $entry) {
            $entry = trim($entry);
            if (empty($entry)) continue;

            $lines = explode("\n", $entry);
            $logData = [];

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;

                if (strpos($line, 'To: ') === 0) {
                    // Extraire email et nom: "To: email@example.com (Nom Prénom)"
                    $toLine = substr($line, 4);
                    if (preg_match('/^(.+?)\s*\((.+)\)$/', $toLine, $matches)) {
                        $logData['email'] = trim($matches[1]);
                        $logData['recipient'] = trim($matches[2]);
                    } else {
                        $logData['email'] = $toLine;
                        $logData['recipient'] = 'Inconnu';
                    }
                } elseif (strpos($line, 'Subject: ') === 0) {
                    $logData['subject'] = substr($line, 9);
                } elseif (strpos($line, 'Date: ') === 0) {
                    $logData['date'] = substr($line, 6);
                } elseif (strpos($line, 'Template: ') === 0) {
                    $logData['template'] = substr($line, 10);
                } elseif (strpos($line, 'Status: ') === 0) {
                    $logData['status'] = substr($line, 8);
                } elseif (strpos($line, 'Content: ') === 0) {
                    $logData['content'] = substr($line, 9);
                } elseif (strpos($line, 'Tracking Code: ') === 0) {
                    $logData['trackingCode'] = substr($line, 15);
                }
            }

            // Détecter le type d'email
            if (isset($logData['subject'])) {
                if (stripos($logData['subject'], 'confirmation') !== false) {
                    $logData['type'] = 'confirmation';
                } elseif (stripos($logData['subject'], 'arrivé') !== false) {
                    $logData['type'] = 'arrivée';
                } else {
                    $logData['type'] = 'autre';
                }
            }

            if (!empty($logData) && isset($logData['email'], $logData['subject'], $logData['date'])) {
                $logs[] = $logData;
            }
        }

        return $logs;
    }

    private function sendSuccess(array $data): void
    {
        echo json_encode(['success' => true] + $data);
    }

    private function sendError(string $message, int $code = 400): void
    {
        http_response_code($code);
        echo json_encode(['error' => $message, 'success' => false]);
    }
}

// Traiter la requête
$api = new SimpleEmailLogsAPI();
$api->handleRequest();
