<?php
/**
 * Lwb-emails — paramètres modifiables (logos, coordonnées, réseaux, textes).
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

        'tagline_en' => ['Textes en anglais', 'Slogan', 'Web hosting, domains and professional email', 'text', 'Utilisé pour les clients dont la langue est « english ». Vide = texte français.'],
        'payments_en' => ['Textes en anglais', 'Moyens de paiement (court)', 'Pay with Mobile Money, Visa or Mastercard.', 'text', ''],
        'payments_long_en' => ['Textes en anglais', 'Moyens de paiement (détaillé)', 'Mobile Money (M-Pesa, Orange Money, Airtel Money, Afrimoney) or Visa / Mastercard', 'text', ''],
        'offer_line_en' => ['Textes en anglais', 'Phrase d’offre', 'Plans start at $2.49/month.', 'text', ''],
        'legal_en' => ['Textes en anglais', 'Mention légale', 'You are receiving this email because you are a LEWEBMAX client.', 'text', ''],
        'hero_text_en' => ['Textes en anglais', 'Accroche du thème Moderne', 'Fast, secure and', 'text', ''],
        'hero_accent_en' => ['Textes en anglais', 'Fin de l’accroche (en orange)', 'reliable web hosting.', 'text', ''],
        'hero_badge_en' => ['Textes en anglais', 'Badge du thème Moderne', '24/7 support · Mobile Money', 'text', ''],
        'promo_title_en' => ['Textes en anglais', 'Titre de la bannière', 'Current offer', 'text', ''],
        'promo_text_en' => ['Textes en anglais', 'Texte de la bannière', '', 'textarea', ''],
        'promo_button_en' => ['Textes en anglais', 'Bouton de la bannière', 'Get the offer', 'text', ''],

        'sig_enabled' => ['Signatures par service', 'Activer les signatures par service', 'on', 'yesno', 'Facturation pour les factures et domaines, Support pour les tickets et services, Commercial pour le reste.'],
        'sig_facturation_title' => ['Signatures par service', 'Facturation — titre', 'Service facturation', 'text', ''],
        'sig_facturation_title_en' => ['Signatures par service', 'Facturation — titre (anglais)', 'Billing department', 'text', ''],
        'sig_facturation_name' => ['Signatures par service', 'Facturation — nom de l’agent', '', 'text', 'Facultatif.'],
        'sig_facturation_photo' => ['Signatures par service', 'Facturation — photo (URL)', '', 'url', 'Facultatif. Image carrée, affichée en rond.'],
        'sig_support_title' => ['Signatures par service', 'Support — titre', 'Support technique', 'text', ''],
        'sig_support_title_en' => ['Signatures par service', 'Support — titre (anglais)', 'Technical support', 'text', ''],
        'sig_support_name' => ['Signatures par service', 'Support — nom de l’agent', '', 'text', ''],
        'sig_support_photo' => ['Signatures par service', 'Support — photo (URL)', '', 'url', ''],
        'sig_commercial_title' => ['Signatures par service', 'Commercial — titre', 'Service commercial', 'text', ''],
        'sig_commercial_title_en' => ['Signatures par service', 'Commercial — titre (anglais)', 'Sales team', 'text', ''],
        'sig_commercial_name' => ['Signatures par service', 'Commercial — nom de l’agent', '', 'text', ''],
        'sig_commercial_photo' => ['Signatures par service', 'Commercial — photo (URL)', '', 'url', ''],

        'countdown_enabled' => ['Compte à rebours et incidents', 'Badge d’échéance dans les factures', 'on', 'yesno', '« Échéance dans 3 jours », « En retard de 5 jours »… calculé à l’envoi pour les factures impayées.'],
        'incident_enabled' => ['Compte à rebours et incidents', 'Bandeau d’incident (module lwb_statut)', 'on', 'yesno', 'Ajoute un encadré en haut de tous les e-mails clients quand un incident est ouvert.'],
        'incident_maintenance' => ['Compte à rebours et incidents', 'Annoncer aussi les maintenances planifiées', 'on', 'yesno', ''],
        'incident_url' => ['Compte à rebours et incidents', 'Page de statut (URL)', 'https://my.lewebmax.com/index.php?m=lwb_statut', 'url', ''],

        'upsell_enabled' => ['Suggestions de produits', 'Afficher « Complétez votre offre »', 'on', 'yesno', 'Encadré de produits recommandés dans les e-mails d’hébergement activé et de domaine enregistré.'],
        'upsell_title' => ['Suggestions de produits', 'Titre', 'Complétez votre offre', 'text', ''],
        'upsell_title_en' => ['Suggestions de produits', 'Titre (anglais)', 'Complete your setup', 'text', ''],
        'upsell_hosting' => ['Suggestions de produits', 'Produits proposés après un hébergement', '37,53', 'products', 'Numéros (ID) de produits WHMCS, séparés par des virgules. Nom, prix et lien de commande sont repris automatiquement.'],
        'upsell_domain' => ['Suggestions de produits', 'Produits proposés après un domaine', '40,37', 'products', ''],

        'webview_enabled' => ['Version web', 'Lien « Voir dans le navigateur »', 'on', 'yesno', 'Ajouté en haut des e-mails clients. Jamais pour les e-mails qui contiennent un mot de passe ou un lien de réinitialisation.'],
        'webview_days' => ['Version web', 'Durée de validité du lien (jours)', '30', 'days', 'Entre 1 et 365.'],

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
        } elseif ($type === 'days') {
            $v = (string) max(1, min(365, (int) $v ?: (int) $default));
        } elseif ($type === 'products') {
            $ids = array_filter(array_map('intval', preg_split('/[^0-9]+/', $v)));
            $v = implode(',', array_slice(array_unique($ids), 0, 3));
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
