<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');
$dbPath = __DIR__ . '/../../db.json';

function readDB() {
    global $dbPath;
    $json = file_get_contents($dbPath);
    return json_decode($json, true);
}

function writeDB($data) {
    global $dbPath;
    file_put_contents($dbPath, json_encode($data, JSON_PRETTY_PRINT));
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($method === 'POST' && strpos($path, '/create') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['type'], $input['origine'], $input['destination'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Données manquantes']);
        exit;
    }
    
    $db = readDB();
  
    $existingIds = array_column($db['cargaisons'], 'id');
    $newId = 'CARG-' . str_pad(count($existingIds) + 1, 3, '0', STR_PAD_LEFT);
    
    $newCargaison = [
        'id' => $newId,
        'type' => $input['type'],
        'origine' => $input['origine'],
        'destination' => $input['destination'],
        'statut' => 'En attente',
        'dateCreation' => date('Y-m-d'),
        'transporteur' => $input['transporteur'] ?? '',
        'numeroVol' => $input['numeroVol'] ?? '',
        'numeroNavire' => $input['numeroNavire'] ?? '',
        'numeroCamion' => $input['numeroCamion'] ?? '',
        'poidsMax' => floatval($input['poidsMax'] ?? 0),
        'prixParKg' => floatval($input['prixParKg'] ?? 0),
        'dateDepart' => $input['dateDepart'] ?? '',
        'fermee' => false,
        'description' => $input['description'] ?? ''
    ];
    
    $db['cargaisons'][] = $newCargaison;
    writeDB($db);
    
    echo json_encode(['success' => true, 'id' => $newId]);
    exit;
}

// Route: GET /php/cargaison-api.php/list
if ($method === 'GET' && strpos($path, '/list') !== false) {
    $db = readDB();
    echo json_encode($db['cargaisons']);
    exit;
}

// Route: PUT /php/cargaison-api.php/{id} - Mettre à jour une cargaison
if ($method === 'PUT' && preg_match('/\/cargaisons\/(.+)/', $path, $matches)) {
    $cargaisonId = $matches[1];
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Données manquantes']);
        exit;
    }
    
    $db = readDB();
    
    // Trouver l'index de la cargaison à mettre à jour
    $index = -1;
    foreach ($db['cargaisons'] as $i => $cargaison) {
        if ($cargaison['id'] === $cargaisonId) {
            $index = $i;
            break;
        }
    }
    
    if ($index === -1) {
        http_response_code(404);
        echo json_encode(['error' => 'Cargaison introuvable']);
        exit;
    }
    
    // Mettre à jour la cargaison avec les nouvelles données
    $db['cargaisons'][$index] = array_merge($db['cargaisons'][$index], $input);
    
    writeDB($db);
    
    echo json_encode([
        'success' => true,
        'message' => 'Cargaison mise à jour avec succès',
        'data' => $db['cargaisons'][$index]
    ]);
    exit;
}

// Route: GET /php/cargaison-api.php - Lister toutes les cargaisons avec filtres optionnels
if ($method === 'GET' && !strpos($path, '/list')) {
    $db = readDB();
    $cargaisons = $db['cargaisons'];
    
    // Filtrer par ID si spécifié
    if (isset($_GET['id'])) {
        $cargaisons = array_filter($cargaisons, function($c) {
            return $c['id'] === $_GET['id'];
        });
    }
    
    echo json_encode(array_values($cargaisons));
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Route non trouvée']);
?>