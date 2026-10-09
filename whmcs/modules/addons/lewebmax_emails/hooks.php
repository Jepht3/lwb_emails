<?php
/**
 * Lwb-emails — hooks (chargés par WHMCS tant que le module est actif).
 *
 * Ajoute, au moment de l'envoi de chaque e-mail client, les variables :
 * - {$lwm_due_badge} / {$lwm_days_left} : badge d'échéance des factures impayées ;
 * - {$lwm_incident} : encadré des incidents ouverts dans le module lwb_statut.
 */

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('Accès direct interdit.');
}

require_once __DIR__ . '/lib/settings.php';
require_once __DIR__ . '/lib/render.php';

/** Langue du client concerné par l'e-mail : 'en' si son compte est en anglais, sinon 'fr'. */
function lwm_hook_client_lang($type, $relid)
{
    $tables = [
        'invoice' => 'tblinvoices',
        'product' => 'tblhosting',
        'domain' => 'tbldomains',
        'support' => 'tbltickets',
    ];
    $userId = $type === 'general' ? (int) $relid : 0;
    if (isset($tables[$type])) {
        $userId = (int) Capsule::table($tables[$type])->where('id', (int) $relid)->value('userid');
    }
    $lang = $userId ? (string) Capsule::table('tblclients')->where('id', $userId)->value('language') : '';
    if ($lang === '') {
        $lang = (string) Capsule::table('tblconfiguration')->where('setting', 'Language')->value('value');
    }
    return strtolower($lang) === 'english' ? 'en' : 'fr';
}

/** Incidents ouverts du module lwb_statut à annoncer aux clients. */
function lwm_hook_incidents(array $s)
{
    if (!class_exists('\\LwbStatut\\Status') || !\LwbStatut\Store::ready()) {
        return [];
    }
    $out = [];
    foreach (\LwbStatut\Status::openIncidents() as $inc) {
        if (!empty($inc['maintenance']) && empty($inc['active']) && !$s['incident_maintenance']) {
            continue;
        }
        $out[] = $inc;
    }
    return $out;
}

add_hook('EmailPreSend', 5, function ($vars) {
    try {
        $s = lwm_settings();
        $tpl = Capsule::table('tblemailtemplates')->where('name', $vars['messagename'] ?? '')->first(['type']);
        if (!$tpl || $tpl->type === 'admin') {
            return [];
        }
        $relid = (int) ($vars['relid'] ?? 0);
        $lang = lwm_hook_client_lang($tpl->type, $relid);
        $merge = ['lwm_due_badge' => '', 'lwm_incident' => ''];

        if ($s['countdown_enabled'] && $tpl->type === 'invoice' && $relid) {
            $invoice = Capsule::table('tblinvoices')->where('id', $relid)->first(['status', 'duedate']);
            if ($invoice && $invoice->status === 'Unpaid' && $invoice->duedate) {
                $today = new DateTime('today');
                $due = new DateTime(substr($invoice->duedate, 0, 10));
                $days = (int) $today->diff($due)->format('%r%a');
                $merge['lwm_days_left'] = $days;
                $merge['lwm_due_badge'] = lwm_due_badge_html($days, $lang);
            }
        }

        if ($s['incident_enabled']) {
            $merge['lwm_incident'] = lwm_incident_html(lwm_hook_incidents($s), $lang, $s);
        }
        return $merge;
    } catch (\Throwable $e) {
        logActivity('Lwb-emails (EmailPreSend) : ' . $e->getMessage());
        return [];
    }
});
