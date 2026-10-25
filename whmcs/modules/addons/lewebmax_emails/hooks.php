<?php
/**
 * Lwb-emails — hooks (chargés par WHMCS tant que le module est actif).
 *
 * Ajoute, au moment de l'envoi de chaque e-mail client, les variables :
 * - {$lwm_due_badge} / {$lwm_days_left} : badge d'échéance des factures impayées ;
 * - {$lwm_incident} : encadré des incidents ouverts dans le module lwb_statut ;
 * - {$lwm_web_url} : lien « Voir dans le navigateur » (sauf e-mails qui contiennent ou mentionnent un mot de passe) ;
 * - {$lwm_amount_full} / {$lwm_balance_full} / {$lwm_early_offer} : remise pour paiement anticipé
 *   (hook lewebmax_early_payment.php). Le montant affiché est le prix normal ; la remise est
 *   présentée comme une offre, avec sa date limite et le montant dû après cette date.
 */

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('Accès direct interdit.');
}

require_once __DIR__ . '/lib/settings.php';
require_once __DIR__ . '/lib/render.php';
require_once __DIR__ . '/lib/actions.php';

/** Les e-mails contenant un mot de passe ou un lien de réinitialisation n'ont jamais de version web. */
function lwm_hook_webview_allowed($messagename)
{
    $messages = Capsule::table('tblemailtemplates')->where('name', $messagename)->pluck('message')->all();
    foreach ($messages as $message) {
        if (preg_match('/\{\$[a-z_]*(password|pw_reset)[a-z_]*\}/i', (string) $message)) {
            return false;
        }
    }
    return true;
}

/** Vrai si une valeur de l'e-mail mentionne un mot de passe (ex. identifiants transmis à la commande). */
function lwm_hook_mentions_secret(array $mergefields)
{
    foreach ($mergefields as $value) {
        if (is_string($value) && preg_match('/mot de passe|password/i', $value)) {
            return true;
        }
    }
    return false;
}

