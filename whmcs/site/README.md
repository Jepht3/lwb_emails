# Correctifs boutique WHMCS (audit mobile, octobre 2026)

Correctifs appliqués sur my.lewebmax.com après un test client sur mobile.

| Fichier | Où il va sur WHMCS | Rôle |
|---|---|---|
| `includes/hooks/lewebmax_storefront.php` | `includes/hooks/` | Affiche « Inclus dans votre offre » au lieu de « 12.99 USD » à l'étape domaine des offres avec domaine offert (.com/.net/.org) |
| `templates/lwb-theme/css/custom-boutique-mobile.css` | ajouté à la fin de `templates/lwb-theme/css/custom.css` | Onglets mobiles qui tiennent à l'écran, contenu avant la colonne latérale, catégories du panier compactes, page « Configurer » sans débordement (bouton Continuer cliquable) |
| `lang/overrides/french-tld-categories.php` | ajouté à la fin de `lang/overrides/french.php` | Catégories d'extensions en français |
| `htaccess-whmcs-rewrite.txt` | ajouté à la fin de `.htaccess` (fichier en 0444) | Adresses courtes `/store`, `/knowledgebase`, `/announcements` |

Réglages modifiés en base :

- `DefaultToClientArea` désactivé : l'accueil montre les offres au lieu de la page de connexion.
- `AllowClientRegister` activé : « Créer un compte » fonctionne sans commande.
- Le département « Support général » (id 1) est ouvert aux visiteurs : le formulaire de contact marche sans compte. Sa traduction française a aussi été corrigée.
- Ordre des moyens de paiement : FlexPay (Mobile Money) en premier, donc choisi par défaut.

Sauvegardes sur le serveur : `/home/jephtcom/lwb-backups/boutique-20261010-153144/`.

## Service « Maintenance de site web » (octobre 2026)

- Groupe de produits n°14 « Maintenance de site web », adresse `/store/maintenance-site-web`.
- Produit n°56 « Maintenance de site web » :
  - type « Autre », sans module serveur, donc pas de compte WHM ;
  - 30 USD par mois, sans frais d'installation ;
  - activé automatiquement au paiement ;
  - e-mail de bienvenue « Other Product/Service Welcome Email ».
- Champs demandés à la commande :
  - adresse du site (obligatoire) ;
  - technologie (obligatoire) ;
  - hébergeur (obligatoire) ;
  - attentes (facultatif).
- Dans la description du produit, une ligne qui contient « : » est affichée par WHMCS comme « valeur en gras + libellé ». Il faut donc éviter les deux-points.

## Relances et avertissements avant expiration (octobre 2026)

**Hook `includes/hooks/lewebmax_reminders.php`** : il remplace le rythme « tous les 3 jours » par des étapes fixes, calées sur la suspension automatique à J+5.

| Moment | E-mail |
|---|---|
| J-14, J-7, J-3, J-1 | Rappel avant échéance (avec compte à rebours Lwb-emails) |
| J+1 | Facture en retard |
| J+3 | Deuxième rappel |
| J+4 | Dernier rappel avant suspension |
| J+10, J+20, J+30 | Deuxième rappel, puis arrêt |

**Réglages modifiés :**

- `CCAttemptOnlyOnce` activé. WHMCS tentait chaque jour d'encaisser les factures PayPal et envoyait chaque jour « Paiement à effectuer », même pour des factures de 2024.
- `CreateInvoiceDaysBeforeMonthly` réglé à 7 : les factures mensuelles (maintenance) sont créées 7 jours avant l'échéance. Les factures annuelles restent créées 30 jours avant.
- `DomainRenewalNotices` réglé à `60,30,-3` : premier avis domaine 60 jours avant l'expiration. Les rappels de facture prennent ensuite le relais à J-14, J-7, J-3 et J-1.

**Maintenance de site web (produit n°56) :**

- E-mail de bienvenue dédié « Lwb Maintenance Bienvenue », qui fait partie du module Lwb-emails.
- Champs d'accès administrateur demandés à la commande : choix « compte créé pour info@lewebmax.com » ou « compte existant », adresse de connexion, identifiant, mot de passe.
- Le mot de passe est masqué dans tous les e-mails par `lewebmax_storefront.php`, et ces e-mails n'ont pas de version web. Il reste visible dans l'administration WHMCS, sur la fiche du service.
- Les options d'une liste déroulante ne doivent pas contenir d'apostrophe droite (`'`) : WHMCS les refuse (« Invalid Select Option »). Utiliser `’`.

## Extension Lwb-dashboard (tableau de bord admin)

Dossier `whmcs/modules/addons/lwb_dashboard`, archive `dist/lwb_dashboard.zip`. L'extension est en lecture seule : elle ne modifie aucune donnée.

**Accès :** Modules complémentaires › Lwb-dashboard (`/kota/addonmodules.php?module=lwb_dashboard`), plus un widget « LEWEBMAX en un coup d'œil » sur l'accueil de l'administration.

**Contenu :**

- 8 indicateurs :
  - encaissé ce mois, comparé à la même date le mois précédent ;
  - revenu récurrent mensuel ;
  - impayés ;
  - commandes en attente ;
  - clients ;
  - services ;
  - domaines ;
  - tickets.
- Graphique des encaissements sur 6, 12 ou 24 mois, avec info-bulle et tableau des données.
- Impayés par ancienneté et plus gros soldes.
- Renouvellements des 30 prochains jours, avec l'état de la facture.
- Domaines à surveiller :
  - en période de grâce ;
  - payés mais pas renouvelés ;
  - expirant dans 15 jours ;
  - sans registraire.
- Revenus par activité et par moyen de paiement.
- Dernières commandes, derniers paiements, tickets à traiter, meilleurs clients.

Captures : `previews/dashboard/` (données d'exemple).
