<?php


/*
|--------------------------------------------------------------------------
| Initialisation centralisée de la session
|--------------------------------------------------------------------------
| Ce fichier doit être inclus sur les pages qui ont besoin d'accéder
| à l'état connecté / non connecté de l'utilisateur.
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}