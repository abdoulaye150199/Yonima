<?php

require_once __DIR__ . '/php/Router.php';

$router = new Router();


$router->get('/', 'landing');
$router->get('/login', 'login');
$router->get('/logout', 'logout'); 
$router->get('/dashboard', 'dashboard');
$router->get('/cargaisons', 'cargaisons');
$router->get('/produits', 'produits');
$router->get('/creation-cargaison', 'creation-cargaison');
$router->get('/enregistrement-colis', 'enregistrement-colis');
$router->get('/outils-gestionnaire', 'outils-gestionnaire');
$router->get('/suivi-colis', 'suivi-colis');
$router->get('/suivi-carte', 'suivi-carte');
$router->get('/details-cargaison', 'details-cargaison');
$router->get('/notifications-log', 'notifications-log');
$router->get('/email-config', 'email-config');

$router->dispatch();

?>