/** Crée le lien de version web (jeton aléatoire, valable N jours) et nettoie les liens expirés. */
function lwm_hook_web_url(array $s, $messagename, $relid, array $mergefields = [])
{
    if (!$s['webview_enabled'] || defined('LWM_WEB_VIEW') || !lwm_hook_webview_allowed($messagename)
        || lwm_hook_mentions_secret($mergefields)) {
        return '';
    }
    lwm_create_backup_table();
    $limit = date('Y-m-d H:i:s', time() - 86400 * (int) $s['webview_days']);
    Capsule::table(LWM_WEB_TABLE)->where('created_at', '<', $limit)->delete();
    $token = bin2hex(random_bytes(16));
    Capsule::table(LWM_WEB_TABLE)->insert([
        'token' => $token,
        'messagename' => $messagename,
        'relid' => (int) $relid,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    return lwm_trailing_slash($s['client_url']) . 'modules/addons/lewebmax_emails/web.php?k=' . $token;
}

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

/** Montant formaté dans la devise du client. */
function lwm_hook_money($amount, $userId)
{
    $cur = (int) Capsule::table('tblclients')->where('id', (int) $userId)->value('currency');
    if (function_exists('formatCurrency')) {
        try {
            return trim((string) formatCurrency((float) $amount, $cur ?: null));
        } catch (\Throwable $e) {
        }
    }
    $c = Capsule::table('tblcurrencies')->where($cur ? 'id' : 'default', $cur ?: 1)->first(['prefix', 'suffix']);
    return trim(($c ? $c->prefix : '') . number_format((float) $amount, 2, '.', ' ') . ($c ? $c->suffix : ''));
}

/** Encadré de l'offre (fonction pure). $daysLeft : jours avant la date limite. */
function lwm_early_offer_html($daysLeft, $date, $price, $saving, $full, $percent, $lang)
{
    $en = $lang === 'en';
    $pct = $percent > 0 ? '&minus;' . (int) $percent . '&nbsp;%, ' : '';
    if ($daysLeft <= 0) {
        $head = $en ? '&#9203; Last day for the early payment offer' : '&#9203; Dernier jour pour l\'offre paiement anticipé';
    } elseif ($daysLeft <= 3) {
        $head = $en ? '&#9203; Only ' . $daysLeft . ' day' . ($daysLeft > 1 ? 's' : '') . ' left for the early payment offer'
            : '&#9203; Plus que ' . $daysLeft . ' jour' . ($daysLeft > 1 ? 's' : '') . ' pour l\'offre paiement anticipé';
    } else {
        $head = $en ? '&#127873; Early payment offer' : '&#127873; Offre paiement anticipé';
    }
    $e = function ($v) {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    };
    $body = $en
        ? 'Pay this invoice by <strong>' . $e($date) . '</strong> and pay only <strong>' . $e($price) . '</strong> (' . $pct . 'you save ' . $e($saving) . ').<br>After this date, the amount will be <strong>' . $e($full) . '</strong>.'
        : 'Réglez cette facture au plus tard le <strong>' . $e($date) . '</strong> et ne payez que <strong>' . $e($price) . '</strong> (' . $pct . 'soit ' . $e($saving) . ' d\'économie).<br>Passé cette date, le montant sera de <strong>' . $e($full) . '</strong>.';
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 18px 0;"><tr>'
        . '<td style="padding:14px 16px;border:1px solid #f5c26b;border-radius:12px;background:#fff6df;font-family:Plus Jakarta Sans,Arial,Helvetica,sans-serif;font-size:14px;line-height:21px;color:#5a3d00;">'
        . '<strong style="color:#5a3d00;">' . $head . '</strong><br>' . $body . '</td></tr></table>';
}

/**
 * Variables de la remise anticipée pour un e-mail de facture. Sans remise valable,
 * les montants sont ceux de WHMCS et l'encadré est vide.
 */
function lwm_hook_early_offer($invoiceId, array $mergefields, $lang)
{
    $out = [
        'lwm_amount_full' => (string) ($mergefields['invoice_total'] ?? ''),
        'lwm_balance_full' => (string) ($mergefields['invoice_balance'] ?? ($mergefields['invoice_total'] ?? '')),
        'lwm_early_offer' => '',
    ];
    if (!Capsule::schema()->hasTable('mod_lwm_early_discounts')) {
        return $out;
    }
    $inv = Capsule::table('tblinvoices')->where('id', (int) $invoiceId)->first(['id', 'userid', 'status', 'total']);
    if (!$inv || $inv->status !== 'Unpaid') {
        return $out;
    }
    $d = Capsule::table('mod_lwm_early_discounts')->where('invoice_id', $inv->id)->whereNull('removed_at')
        ->where('deadline', '>=', date('Y-m-d'))->first();
    if (!$d) {
        return $out;
    }
    $paid = (float) Capsule::table('tblaccounts')->where('invoiceid', $inv->id)->sum('amountin')
        - (float) Capsule::table('tblaccounts')->where('invoiceid', $inv->id)->sum('amountout');
    $balance = max(0, (float) $inv->total - $paid);
    $full = $balance + (float) $d->amount;
    $days = (int) floor((strtotime($d->deadline) - strtotime(date('Y-m-d'))) / 86400);
    $out['lwm_amount_full'] = lwm_hook_money((float) $inv->total + (float) $d->amount, $inv->userid);
    $out['lwm_balance_full'] = lwm_hook_money($full, $inv->userid);
    $out['lwm_early_offer'] = lwm_early_offer_html($days, date('d/m/Y', strtotime($d->deadline)), lwm_hook_money($balance, $inv->userid),
        lwm_hook_money($d->amount, $inv->userid), lwm_hook_money($full, $inv->userid), defined('LWM_EARLY_PERCENT') ? LWM_EARLY_PERCENT : 0, $lang);
    return $out;
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
        $merge = ['lwm_due_badge' => '', 'lwm_incident' => '', 'lwm_web_url' => ''];

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

        if ($tpl->type === 'invoice' && $relid) {
            $merge += lwm_hook_early_offer($relid, (array) ($vars['mergefields'] ?? []), $lang);
        }

        $merge['lwm_web_url'] = lwm_hook_web_url($s, $vars['messagename'], $relid, (array) ($vars['mergefields'] ?? []));

        if ($s['incident_enabled']) {
            $merge['lwm_incident'] = lwm_incident_html(lwm_hook_incidents($s), $lang, $s);
        }
        return $merge;
    } catch (\Throwable $e) {
        logActivity('Lwb-emails (EmailPreSend) : ' . $e->getMessage());
        return [];
    }
});
