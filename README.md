# Templates e-mail LEWEBMAX

Des modèles d'e-mails HTML pro et responsive, aux couleurs de [lewebmax.com](https://lewebmax.com) et de l'espace client [my.lewebmax.com](https://my.lewebmax.com) (WHMCS).

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

## Contenu

```
whmcs/
  email-css.css     CSS global (resets + mobile)
  header.html       En-tête commun : logo, liseré bleu/orange, ouverture de la carte
  footer.html       Pied commun : bloc d'aide (WhatsApp, e-mail, ticket) et mentions
  templates/        Corps de chaque e-mail WHMCS (12 modèles)
  templates.json    Correspondance fichier ↔ template WHMCS ↔ sujet
standalone/
  annonce.html      E-mail complet et autonome (promo, annonce, newsletter)
previews/           Aperçus avec données fictives : ouvrez previews/index.html
tools/
  build-previews.js Regénère les aperçus
assets/             Logos
```

## Installation dans WHMCS

1. **Configuration > Paramètres généraux > onglet Courriel**
   - « Style CSS des e-mails » : collez `whmcs/email-css.css`
   - « Contenu de l'en-tête des e-mails clients » : collez `whmcs/header.html`
   - « Contenu du pied de page des e-mails clients » : collez `whmcs/footer.html`
   - Vérifiez que les e-mails sont envoyés en **HTML** (pas en texte brut).
2. **Configuration > Templates d'e-mails** : pour chaque modèle ci-dessous, ouvrez le template WHMCS, passez l'éditeur en mode code source, remplacez le contenu par le fichier correspondant et mettez le sujet indiqué.
3. Utilisez le bouton d'aperçu de WHMCS puis envoyez-vous un e-mail test.

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

> **À vérifier :** les variables (`{$...}`) suivent les noms standard de WHMCS. Si l'une d'elles reste vide dans un e-mail test, comparez-la avec la liste « Champs de fusion disponibles » affichée sous l'éditeur du template. Les plus susceptibles de varier selon la version sont `{$ticket_url}` et `{$service_server_hostname}`.

## E-mail autonome

`standalone/annonce.html` est un e-mail complet. Remplacez les textes entre `[crochets]` (et l'image d'illustration), puis collez le code dans votre outil d'envoi : Brevo, Mailchimp, Thunderbird, etc. Pensez à ajouter un lien de désinscription pour les envois marketing.

## Modifier et prévisualiser

Les blocs se répètent d'un modèle à l'autre (étiquette orange, titre, tableau d'informations, bouton). Pour en créer un nouveau, copiez un fichier existant et adaptez le texte.

```bash
node tools/build-previews.js   # puis ouvrez previews/index.html
```

Pour ajouter un modèle aux aperçus, déclarez-le dans `whmcs/templates.json`.
