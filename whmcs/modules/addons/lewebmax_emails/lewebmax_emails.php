<?php
/**
 * LEWEBMAX E-mails — module d'administration WHMCS
 *
 * - Thèmes : 5 designs d'e-mail aux couleurs de LEWEBMAX, appliqués en un clic.
 * - Personnalisation : logos jour/nuit, coordonnées, réseaux sociaux (icônes), textes, bannière promo.
 * - Modèles : installation ou restauration des 12 e-mails, un par un ou tous ensemble.
 * - Aperçu & test : aperçu ordinateur/mobile et envoi d'un e-mail de test.
 * - Sauvegardes : tout ce qui est remplacé est sauvegardé et restaurable.
 *
 * Installation : copier le dossier dans /modules/addons/ de WHMCS, puis
 * Configuration > Modules complémentaires > LEWEBMAX E-mails > Activer.
 */

if (!defined('WHMCS')) {
    die('Accès direct interdit.');
}

require_once __DIR__ . '/lib/settings.php';
require_once __DIR__ . '/lib/render.php';
require_once __DIR__ . '/lib/actions.php';
require_once __DIR__ . '/lib/admin.php';

function lewebmax_emails_config()
{
    return [
        'name' => 'LEWEBMAX E-mails',
        'description' => 'Thèmes d\'e-mail aux couleurs de LEWEBMAX : choix du design, personnalisation (logos, réseaux sociaux, coordonnées), modèles en français, aperçu et e-mails de test.',
        'version' => '2.1',
        'author' => 'LEWEBMAX',
        'fields' => [],
    ];
}

function lewebmax_emails_activate()
{
    try {
        lwm_create_backup_table();
        return ['status' => 'success', 'description' => 'Module activé. Rendez-vous dans Modules complémentaires > LEWEBMAX E-mails.'];
    } catch (\Exception $e) {
        return ['status' => 'error', 'description' => 'Impossible de créer la table de sauvegarde : ' . $e->getMessage()];
    }
}

function lewebmax_emails_deactivate()
{
    // La table de sauvegarde est conservée volontairement pour pouvoir restaurer plus tard.
    return ['status' => 'success', 'description' => 'Module désactivé. Les sauvegardes sont conservées.'];
}

function lewebmax_emails_upgrade($vars)
{
    lwm_create_backup_table();
}

function lewebmax_emails_output($vars)
{
    lwm_create_backup_table();
    lwm_admin_output($vars);
}
