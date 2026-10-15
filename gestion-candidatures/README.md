# Gestion Candidatures (plugin WordPress)

Plugin de départ pour recevoir des candidatures, analyser les dossiers en équipe et suivre chaque personne jusqu'au versement. **Il n'y a aucun tirage au sort** : la sélection se fait sur dossier, par des personnes.

## Ce qui est déjà fait (v0.1.0)

- Formulaire public avec le code court `[gc_formulaire]` (nonce, champ piège anti-robots, limite de 5 envois par heure et par IP, un seul dossier par numéro de téléphone, case de consentement, date limite)
- Candidatures stockées dans un type de contenu **privé** (aucune URL publique)
- 10 statuts : Reçue, En vérification, En analyse, Liste courte, Retenue, Contactée, Injoignable, Confirmée, Payée, Refusée
- Compteur de places (58 par défaut, modifiable) : impossible de dépasser
- Motif obligatoire pour un refus
- Historique de chaque changement (table `gc_history`, jamais supprimée)
- Rôles : **Évaluateur** (analyse seulement), **Responsable** (décide), **Administrateur** (tout)
- Liste admin avec colonnes et filtres par statut, page Réglages (places, date limite)
- Tables déjà créées mais **sans interface** : `gc_evaluations` (notes), `gc_contacts` (suivi des appels)

## Installation locale (LocalWP)

1. Copier ce dossier dans `wp-content/plugins/gestion-candidatures/` (ou envoyer le `.zip` via Extensions > Ajouter > Téléverser).
2. Activer **Gestion Candidatures** dans Extensions.
3. Créer une page « Candidater » contenant le code court `[gc_formulaire]`.
4. Créer des utilisateurs de test avec les rôles *Évaluateur* et *Responsable* (Utilisateurs > Ajouter).
5. Envoyer de fausses candidatures et tester chaque statut.

## Règles du projet

- Ne jamais utiliser de vraies données de candidats en local.
- Ne jamais écraser la base de production avec une base locale après le lancement : on déploie seulement le code.
- Ne jamais mettre de mot de passe ou de clé dans le dépôt.
- Le `.gitignore` exclut `wp-config.php`, `uploads/`, les `.sql` et les `.zip`.

## Structure

```
gestion-candidatures/
├── gestion-candidatures.php   (point d'entrée)
├── includes/
│   ├── statuses.php           (statuts, règles, historique)
│   ├── install.php            (tables, rôles)
│   ├── cpt.php                (type de contenu privé)
│   ├── form.php               (formulaire public)
│   ├── admin.php              (liste, fiche, statut)
│   └── settings.php           (réglages)
├── README.md
├── PROMPT_ANTIGRAVITY.md
└── .gitignore
```

## Suite à construire

Voir `PROMPT_ANTIGRAVITY.md` : grille d'évaluation, suivi des contacts, modèles de messages, pièces justificatives, tableau de bord et export, page de suivi, audit sécurité.
