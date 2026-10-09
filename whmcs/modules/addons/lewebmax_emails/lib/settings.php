<?php
/**
 * LEWEBMAX E-mails — paramètres modifiables (logos, coordonnées, réseaux, textes).
 * Stockés dans tbladdonmodules, comme les réglages de tout module WHMCS.
 */

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('Accès direct interdit.');
}

const LWM_MODULE = 'lewebmax_emails';

const LWM_SOCIALS = [
    'facebook' => 'Facebook',
    'instagram' => 'Instagram',
    'whatsapp' => 'WhatsApp',
    'linkedin' => 'LinkedIn',
    'x' => 'X (Twitter)',
    'tiktok' => 'TikTok',
    'youtube' => 'YouTube',
    'telegram' => 'Telegram',
];

/**
 * Définition des champs : clé => [groupe, libellé, défaut, type, aide].
 * Types : text, url, email, number, textarea, yesno, select:<a>|<b>.
 */
function lwm_fields()
{
    return [
        'company' => ['Identité', 'Nom affiché', 'LEWEBMAX', 'text', ''],
        'tagline' => ['Identité', 'Slogan', 'Hébergement web, domaines et e-mails pro', 'text', 'Affiché dans le pied de page.'],
        'logo_light' => ['Identité', 'Logo pour fond clair (bleu)', 'https://lewebmax.com/wp-content/uploads/2023/01/cropped-cropped-288x80-noir-1.png', 'url', 'PNG ou JPG, idéalement 2× la largeur affichée.'],
        'logo_dark' => ['Identité', 'Logo pour fond sombre (blanc)', 'https://lewebmax.com/wp-content/uploads/2023/01/288x80-blanc.png', 'url', 'Utilisé sur les bandeaux bleus et en mode nuit.'],
        'logo_width' => ['Identité', 'Largeur du logo (px)', '160', 'number', 'Entre 100 et 240.'],

        'site_url' => ['Coordonnées', 'Site web', 'https://lewebmax.com', 'url', ''],
        'client_url' => ['Coordonnées', 'Espace client (WHMCS)', 'https://my.lewebmax.com/', 'url', 'Utilisé pour les liens Espace client, tickets, factures.'],
        'email' => ['Coordonnées', 'E-mail de contact', 'info@lewebmax.com', 'email', ''],
        'phone' => ['Coordonnées', 'Téléphone affiché', '+1 567 293 1610', 'text', ''],
        'whatsapp' => ['Coordonnées', 'Numéro WhatsApp', '15672931610', 'text', 'Chiffres uniquement, avec l’indicatif (lien wa.me).'],
        'address' => ['Coordonnées', 'Adresse', 'Kinshasa, RDC', 'text', ''],
        'cpanel_url' => ['Coordonnées', 'Adresse cPanel', 'https://server.lewebmax.com:2083/', 'url', 'Bouton « Ouvrir mon cPanel » de l’e-mail d’hébergement.'],

        'social_facebook' => ['Réseaux sociaux', 'Facebook', 'https://facebook.com/lewebmax', 'url', 'Laisser vide pour masquer l’icône.'],
        'social_instagram' => ['Réseaux sociaux', 'Instagram', 'https://instagram.com/lewebmax', 'url', ''],
        'social_whatsapp' => ['Réseaux sociaux', 'Icône WhatsApp', 'on', 'yesno', 'Utilise le numéro WhatsApp ci-dessus.'],
        'social_linkedin' => ['Réseaux sociaux', 'LinkedIn', '', 'url', ''],
        'social_x' => ['Réseaux sociaux', 'X (Twitter)', '', 'url', ''],
        'social_tiktok' => ['Réseaux sociaux', 'TikTok', '', 'url', ''],
        'social_youtube' => ['Réseaux sociaux', 'YouTube', '', 'url', ''],
        'social_telegram' => ['Réseaux sociaux', 'Telegram', '', 'url', ''],
        'icon_style' => ['Réseaux sociaux', 'Style des icônes', 'marque', 'select:marque|couleur', 'marque = bleu LEWEBMAX (blanc sur fond sombre) ; couleur = couleurs officielles des réseaux.'],
        'icons_base_url' => ['Réseaux sociaux', 'Dossier des icônes (URL)', 'https://lewebmax.com/wp-content/uploads/lwm-email/icons/', 'url', 'Dossier public contenant les PNG du module (assets/icons).'],

        'payments' => ['Textes', 'Moyens de paiement (court)', 'Paiement Mobile Money, Visa ou Mastercard.', 'text', 'Pied de page.'],
        'payments_long' => ['Textes', 'Moyens de paiement (détaillé)', 'Mobile Money (M-Pesa, Orange Money, Airtel Money, Afrimoney) ou carte Visa / Mastercard', 'text', 'E-mail « facture créée ».'],
        'offer_line' => ['Textes', 'Phrase d’offre', 'Nos offres démarrent à 2,49 $/mois.', 'text', 'E-mail « domaine enregistré ».'],
        'legal' => ['Textes', 'Mention légale', 'Vous recevez cet e-mail car vous êtes client LEWEBMAX.', 'text', ''],
        'hero_text' => ['Textes', 'Accroche du thème Moderne', 'Hébergement web rapide, sécurisé et', 'text', ''],
        'hero_accent' => ['Textes', 'Fin de l’accroche (en orange)', 'fiable.', 'text', ''],
        'hero_badge' => ['Textes', 'Badge du thème Moderne', 'Support 24/7 · Mobile Money', 'text', ''],

        'promo_enabled' => ['Bannière promotionnelle', 'Afficher la bannière', '', 'yesno', 'Encadré affiché en bas de tous les e-mails.'],
        'promo_title' => ['Bannière promotionnelle', 'Titre', 'Offre du moment', 'text', ''],
        'promo_text' => ['Bannière promotionnelle', 'Texte', '', 'textarea', ''],
        'promo_url' => ['Bannière promotionnelle', 'Lien du bouton', '', 'url', ''],
        'promo_button' => ['Bannière promotionnelle', 'Texte du bouton', 'En profiter', 'text', ''],

        'dark_mode' => ['Options', 'Optimiser pour le mode nuit', 'on', 'yesno', 'Logo blanc et couleurs sombres dans Apple Mail, iOS et Outlook. Gmail applique sa propre inversion.'],
        'test_email' => ['Options', 'Adresse des e-mails de test', '', 'email', 'Par défaut : l’e-mail de l’administrateur connecté.'],
    ];
}

