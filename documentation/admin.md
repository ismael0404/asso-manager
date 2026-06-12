# Documentation Administrateur - Association Manager

## 1. Rôle de l'administrateur
L'administrateur (Admin) est le gestionnaire principal de la plateforme Association Manager. Il possède des privilèges étendus permettant de configurer l'application, de gérer les membres, de modérer les contenus, et d'assurer le bon déroulement des activités de l'association. 

Son rôle est crucial pour :
* Maintenir l'intégrité des informations et données (utilisateurs, activités, publications).
* Assurer la sécurité et la modération des plateformes communautaires (commentaires).
* Suivre l'évolution et l'engagement des membres via des statistiques et des historiques de logs.

## 2. Fonctionnalités Administrateur

### 2.1 Dashboard interactif
* **Vue globale** : Affichage d'indicateurs de performance clés (KPI) tels que le nombre d'utilisateurs actifs, de membres inscrits, d'activités futures, et les messages non lus.
* **Activités récentes** : Accès rapide aux derniers éléments inscrits, permettant à l'admin de suivre les événements chauds.

### 2.2 Gestion des Utilisateurs et Membres
* **Utilisateurs de la plateforme (users)** : Lister, ajouter, activer ou désactiver (`is_active`) un compte utilisateur, réinitialiser des mots de passe.
* **Membres officiels (members)** : Gestion des membres physiques de l'association (qui ne sont pas forcément en ligne). L'admin peut statuer sur l'état de la cotisation ou du membre (`active`, `inactive`).

### 2.3 Gestion des Activités
* **Création et édition** : Créer des activités (titre, description, lieu, date, image).
* **Statut de l'activité** : L'admin peut passer une activité en `upcoming`, `ongoing`, ou `completed`.
* **Publication et Inscription** : Gestion de la visibilité (`draft`, `published`) et de l'état des inscriptions (`open`, `closed`).
* **Limite de participants** : Définition d'un seuil maximal (`max_participants`) et blocage automatique une fois le quota atteint.

### 2.4 Gestion des Participations (Workflow de validation)
* **Demandes de participation** : Les utilisateurs s'inscrivent ("pending").
* **Validation/Refus** : L'administrateur examine les demandes et peut les valider ("accepted") ou les refuser ("rejected").
* L'action de validation / refus déclenche automatiquement l'envoi de **notifications ciblées** à l'utilisateur.

### 2.5 Modération, Publications et Communication
* **Publications (Posts)** : Rédaction d'articles, appels à bénévoles, événements via un système de brouillon (`draft`) puis publication (`published`).
* **Messages de contact** : Lecture et réponse aux messages provenant du formulaire de contact public.
* **Messagerie privée interne** : Possibilité d'échanger en privé avec un utilisateur du système.

### 2.6 Statistiques et Audit
* **Historique des logs** : Permet de tracer chaque action (qui a fait quoi et quand sur la plateforme), garantissant une totale transparence et sécurité (qui a validé une participation, supprimé un post, etc.).

## 3. Description des pages du panel d'administration

* `dashboard.php` : Page d'accueil du panel listant les statistiques globales et widgets.
* `users.php` : Liste de tous les utilisateurs (comptes) inscrits. Actions (Voir, Editer, Désactiver).
* `members.php` : Liste de tous les membres administratifs de l'association. 
* `activities.php` : Tableau de bord des événements. Possibilité d'entrer dans les détails pour valider les participations.
* `posts.php` : Gestion du contenu de type actualités ou blog.
* `messages.php` : Interface de la boîte de réception (interne et externe).
* `logs.php` : Page d'audit, journal en lecture seule répertoriant chronologiquement les actions des utilisateurs et admins.
* `settings.php` : Configuration globale de la plateforme ou édition du profil administrateur.

## 4. Flux de fonctionnement
1. **Création** : L'administrateur crée une "Activité". Elle est d'abord en `draft`.
2. **Lancement** : L'admin publie l'activité (`published`) et ouvre les inscriptions (`open`).
3. **Réception** : Des demandes de participations arrivent dans l'onglet des gestions.
4. **Validation** : L'administrateur filtre par statut, vérifie la limite de jauge et accepte/refuse un à un ou en masse.
5. **Clôture** : Une fois l'activité terminée en date, il passe son statut à `completed` et referme les inscriptions (`closed`).

## 5. Sécurité et Rôles
* L'accès au répertoire `/admin/` est strictement filtré via session par la vérification du niveau hiérarchique `role = 'admin'`. Mieux, si la colonne `is_active` est à `0`, aucun accès n'est possible, y compris pour un admin.
* **Protection CSRF / XSS** (implicite et via PDO).
* Les mots de passes utilisent le chiffrement `bcrypt`.

## 6. Exemples d'utilisation courante
> **Cas : Une tempête contraint l'annulation d'une activité extérieure.** 
> L'administrateur se rend sur `activities.php`, ferme les inscriptions, modifie son statut ou poste une nouvelle d'urgence ciblée dans `posts.php` qui gérera automatiquement une communication massive et modifiera les participations par des refus motivés.
