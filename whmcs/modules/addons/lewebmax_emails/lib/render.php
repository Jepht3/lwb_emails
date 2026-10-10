<?php
/**
 * Lwb-emails — construction des thèmes et des modèles à partir des paramètres.
 */

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('Accès direct interdit.');
}

const LWM_FONT = 'Plus Jakarta Sans,Arial,Helvetica,sans-serif';

function lwm_path($relative)
{
    return dirname(__DIR__) . '/' . $relative;
}

function lwm_json($file)
{
    $data = json_decode(file_get_contents(lwm_path($file)), true);
    return is_array($data) ? $data : [];
}

function lwm_themes()
{
    $themes = [];
    foreach (lwm_json('themes.json') as $t) {
        $themes[$t['slug']] = $t;
    }
    return $themes;
}

function lwm_templates()
{
    $list = [];
    foreach (lwm_json('templates.json') as $t) {
        $list[$t['template_whmcs']] = $t;
    }
    return $list;
}

function lwm_strip_doc_comment($html)
{
    return preg_replace('/^\s*<!--.*?-->\s*/s', '', $html, 1);
}

/** Échappe une valeur saisie pour l'insérer dans du HTML, sans accolades interprétables par Smarty. */
function lwm_e($value)
{
    return str_replace(['{', '}'], ['&#123;', '&#125;'], htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'));
}

function lwm_host($url, $keepPort = false)
{
    $p = parse_url($url);
    $host = isset($p['host']) ? preg_replace('/^www\./', '', $p['host']) : $url;
    if ($keepPort && !empty($p['port'])) {
        $host .= ':' . $p['port'];
    }
    return $host;
}

function lwm_trailing_slash($url)
{
    return rtrim($url, '/') . '/';
}

function lwm_logo_img($src, array $s, $class = '', $center = false)
{
    $w = (int) $s['logo_width'];
    $margin = $center ? 'margin:0 auto;' : '';
    return '<img src="' . lwm_e($src) . '" width="' . $w . '" alt="' . lwm_e($s['company']) . '"'
        . ($class ? ' class="' . $class . '"' : '')
        . ' style="display:block;' . $margin . 'width:' . $w . 'px;max-width:100%;height:auto;border:0;outline:none;color:#030673;font-family:' . LWM_FONT . ';font-size:20px;font-weight:800;">';
}

/** Logo bleu sur fond clair ; remplacé par le logo blanc quand le client e-mail est en mode nuit. */
function lwm_logo_on_light(array $s, $center = false)
{
    $link = '<a href="' . lwm_e($s['site_url']) . '" target="_blank" style="text-decoration:none;">';
    $html = $link . lwm_logo_img($s['logo_light'], $s, 'lwm-logo-l', $center) . '</a>';
    if ($s['dark_mode']) {
        $html .= '<!--[if !mso]><!--><div class="lwm-logo-d" style="display:none;max-height:0;overflow:hidden;">'
            . $link . lwm_logo_img($s['logo_dark'], $s, '', $center) . '</a></div><!--<![endif]-->';
    }
    return $html;
}

function lwm_logo_on_dark(array $s)
{
    return '<a href="' . lwm_e($s['site_url']) . '" target="_blank" style="text-decoration:none;">'
        . lwm_logo_img($s['logo_dark'], $s) . '</a>';
}

function lwm_social_links(array $s)
{
    $links = [];
    foreach (LWM_SOCIALS as $key => $label) {
        if ($key === 'whatsapp') {
            if ($s['social_whatsapp'] && $s['whatsapp'] !== '') {
                $links[$key] = 'https://wa.me/' . $s['whatsapp'];
            }
        } elseif ($s['social_' . $key] !== '') {
            $links[$key] = $s['social_' . $key];
        }
    }
    return $links;
}

/** Rangée d'icônes de réseaux sociaux (images PNG, compatibles avec tous les clients e-mail). */
function lwm_social_html(array $s, $onDark, $align)
{
    $links = lwm_social_links($s);
    if (!$links) {
        return '';
    }
    $set = $s['icon_style'] === 'couleur' ? 'couleur' : ($onDark ? 'blanc' : 'bleu');
    $base = lwm_trailing_slash($s['icons_base_url']);
    $cells = '';
    foreach ($links as $key => $url) {
        $pad = $align === 'center' ? '0 5px' : '0 10px 0 0';
        $cells .= '<td style="padding:' . $pad . ';"><a href="' . lwm_e($url) . '" target="_blank" style="text-decoration:none;">'
            . '<img src="' . lwm_e($base . $key . '-' . $set . '.png') . '" width="30" height="30" alt="' . lwm_e(LWM_SOCIALS[$key]) . '"'
            . ' style="display:block;width:30px;height:30px;border:0;outline:none;"></a></td>';
    }
    return '<table role="presentation" cellpadding="0" cellspacing="0" border="0"' . ($align === 'center' ? ' align="center"' : '')
        . '><tr>' . $cells . '</tr></table>';
}

/**
 * Texte bilingue évalué par WHMCS au moment de l'envoi : anglais si la langue du client est « english ».
 * $fr et $en doivent déjà être échappés.
 */
function lwm_i18n($fr, $en)
{
    if ($en === '' || $en === $fr) {
        return $fr;
    }
    return '{if $client_language=="english"}' . $en . '{else}' . $fr . '{/if}';
}

/** Réglage texte bilingue (clé et clé_en), échappé. */
function lwm_ti(array $s, $key)
{
    return lwm_i18n(lwm_e($s[$key]), isset($s[$key . '_en']) ? lwm_e($s[$key . '_en']) : '');
}

function lwm_promo_html(array $s)
{
    if (!$s['promo_enabled'] || ($s['promo_text'] === '' && $s['promo_title'] === '')) {
        return '';
    }
    $button = '';
    if ($s['promo_url'] !== '') {
        $button = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:12px 0 0 0;"><tr>'
            . '<td bgcolor="#ff6a3d" style="background-color:#ff6a3d;border-radius:10px;">'
            . '<a href="' . lwm_e($s['promo_url']) . '" target="_blank" style="display:inline-block;padding:11px 20px;font-family:' . LWM_FONT . ';font-size:14px;line-height:18px;font-weight:700;color:#ffffff;text-decoration:none;">'
            . lwm_i18n(lwm_e($s['promo_button'] ?: 'En profiter'), lwm_e($s['promo_button_en'])) . '&nbsp;&rarr;</a></td></tr></table>';
    }
    $text = lwm_i18n(nl2br(lwm_e($s['promo_text'])), $s['promo_text_en'] !== '' ? nl2br(lwm_e($s['promo_text_en'])) : '');
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#fff7f3" style="background-color:#fff7f3;border:1px dashed #ff6a3d;border-radius:12px;margin:28px 0 0 0;">'
        . '<tr><td style="padding:18px 22px;font-family:' . LWM_FONT . ';">'
        . '<p style="margin:0 0 4px 0;font-size:11px;line-height:16px;font-weight:700;letter-spacing:1.4px;text-transform:uppercase;color:#ff6a3d;">' . lwm_ti($s, 'promo_title') . '</p>'
        . '<p style="margin:0;font-size:14px;line-height:22px;color:#0d0d2b;">' . $text . '</p>'
        . $button . '</td></tr></table>';
}

/** Signature selon le service (facturation, support, commercial) et la langue du modèle. */
function lwm_signature_html(array $s, $service, $lang)
{
    $regards = $lang === 'en' ? 'Kind regards,' : 'Cordialement,';
    $p = '<p style="margin:0 0 16px 0;font-family:' . LWM_FONT . ';font-size:15px;line-height:24px;color:#575b78;">';
    if (!$s['sig_enabled'] || !in_array($service, ['facturation', 'support', 'commercial'], true)) {
        $team = $lang === 'en' ? 'The {$company_name} team' : 'L&rsquo;équipe {$company_name}';
        return $p . $regards . '<br>{if $signature}{$signature}{else}<strong style="color:#0d0d2b;">' . $team . '</strong>{/if}</p>';
    }
    $title = $s['sig_' . $service . '_title' . ($lang === 'en' ? '_en' : '')] ?: $s['sig_' . $service . '_title'];
    $name = $s['sig_' . $service . '_name'];
    $photo = $s['sig_' . $service . '_photo'];
    $lines = ($name !== '' ? '<strong style="color:#0d0d2b;">' . lwm_e($name) . '</strong><br>' . lwm_e($title) : '<strong style="color:#0d0d2b;">' . lwm_e($title) . '</strong>')
        . '<br><span style="color:#8a8ea8;font-size:13px;">' . lwm_e($s['company']) . '</span>';
    $img = $photo !== ''
        ? '<td valign="middle" style="padding:0 14px 0 0;"><img src="' . lwm_e($photo) . '" width="48" height="48" alt="" style="display:block;width:48px;height:48px;border-radius:24px;border:0;"></td>'
        : '<td valign="middle" style="padding:0 14px 0 0;"><table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td width="4" height="44" bgcolor="#ff6a3d" style="background-color:#ff6a3d;width:4px;height:44px;border-radius:2px;font-size:0;line-height:0;">&nbsp;</td></tr></table></td>';
    return $p . $regards . '</p>'
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 16px 0;"><tr>' . $img
        . '<td valign="middle" style="font-family:' . LWM_FONT . ';font-size:14px;line-height:20px;color:#575b78;">' . $lines . '</td></tr></table>';
}

/** Badge d'échéance : « Échéance dans 3 jours », « En retard de 5 jours »… */
function lwm_due_badge_html($days, $lang)
{
    $days = (int) $days;
    $en = $lang === 'en';
    if ($days > 3) {
        list($bg, $fg) = ['#eef0fb', '#030673'];
        $text = $en ? "Due in $days days" : "Échéance dans $days jours";
    } elseif ($days > 0) {
        list($bg, $fg) = ['#fff1ec', '#c2410c'];
        $text = $en ? ($days === 1 ? 'Due tomorrow' : "Due in $days days") : ($days === 1 ? 'Échéance demain' : "Échéance dans $days jours");
    } elseif ($days === 0) {
        list($bg, $fg) = ['#fff1ec', '#c2410c'];
        $text = $en ? 'Due today' : 'Échéance aujourd&rsquo;hui';
    } else {
        $late = -$days;
        list($bg, $fg) = ['#fdeeee', '#b42318'];
        $text = $en ? ($late === 1 ? '1 day overdue' : "$late days overdue") : ($late === 1 ? 'En retard d&rsquo;1 jour' : "En retard de $late jours");
    }
    return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 12px 0;"><tr>'
        . '<td bgcolor="' . $bg . '" style="background-color:' . $bg . ';border-radius:999px;padding:6px 14px;font-family:' . LWM_FONT . ';font-size:13px;line-height:18px;font-weight:800;color:' . $fg . ';">'
        . '&#9201;&nbsp; ' . $text . '</td></tr></table>';
}

/** Encadré d'incident (données du module lwb_statut). */
function lwm_incident_html(array $incidents, $lang, array $s)
{
    if (!$incidents) {
        return '';
    }
    $en = $lang === 'en';
    $rows = '';
    foreach ($incidents as $inc) {
        $maint = !empty($inc['maintenance']);
        $when = '';
        if ($maint && !empty($inc['scheduled_for'])) {
            $when = ' &middot; ' . lwm_e($inc['scheduled_for']) . (!empty($inc['scheduled_until']) ? ' &rarr; ' . lwm_e($inc['scheduled_until']) : '');
        }
        $last = !empty($inc['updates']) ? end($inc['updates']) : null;
        $rows .= '<p style="margin:0 0 4px 0;font-family:' . LWM_FONT . ';font-size:14px;line-height:21px;font-weight:800;color:#0d0d2b;">' . lwm_e($inc['title']) . '</p>'
            . '<p style="margin:0 0 10px 0;font-family:' . LWM_FONT . ';font-size:13px;line-height:20px;color:#575b78;">' . lwm_e($inc['status_label'] ?? '') . $when
            . ($last && !empty($last['message']) ? '<br>' . lwm_e($last['message']) : '') . '</p>';
    }
    $anyIncident = (bool) array_filter($incidents, function ($i) { return empty($i['maintenance']); });
    $label = $anyIncident ? ($en ? 'Ongoing incident' : 'Incident en cours') : ($en ? 'Scheduled maintenance' : 'Maintenance planifiée');
    $color = $anyIncident ? '#b42318' : '#030673';
    $bg = $anyIncident ? '#fdeeee' : '#eef0fb';
    $link = $s['incident_url'] !== ''
        ? '<a href="' . lwm_e($s['incident_url']) . '" target="_blank" style="font-family:' . LWM_FONT . ';font-size:13px;font-weight:700;color:' . $color . ';text-decoration:none;">' . ($en ? 'Follow the service status' : 'Suivre l&rsquo;état des services') . '&nbsp;&rarr;</a>'
        : '';
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="' . $bg . '" style="background-color:' . $bg . ';border-left:4px solid ' . $color . ';border-radius:8px;margin:0 0 26px 0;">'
        . '<tr><td style="padding:14px 18px;">'
        . '<p style="margin:0 0 8px 0;font-family:' . LWM_FONT . ';font-size:11px;line-height:16px;font-weight:800;letter-spacing:1.4px;text-transform:uppercase;color:' . $color . ';">&#9679;&nbsp; ' . $label . '</p>'
        . $rows . $link . '</td></tr></table>';
}

function lwm_doc_start(array $s, $bg, $slug = '')
{
    $scheme = $s['dark_mode']
        ? "<meta name=\"color-scheme\" content=\"light dark\">\n<meta name=\"supported-color-schemes\" content=\"light dark\">\n"
        : '';
    $web = '';
    if ($s['webview_enabled']) {
        // {$lwm_web_url} est fourni par hooks.php ; vide pour les e-mails sensibles et dans la version web elle-même.
        $web = '{if $lwm_web_url}<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
            . '<td align="center" class="lwm-weblink" style="padding:12px 12px 0 12px;font-family:' . LWM_FONT . ';font-size:12px;line-height:18px;color:#8a8ea8;">'
            . lwm_i18n('E-mail mal affiché&nbsp;?', 'Email not displaying correctly?')
            . ' <a href="{$lwm_web_url}" target="_blank" style="color:#575b78;font-weight:700;text-decoration:underline;">'
            . lwm_i18n('Voir dans le navigateur', 'View in your browser') . '</a></td></tr></table>{/if}' . "\n";
    }
    return "<!DOCTYPE html>\n<html lang=\"fr\" xmlns=\"http://www.w3.org/1999/xhtml\">\n<head>\n"
        . "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\">\n"
        . "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n"
        . "<meta name=\"x-apple-disable-message-reformatting\">\n" . $scheme
        . "<link href=\"https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&amp;display=swap\" rel=\"stylesheet\">\n"
        . "<style type=\"text/css\">\n[EmailCSS]\n</style>\n</head>\n"
        . '<body class="lwm-bg" data-lwm-theme="' . lwm_e($slug) . '" style="margin:0;padding:0;background-color:' . $bg . ';">' . "\n" . $web;
}

/** Encadré « Complétez votre offre » : produits WHMCS choisis dans les paramètres (nom, prix et lien repris du catalogue). */
function lwm_upsell_html(array $s, $key, $lang)
{
    if (!$s['upsell_enabled'] || $s[$key] === '') {
        return '';
    }
    try {
        $ids = array_filter(array_map('intval', explode(',', $s[$key])));
        $currency = Capsule::table('tblcurrencies')->where('default', 1)->first(['id', 'prefix', 'suffix']);
        $products = Capsule::table('tblproducts as p')->leftJoin('tblproductgroups as g', 'g.id', '=', 'p.gid')
            ->whereIn('p.id', $ids)->where('p.hidden', 0)->where('p.retired', 0)->get(['p.id', 'p.name', 'g.name as grp']);
        $byId = [];
        foreach ($products as $prod) {
            $byId[(int) $prod->id] = $prod;
        }
        $client = lwm_trailing_slash($s['client_url']);
        $rows = '';
        foreach ($ids as $id) {
            if (!isset($byId[$id])) {
                continue;
            }
            $price = '';
            if ($currency) {
                $pr = Capsule::table('tblpricing')->where('type', 'product')->where('relid', $id)->where('currency', $currency->id)->first(['monthly', 'annually']);
                $fmt = function ($v) use ($currency) {
                    return trim($currency->prefix . number_format((float) $v, 2, ',', ' ') . $currency->suffix);
                };
                if ($pr && (float) $pr->monthly > 0) {
                    $price = ($lang === 'en' ? 'from ' : 'dès ') . $fmt($pr->monthly) . ($lang === 'en' ? '/month' : '/mois');
                } elseif ($pr && (float) $pr->annually > 0) {
                    $price = $fmt($pr->annually) . ($lang === 'en' ? '/year' : '/an');
                }
            }
            $rows .= '<tr><td style="padding:12px 0;border-top:1px solid #e2e5f2;font-family:' . LWM_FONT . ';font-size:14px;line-height:20px;color:#575b78;">'
                . '<strong style="color:#0d0d2b;">' . lwm_e($byId[$id]->name) . '</strong>'
                . ($byId[$id]->grp ? ' <span style="color:#8a8ea8;font-size:12px;">&middot; ' . lwm_e($byId[$id]->grp) . '</span>' : '')
                . ($price !== '' ? '<br>' . lwm_e($price) : '') . '</td>'
                . '<td align="right" style="padding:12px 0;border-top:1px solid #e2e5f2;font-family:' . LWM_FONT . ';font-size:14px;font-weight:700;white-space:nowrap;">'
                . '<a href="' . lwm_e($client . 'cart.php?a=add&pid=' . $id) . '" target="_blank" style="color:#030673;text-decoration:none;">'
                . ($lang === 'en' ? 'Order' : 'Commander') . '&nbsp;&rarr;</a></td></tr>';
        }
        if ($rows === '') {
            return '';
        }
        $title = $lang === 'en' ? ($s['upsell_title_en'] ?: $s['upsell_title']) : $s['upsell_title'];
        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f6f7fc" style="background-color:#f6f7fc;border:1px solid #e2e5f2;border-radius:12px;margin:4px 0 24px 0;">'
            . '<tr><td style="padding:16px 22px 6px 22px;">'
            . '<p style="margin:0 0 6px 0;font-family:' . LWM_FONT . ';font-size:11px;line-height:16px;font-weight:800;letter-spacing:1.4px;text-transform:uppercase;color:#ff6a3d;">' . lwm_e($title) . '</p>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">' . $rows . '</table>'
            . '</td></tr></table>';
    } catch (\Throwable $e) {
        return '';
    }
}

/** CSS du mode nuit (Apple Mail, iOS, Outlook macOS/iOS). Gmail l'ignore et applique sa propre inversion. */
function lwm_dark_css()
{
    return <<<CSS

/* Mode nuit */
:root { color-scheme: light dark; supported-color-schemes: light dark; }
@media (prefers-color-scheme: dark) {
  body, .lwm-bg { background-color: #0b0b1f !important; }
  .lwm-card, .lwm-surface, td[bgcolor="#ffffff"], table[bgcolor="#ffffff"] { background-color: #15152f !important; }
  table[bgcolor="#f6f7fc"], td[bgcolor="#f6f7fc"], table[bgcolor="#eef0fb"] { background-color: #1d1d3d !important; border-color: #2c2c55 !important; }
  table[bgcolor="#fff1ec"], table[bgcolor="#fff7f3"] { background-color: #2a1a14 !important; border-color: #5a3020 !important; }
  table[bgcolor="#fdeeee"] { background-color: #2a1414 !important; }
  table[bgcolor="#e7f9f0"] { background-color: #0f2a1f !important; }
  .lwm-card p, .lwm-card td, .lwm-surface td { color: #c4c6e0 !important; }
  .lwm-h1, .lwm-card [style*="color:#0d0d2b"], .lwm-surface [style*="color:#0d0d2b"], [style*="color:#0d0d2b"] { color: #ffffff !important; }
  .lwm-card [style*="color:#030673"], .lwm-surface [style*="color:#030673"], a[style*="color:#030673"] { color: #a9adff !important; }
  .lwm-card [style*="color:#7a2e14"] { color: #ffc2ad !important; }
  .lwm-card [style*="color:#7a1010"] { color: #ffb4b4 !important; }
  .lwm-card [style*="color:#05603a"] { color: #9ff0c8 !important; }
  [style*="color:#575b78"] { color: #c4c6e0 !important; }
  [style*="color:#8a8ea8"] { color: #9a9cbc !important; }
  .lwm-chip { color: #ffffff !important; border-color: #2c2c55 !important; }
  .lwm-logo-l { display: none !important; }
  .lwm-logo-d { display: block !important; max-height: none !important; overflow: visible !important; }
}
CSS;
}

/** Valeurs communes à l'en-tête, au pied de page et aux modèles. */
function lwm_placeholders(array $s)
{
    $client = lwm_trailing_slash($s['client_url']);
    return [
        '%%company%%' => lwm_e($s['company']),
        '%%tagline%%' => lwm_ti($s, 'tagline'),
        '%%site_url%%' => lwm_e($s['site_url']),
        '%%site_label%%' => lwm_e(lwm_host($s['site_url'])),
        '%%client_url%%' => lwm_e($client),
        '%%client_label%%' => lwm_e(lwm_host($client)),
        '%%email%%' => lwm_e($s['email']),
        '%%phone%%' => lwm_e($s['phone']),
        '%%whatsapp_url%%' => lwm_e('https://wa.me/' . $s['whatsapp']),
        '%%address%%' => lwm_e($s['address']),
        '%%cpanel_url%%' => lwm_e($s['cpanel_url']),
        '%%cpanel_label%%' => lwm_e(lwm_host($s['cpanel_url'], true)),
        '%%payments%%' => lwm_ti($s, 'payments'),
        '%%legal%%' => lwm_ti($s, 'legal'),
        '%%hero_text%%' => lwm_ti($s, 'hero_text'),
        '%%hero_accent%%' => lwm_ti($s, 'hero_accent'),
        '%%hero_badge%%' => lwm_ti($s, 'hero_badge'),
        // Modèles : une version par langue, donc pas de bascule à l'envoi.
        '%%payments_long%%' => lwm_e($s['payments_long']),
        '%%payments_long_en%%' => lwm_e($s['payments_long_en'] ?: $s['payments_long']),
        '%%offer_line%%' => lwm_e($s['offer_line']),
        '%%offer_line_en%%' => lwm_e($s['offer_line_en'] ?: $s['offer_line']),
    ];
}

/** Remplace les %%i18n:français|anglais%% des fichiers de thème. */
function lwm_apply_i18n($html)
{
    return preg_replace_callback('/%%i18n:([^|]*?)\|(.*?)%%/', function ($m) {
        return lwm_i18n($m[1], $m[2]);
    }, $html);
}

/** Construit le CSS, l'en-tête et le pied de page d'un thème (HTML brut, non encodé). */
function lwm_build_theme($slug, ?array $s = null)
{
    $s = $s ?: lwm_settings();
    $themes = lwm_themes();
    if (!isset($themes[$slug])) {
        throw new InvalidArgumentException('Thème inconnu : ' . $slug);
    }
    $dir = lwm_path('themes/' . $slug);
    $css = file_get_contents($dir . '/email.css');
    if ($s['dark_mode']) {
        $css .= lwm_dark_css();
    }
    $map = lwm_placeholders($s) + [
        '%%doc_start%%' => lwm_doc_start($s, $themes[$slug]['bg'], $slug),
        '%%logo_on_light%%' => lwm_logo_on_light($s),
        '%%logo_on_light_center%%' => lwm_logo_on_light($s, true),
        '%%logo_on_dark%%' => lwm_logo_on_dark($s),
        '%%social_light_center%%' => lwm_social_html($s, false, 'center'),
        '%%social_dark_left%%' => lwm_social_html($s, true, 'left'),
        '%%promo_in_card%%' => lwm_promo_html($s),
    ];
    return [
        'EmailCSS' => $css,
        'EmailGlobalHeader' => strtr(lwm_apply_i18n(lwm_strip_doc_comment(file_get_contents($dir . '/header.html'))), $map),
        'EmailGlobalFooter' => strtr(lwm_apply_i18n(lwm_strip_doc_comment(file_get_contents($dir . '/footer.html'))), $map),
    ];
}

/** Sujets et textes d'aperçu personnalisés depuis le module (clé ov_<type>_<fichier>_<langue>). */
function lwm_overrides($reload = false)
{
    static $cache = null;
    if ($cache === null || $reload) {
        $cache = [];
        foreach (Capsule::table('tbladdonmodules')->where('module', LWM_MODULE)->pluck('value', 'setting')->all() as $k => $v) {
            if (strpos($k, 'ov_') === 0 && $v !== '') {
                $cache[$k] = (string) $v;
            }
        }
    }
    return $cache;
}

function lwm_override_key($kind, $file, $lang)
{
    return 'ov_' . $kind . '_' . preg_replace('/\.html$/', '', $file) . '_' . $lang;
}

/** Sujet du modèle dans la langue demandée (personnalisé ou par défaut). */
function lwm_subject(array $tpl, $lang)
{
    $ov = lwm_overrides();
    $key = lwm_override_key('subject', $tpl['fichier'], $lang);
    if (isset($ov[$key])) {
        return $ov[$key];
    }
    return $lang === 'en' ? $tpl['sujet_en'] : $tpl['sujet'];
}

/** Corps d'un modèle prêt à enregistrer dans WHMCS (variables Smarty conservées). */
function lwm_build_body($file, ?array $s = null, $lang = 'fr')
{
    $s = $s ?: lwm_settings();
    $path = lwm_path('templates/' . ($lang === 'en' ? 'en/' : '') . $file);
    $html = lwm_strip_doc_comment(file_get_contents($path));
    $tpl = null;
    foreach (lwm_templates() as $t) {
        if ($t['fichier'] === $file) {
            $tpl = $t;
        }
    }
    $map = lwm_placeholders($s) + [
        '%%signature%%' => lwm_signature_html($s, $tpl ? $tpl['service'] : '', $lang),
        '%%upsell_hosting%%' => lwm_upsell_html($s, 'upsell_hosting', $lang),
        '%%upsell_domain%%' => lwm_upsell_html($s, 'upsell_domain', $lang),
    ];
    $html = strtr($html, $map);
    $ov = lwm_overrides();
    $key = lwm_override_key('pre', $file, $lang);
    if (isset($ov[$key])) {
        $html = preg_replace('/(<div class="lwm-preheader"[^>]*>).*?(<\/div>)/s', '${1}' . str_replace(['\\', '$'], ['\\\\', '\\$'], lwm_e($ov[$key])) . '${2}', $html, 1);
    }
    return $html;
}

/** Données fictives pour les aperçus et les e-mails de test. */
function lwm_sample_data()
{
    return [
        'client_first_name' => 'Jephté',
        'client_name' => 'Jephté Topde',
        'client_email' => 'client@exemple.cd',
        'company_name' => 'LE WEB MAX',
        'signature' => '',
        'reset_password_url' => 'https://my.lewebmax.com/index.php?rp=/password/reset/redeem/exemple',
        'invoice_id' => '1042',
        'invoice_num' => '2026-1042',
        'invoice_date_created' => '09/10/2026',
        'invoice_date_due' => '16/10/2026',
        'invoice_total' => '29,88 $ USD',
        'invoice_balance' => '29,88 $ USD',
        'invoice_amount_paid' => '29,88 $ USD',
        'invoice_status' => 'Payée',
        'invoice_last_payment_amount' => '29,88 $ USD',
        'invoice_last_payment_transid' => 'MP261009.1432.A12345',
        'invoice_html_contents' => '<table width="100%" style="border-collapse:collapse"><tr><td>Hébergement Pro (09/10/2026 - 08/10/2027)</td><td align="right">29,88 $ USD</td></tr><tr><td><strong>Total</strong></td><td align="right"><strong>29,88 $ USD</strong></td></tr></table>',
        'invoice_payment_method' => 'Mobile Money',
        'invoice_pay_method_display_name' => 'M-Pesa',
        'invoice_next_payment_attempt_date' => '16/10/2026',
        'order_number' => '7598914935',
        'order_details' => 'Produit/Service : Hébergement Pro<br>Domaine : monentreprise.cd<br>Premier paiement : 29,88 $ USD<br>Montant récurrent : 29,88 $ USD<br>Cycle de facturation : Annuel<br><br>Total dû aujourd’hui : 29,88 $ USD',
        'flexpay_pay_link' => 'https://my.lewebmax.com/modules/gateways/flexpay/pay.php?i=1042',
        'service_product_name' => 'Hébergement Pro',
        'service_domain' => 'monentreprise.cd',
        'service_username' => 'monentre',
        'service_password' => 'x7#Pq2!vLm9',
        'service_server_name' => 'server.lewebmax.com',
        'service_server_ip' => '176.9.142.250',
        'service_ns1' => 'ns1.lewebmax.com',
        'service_ns1_ip' => '176.9.142.250',
        'service_ns2' => 'ns2.lewebmax.com',
        'service_ns2_ip' => '176.9.142.251',
        'service_first_payment_amount' => '29,88 $ USD',
        'service_recurring_amount' => '29,88 $ USD',
        'service_billing_cycle' => 'Annuel',
        'service_next_due_date' => '09/10/2027',
        'service_suspension_reason' => 'Facture impayée',
        'domain_name' => 'monentreprise.cd',
        'domain_reg_date' => '09/10/2026',
        'domain_reg_period' => '1 an',
        'domain_first_payment_amount' => '35,00 $ USD',
        'domain_next_due_date' => '09/11/2026',
        'domain_days_until_nextdue' => '30',
        'domain_renewal_url' => 'https://my.lewebmax.com/cart.php?a=renewals',
        'domains_manage_url' => 'https://my.lewebmax.com/clientarea.php?action=domains',
        'ticket_id' => '482913',
        'ticket_subject' => 'Configuration de mes e-mails sur Outlook',
        'ticket_priority' => 'Moyenne',
        'ticket_status' => 'Répondu',
        'ticket_link' => '<a href="https://my.lewebmax.com/viewticket.php?tid=482913">https://my.lewebmax.com/viewticket.php?tid=482913</a>',
        'ticket_message' => 'Bonjour Jephté,<br><br>Pour configurer votre adresse sur Outlook, utilisez le serveur <strong>mail.monentreprise.cd</strong>, port IMAP 993 (SSL) et SMTP 465 (SSL).',
    ];
}

/**
 * Interprète le strict nécessaire de Smarty pour l'aperçu : {$var}, {if $var}…{else}…{/if}
 * et {if isset($var)}…{/if} (sans imbrication).
 */
function lwm_render_sample($html, array $data)
{
    // Conditions traitées de l'intérieur vers l'extérieur, pour gérer les {if} imbriqués.
    $pattern = '/\{if (?:isset\()?\$([a-z0-9_]+)\)?(?:\s*==\s*"([^"]*)")?\}((?:(?!\{if ).)*?)(?:\{else\}((?:(?!\{if ).)*?))?\{\/if\}/s';
    do {
        $html = preg_replace_callback($pattern, function ($m) use ($data) {
            $value = isset($data[$m[1]]) ? $data[$m[1]] : '';
            $true = $m[2] !== '' ? $value === $m[2] : !empty($value);
            return $true ? $m[3] : (isset($m[4]) ? $m[4] : '');
        }, $html, -1, $count);
    } while ($count > 0);
    return preg_replace_callback('/\{\$([a-z0-9_]+)\}/', function ($m) use ($data) {
        return isset($data[$m[1]]) ? $data[$m[1]] : '';
    }, $html);
}

/** E-mail complet (thème + modèle) avec données fictives : [sujet, html]. */
function lwm_render_preview($slug, $templateName, ?array $s = null, $lang = 'fr', array $opts = [])
{
    $s = $s ?: lwm_settings();
    $tpl = lwm_templates()[$templateName];
    $theme = lwm_build_theme($slug, $s);
    $body = lwm_build_body($tpl['fichier'], $s, $lang);
    $data = lwm_sample_data();
    $data['client_language'] = $lang === 'en' ? 'english' : 'french';
    $data['lwm_web_url'] = '#';
    if ($s['countdown_enabled'] && strpos($body, '{$lwm_due_badge}') !== false) {
        $data['lwm_due_badge'] = lwm_due_badge_html(isset($opts['days']) ? (int) $opts['days'] : 3, $lang);
    }
    if (!empty($opts['incident'])) {
        $data['lwm_incident'] = lwm_incident_html([[
            'title' => $lang === 'en' ? 'Slow email delivery' : 'Lenteurs sur la messagerie',
            'status_label' => $lang === 'en' ? 'Identified' : 'Cause identifiée',
            'maintenance' => false,
            'updates' => [['message' => $lang === 'en' ? 'Our teams are working on it.' : 'Nos équipes interviennent, retour à la normale prévu sous 1 heure.']],
        ]], $lang, $s);
    }
    $html = str_replace('[EmailCSS]', $theme['EmailCSS'], $theme['EmailGlobalHeader']) . $body . $theme['EmailGlobalFooter'];
    return [lwm_render_sample(lwm_subject($tpl, $lang), $data), lwm_render_sample($html, $data)];
}
