<?php
/**
 * LEWEBMAX E-mails — module d'administration WHMCS
 *
 * Permet de choisir l'un des 5 thèmes d'e-mail LEWEBMAX (en-tête, pied de page, CSS)
 * et d'installer les 12 modèles d'e-mails en français, avec sauvegarde et restauration
 * des contenus d'origine.
 *
 * Installation : copier le dossier dans /modules/addons/ de WHMCS, puis
 * Configuration > Modules complémentaires > LEWEBMAX E-mails > Activer.
 */

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('Accès direct interdit.');
}

const LWM_BACKUP_TABLE = 'mod_lewebmax_emails_backup';
const LWM_SETTINGS = ['EmailCSS', 'EmailGlobalHeader', 'EmailGlobalFooter'];
// Seule la version par défaut (langue du système) est remplacée ; les traductions restent intactes.
const LWM_TEMPLATE_LANGUAGES = [''];

function lewebmax_emails_config()
{
    return [
        'name' => 'LEWEBMAX E-mails',
        'description' => 'Thèmes d\'e-mail aux couleurs de LEWEBMAX : choisissez le design de vos e-mails clients et installez les modèles en français.',
        'version' => '1.0',
        'author' => 'LEWEBMAX',
        'fields' => [],
    ];
}

function lewebmax_emails_activate()
{
    try {
        if (!Capsule::schema()->hasTable(LWM_BACKUP_TABLE)) {
            Capsule::schema()->create(LWM_BACKUP_TABLE, function ($table) {
                $table->increments('id');
                $table->string('kind', 16);
                $table->string('name', 191);
                $table->string('language', 32)->default('');
                $table->text('subject')->nullable();
                $table->longText('value')->nullable();
                $table->boolean('plaintext')->default(false);
                $table->timestamp('created_at')->nullable();
            });
        }
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

function lwm_path($relative)
{
    return __DIR__ . '/' . $relative;
}

function lwm_json($file)
{
    $data = json_decode(file_get_contents(lwm_path($file)), true);
    return is_array($data) ? $data : [];
}

function lwm_strip_doc_comment($html)
{
    return preg_replace('/^\s*<!--.*?-->\s*/s', '', $html, 1);
}

function lwm_theme_files($slug)
{
    $dir = lwm_path('themes/' . $slug);
    return [
        'EmailCSS' => file_get_contents($dir . '/email.css'),
        'EmailGlobalHeader' => lwm_strip_doc_comment(file_get_contents($dir . '/header.html')),
        'EmailGlobalFooter' => lwm_strip_doc_comment(file_get_contents($dir . '/footer.html')),
    ];
}

/**
 * WHMCS enregistre l'en-tête, le pied et le CSS des e-mails encodés en entités HTML
 * (comme le fait le formulaire Paramètres généraux > Courriel) ; on fait de même.
 */
function lwm_get_setting($key)
{
    return (string) Capsule::table('tblconfiguration')->where('setting', $key)->value('value');
}

function lwm_set_setting($key, $value, $encode = true)
{
    $stored = $encode ? htmlspecialchars($value, ENT_COMPAT | ENT_HTML401, 'UTF-8') : $value;
    if (Capsule::table('tblconfiguration')->where('setting', $key)->exists()) {
        Capsule::table('tblconfiguration')->where('setting', $key)->update(['value' => $stored]);
    } else {
        Capsule::table('tblconfiguration')->insert(['setting' => $key, 'value' => $stored]);
    }
}

function lwm_has_backup($kind, $name = null, $language = '')
{
    $q = Capsule::table(LWM_BACKUP_TABLE)->where('kind', $kind);
    if ($name !== null) {
        $q->where('name', $name)->where('language', $language);
    }
    return $q->exists();
}

function lwm_apply_theme($slug)
{
    if (!lwm_has_backup('setting')) {
        foreach (LWM_SETTINGS as $key) {
            Capsule::table(LWM_BACKUP_TABLE)->insert([
                'kind' => 'setting',
                'name' => $key,
                'value' => lwm_get_setting($key),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
    foreach (lwm_theme_files($slug) as $key => $value) {
        lwm_set_setting($key, $value);
    }
    lwm_set_setting('LewebmaxEmailTheme', $slug, false);
}

function lwm_install_templates()
{
    $updated = [];
    $missing = [];
    foreach (lwm_json('templates.json') as $tpl) {
        $message = lwm_strip_doc_comment(file_get_contents(lwm_path('templates/' . $tpl['fichier'])));
        $rows = Capsule::table('tblemailtemplates')
            ->where('name', $tpl['template_whmcs'])
            ->whereIn('language', LWM_TEMPLATE_LANGUAGES)
            ->get();
        if (count($rows) === 0) {
            $missing[] = $tpl['template_whmcs'];
            continue;
        }
        foreach ($rows as $row) {
            if (!lwm_has_backup('template', $row->name, $row->language)) {
                Capsule::table(LWM_BACKUP_TABLE)->insert([
                    'kind' => 'template',
                    'name' => $row->name,
                    'language' => $row->language,
                    'subject' => $row->subject,
                    'value' => $row->message,
                    'plaintext' => (int) $row->plaintext,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
            Capsule::table('tblemailtemplates')->where('id', $row->id)->update([
                'subject' => $tpl['sujet'],
                'message' => $message,
                'plaintext' => 0,
            ]);
        }
        $updated[] = $tpl['template_whmcs'];
    }
    return [$updated, $missing];
}

function lwm_restore()
{
    foreach (Capsule::table(LWM_BACKUP_TABLE)->get() as $b) {
        if ($b->kind === 'setting') {
            // Valeur sauvegardée telle qu'elle était stockée : on la remet sans ré-encoder.
            lwm_set_setting($b->name, (string) $b->value, false);
        } elseif ($b->kind === 'template') {
            Capsule::table('tblemailtemplates')
                ->where('name', $b->name)
                ->where('language', $b->language)
                ->update(['subject' => $b->subject, 'message' => $b->value, 'plaintext' => (int) $b->plaintext]);
        }
    }
    Capsule::table(LWM_BACKUP_TABLE)->delete();
    lwm_set_setting('LewebmaxEmailTheme', '', false);
}

/**
 * Interprète le strict nécessaire de Smarty pour l'aperçu : {$var}, {if $var}…{else}…{/if}
 * et {if isset($var)}…{/if} (sans imbrication).
 */
function lwm_render_sample($html, array $sample)
{
    $html = preg_replace_callback(
        '/\{if (?:isset\()?\$([a-z0-9_]+)\)?\}(.*?)(?:\{else\}(.*?))?\{\/if\}/s',
        function ($m) use ($sample) {
            return !empty($sample[$m[1]]) ? $m[2] : (isset($m[3]) ? $m[3] : '');
        },
        $html
    );
    return preg_replace_callback('/\{\$([a-z0-9_]+)\}/', function ($m) use ($sample) {
        return isset($sample[$m[1]]) ? $sample[$m[1]] : '';
    }, $html);
}

function lwm_sample_preview($slug)
{
    $sample = [
        'client_first_name' => 'Jephté',
        'company_name' => 'LE WEB MAX',
        'invoice_num' => '2026-1042',
        'invoice_date_created' => '09/10/2026',
        'invoice_date_due' => '16/10/2026',
        'invoice_total' => '29,88 $ USD',
        'invoice_link' => '#',
        'flexpay_pay_link' => '#',
    ];
    $files = lwm_theme_files($slug);
    $body = lwm_strip_doc_comment(file_get_contents(lwm_path('templates/facture-creee.html')));
    $html = str_replace('[EmailCSS]', $files['EmailCSS'], $files['EmailGlobalHeader']) . $body . $files['EmailGlobalFooter'];
    return lwm_render_sample($html, $sample);
}

function lewebmax_emails_output($vars)
{
    $link = $vars['modulelink'];
    $themes = lwm_json('themes.json');
    $slugs = array_column($themes, 'slug');
    $notice = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lwm_action'])) {
        check_token('WHMCS.admin.default');
        try {
            $action = $_POST['lwm_action'];
            if ($action === 'theme' && in_array($_POST['theme'] ?? '', $slugs, true)) {
                lwm_apply_theme($_POST['theme']);
                $notice = '<div class="alert alert-success">Thème appliqué. Envoyez-vous un e-mail test pour vérifier le rendu.</div>';
            } elseif ($action === 'templates') {
                list($updated, $missing) = lwm_install_templates();
                $notice = '<div class="alert alert-success">' . count($updated) . ' modèle(s) mis à jour.</div>';
                if ($missing) {
                    $notice .= '<div class="alert alert-warning">Introuvables dans WHMCS : ' . htmlspecialchars(implode(', ', $missing)) . '</div>';
                }
            } elseif ($action === 'restore') {
                lwm_restore();
                $notice = '<div class="alert alert-info">Les contenus d\'origine ont été restaurés.</div>';
            }
        } catch (\Exception $e) {
            $notice = '<div class="alert alert-danger">Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }

    $current = lwm_get_setting('LewebmaxEmailTheme');
    $token = generate_token('form');

    echo $notice;
    echo '<p>Choisissez le design appliqué à <strong>tous les e-mails clients</strong> (en-tête, pied de page et CSS globaux). '
        . 'Les réglages d\'origine sont sauvegardés au premier changement.</p>';

    echo '<div class="row">';
    foreach ($themes as $t) {
        $active = $current === $t['slug'];
        echo '<div class="col-md-4 col-sm-6" style="margin-bottom:24px;">'
            . '<div class="panel ' . ($active ? 'panel-primary' : 'panel-default') . '">'
            . '<div class="panel-heading"><strong>' . htmlspecialchars($t['nom']) . '</strong>' . ($active ? ' &mdash; actif' : '') . '</div>'
            . '<iframe srcdoc="' . htmlspecialchars(lwm_sample_preview($t['slug'])) . '" style="width:100%;height:420px;border:0;display:block;background:#fff;"></iframe>'
            . '<div class="panel-body"><p style="min-height:60px;">' . htmlspecialchars($t['description']) . '</p>'
            . '<form method="post" action="' . $link . '">' . $token
            . '<input type="hidden" name="lwm_action" value="theme">'
            . '<input type="hidden" name="theme" value="' . htmlspecialchars($t['slug']) . '">'
            . '<button type="submit" class="btn ' . ($active ? 'btn-default' : 'btn-primary') . ' btn-block"' . ($active ? ' disabled' : '') . '>'
            . ($active ? 'Thème actif' : 'Utiliser ce thème') . '</button></form></div></div></div>';
    }
    echo '</div>';

    echo '<hr><h3>Modèles d\'e-mails en français</h3>'
        . '<p>Remplace le sujet et le contenu de 12 modèles WHMCS (bienvenue, factures, hébergement, domaines, suspension, tickets) '
        . 'par les versions LEWEBMAX. Les versions actuelles sont sauvegardées.</p>'
        . '<form method="post" action="' . $link . '" style="display:inline-block;margin-right:8px;">' . $token
        . '<input type="hidden" name="lwm_action" value="templates">'
        . '<button type="submit" class="btn btn-success" onclick="return confirm(\'Remplacer les 12 modèles d\\\'e-mails ?\');">Installer les 12 modèles</button></form>'
        . '<form method="post" action="' . $link . '" style="display:inline-block;">' . $token
        . '<input type="hidden" name="lwm_action" value="restore">'
        . '<button type="submit" class="btn btn-default" onclick="return confirm(\'Restaurer les e-mails d\\\'origine ?\');">Restaurer les e-mails d\'origine</button></form>';
}
