<?php
/**
 * LEWEBMAX E-mails — interface d'administration (onglets).
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
    'backups' => 'Sauvegardes',
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
                lwm_send_test($to, $slug, $tpl);
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

function lwm_tab_templates($link, $token)
{
    $status = lwm_templates_status();
    echo '<p>Cochez les modèles à installer (ou à remettre dans leur version d\'origine). Seule la version française par défaut est modifiée&nbsp;; '
        . 'les traductions (english…) restent intactes. Les sujets sont conservés.</p>';
    echo lwm_form_open($link, $token, 'templates', 'install', ' id="lwm-tpl-form"');
    echo '<table class="table table-striped"><thead><tr><th style="width:30px;"><input type="checkbox" onclick="var c=this.checked;document.querySelectorAll(\'.lwm-cb\').forEach(function(x){x.checked=c});"></th>'
        . '<th>Modèle WHMCS</th><th>Sujet actuel</th><th>État</th><th>Sauvegarde</th><th></th></tr></thead><tbody>';
    foreach ($status as $name => $st) {
        $badge = !$st['exists'] ? '<span class="lwm-badge lwm-off">Introuvable</span>'
            : ($st['installed'] ? '<span class="lwm-badge lwm-ok">LEWEBMAX</span>' : '<span class="lwm-badge lwm-orig">Origine / personnalisé</span>');
        if ($st['disabled']) {
            $badge .= ' <span class="lwm-badge lwm-off">Désactivé dans WHMCS</span>';
        }
        echo '<tr><td><input class="lwm-cb" type="checkbox" name="templates[]" value="' . lwm_h($name) . '"' . ($st['exists'] ? '' : ' disabled') . '></td>'
            . '<td><strong>' . lwm_h($name) . '</strong></td><td>' . lwm_h($st['subject']) . '</td><td>' . $badge . '</td>'
            . '<td>' . ($st['backup'] ? 'Oui' : '&mdash;') . '</td>'
            . '<td><a href="' . lwm_h($link . '&tab=preview&template=' . rawurlencode($name)) . '">Aperçu</a></td></tr>';
    }
    echo '</tbody></table>';
    echo '<button type="submit" class="btn btn-success">Installer / mettre à jour la sélection</button> '
        . '<button type="submit" class="btn btn-default" onclick="this.form.lwm_action.value=\'restore_templates\';return confirm(\'Remettre la sélection dans sa version d\\\'origine ?\');">Restaurer la sélection</button>';
    echo '<p class="lwm-help" style="margin-top:10px;">Sans sélection, le bouton « Installer » traite les 12 modèles.</p></form>';
}

function lwm_tab_preview($link, $token)
{
    $themes = lwm_themes();
    $templates = lwm_templates();
    $slug = isset($_GET['theme']) && isset($themes[$_GET['theme']]) ? $_GET['theme'] : (lwm_active_theme() ?: 'classique');
    $tpl = isset($_GET['template']) && isset($templates[$_GET['template']]) ? $_GET['template'] : 'Invoice Created';
    $width = isset($_GET['width']) && $_GET['width'] === 'mobile' ? 'mobile' : 'desktop';
    list($subject, $html) = lwm_render_preview($slug, $tpl);

    echo '<form method="get" class="form-inline" style="margin-bottom:16px;">'
        . '<input type="hidden" name="module" value="lewebmax_emails"><input type="hidden" name="tab" value="preview">'
        . '<select name="theme" class="form-control">';
    foreach ($themes as $k => $t) {
        echo '<option value="' . lwm_h($k) . '"' . ($k === $slug ? ' selected' : '') . '>' . lwm_h($t['nom']) . '</option>';
    }
    echo '</select> <select name="template" class="form-control">';
    foreach ($templates as $k => $t) {
        echo '<option value="' . lwm_h($k) . '"' . ($k === $tpl ? ' selected' : '') . '>' . lwm_h($k) . '</option>';
    }
    echo '</select> <select name="width" class="form-control"><option value="desktop">Ordinateur</option><option value="mobile"' . ($width === 'mobile' ? ' selected' : '') . '>Mobile</option></select> '
        . '<button class="btn btn-default">Afficher</button></form>';

    echo '<div class="row"><div class="col-md-8"><p><strong>Sujet :</strong> ' . lwm_h($subject) . ' <span class="lwm-help">(données fictives)</span></p>'
        . '<div class="lwm-preview-wrap"><iframe srcdoc="' . lwm_h($html) . '" style="width:' . ($width === 'mobile' ? '380px' : '100%') . ';height:900px;"></iframe></div></div>';

    $s = lwm_settings();
    $to = $s['test_email'] ?: lwm_admin_email();
    echo '<div class="col-md-4"><div class="panel panel-default"><div class="panel-heading"><strong>Envoyer un e-mail de test</strong></div><div class="panel-body">'
        . lwm_form_open($link, $token, 'preview', 'test')
        . '<input type="hidden" name="theme" value="' . lwm_h($slug) . '"><input type="hidden" name="template" value="' . lwm_h($tpl) . '">'
        . '<div class="form-group"><label>Destinataire</label><input class="form-control" type="email" name="to" value="' . lwm_h($to) . '" required></div>'
        . '<p class="lwm-help">Envoie l\'aperçu ci-dessus (thème ' . lwm_h($themes[$slug]['nom']) . ', données fictives) avec la configuration d\'envoi de WHMCS. Le sujet commence par [TEST].</p>'
        . '<button type="submit" class="btn btn-primary btn-block">Envoyer le test</button></form>'
        . '</div></div>'
        . '<div class="panel panel-default"><div class="panel-body lwm-help">Le rendu exact varie selon la messagerie : Gmail ignore le mode nuit personnalisé et applique sa propre inversion des couleurs ; Outlook (Windows) affiche des coins carrés.</div></div>'
        . '</div></div>';
}

function lwm_tab_backups($link, $token)
{
    $rows = Capsule::table(LWM_BACKUP_TABLE)->orderBy('kind')->orderBy('name')->get();
    echo '<p>Avant toute modification, le module sauvegarde la version d\'origine. Restaurer supprime la sauvegarde correspondante.</p>';
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
