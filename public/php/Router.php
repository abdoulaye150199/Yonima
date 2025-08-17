<?php
// public/php/Router.php

class Router
{
    private array $routes = [];
    private array $protectedRoutes = ['/cargaisons', '/produits', '/creation-cargaison', '/enregistrement-colis', '/outils-gestionnaire', '/suivi-colis', '/suivi-carte'];

    public function get(string $path, string $view)
    {
        $this->routes[$path] = $view;
    }

    public function dispatch()
    {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Gérer les routes API cargaisons
        if (strpos($path, '/api/cargaisons') === 0) {
            $this->handleCargaisonAPI();
            return;
        }

        // Gérer les routes API colis - AJOUT
        if (strpos($path, '/api/colis') === 0) {
            $this->handleColisAPI();
            return;
        }

        // Gérer les routes API email logs (simple version pour TypeScript)
        if (strpos($path, '/api/email-logs') === 0 || strpos($path, '/api/log-email') === 0) {
            require_once 'simple-email-logs-api.php';
            return;
        }

        // Gérer les routes API email config (simple version pour TypeScript)
        if (strpos($path, '/api/email-config') === 0 || strpos($path, '/api/test-email') === 0 || strpos($path, '/api/send-email') === 0) {
            require_once 'simple-email-config-api.php';
            return;
        }


        $view = $this->routes[$path] ?? '404';

        // Gestion de la connexion
        if ($path === "/connexion") {
            $this->handleLogin();
        }

        // Gestion de la déconnexion
        if ($path === "/logout") {
            $this->handleLogout();
        }

        // Vérification des routes protégées
        if (in_array($path, $this->protectedRoutes) || $path === '/dashboard') {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
                $_SESSION['login_required'] = 'Vous devez vous connecter pour accéder à cette page';
                header("Location: /login");
                exit();
            }
        }

