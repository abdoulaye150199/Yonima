<?php
// public/php/simple-email-config-api.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

class SimpleEmailConfigAPI
{
    public function handleRequest(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        try {
            switch ($method) {
                case 'GET':
                    if ($path === '/api/email-config') {
                        $this->getEmailConfig();
                    } else {
                        $this->sendError('Route non trouvée', 404);
                    }
                    break;
                case 'POST':
                    if ($path === '/api/test-email') {
                        $this->testEmail();
                    } elseif ($path === '/api/send-email') {
                        $this->sendEmail();
                    } else {
                        $this->sendError('Route non trouvée', 404);
                    }
                    break;
                default:
                    $this->sendError('Méthode non autorisée', 405);
            }
        } catch (Exception $e) {
            error_log("Erreur SimpleEmailConfigAPI: " . $e->getMessage());
            $this->sendError('Erreur serveur: ' . $e->getMessage(), 500);
        }
    }

    private function getEmailConfig(): void
    {
        // Retourner la configuration par défaut (sans les mots de passe)
        $config = [
            'smtp' => [
                'host' => 'smtp.gmail.com',
                'port' => 587,
                'secure' => false
            ],
            'from' => [
                'name' => 'YONIMA Transport',
                'email' => 'noreply@yonima.com'
            ],
            'templates' => [
                'baseUrl' => 'https://yonima.com',
                'trackingUrl' => 'https://yonima.com/suivi-colis'
            ],
            'settings' => [
                'enableEmailSending' => true,
                'logEmails' => true,
                'retryAttempts' => 3
            ]
        ];

        $this->sendSuccess($config);
    }

    private function sendEmail(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            $this->sendError('Données JSON invalides', 400);
            return;
        }

        // Pour l'instant, on simule juste l'envoi et on logue
        $emailData = $input['emailData'] ?? [];
        
        $this->logEmailAttempt($emailData, 'simulated');
        
        $this->sendSuccess([
            'success' => true,
            'message' => 'Email simulé avec succès (TypeScript handling)',
            'emailSent' => false // Simulé pour l'instant
        ]);
    }

    private function testEmail(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Logger la tentative de test
        $this->logEmailAttempt([
            'to' => 'test@example.com',
            'subject' => 'Test Email Configuration - YONIMA TypeScript',
            'template' => 'test'
        ], 'test');

        $this->sendSuccess([
            'success' => true,
            'message' => 'Test de configuration simulé avec succès',
            'emailSent' => false
        ]);
    }

    private function logEmailAttempt($emailData, $type = 'attempt'): void
    {
        $logMessage = "=== EMAIL NOTIFICATION (TYPESCRIPT CONFIG TEST) ===\n";
        $logMessage .= "To: " . ($emailData['to'] ?? 'test@example.com') . "\n";
        $logMessage .= "Subject: " . ($emailData['subject'] ?? 'Test Email') . "\n";
        $logMessage .= "Date: " . date('Y-m-d H:i:s') . "\n";
        $logMessage .= "Type: " . $type . "\n";
        $logMessage .= "Template: " . ($emailData['template'] ?? 'unknown') . "\n";
        $logMessage .= "Status: logged\n";
        $logMessage .= "Content: Email géré par le système TypeScript\n\n";
        
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logDir . '/email-notifications.log', $logMessage, FILE_APPEND | LOCK_EX);
    }

    private function sendSuccess($data): void
    {
        echo json_encode(['success' => true] + (is_array($data) ? $data : ['data' => $data]));
    }

    private function sendError(string $message, int $code = 400): void
    {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $message]);
    }
}

// Traiter la requête
$api = new SimpleEmailConfigAPI();
$api->handleRequest();
