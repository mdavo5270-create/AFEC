# Consignes pour Antigravity

Comment s'en servir :
1. Ouvre le dossier `gestion-candidatures` (le plugin) dans Antigravity.
2. Colle d'abord le **bloc « Contexte permanent »** en premier message.
3. Envoie ensuite **une seule étape à la fois**. Teste dans LocalWP et fais un commit Git avant de passer à la suivante.

---

## Contexte permanent (à coller en premier)

```
Tu travailles sur un plugin WordPress nommé « gestion-candidatures » (PHP 7.4+, WordPress 6+).
Le dossier ouvert est le plugin lui-même. Lis d'abord README.md et tous les fichiers de includes/ avant de modifier quoi que ce soit.

OBJET DU PROJET
Un homme qui a gagné à la loterie veut aider 58 personnes. Le site reçoit des candidatures. Une équipe ANALYSE les dossiers, choisit les 58 personnes, les RECONTACTE, puis leur verse l'aide. Il n'y a AUCUN tirage au sort et tu n'en ajoutes jamais : la sélection est faite par des personnes, sur dossier.
Beaucoup de candidats sont des personnes en difficulté : les données sont sensibles et les escroqueries sont probables.

ARCHITECTURE ACTUELLE
- Candidatures = type de contenu privé « gc_candidature » (titre = nom du candidat). Données dans les métadonnées : gc_phone (chiffres seulement), gc_email, gc_city, gc_situation, gc_dossier, gc_status.
- 10 statuts (includes/statuses.php) : recue, en_verification, en_analyse, liste_courte, retenue, contactee, injoignable, confirmee, payee, refusee.
- Les statuts « retenue, contactee, injoignable, confirmee, payee » occupent une place. Limite = option gc_places (58). Ne jamais permettre de dépasser.
- Tout changement de statut passe par gc_change_status() et écrit une ligne dans wp_gc_history. Cette table n'est jamais supprimée ni modifiée. Ne contourne jamais gc_change_status().
- Capacités : gc_view (voir et analyser), gc_decide (retenir, refuser, payer), gc_settings, gc_delete.
- Rôles : gc_evaluateur (gc_view), gc_responsable (gc_view + gc_decide), administrator (tout).
- Tables déjà créées, sans interface : wp_gc_evaluations, wp_gc_contacts.
- Formulaire public : shortcode [gc_formulaire], traité par admin-post.php.

RÈGLES NON NÉGOCIABLES
1. Sécurité : vérifie une capacité (current_user_can) ET un nonce pour chaque action ; nettoie toutes les entrées (sanitize_*, absint) ; échappe toutes les sorties (esc_html, esc_attr, esc_url) ; utilise $wpdb->prepare() pour tout SQL avec une valeur variable.
2. Ne modifie jamais le noyau de WordPress ni un autre plugin. Reste dans ce dossier.
3. Tout le texte visible est en FRANÇAIS, simple et poli. Le code et les commentaires restent courts.
4. N'ajoute aucune bibliothèque externe sans me le demander. Pas de service externe appelé en secret.
5. Jamais de mot de passe, clé API, ni vraie donnée personnelle dans le code, les tests ou les exemples.
6. Un évaluateur ne peut jamais retenir, refuser ni payer. Seuls gc_decide le peuvent.
7. Ne supprime aucune donnée existante sans me demander. Ne change pas les noms de tables ni de métadonnées existants.
8. Après chaque étape : explique en quelques lignes ce que tu as changé, liste les fichiers touchés, et donne-moi les étapes précises pour tester dans LocalWP. Ne passe pas à l'étape suivante sans mon accord.
9. Si une demande est ambiguë ou risquée, pose-moi la question avant d'écrire du code.
10. Utilise des fonctions préfixées gc_ et respecte les standards de code WordPress.

Réponds « compris » et résume en 5 lignes ce que tu as retenu, sans toucher au code.
```

---

## Étape 0 : revue du code existant

