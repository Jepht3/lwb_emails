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
