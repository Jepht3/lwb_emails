# Templates e-mail LEWEBMAX

5 thèmes d'e-mails HTML pro et responsive, à choisir en un clic dans WHMCS, aux couleurs de [lewebmax.com](https://lewebmax.com) et de l'espace client [my.lewebmax.com](https://my.lewebmax.com) (WHMCS).

## Charte utilisée

Elle est reprise du front-end de lewebmax.com.

| Élément | Valeur |
|---|---|
| Bleu principal (boutons, liens) | `#030673` |
| Texte titres | `#0d0d2b` |
| Texte courant | `#575b78` |
| Accent orange (étiquettes, alertes) | `#ff6a3d` |
| Bordures | `#e2e5f2` |
| Fond | `#f4f5fb` |
| Police | Plus Jakarta Sans, avec Arial en secours |
| Logo | `https://lewebmax.com/wp-content/uploads/2023/01/cropped-cropped-288x80-noir-1.png` |

Le dossier `assets/` contient une copie des logos : bleu, blanc (pour fond sombre) et icône.

## Les 5 thèmes

| Thème | Style |
|---|---|
| **Classique** | En-tête blanc avec logo, liseré bleu et orange, carte blanche sur fond gris clair. Fidèle au site. |
| **Prestige** | Bandeau et pied de page bleu nuit, logo dans un cartouche blanc, bouton WhatsApp orange. Fidèle à l'espace client. |
| **Épuré** | Fond tout blanc, logo centré, filets fins. Sobre et élégant. |
| **Moderne** | Grand bandeau en dégradé bleu avec accroche, pied de page sombre en trois colonnes. |
| **Corporate** | Style lettre officielle : filet bleu, coordonnées en en-tête, contacts en deux colonnes. |

Les boutons, tableaux d'informations, étiquettes et encadrés d'alerte sont identiques dans les 5 thèmes. Seuls l'en-tête, le pied de page et le cadre changent.

## Fonctionnalités du module (v2)

Le module ajoute la page **Modules complémentaires > LEWEBMAX E-mails**, organisée en 5 onglets :

| Onglet | Rôle |
|---|---|
| **Thèmes** | Aperçu des 5 designs avec vos paramètres ; application en un clic. |
| **Personnalisation** | Logos jour (bleu) et nuit (blanc), site, espace client, e-mail, téléphone, WhatsApp, adresse, cPanel, réseaux sociaux (Facebook, Instagram, WhatsApp, LinkedIn, X, TikTok, YouTube, Telegram), style des icônes, textes (moyens de paiement, mention légale, accroche), bannière promotionnelle, mode nuit. **À l'enregistrement, le thème actif et les modèles installés sont régénérés automatiquement.** |
| **Modèles** | État des 12 modèles (LEWEBMAX ou d'origine) ; installation ou restauration sélective. |
| **Aperçu & test** | N'importe quel modèle dans n'importe quel thème, en version ordinateur ou mobile ; envoi d'un e-mail de test via la configuration d'envoi de WHMCS. |
| **Sauvegardes** | Liste des éléments sauvegardés ; restauration de l'en-tête et du pied, des modèles, ou de tout. |

**Icônes des réseaux sociaux** : PNG en 3 jeux, dans `assets/icons/` : `bleu` (fond clair), `blanc` (fond sombre) et `couleur` (couleurs officielles). Les clients e-mail n'affichent ni SVG ni polices d'icônes. Les PNG doivent être publiés dans un dossier public, par défaut `https://lewebmax.com/wp-content/uploads/lwm-email/icons/`, réglable dans Personnalisation.

**Mode nuit** : sur les thèmes clairs, le logo bleu est remplacé par le logo blanc et les couleurs passent en sombre dans Apple Mail, iOS et Outlook. Gmail ignore ce réglage et applique sa propre inversion. Les thèmes Prestige et Moderne affichent directement le logo blanc sur le bandeau bleu.

## Contenu

```
whmcs/modules/addons/lewebmax_emails/   Module WHMCS (à copier tel quel sur le serveur)
  lewebmax_emails.php                   Point d'entrée du module
  lib/settings.php                      Paramètres modifiables (valeurs par défaut, validation)
  lib/render.php                        Construction des thèmes et modèles, aperçus
  lib/actions.php                       Thème, modèles, sauvegardes, e-mail de test
  lib/admin.php                         Interface d'administration (onglets)
  themes/<thème>/                       email.css, header.html, footer.html (avec des %%paramètres%%)
  themes.json                           Liste et description des thèmes
  templates/                            Corps des 12 e-mails (communs aux 5 thèmes)
  templates.json                        Correspondance fichier ↔ template WHMCS ↔ sujet
  assets/icons/                         Icônes des réseaux sociaux (PNG)
dist/lewebmax_emails.zip                Le module zippé, prêt à téléverser
standalone/annonce.html                 E-mail complet et autonome (promo, annonce, newsletter)
previews/                               Aperçus avec données fictives : ouvrez previews/index.html
tools/build-previews.php                Regénère les aperçus (php tools/build-previews.php)
assets/                                 Logos (bleu, blanc, icône)
```

## Installation dans WHMCS (avec le module, recommandé)

1. Téléversez le dossier `lewebmax_emails` (ou décompressez `dist/lewebmax_emails.zip`) dans le dossier `modules/addons/` de votre WHMCS.
2. Copiez le contenu de `assets/icons/` dans un dossier public (par défaut `wp-content/uploads/lwm-email/icons/` sur lewebmax.com).
3. Dans l'administration : **Configuration > Modules complémentaires** (Addon Modules), activez **LEWEBMAX E-mails** et donnez l'accès à votre rôle administrateur.
4. Ouvrez **Modules complémentaires > LEWEBMAX E-mails** : vérifiez l'onglet Personnalisation, choisissez un thème, installez les modèles, puis envoyez-vous un e-mail de test.

Le module enregistre l'en-tête, le pied de page et le CSS globaux de WHMCS (`EmailGlobalHeader`, `EmailGlobalFooter`, `EmailCSS`), encodés comme le fait le formulaire de WHMCS. Seule la version par défaut (français) des modèles est remplacée ; les traductions restent intactes.

Les modèles utilisent les variables de my.lewebmax.com : `{$reset_password_url}`, `{$domain_renewal_url}`, `{$ticket_link}` et le lien Mobile Money `{$flexpay_pay_link}` du hook FlexPay. Attention : dans WHMCS, `{$invoice_link}` et `{$ticket_link}` sont des liens HTML complets ; ils ne doivent jamais être placés dans un `href`. Les boutons de facture utilisent `viewinvoice.php?id={$invoice_id}`.

## Installation manuelle (sans module)

1. **Configuration > Paramètres généraux > onglet Courriel** : collez le `email.css`, le `header.html` et le `footer.html` du thème choisi dans « Style CSS des e-mails », « Contenu de l'en-tête des e-mails clients » et « Contenu du pied de page des e-mails clients ».
2. **Configuration > Templates d'e-mails** : pour chaque modèle ci-dessous, passez l'éditeur en mode code source, collez le fichier correspondant et mettez le sujet indiqué.

| Fichier | Template WHMCS | Sujet |
|---|---|---|
| `bienvenue-client.html` | Client Signup Email | Bienvenue chez LEWEBMAX, {$client_first_name} ! |
| `reinitialisation-mot-de-passe.html` | Password Reset Validation | Réinitialisez votre mot de passe LEWEBMAX |
| `facture-creee.html` | Invoice Created | Facture n°{$invoice_num} disponible |
| `rappel-paiement.html` | Invoice Payment Reminder | Rappel : facture n°{$invoice_num} à régler |
| `facture-en-retard.html` | First Invoice Overdue Notice | Facture n°{$invoice_num} en retard de paiement |
| `paiement-confirme.html` | Invoice Payment Confirmation | Paiement reçu — merci {$client_first_name} ! |
| `bienvenue-hebergement.html` | Hosting Account Welcome | Votre hébergement {$service_product_name} est actif |
| `domaine-enregistre.html` | Domain Registration Confirmation | Votre domaine {$domain_name} est enregistré |
| `renouvellement-domaine.html` | Upcoming Domain Renewal Notice | {$domain_name} expire dans {$domain_days_until_expiry} jours |
| `service-suspendu.html` | Service Suspension Notification | Service suspendu : {$service_domain} |
| `ticket-ouvert.html` | Support Ticket Opened | [Ticket #{$ticket_id}] {$ticket_subject} |
| `ticket-reponse.html` | Support Ticket Reply | [Ticket #{$ticket_id}] {$ticket_subject} |

> Les sujets reprennent ceux déjà configurés dans WHMCS. Les liens fixes pointent vers `https://my.lewebmax.com/` et l'accès cPanel vers `https://server.lewebmax.com:2083/`.

## E-mail autonome

`standalone/annonce.html` est un e-mail complet. Remplacez les textes entre `[crochets]` (et l'image d'illustration), puis collez le code dans votre outil d'envoi : Brevo, Mailchimp, Thunderbird, etc. Pensez à ajouter un lien de désinscription pour les envois marketing.

## Modifier et prévisualiser

Les blocs se répètent d'un modèle à l'autre (étiquette orange, titre, tableau d'informations, bouton). Pour en créer un nouveau, copiez un fichier existant et adaptez le texte.

```bash
php tools/build-previews.php   # puis ouvrez previews/index.html
```

Pour ajouter un modèle, déclarez-le dans `templates.json` du module : il sera alors installé par le module et inclus dans les aperçus. Pensez à refaire le ZIP (`cd whmcs/modules/addons && zip -r ../../../dist/lewebmax_emails.zip lewebmax_emails`).
