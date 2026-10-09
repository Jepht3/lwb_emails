<?php
/**
 * LEWEBMAX E-mails — actions : thème, modèles, sauvegardes, e-mails de test.
 */

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('Accès direct interdit.');
}

const LWM_BACKUP_TABLE = 'mod_lewebmax_emails_backup';
const LWM_SETTINGS_KEYS = ['EmailCSS', 'EmailGlobalHeader', 'EmailGlobalFooter'];
// Seule la version par défaut (langue du système) est remplacée ; les traductions restent intactes.
const LWM_TEMPLATE_LANGUAGES = [''];

function lwm_create_backup_table()
{
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

function lwm_active_theme()
{
    return lwm_get_setting('LewebmaxEmailTheme');
}

function lwm_apply_theme($slug)
{
    $files = lwm_build_theme($slug);
    if (!lwm_has_backup('setting')) {
        foreach (LWM_SETTINGS_KEYS as $key) {
            Capsule::table(LWM_BACKUP_TABLE)->insert([
                'kind' => 'setting',
                'name' => $key,
                'value' => lwm_get_setting($key),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
    foreach ($files as $key => $value) {
        lwm_set_setting($key, $value);
    }
    lwm_set_setting('LewebmaxEmailTheme', $slug, false);
}

/** État des 12 modèles : installé (LEWEBMAX), d'origine, absent ; sauvegarde disponible. */
function lwm_templates_status()
{
    $status = [];
    foreach (lwm_templates() as $name => $tpl) {
        $row = Capsule::table('tblemailtemplates')->where('name', $name)->where('language', '')->first();
        $status[$name] = [
            'tpl' => $tpl,
            'exists' => (bool) $row,
            'installed' => $row && strpos($row->message, 'data-lwm="') !== false,
            'disabled' => $row && $row->disabled,
            'backup' => lwm_has_backup('template', $name, ''),
            'subject' => $row ? $row->subject : '',
        ];
    }
    return $status;
}

/** Installe (ou met à jour) les modèles demandés ; sauvegarde la version d'origine la première fois. */
function lwm_install_templates(?array $names = null)
{
    $all = lwm_templates();
    $names = $names === null ? array_keys($all) : array_values(array_intersect($names, array_keys($all)));
    $updated = [];
    $missing = [];
    foreach ($names as $name) {
        $tpl = $all[$name];
        $message = lwm_build_body($tpl['fichier']);
        $rows = Capsule::table('tblemailtemplates')
            ->where('name', $name)
            ->whereIn('language', LWM_TEMPLATE_LANGUAGES)
            ->get();
        if (count($rows) === 0) {
            $missing[] = $name;
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
        $updated[] = $name;
    }
    return [$updated, $missing];
}

/** Remet la version d'origine des modèles demandés (null = tous). */
function lwm_restore_templates(?array $names = null)
{
    $q = Capsule::table(LWM_BACKUP_TABLE)->where('kind', 'template');
    if ($names !== null) {
        $q->whereIn('name', $names);
    }
    $restored = [];
    foreach ($q->get() as $b) {
        Capsule::table('tblemailtemplates')
            ->where('name', $b->name)
            ->where('language', $b->language)
            ->update(['subject' => $b->subject, 'message' => $b->value, 'plaintext' => (int) $b->plaintext]);
        Capsule::table(LWM_BACKUP_TABLE)->where('id', $b->id)->delete();
        $restored[] = $b->name;
    }
    return $restored;
}

function lwm_restore_theme()
{
    foreach (Capsule::table(LWM_BACKUP_TABLE)->where('kind', 'setting')->get() as $b) {
        // Valeur sauvegardée telle qu'elle était stockée : on la remet sans ré-encoder.
        lwm_set_setting($b->name, (string) $b->value, false);
    }
    Capsule::table(LWM_BACKUP_TABLE)->where('kind', 'setting')->delete();
    lwm_set_setting('LewebmaxEmailTheme', '', false);
}

/** Après une modification des paramètres : régénère le thème actif et les modèles installés. */
function lwm_refresh_installed()
{
    $theme = lwm_active_theme();
    if ($theme !== '' && isset(lwm_themes()[$theme])) {
        lwm_apply_theme($theme);
    }
    $installed = [];
    foreach (lwm_templates_status() as $name => $st) {
        if ($st['installed']) {
            $installed[] = $name;
        }
    }
    if ($installed) {
        lwm_install_templates($installed);
    }
    return [$theme, count($installed)];
}

/** Envoie un e-mail de test (données fictives) avec la configuration d'envoi de WHMCS. */
function lwm_send_test($to, $slug, $templateName)
{
    list($subject, $html) = lwm_render_preview($slug, $templateName);
    $subject = '[TEST] ' . $subject;
    $fromEmail = lwm_get_setting('SystemEmailsFromEmail') ?: lwm_get_setting('Email');
    $fromName = html_entity_decode(lwm_get_setting('SystemEmailsFromName') ?: lwm_get_setting('CompanyName'), ENT_QUOTES, 'UTF-8');

    if (lwm_get_setting('MailType') === 'smtp' && class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = lwm_get_setting('SMTPHost');
        $mail->Port = (int) lwm_get_setting('SMTPPort');
        $secure = lwm_get_setting('SMTPSSL');
        if ($secure) {
            $mail->SMTPSecure = $secure;
        }
        $user = lwm_get_setting('SMTPUsername');
        if ($user !== '') {
            $mail->SMTPAuth = true;
            $mail->Username = $user;
            $mail->Password = function_exists('decrypt') ? decrypt(lwm_get_setting('SMTPPassword')) : '';
        }
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->send();
        return true;
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . mb_encode_mimeheader($fromName, 'UTF-8') . ' <' . $fromEmail . '>',
    ];
    if (!mail($to, mb_encode_mimeheader($subject, 'UTF-8'), $html, implode("\r\n", $headers))) {
        throw new RuntimeException('Le serveur a refusé l’envoi (fonction mail()).');
    }
    return true;
}

/**
 * Harmonisation automatique des autres modèles clients (ceux sans version LEWEBMAX dédiée) :
 * on garde leur texte et on applique la typographie et les couleurs LEWEBMAX
 * aux balises qui n'ont pas déjà un style.
 */
function lwm_harmonize_html($html)
{
    $font = 'font-family:' . LWM_FONT . ';';
    $styles = [
        'p' => 'margin:0 0 16px 0;' . $font . 'font-size:15px;line-height:24px;color:#575b78;',
        'a' => 'color:#030673;font-weight:700;text-decoration:none;',
        'strong' => 'color:#0d0d2b;',
        'b' => 'color:#0d0d2b;',
        'h1' => 'margin:0 0 16px 0;' . $font . 'font-size:24px;line-height:32px;font-weight:800;color:#0d0d2b;',
        'h2' => 'margin:8px 0 12px 0;' . $font . 'font-size:20px;line-height:28px;font-weight:800;color:#0d0d2b;',
        'h3' => 'margin:8px 0 10px 0;' . $font . 'font-size:17px;line-height:24px;font-weight:800;color:#0d0d2b;',
        'ul' => 'margin:0 0 16px 0;padding-left:22px;' . $font . 'font-size:15px;line-height:24px;color:#575b78;',
        'ol' => 'margin:0 0 16px 0;padding-left:22px;' . $font . 'font-size:15px;line-height:24px;color:#575b78;',
        'li' => 'margin:0 0 6px 0;',
    ];
    foreach ($styles as $tag => $style) {
        $html = preg_replace('/<' . $tag . '(?=[\s>])(?![^>]*\sstyle=)([^>]*)>/i', '<' . $tag . '$1 style="' . $style . '">', $html);
    }
    return '<div class="lwm-preheader" data-lwm="auto" style="display:none;max-height:0;overflow:hidden;mso-hide:all;"></div>' . "\n" . $html;
}

/** Modèles clients (version par défaut) qui n'ont pas de version LEWEBMAX dédiée. */
function lwm_other_templates()
{
    return Capsule::table('tblemailtemplates')
        ->where('language', '')
        ->whereNotIn('type', ['admin'])
        ->whereNotIn('name', array_keys(lwm_templates()))
        ->where('plaintext', 0)
        ->orderBy('name')
        ->get();
}

function lwm_harmonize_others()
{
    $done = 0;
    foreach (lwm_other_templates() as $row) {
        if (strpos($row->message, 'data-lwm="') !== false) {
            continue;
        }
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
        Capsule::table('tblemailtemplates')->where('id', $row->id)->update(['message' => lwm_harmonize_html($row->message)]);
        $done++;
    }
    return $done;
}

function lwm_restore_others()
{
    $names = [];
    foreach (lwm_other_templates() as $row) {
        $names[] = $row->name;
    }
    return $names ? lwm_restore_templates($names) : [];
}
