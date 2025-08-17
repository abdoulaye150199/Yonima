<?php
// public/php/colis-api.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

class ColisAPI
{
    private string $dbPath;

    public function __construct()
    {
        $this->dbPath = __DIR__ . '/../../db.json';
    }

    public function handleRequest(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // Logs pour debugging
        error_log("ColisAPI - Méthode: $method, Chemin: $path");

        try {
            switch ($method) {
                case 'POST':
                    if ($path === '/api/colis') {
                        $this->createColis();
                    } else {
                        $this->sendError('Route non trouvée', 404);
                    }
                    break;
                case 'GET':
                    if ($path === '/api/colis') {
                        $this->getColis();
                    } else {
                        $this->sendError('Route non trouvée', 404);
                    }
                    break;
                default:
                    $this->sendError('Méthode non autorisée', 405);
            }
        } catch (Exception $e) {
            error_log("Erreur ColisAPI: " . $e->getMessage());
            $this->sendError('Erreur serveur: ' . $e->getMessage(), 500);
        }
    }

    private function createColis(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        error_log("Données reçues: " . json_encode($input));
        
        if (!$input) {
            $this->sendError('Données JSON invalides', 400);
            return;
        }

        // Validation des champs requis
        $requiredFields = ['trackingCode', 'client', 'destinataire', 'nombreColis', 'poids', 'typeProduit', 'typeCargaison', 'prix'];
        foreach ($requiredFields as $field) {
            if (!isset($input[$field])) {
                $this->sendError("Champ manquant: $field", 400);
                return;
            }
        }

        // Validation des sous-champs client et destinataire
        $personRequiredFields = ['nom', 'prenom', 'telephone', 'adresse'];
        foreach (['client', 'destinataire'] as $person) {
            if (!isset($input[$person]) || !is_array($input[$person])) {
                $this->sendError("Section manquante: $person", 400);
                return;
            }
            
            foreach ($personRequiredFields as $field) {
                if (!isset($input[$person][$field]) || empty(trim($input[$person][$field]))) {
                    $this->sendError("Champ manquant: $person.$field", 400);
                    return;
                }
            }
        }

        // Charger la base de données
        $db = $this->loadDatabase();
        
        // Vérifier l'unicité du tracking code
        if (isset($db['colis'])) {
            foreach ($db['colis'] as $existingColis) {
                if ($existingColis['trackingCode'] === $input['trackingCode']) {
                    $this->sendError('Code de suivi déjà existant', 409);
                    return;
                }
            }
        }

        // Préparer les données du colis
        $colis = [
            'id' => $input['id'],
            'trackingCode' => $input['trackingCode'],
            'client' => [
                'nom' => trim($input['client']['nom']),
                'prenom' => trim($input['client']['prenom']),
                'telephone' => trim($input['client']['telephone']),
                'email' => isset($input['client']['email']) && !empty($input['client']['email']) ? trim($input['client']['email']) : null,
                'adresse' => trim($input['client']['adresse'])
            ],
            'destinataire' => [
                'nom' => trim($input['destinataire']['nom']),
                'prenom' => trim($input['destinataire']['prenom']),
                'telephone' => trim($input['destinataire']['telephone']),
                'email' => isset($input['destinataire']['email']) && !empty($input['destinataire']['email']) ? trim($input['destinataire']['email']) : null,
                'adresse' => trim($input['destinataire']['adresse'])
            ],
            'nombreColis' => intval($input['nombreColis']),
            'poids' => floatval($input['poids']),
            'typeProduit' => $input['typeProduit'],
            'typeCargaison' => $input['typeCargaison'],
            'valeurDeclaree' => isset($input['valeurDeclaree']) && !empty($input['valeurDeclaree']) ? floatval($input['valeurDeclaree']) : null,
            'prix' => floatval($input['prix']),
            'description' => isset($input['description']) && !empty($input['description']) ? trim($input['description']) : null,
            'statut' => $input['statut'],
            'dateCreation' => $input['dateCreation']
        ];

        // Initialiser les tableaux s'ils n'existent pas
        if (!isset($db['colis'])) {
            $db['colis'] = [];
        }
        if (!isset($db['produits'])) {
            $db['produits'] = [];
        }

        // Ajouter le colis
        $db['colis'][] = $colis;
        
        // Créer l'entrée produit correspondante
        $produit = $this->colisToProduct($colis);
        $db['produits'][] = $produit;

        // Sauvegarder
        if ($this->saveDatabase($db)) {
            error_log("Colis sauvegardé avec succès: " . $input['trackingCode']);
            
            $this->sendSuccess([
                'message' => 'Colis enregistré avec succès',
                'trackingCode' => $input['trackingCode'],
                'id' => $input['id'],
                'emailSent' => !empty($colis['destinataire']['email'])
            ]);
        } else {
            $this->sendError('Erreur lors de la sauvegarde', 500);
        }
    }