```
Relis tout le plugin sans rien modifier. Dresse la liste (1) des bugs probables, (2) des failles de sécurité possibles, (3) de ce qui manque pour que le formulaire et la liste fonctionnent bien. Pour chaque point, indique le fichier et la ligne. Ne corrige rien : je choisirai.
```

## Étape 1 : grille d'évaluation et score

```
Construis la grille d'évaluation avec la table wp_gc_evaluations.

- Réglages (page Réglages existante, réservée à gc_settings) : liste de critères modifiable. Valeurs par défaut : « Urgence du besoin », « Situation familiale et sociale », « Justificatifs fournis », « Cohérence du dossier », « Impact concret de l'aide ». Chaque critère a un libellé, une note maximale (5 par défaut) et un poids (1 par défaut).
- Fiche candidat : nouveau bloc « Mon évaluation ». Chaque évaluateur note les critères, ajoute un commentaire, et peut cocher « Je connais ce candidat, je me récuse ». Si la case est cochée, aucune note n'est enregistrée et la récusation est visible dans la fiche.
- Une seule évaluation par évaluateur et par candidature (clé unique déjà présente) : une nouvelle saisie met à jour l'existante.
- Le total est calculé côté serveur (jamais par le navigateur), ramené sur 100.
- Un évaluateur ne voit que sa propre évaluation tant que le dossier n'est pas en statut liste_courte ou plus. Les utilisateurs gc_decide voient toutes les évaluations (évaluateur, note, commentaire).
- Liste des candidatures : nouvelle colonne « Score moyen » (moyenne des évaluations non récusées) et « Nb d'évaluations », triables.
- Les évaluations modifiées sont tracées (updated_at).

Termine en me donnant la procédure de test avec 2 évaluateurs et 1 responsable.
```

## Étape 2 : suivi des contacts

```
Construis le suivi des contacts avec la table wp_gc_contacts.

- Fiche candidat : bloc « Contacts » (visible seulement si le statut est retenue, contactee, injoignable, confirmee ou payee). Formulaire : canal (appel, WhatsApp, SMS, e-mail), résultat (joint, pas de réponse, numéro faux, a refusé l'aide, à rappeler), note, date de prochaine relance. Réservé à gc_decide.
- Liste des contacts passés dans le bloc, du plus récent au plus ancien, avec l'auteur.
- Règle : après 3 tentatives sans réponse (« pas de réponse » ou « numéro faux ») sur au moins 2 jours différents, propose (sans l'imposer) de passer le candidat en statut injoignable.
- Nouvel écran « À rappeler » sous le menu Candidatures : candidats dont la relance est aujourd'hui ou en retard, triés par date.
- Passer un contact en « joint » propose de passer le statut à contactee, via gc_change_status().
```

## Étape 3 : modèles de messages

```
Ajoute des modèles de messages en français (retenu, refusé, pièce manquante, rappel) sur la fiche candidat.

- Les modèles sont modifiables dans Réglages, avec les variables {nom}, {dossier}, {ville}.
- Sur la fiche, un bouton « Copier le message » et un lien « Ouvrir dans WhatsApp » (https://wa.me/NUMERO?text=...) avec le texte encodé via rawurlencode.
- Chaque modèle contient la phrase : « Aucune somme d'argent ne vous sera jamais demandée. »
- Le message de refus ne contient jamais le motif interne.
- Aucun envoi automatique : l'humain copie et envoie lui-même.
```

## Étape 4 : pièces justificatives (sécurisées)