        // Redirection si déjà connecté
        if ($path === '/login') {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
                header("Location: /dashboard");
                exit();
            }
        }

        // Servir les fichiers statiques TypeScript compilés
        if (strpos($path, '/dist/') === 0) {
            $this->serveStaticFile($path);
            return;
        }

        $this->loadPage($view);
    }

    // NOUVELLE MÉTHODE pour gérer l'API colis
    private function handleColisAPI()
    {
        require_once __DIR__ . '/colis-api.php';
        $api = new ColisAPI();
        $api->handleRequest();
    }

    private function handleCargaisonAPI()
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
        header('Access-Control-Allow-Headers: Content-Type');

        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        if ($method === 'POST' && $path === '/api/cargaisons') {
            $this->createCargaison();
        } elseif ($method === 'GET' && $path === '/api/cargaisons') {
            $this->getCargaisons();
        } elseif ($method === 'POST' && $path === '/api/cargaisons/update-status') {
            $this->updateCargaisonStatus();
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Route API non trouvée']);
        }
    }

    private function createCargaison()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['type'], $input['origine'], $input['destination'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Données manquantes']);
            return;
        }
        
        $dbPath = __DIR__ . '/../../db.json';
        $db = json_decode(file_get_contents($dbPath), true);
        
        // Générer un nouvel ID
        $newId = 'CARG-' . str_pad(count($db['cargaisons']) + 1, 3, '0', STR_PAD_LEFT);
        
        $newCargaison = [
            'id' => $newId,
            'type' => $input['type'],
            'origine' => $input['origine'],
            'destination' => $input['destination'],
            'statut' => 'En attente',
            'dateCreation' => date('Y-m-d'),
            'transporteur' => $input['transporteur'] ?? '',
            'poidsMax' => floatval($input['poidsMax'] ?? 0),
            'prixParKg' => floatval($input['prixParKg'] ?? 0),
            'dateDepart' => $input['dateDepart'] ?? '',
            'description' => $input['description'] ?? ''
        ];
        
        $db['cargaisons'][] = $newCargaison;
        file_put_contents($dbPath, json_encode($db, JSON_PRETTY_PRINT));
        
        echo json_encode(['success' => true, 'id' => $newId]);
    }

    private function getCargaisons()
    {
        $dbPath = __DIR__ . '/../../db.json';
        $db = json_decode(file_get_contents($dbPath), true);
        echo json_encode($db['cargaisons']);
    }

    private function updateCargaisonStatus()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['id'], $input['statut'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Données manquantes (id et statut requis)']);
            return;
        }
        
        $dbPath = __DIR__ . '/../../db.json';
        $db = json_decode(file_get_contents($dbPath), true);
        
        // Trouver la cargaison à modifier
        $cargaisonIndex = -1;
        for ($i = 0; $i < count($db['cargaisons']); $i++) {
            if ($db['cargaisons'][$i]['id'] === $input['id']) {
                $cargaisonIndex = $i;
                break;
            }
        }
        
        if ($cargaisonIndex === -1) {
            http_response_code(404);
            echo json_encode(['error' => 'Cargaison non trouvée']);
            return;
        }
        
        $cargaison = &$db['cargaisons'][$cargaisonIndex];
        $ancienStatut = $cargaison['statut'];
        $nouveauStatut = $input['statut'];
        
        // Mettre à jour le statut
        $cargaison['statut'] = $nouveauStatut;
        $cargaison['dateModification'] = date('Y-m-d H:i:s');
        
        // Ajouter une note si fournie
        if (isset($input['note']) && !empty($input['note'])) {
            if (!isset($cargaison['notes'])) {
                $cargaison['notes'] = [];
            }
            $cargaison['notes'][] = [
                'date' => date('Y-m-d H:i:s'),
                'statut' => $nouveauStatut,
                'note' => $input['note']
            ];
        }
        
        // Sauvegarder
        file_put_contents($dbPath, json_encode($db, JSON_PRETTY_PRINT));
        
        echo json_encode([
            'success' => true, 
            'message' => 'Statut mis à jour avec succès'
        ]);
    }

    private function handleLogin()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $errors = [];
        $generalError = '';

        if (isset($_POST['email']) || isset($_POST['password'])) {
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';

            if (empty($email)) {
                $errors['email'] = 'L\'adresse email est vide';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'L\'adresse email n\'est pas valide';
            }

            if (empty($password)) {
                $errors['password'] = 'entrer un mot de passe';
            }

            if (empty($errors)) {
                try {
                    $url = "http://localhost:3000/gestionnaires?email=" . urlencode($email);
                    $response = file_get_contents($url);

                    if ($response) {
                        $users = json_decode($response, true);

                        if (!empty($users) && isset($users[0]["motDePasse"])) {
                            if ($users[0]["motDePasse"] === $password) {
                                $_SESSION['user_logged_in'] = true;
                                $_SESSION['user'] = $users[0];
                                $_SESSION['success_message'] = [
                                    'type' => 'success',
                                    'title' => 'Connexion réussie !',
                                    'message' => 'Bienvenue dans YONIMA, ' . $users[0]['prenom'] . ' !',
                                    'icon' => 'fas fa-check-circle'
                                ];

                                header("Location: /dashboard");
                                exit();
                            } else {
                                $errors['password'] = 'Le mot de passe saisi est incorrect';
                            }
                        } else {
                            $errors['email'] = 'Aucun compte trouvé avec cette adresse email';
                        }
                    } else {
                        $generalError = 'Erreur de connexion au serveur. Veuillez réessayer.';
                    }
                } catch (Exception $e) {
                    $generalError = 'Erreur de connexion. Veuillez réessayer plus tard.';
                }
            }

            if (!empty($errors) || !empty($generalError)) {
                $_SESSION['login_errors'] = $errors;
                $_SESSION['login_error'] = $generalError;
                $_SESSION['old_email'] = $email;

                header("Location: /login");
                exit();
            }
        }
    }

    private function handleLogout()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = array();

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();

        session_start();
        $_SESSION['logout_message'] = [
            'type' => 'info',
            'title' => 'Déconnexion réussie !',
            'message' => 'Vous avez été déconnecté avec succès. À bientôt !',
            'icon' => 'fas fa-sign-out-alt'
        ];

        header("Location: /login");
        exit();
    }

    private function serveStaticFile($path)
    {
        $filePath = __DIR__ . '/../..' . $path;
        if (file_exists($filePath)) {
            $extension = pathinfo($filePath, PATHINFO_EXTENSION);
            
            switch ($extension) {
                case 'js':
                    header('Content-Type: application/javascript');
                    break;
                case 'js.map':
                    header('Content-Type: application/json');
                    break;
                default:
                    header('Content-Type: text/plain');
            }
            
            readfile($filePath);
            exit();
        } else {
            http_response_code(404);
            echo "File not found: " . $path;
            exit();
        }
    }

    private function loadPage($view)
    {
        $file = __DIR__ . '/../../src/views/pages/' . $view . '.php';

        if (file_exists($file)) {
            ob_start();
            require $file;
            echo ob_get_clean();
        } else {
            http_response_code(404);
            $error404 = __DIR__ . '/../../src/views/pages/404.php';
            if (file_exists($error404)) {
                require $error404;
            } else {
                echo "Page non trouvée";
            }
        }
    }
}
?>