    private function getColis(): void
    {
        $db = $this->loadDatabase();
        $colis = isset($db['colis']) ? $db['colis'] : [];
        $this->sendSuccess($colis);
    }

    private function colisToProduct(array $colis): array
    {
        return [
            'id' => $colis['id'],
            'code' => $colis['trackingCode'],
            'type' => $colis['typeProduit'],
            'expediteur' => $colis['client']['prenom'] . ' ' . $colis['client']['nom'],
            'expediteurVille' => $this->extractCity($colis['client']['adresse']),
            'destinataire' => $colis['destinataire']['prenom'] . ' ' . $colis['destinataire']['nom'],
            'destinataireVille' => $this->extractCity($colis['destinataire']['adresse']),
            'poids' => $colis['poids'],
            'statut' => $this->mapStatut($colis['statut']),
            'prix' => $colis['prix'],
            'dateCreation' => $colis['dateCreation'],
            'typeCargaison' => $colis['typeCargaison'],
            'description' => $colis['description']
        ];
    }

    private function extractCity(string $adresse): string
    {
        // Extraire la ville de l'adresse (logique simple)
        $parts = explode(',', $adresse);
        return trim(end($parts));
    }

    private function mapStatut(string $statut): string
    {
        $mapping = [
            'en-attente' => 'En attente',
            'en-cours' => 'En cours',
            'arrive' => 'Arrivé',
            'livre' => 'Livré',
            'perdu' => 'Perdu'
        ];
        
        return $mapping[$statut] ?? 'En attente';
    }

    private function loadDatabase(): array
    {
        if (!file_exists($this->dbPath)) {
            // Créer un fichier db.json vide avec structure de base
            $defaultDb = [
                'gestionnaires' => [],
                'cargaisons' => [],
                'produits' => [],
                'clients' => [],
                'colis' => []
            ];
            $this->saveDatabase($defaultDb);
            return $defaultDb;
        }

        $content = file_get_contents($this->dbPath);
        if ($content === false) {
            throw new Exception('Impossible de lire le fichier de base de données');
        }

        $db = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Erreur lors du décodage JSON: ' . json_last_error_msg());
        }

        // S'assurer que toutes les clés nécessaires existent
        $requiredKeys = ['gestionnaires', 'cargaisons', 'produits', 'clients', 'colis'];
        foreach ($requiredKeys as $key) {
            if (!isset($db[$key])) {
                $db[$key] = [];
            }
        }

        return $db;
    }

    private function saveDatabase(array $db): bool
    {
        $json = json_encode($db, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            error_log('Erreur lors de l\'encodage JSON: ' . json_last_error_msg());
            return false;
        }
        
        $result = file_put_contents($this->dbPath, $json);
        if ($result === false) {
            error_log('Erreur lors de l\'écriture du fichier: ' . $this->dbPath);
            return false;
        }
        
        return true;
    }

    private function sendSuccess($data): void
    {
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    private function sendError(string $message, int $code = 400): void
    {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $message]);
        exit;
    }
}

// Point d'entrée
if (basename($_SERVER['SCRIPT_NAME']) === 'colis-api.php') {
    $api = new ColisAPI();
    $api->handleRequest();
}
?>