<?php
/**
 * Lwb-emails — version web d'un e-mail (« Voir dans le navigateur »).
 *
 * Le lien contient un jeton aléatoire créé à l'envoi par hooks.php, valable le nombre de jours
 * réglé dans le module. L'e-mail est régénéré avec les données actuelles de WHMCS.
 */

define('LWM_WEB_VIEW', true);
require __DIR__ . '/../../../init.php';
require_once __DIR__ . '/lib/settings.php';
require_once __DIR__ . '/lib/render.php';
require_once __DIR__ . '/lib/actions.php';

use WHMCS\Database\Capsule;

header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('Cache-Control: private, no-store');

function lwm_web_fail($code, $title, $text)
{
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . $title . '</title></head>'
        . '<body style="margin:0;background:#f4f5fb;font-family:Arial,Helvetica,sans-serif;color:#0d0d2b;">'
        . '<div style="max-width:480px;margin:80px auto;padding:32px;background:#fff;border-radius:16px;text-align:center;">'
        . '<h1 style="font-size:22px;margin:0 0 12px;">' . $title . '</h1><p style="color:#575b78;line-height:1.6;margin:0;">' . $text . '</p></div></body></html>';
    exit;
}

$active = explode(',', (string) Capsule::table('tblconfiguration')->where('setting', 'ActiveAddonModules')->value('value'));
$s = lwm_settings();
$token = preg_replace('/[^a-f0-9]/', '', (string) ($_GET['k'] ?? ''));
$row = strlen($token) === 32 && Capsule::schema()->hasTable(LWM_WEB_TABLE)
    ? Capsule::table(LWM_WEB_TABLE)->where('token', $token)->first()
    : null;

if (!in_array('lewebmax_emails', $active, true) || !$s['webview_enabled'] || !$row
    || strtotime($row->created_at) < time() - 86400 * (int) $s['webview_days']) {
    lwm_web_fail(404, 'Lien expiré', 'Ce lien n’est plus valable. Retrouvez tous vos e-mails dans votre espace client.');
}

try {
    $message = WHMCS\Mail\Emailer::factoryByTemplate($row->messagename, (int) $row->relid)->preview();
    header('Content-Type: text/html; charset=utf-8');
    echo $message->getBody();
} catch (\Throwable $e) {
    lwm_web_fail(500, 'E-mail indisponible', 'Cet e-mail ne peut pas être affiché pour le moment.');
}
