<?php
/**
 * Lwb-emails — interface d'administration (onglets).
 */

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('Accès direct interdit.');
}

const LWM_TABS = [
    'themes' => 'Thèmes',
    'settings' => 'Personnalisation',
    'templates' => 'Modèles',
    'preview' => 'Aperçu & test',
    'backups' => 'Sauvegardes & export',
];

function lwm_h($v)
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function lwm_alert($type, $html)
{
    return '<div class="alert alert-' . $type . '">' . $html . '</div>';
}

function lwm_admin_email()
{
    $id = isset($_SESSION['adminid']) ? (int) $_SESSION['adminid'] : 0;
    return $id ? (string) Capsule::table('tbladmins')->where('id', $id)->value('email') : '';
}

/** Traite le formulaire envoyé ; retourne [messages HTML, onglet à afficher]. */
function lwm_handle_post($tab)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['lwm_action'])) {
        return ['', $tab];
    }
    check_token('WHMCS.admin.default');
    $action = $_POST['lwm_action'];
    $themes = lwm_themes();
    $names = isset($_POST['templates']) && is_array($_POST['templates']) ? array_map('strval', $_POST['templates']) : [];

    try {
        switch ($action) {
            case 'theme':
                $slug = (string) ($_POST['theme'] ?? '');
                if (!isset($themes[$slug])) {
                    return [lwm_alert('danger', 'Thème inconnu.'), 'themes'];
                }
                lwm_apply_theme($slug);
                return [lwm_alert('success', 'Thème <strong>' . lwm_h($themes[$slug]['nom']) . '</strong> appliqué à tous les e-mails clients.'), 'themes'];

            case 'settings':
                $errors = lwm_save_settings($_POST['s'] ?? []);
                if ($errors) {
                    return [lwm_alert('danger', 'Paramètres non enregistrés :<br>' . implode('<br>', array_map('lwm_h', $errors))), 'settings'];
                }
                list($theme, $count) = lwm_refresh_installed();
                $msg = 'Paramètres enregistrés.';
                if ($theme !== '' || $count) {
                    $msg .= ' Mis à jour automatiquement : ' . ($theme !== '' ? 'le thème actif' : '') . ($theme !== '' && $count ? ' et ' : '') . ($count ? $count . ' modèle(s) installé(s)' : '') . '.';
                }
                return [lwm_alert('success', $msg), 'settings'];

            case 'settings_reset':
                lwm_reset_settings();
                lwm_refresh_installed();
                return [lwm_alert('info', 'Paramètres remis aux valeurs par défaut.'), 'settings'];

            case 'install':
                list($updated, $missing) = lwm_install_templates($names ?: null);
                $msg = lwm_alert('success', count($updated) . ' modèle(s) installé(s) ou mis à jour.');
                if ($missing) {
                    $msg .= lwm_alert('warning', 'Introuvables dans WHMCS : ' . lwm_h(implode(', ', $missing)));
                }
                return [$msg, 'templates'];

            case 'restore_templates':
                $restored = lwm_restore_templates($names ?: null);
                return [lwm_alert('info', count($restored) . ' modèle(s) remis dans leur version d\'origine.'), $tab === 'backups' ? 'backups' : 'templates'];

            case 'harmonize':
                $n = lwm_harmonize_others();
                return [lwm_alert('success', $n . ' autre(s) modèle(s) harmonisé(s) aux couleurs LEWEBMAX (texte conservé, version d\'origine sauvegardée).'), 'templates'];

            case 'restore_others':
                $restored = lwm_restore_others();
                return [lwm_alert('info', count($restored) . ' autre(s) modèle(s) remis dans leur version d\'origine.'), 'templates'];

            case 'override':
                $name = (string) ($_POST['template'] ?? '');
                if (!isset(lwm_templates()[$name])) {
                    return [lwm_alert('danger', 'Modèle inconnu.'), 'templates'];
                }
                $reinstalled = lwm_save_overrides($name, $_POST['ov'] ?? []);
                return [lwm_alert('success', 'Sujet et texte d\'aperçu enregistrés' . ($reinstalled ? ' et appliqués au modèle dans WHMCS.' : '. Ils seront appliqués à l\'installation du modèle.')), 'templates'];

            case 'import':
                $json = '';
                if (!empty($_FILES['config']['tmp_name']) && is_uploaded_file($_FILES['config']['tmp_name'])) {
                    $json = file_get_contents($_FILES['config']['tmp_name']);
                } elseif (!empty($_POST['config_text'])) {
                    $json = (string) $_POST['config_text'];
                }
                $errors = lwm_import_config($json, !empty($_POST['apply_theme']));
                if ($errors) {
                    return [lwm_alert('danger', 'Import impossible :<br>' . implode('<br>', array_map('lwm_h', $errors))), 'backups'];
                }
                return [lwm_alert('success', 'Configuration importée. Le thème et les modèles installés ont été mis à jour.'), 'backups'];

            case 'restore_theme':
                lwm_restore_theme();
                return [lwm_alert('info', 'En-tête, pied de page et CSS d\'origine restaurés.'), 'backups'];

            case 'restore_all':
                lwm_restore_templates();
                lwm_restore_theme();
                return [lwm_alert('info', 'Tous les e-mails ont été remis dans leur état d\'origine.'), 'backups'];

            case 'test':
                $to = trim((string) ($_POST['to'] ?? ''));
                $slug = (string) ($_POST['theme'] ?? '');
                $tpl = (string) ($_POST['template'] ?? '');
                if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !isset($themes[$slug]) || !isset(lwm_templates()[$tpl])) {
                    return [lwm_alert('danger', 'Adresse, thème ou modèle invalide.'), 'preview'];
                }
                $lang = ($_POST['lang'] ?? '') === 'en' ? 'en' : 'fr';
                lwm_send_test($to, $slug, $tpl, $lang, ['incident' => !empty($_POST['incident']), 'days' => (int) ($_POST['days'] ?? 3)]);
                return [lwm_alert('success', 'E-mail de test envoyé à <strong>' . lwm_h($to) . '</strong>. Pensez à vérifier les spams.'), 'preview'];
        }
    } catch (\Throwable $e) {
        return [lwm_alert('danger', 'Erreur : ' . lwm_h($e->getMessage())), $tab];
    }
    return ['', $tab];
}