```
Ajoute le dépôt de pièces justificatives.

- Formulaire public : jusqu'à 3 fichiers (PDF, JPG, PNG), 3 Mo chacun maximum. Vérifie le type MIME réel (wp_check_filetype_and_ext) et la taille côté serveur.
- Stockage HORS de l'accès public : un dossier privé sous wp-content/uploads/gc-private/ avec un fichier .htaccess « Deny from all » et un index.php vide. Les fichiers sont renommés avec un nom aléatoire.
- Téléchargement uniquement par un script PHP qui vérifie gc_view et un nonce, puis envoie le fichier. Jamais de lien direct.
- Fiche candidat : liste des pièces avec lien de téléchargement protégé.
- Chaque téléchargement est enregistré (qui, quand, quelle pièce).
- Prévois la suppression des pièces à la demande du responsable.
```

## Étape 5 : tableau de bord et export

```
Ajoute un écran « Tableau de bord » en tête du menu Candidatures :
- compteurs par statut, « 41 / 58 places attribuées », nombre de candidatures reçues aujourd'hui, nombre de relances en retard ;
- liste des dossiers « Reçue » depuis plus de 7 jours.

Ajoute un export CSV, réservé à gc_decide, avec filtres par statut. Le fichier doit être compatible Excel (BOM UTF-8, séparateur point-virgule) et protégé contre l'injection de formules (préfixe une apostrophe si une cellule commence par = + - @). Chaque export est enregistré dans un journal (qui, quand, quels filtres, nombre de lignes).
```

## Étape 6 : anti-doublons et suivi par le candidat

```
1. Anti-doublons : la vérification actuelle se fait dans une requête, donc deux envois simultanés peuvent passer. Ajoute une table wp_gc_phones (phone UNIQUE) et fais l'insertion de façon atomique pour qu'un même numéro ne puisse jamais créer deux dossiers. Prévois la migration des numéros existants.
2. Anti-spam : prépare l'ajout de Cloudflare Turnstile (clé de site et clé secrète saisies dans Réglages, jamais dans le code). Si les clés sont vides, le formulaire fonctionne comme aujourd'hui.
3. Page de suivi : shortcode [gc_suivi]. Le candidat saisit son numéro de dossier ET son téléphone. Si les deux correspondent, affiche un statut simplifié (« Reçue », « En cours d'étude », « Résultat disponible, nous vous contacterons »). Ne jamais révéler le motif de refus, les notes ni le score. Limite : 5 essais par heure et par IP, et un message d'erreur identique que le dossier existe ou non.
```

## Étape 7 : audit sécurité et données personnelles

```
Fais un audit complet du plugin.
- Vérifie chaque action : capacité, nonce, nettoyage, échappement, requêtes préparées.
- Vérifie qu'aucune donnée de candidat n'est accessible sans connexion (REST, recherche, flux, URL des fichiers).
- Branche le plugin sur les outils de confidentialité de WordPress (export et effacement des données personnelles d'un candidat, avec conservation de l'historique anonymisé).
- Ajoute une durée de conservation configurable (par exemple 12 mois après la fin) avec une alerte dans le tableau de bord, sans suppression automatique.
- Écris un uninstall.php qui NE supprime PAS les données par défaut, mais propose une case « supprimer toutes les données à la désinstallation » dans Réglages.
- Donne la liste des risques restants par ordre de gravité.
```

## Étape 8 : préparer la mise en ligne

```
Prépare le plugin pour la production :
- numéro de version et fichier CHANGELOG.md ;
- un fichier .zip propre (sans .git, sans fichiers de test) ;
- une checklist de mise en ligne : HTTPS actif, sauvegardes, mots de passe forts, double authentification des comptes admin, suppression des fausses candidatures, test complet du formulaire sur le serveur, test d'envoi d'e-mail, désactivation du renouvellement automatique de l'hébergement après la sélection.
```

---

## Si Antigravity se trompe

- Demande-lui de **montrer le diff** (les lignes changées) avant d'accepter.
- Si quelque chose casse : `git restore .` pour revenir au dernier commit.
- Si l'agent veut lancer une commande dans le terminal, lis-la avant d'autoriser. Refuse tout ce qui supprime des fichiers ou touche à la base de données.
- Ne lui donne jamais les identifiants de l'hébergeur ni de la vraie base de données.