function lwm_settings($reload = false)
{
    static $cache = null;
    if ($cache !== null && !$reload) {
        return $cache;
    }
    $stored = Capsule::table('tbladdonmodules')->where('module', LWM_MODULE)->pluck('value', 'setting')->all();
    $cache = [];
    foreach (lwm_fields() as $key => $f) {
        $cache[$key] = array_key_exists($key, $stored) ? (string) $stored[$key] : $f[2];
    }
    return $cache;
}

function lwm_set_module_setting($key, $value)
{
    $q = Capsule::table('tbladdonmodules')->where('module', LWM_MODULE)->where('setting', $key);
    if ($q->exists()) {
        $q->update(['value' => $value]);
    } else {
        Capsule::table('tbladdonmodules')->insert(['module' => LWM_MODULE, 'setting' => $key, 'value' => $value]);
    }
}

/**
 * Valide et enregistre les paramètres envoyés par le formulaire.
 * Retourne la liste des erreurs (vide si tout est valide).
 */
function lwm_save_settings(array $input)
{
    $errors = [];
    $clean = [];
    foreach (lwm_fields() as $key => $f) {
        list($group, $label, $default, $type) = $f;
        $v = isset($input[$key]) ? trim((string) $input[$key]) : '';
        if ($type === 'yesno') {
            $v = $v ? 'on' : '';
        } elseif ($type === 'url' && $v !== '') {
            if (!preg_match('#^https?://#i', $v) || filter_var($v, FILTER_VALIDATE_URL) === false) {
                $errors[] = $label . ' : adresse invalide (elle doit commencer par https://).';
                continue;
            }
        } elseif ($type === 'email' && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
            $errors[] = $label . ' : adresse e-mail invalide.';
            continue;
        } elseif ($type === 'number') {
            $v = (string) max(100, min(240, (int) $v ?: (int) $default));
        } elseif (strpos($type, 'select:') === 0) {
            $allowed = explode('|', substr($type, 7));
            $v = in_array($v, $allowed, true) ? $v : $default;
        }
        if ($key === 'whatsapp') {
            $v = preg_replace('/\D+/', '', $v);
        }
        $clean[$key] = $v;
    }
    foreach (['site_url', 'client_url', 'email', 'whatsapp', 'logo_light', 'logo_dark'] as $required) {
        if (isset($clean[$required]) && $clean[$required] === '') {
            $errors[] = lwm_fields()[$required][1] . ' : champ obligatoire.';
        }
    }
    if ($errors) {
        return $errors;
    }
    foreach ($clean as $key => $v) {
        lwm_set_module_setting($key, $v);
    }
    lwm_settings(true);
    return [];
}

function lwm_reset_settings()
{
    Capsule::table('tbladdonmodules')
        ->where('module', LWM_MODULE)
        ->whereIn('setting', array_keys(lwm_fields()))
        ->delete();
    lwm_settings(true);
}