function lwm_admin_output($vars)
{
    $link = $vars['modulelink'];
    $tab = isset($_GET['tab']) && isset(LWM_TABS[$_GET['tab']]) ? $_GET['tab'] : 'themes';
    list($notice, $tab) = lwm_handle_post($tab);

    echo '<style>
        .lwm-tabs{margin-bottom:20px}
        .lwm-theme-frame{width:100%;height:440px;border:0;display:block;background:#fff}
        .lwm-help{color:#888;font-size:12px;margin-top:4px}
        .lwm-badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600}
        .lwm-ok{background:#e7f9f0;color:#05603a}.lwm-orig{background:#f1f1f4;color:#555}.lwm-off{background:#fdeeee;color:#7a1010}
        .lwm-preview-wrap{background:#e9eaf2;padding:16px;border-radius:8px;text-align:center}
        .lwm-preview-wrap iframe{border:0;background:#fff;box-shadow:0 2px 12px rgba(0,0,0,.08)}
    </style>';

    echo '<ul class="nav nav-tabs lwm-tabs">';
    foreach (LWM_TABS as $key => $label) {
        echo '<li class="' . ($key === $tab ? 'active' : '') . '"><a href="' . lwm_h($link . '&tab=' . $key) . '">' . lwm_h($label) . '</a></li>';
    }
    echo '</ul>';
    echo $notice;

    $token = generate_token('form');
    $fn = 'lwm_tab_' . $tab;
    $fn($link, $token);
}

function lwm_form_open($link, $token, $tab, $action, $extra = '')
{
    return '<form method="post" action="' . lwm_h($link . '&tab=' . $tab) . '"' . $extra . '>' . $token
        . '<input type="hidden" name="lwm_action" value="' . lwm_h($action) . '">';
}

function lwm_tab_themes($link, $token)
{
    $current = lwm_active_theme();
    echo '<p>Choisissez le design appliqué à <strong>tous les e-mails clients</strong> : en-tête, pied de page et CSS globaux. '
        . 'Les réglages d\'origine sont sauvegardés au premier changement. Aperçu ci-dessous avec vos paramètres actuels.</p>';
    echo '<div class="row">';
    foreach (lwm_themes() as $slug => $t) {
        $active = $current === $slug;
        list(, $html) = lwm_render_preview($slug, 'Invoice Created');
        echo '<div class="col-md-4 col-sm-6" style="margin-bottom:24px;">'
            . '<div class="panel ' . ($active ? 'panel-primary' : 'panel-default') . '">'
            . '<div class="panel-heading"><strong>' . lwm_h($t['nom']) . '</strong>' . ($active ? ' &mdash; actif' : '') . '</div>'
            . '<iframe class="lwm-theme-frame" srcdoc="' . lwm_h($html) . '"></iframe>'
            . '<div class="panel-body"><p style="min-height:60px;">' . lwm_h($t['description']) . '</p>'
            . lwm_form_open($link, $token, 'themes', 'theme')
            . '<input type="hidden" name="theme" value="' . lwm_h($slug) . '">'
            . '<button type="submit" class="btn ' . ($active ? 'btn-default' : 'btn-primary') . ' btn-block"' . ($active ? ' disabled' : '') . '>'
            . ($active ? 'Thème actif' : 'Utiliser ce thème') . '</button></form>'
            . '<a class="btn btn-link btn-block" href="' . lwm_h($link . '&tab=preview&theme=' . $slug) . '">Voir tous les e-mails avec ce thème</a>'
            . '</div></div></div>';
    }
    echo '</div>';
}

function lwm_tab_settings($link, $token)
{
    $s = lwm_settings();
    $groups = [];
    foreach (lwm_fields() as $key => $f) {
        $groups[$f[0]][$key] = $f;
    }
    echo '<p>Ces informations remplissent l\'en-tête, le pied de page et les modèles. '
        . 'À l\'enregistrement, le thème actif et les modèles installés sont <strong>mis à jour automatiquement</strong>.</p>';
    echo lwm_form_open($link, $token, 'settings', 'settings');
    echo '<div class="row">';
    $i = 0;
    foreach ($groups as $group => $fields) {
        echo '<div class="col-md-6"><div class="panel panel-default"><div class="panel-heading"><strong>' . lwm_h($group) . '</strong></div><div class="panel-body">';
        foreach ($fields as $key => $f) {
            list(, $label, $default, $type, $help) = $f;
            $name = 's[' . $key . ']';
            $val = $s[$key];
            if ($key === 'test_email' && $val === '') {
                $help = 'Par défaut : ' . lwm_admin_email();
            }
            echo '<div class="form-group">';
            if ($type === 'yesno') {
                echo '<div class="checkbox"><label><input type="checkbox" name="' . lwm_h($name) . '" value="on"' . ($val ? ' checked' : '') . '> ' . lwm_h($label) . '</label></div>';
            } else {
                echo '<label>' . lwm_h($label) . '</label>';
                if ($type === 'textarea') {
                    echo '<textarea class="form-control" rows="3" name="' . lwm_h($name) . '">' . lwm_h($val) . '</textarea>';
                } elseif (strpos($type, 'select:') === 0) {
                    echo '<select class="form-control" name="' . lwm_h($name) . '">';
                    foreach (explode('|', substr($type, 7)) as $opt) {
                        echo '<option value="' . lwm_h($opt) . '"' . ($opt === $val ? ' selected' : '') . '>' . lwm_h(ucfirst($opt)) . '</option>';
                    }
                    echo '</select>';
                } else {
                    $input = $type === 'number' ? 'number' : ($type === 'email' ? 'email' : ($type === 'url' ? 'url' : 'text'));
                    echo '<input class="form-control" type="' . $input . '" name="' . lwm_h($name) . '" value="' . lwm_h($val) . '">';
                }
                if ($key === 'logo_light' || $key === 'logo_dark') {
                    $bg = $key === 'logo_dark' ? '#030673' : '#ffffff';
                    echo '<div style="margin-top:6px;padding:10px;background:' . $bg . ';border:1px solid #ddd;border-radius:6px;display:inline-block;"><img src="' . lwm_h($val) . '" style="height:36px;" alt=""></div>';
                }
            }
            if ($help) {
                echo '<div class="lwm-help">' . lwm_h($help) . '</div>';
            }
            echo '</div>';
        }
        echo '</div></div></div>';
        if (++$i % 2 === 0) {
            echo '<div class="clearfix"></div>';
        }
    }
    echo '</div>';
    echo '<button type="submit" class="btn btn-primary btn-lg">Enregistrer et mettre à jour les e-mails</button></form>';
    echo '<hr>' . lwm_form_open($link, $token, 'settings', 'settings_reset', ' onsubmit="return confirm(\'Remettre tous les paramètres par défaut ?\');"')
        . '<button type="submit" class="btn btn-default">Remettre les paramètres par défaut</button></form>';
}

/** Variables Smarty utilisées par un modèle (corps et sujet), pour aider à écrire les sujets. */
function lwm_template_variables(array $tpl)
{
    $text = file_get_contents(lwm_path('templates/' . $tpl['fichier'])) . $tpl['sujet'];
    preg_match_all('/\{\$([a-z0-9_]+)\}/', $text, $m);
    $vars = array_unique(array_merge(['client_first_name', 'client_name', 'company_name'], $m[1]));
    $vars = array_diff($vars, ['lwm_due_badge', 'lwm_incident', 'signature']);
    sort($vars);
    return $vars;
}

function lwm_tab_templates_edit($link, $token, $name)
{
    $tpl = lwm_templates()[$name];
    $ov = lwm_overrides();
    $val = function ($kind, $lang) use ($ov, $tpl) {
        $key = lwm_override_key($kind, $tpl['fichier'], $lang);
        return isset($ov[$key]) ? $ov[$key] : '';
    };
    echo '<p><a href="' . lwm_h($link . '&tab=templates') . '">&larr; Retour aux modèles</a></p>'
        . '<h3>' . lwm_h($name) . '</h3>'
        . '<p>Laissez un champ vide pour garder le texte par défaut (affiché en gris). Le texte d\'aperçu est la ligne grise affichée après le sujet dans la boîte de réception.</p>';
    echo lwm_form_open($link, $token, 'templates', 'override') . '<input type="hidden" name="template" value="' . lwm_h($name) . '">';
    echo '<div class="row">';
    foreach (['fr' => 'Français (par défaut)', 'en' => 'Anglais (clients « english »)'] as $lang => $label) {
        $defSubject = $lang === 'en' ? $tpl['sujet_en'] : $tpl['sujet'];
        $defPre = $lang === 'en' ? $tpl['apercu_en'] : $tpl['apercu'];
        echo '<div class="col-md-6"><div class="panel panel-default"><div class="panel-heading"><strong>' . lwm_h($label) . '</strong></div><div class="panel-body">'
            . '<div class="form-group"><label>Sujet</label><input class="form-control" name="ov[subject_' . $lang . ']" value="' . lwm_h($val('subject', $lang)) . '" placeholder="' . lwm_h($defSubject) . '"></div>'
            . '<div class="form-group"><label>Texte d\'aperçu</label><input class="form-control" name="ov[pre_' . $lang . ']" value="' . lwm_h($val('pre', $lang)) . '" placeholder="' . lwm_h($defPre) . '"></div>'
            . '<a href="' . lwm_h($link . '&tab=preview&template=' . rawurlencode($name) . '&lang=' . $lang) . '">Voir l\'aperçu</a>'
            . '</div></div></div>';
    }
    echo '</div>';
    echo '<p><strong>Variables disponibles :</strong> ';
    foreach (lwm_template_variables($tpl) as $v) {
        echo '<code style="margin:0 4px 4px 0;display:inline-block;">{$' . lwm_h($v) . '}</code>';
    }
    echo '</p><button type="submit" class="btn btn-primary">Enregistrer</button></form>';
}

function lwm_tab_templates($link, $token)
{
    if (isset($_GET['edit']) && isset(lwm_templates()[$_GET['edit']])) {
        lwm_tab_templates_edit($link, $token, $_GET['edit']);
        return;
    }
    $status = lwm_templates_status();
    $services = ['facturation' => 'Facturation', 'support' => 'Support', 'commercial' => 'Commercial'];
    echo '<p>Cochez les modèles LEWEBMAX à installer (ou à remettre dans leur version d\'origine). La version française (par défaut) et la version anglaise '
        . '(clients dont la langue est « english ») sont installées ensemble&nbsp;; les autres traductions restent intactes.</p>';
    echo lwm_form_open($link, $token, 'templates', 'install', ' id="lwm-tpl-form"');
    echo '<table class="table table-striped"><thead><tr><th style="width:30px;"><input type="checkbox" onclick="var c=this.checked;document.querySelectorAll(\'.lwm-cb\').forEach(function(x){x.checked=c});"></th>'
        . '<th>Modèle WHMCS</th><th>Sujet actuel</th><th>Signature</th><th>Français</th><th>Anglais</th><th>Sauvegarde</th><th></th></tr></thead><tbody>';
    foreach ($status as $name => $st) {
        $badge = function ($exists, $installed) {
            return !$exists ? '<span class="lwm-badge lwm-off">Absent</span>'
                : ($installed ? '<span class="lwm-badge lwm-ok">LEWEBMAX</span>' : '<span class="lwm-badge lwm-orig">Origine</span>');
        };
        $fr = $badge($st['exists'], $st['installed']) . ($st['disabled'] ? ' <span class="lwm-badge lwm-off">Désactivé</span>' : '');
        echo '<tr><td><input class="lwm-cb" type="checkbox" name="templates[]" value="' . lwm_h($name) . '"' . ($st['exists'] ? '' : ' disabled') . '></td>'
            . '<td><strong>' . lwm_h($name) . '</strong></td><td>' . lwm_h($st['subject']) . '</td>'
            . '<td>' . lwm_h($services[$st['tpl']['service']] ?? '') . '</td>'
            . '<td>' . $fr . '</td><td>' . $badge($st['en_exists'], $st['en_installed']) . '</td>'
            . '<td>' . ($st['backup'] ? 'Oui' : '&mdash;') . '</td>'
            . '<td style="white-space:nowrap;"><a href="' . lwm_h($link . '&tab=templates&edit=' . rawurlencode($name)) . '">Modifier</a> &middot; '
            . '<a href="' . lwm_h($link . '&tab=preview&template=' . rawurlencode($name)) . '">Aperçu</a></td></tr>';
    }
    echo '</tbody></table>';
    echo '<button type="submit" class="btn btn-success">Installer / mettre à jour la sélection</button> '
        . '<button type="submit" class="btn btn-default" onclick="this.form.lwm_action.value=\'restore_templates\';return confirm(\'Remettre la sélection dans sa version d\\\'origine ?\');">Restaurer la sélection</button>';
    echo '<p class="lwm-help" style="margin-top:10px;">Sans sélection, le bouton « Installer » traite les ' . count($status) . ' modèles.</p></form>';

    $others = lwm_other_templates();
    $auto = 0;
    foreach ($others as $row) {
        if (strpos($row->message, 'data-lwm="') !== false) {
            $auto++;
        }
    }
    echo '<hr><h3>Autres modèles WHMCS</h3>'
        . '<p>' . count($others) . ' autres modèles clients (français et anglais) n\'ont pas de version dédiée (transferts, SSL, devis, remboursements…). '
        . 'Ils utilisent déjà l\'en-tête et le pied de page du thème&nbsp;; l\'harmonisation applique en plus la typographie et les couleurs LEWEBMAX à leur texte, sans le modifier. '
        . '<strong>' . $auto . ' / ' . count($others) . '</strong> sont harmonisés.</p>'
        . '<p class="lwm-help">Les notifications internes envoyées aux administrateurs (« WHMCS User Details Change »…) ont une mise en page imposée par WHMCS et ne peuvent pas être modifiées.</p>'
        . lwm_form_open($link, $token, 'templates', 'harmonize', ' style="display:inline-block;margin-right:8px;"')
        . '<button class="btn btn-success">Harmoniser les autres modèles</button></form>'
        . lwm_form_open($link, $token, 'templates', 'restore_others', ' style="display:inline-block;" onsubmit="return confirm(\'Remettre les autres modèles dans leur version d\\\'origine ?\');"')
        . '<button class="btn btn-default">Restaurer les autres modèles</button></form>';
}

function lwm_tab_preview($link, $token)
{
    $themes = lwm_themes();
    $templates = lwm_templates();
    $slug = isset($_GET['theme']) && isset($themes[$_GET['theme']]) ? $_GET['theme'] : (lwm_active_theme() ?: 'classique');
    $tpl = isset($_GET['template']) && isset($templates[$_GET['template']]) ? $_GET['template'] : 'Invoice Created';
    $width = isset($_GET['width']) && $_GET['width'] === 'mobile' ? 'mobile' : 'desktop';
    $lang = isset($_GET['lang']) && $_GET['lang'] === 'en' ? 'en' : 'fr';
    $incident = !empty($_GET['incident']);
    $days = isset($_GET['days']) ? (int) $_GET['days'] : 3;
    list($subject, $html) = lwm_render_preview($slug, $tpl, null, $lang, ['incident' => $incident, 'days' => $days]);

    $sel = function ($name, array $options, $current) {
        $o = '<select name="' . $name . '" class="form-control">';
        foreach ($options as $k => $v) {
            $o .= '<option value="' . lwm_h($k) . '"' . ((string) $k === (string) $current ? ' selected' : '') . '>' . lwm_h($v) . '</option>';
        }
        return $o . '</select> ';
    };
    echo '<form method="get" class="form-inline" style="margin-bottom:16px;">'
        . '<input type="hidden" name="module" value="lewebmax_emails"><input type="hidden" name="tab" value="preview">'
        . $sel('theme', array_map(function ($t) { return $t['nom']; }, $themes), $slug)
        . $sel('template', array_combine(array_keys($templates), array_keys($templates)), $tpl)
        . $sel('lang', ['fr' => 'Français', 'en' => 'Anglais'], $lang)
        . $sel('width', ['desktop' => 'Ordinateur', 'mobile' => 'Mobile'], $width)
        . $sel('days', [7 => 'Échéance J-7', 3 => 'Échéance J-3', 0 => 'Échéance aujourd\'hui', -5 => 'Retard de 5 jours'], $days)
        . '<label style="margin:0 10px;font-weight:normal;"><input type="checkbox" name="incident" value="1"' . ($incident ? ' checked' : '') . '> Simuler un incident</label>'
        . '<button class="btn btn-default">Afficher</button></form>';

    echo '<div class="row"><div class="col-md-8"><p><strong>Sujet :</strong> ' . lwm_h($subject) . ' <span class="lwm-help">(données fictives)</span></p>'
        . '<div class="lwm-preview-wrap"><iframe srcdoc="' . lwm_h($html) . '" style="width:' . ($width === 'mobile' ? '380px' : '100%') . ';height:900px;"></iframe></div></div>';

    $s = lwm_settings();
    $to = $s['test_email'] ?: lwm_admin_email();
    echo '<div class="col-md-4"><div class="panel panel-default"><div class="panel-heading"><strong>Envoyer un e-mail de test</strong></div><div class="panel-body">'
        . lwm_form_open($link, $token, 'preview', 'test')
        . '<input type="hidden" name="theme" value="' . lwm_h($slug) . '"><input type="hidden" name="template" value="' . lwm_h($tpl) . '">'
        . '<input type="hidden" name="lang" value="' . lwm_h($lang) . '"><input type="hidden" name="days" value="' . (int) $days . '">'
        . ($incident ? '<input type="hidden" name="incident" value="1">' : '')
        . '<div class="form-group"><label>Destinataire</label><input class="form-control" type="email" name="to" value="' . lwm_h($to) . '" required></div>'
        . '<p class="lwm-help">Envoie l\'aperçu ci-dessus (thème ' . lwm_h($themes[$slug]['nom']) . ', ' . ($lang === 'en' ? 'anglais' : 'français') . ', données fictives) avec la configuration d\'envoi de WHMCS. Le sujet commence par [TEST].</p>'
        . '<button type="submit" class="btn btn-primary btn-block">Envoyer le test</button></form>'
        . '</div></div>'
        . '<div class="panel panel-default"><div class="panel-body lwm-help">Le badge d\'échéance et le bandeau d\'incident sont calculés au moment de l\'envoi réel. '
        . 'Le rendu varie selon la messagerie&nbsp;: Gmail ignore le mode nuit personnalisé et applique sa propre inversion des couleurs&nbsp;; Outlook (Windows) affiche des coins carrés.</div></div>'
        . '</div></div>';
}

function lwm_tab_backups($link, $token)
{
    $json = json_encode(lwm_export_config(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo '<div class="row"><div class="col-md-6"><div class="panel panel-default"><div class="panel-heading"><strong>Exporter la configuration</strong></div><div class="panel-body">'
        . '<p>Paramètres, sujets personnalisés et thème actif, dans un fichier JSON. Utile pour sauvegarder vos réglages ou les copier sur un autre WHMCS.</p>'
        . '<a class="btn btn-primary" download="lwb-emails-' . date('Y-m-d') . '.json" href="data:application/json;charset=utf-8;base64,' . base64_encode($json) . '">Télécharger le fichier</a>'
        . '</div></div></div>';
    echo '<div class="col-md-6"><div class="panel panel-default"><div class="panel-heading"><strong>Importer une configuration</strong></div><div class="panel-body">'
        . lwm_form_open($link, $token, 'backups', 'import', ' enctype="multipart/form-data" onsubmit="return confirm(\'Remplacer les paramètres actuels par ceux du fichier ?\');"')
        . '<div class="form-group"><input type="file" name="config" accept=".json,application/json"></div>'
        . '<div class="form-group"><textarea class="form-control" name="config_text" rows="3" placeholder="… ou collez ici le contenu du fichier JSON"></textarea></div>'
        . '<div class="checkbox"><label><input type="checkbox" name="apply_theme" value="1" checked> Appliquer aussi le thème du fichier</label></div>'
        . '<button class="btn btn-default">Importer</button></form>'
        . '</div></div></div></div>';

    $rows = Capsule::table(LWM_BACKUP_TABLE)->orderBy('kind')->orderBy('name')->get();
    echo '<h3>Sauvegardes des e-mails d\'origine</h3><p>Avant toute modification, le module sauvegarde la version d\'origine. Restaurer supprime la sauvegarde correspondante.</p>';
    if (count($rows) === 0) {
        echo lwm_alert('info', 'Aucune sauvegarde : les e-mails sont dans leur état d\'origine.');
        return;
    }
    echo '<table class="table table-striped"><thead><tr><th>Élément</th><th>Type</th><th>Langue</th><th>Sauvegardé le</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr><td>' . lwm_h($r->name) . '</td><td>' . ($r->kind === 'setting' ? 'Réglage global' : 'Modèle') . '</td><td>' . lwm_h($r->language ?: 'par défaut') . '</td><td>' . lwm_h($r->created_at) . '</td></tr>';
    }
    echo '</tbody></table>';
    echo lwm_form_open($link, $token, 'backups', 'restore_theme', ' style="display:inline-block;margin-right:8px;" onsubmit="return confirm(\'Restaurer l\\\'en-tête, le pied et le CSS d\\\'origine ?\');"')
        . '<button class="btn btn-default">Restaurer l\'en-tête et le pied d\'origine</button></form>';
    echo lwm_form_open($link, $token, 'backups', 'restore_templates', ' style="display:inline-block;margin-right:8px;" onsubmit="return confirm(\'Restaurer tous les modèles d\\\'origine ?\');"')
        . '<button class="btn btn-default">Restaurer tous les modèles</button></form>';
    echo lwm_form_open($link, $token, 'backups', 'restore_all', ' style="display:inline-block;" onsubmit="return confirm(\'Tout remettre comme avant le module ?\');"')
        . '<button class="btn btn-danger">Tout restaurer</button></form>';
}
