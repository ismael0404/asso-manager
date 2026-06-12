# Documentation Utilisateur - Association Manager

## 1. Rôle de l'utilisateur
L'utilisateur est le membre actif ou le simple sympathisant de l'association. Sa présence sur la plateforme vise à s'informer des objectifs et de l'actualité de l'association, à interagir avec les autres membres et surtout à participer activement aux événements organisés. 

Son rôle se veut :
* **Actif** : Participer physiquement (bénévolat, présence) et numériquement (commentaires).
* **Suivi** : Consulter ses historiques, et recevoir des alertes ou notifications.

## 2. Fonctionnalités Utilisateur

### 2.1 Inscription et Connexion
* **Création de compte** : Les utilisateurs peuvent s'inscrire via `register.php`.
* **Authentification** : Connexion sécurisée sur `login.php`.
* **Profil personnel** : Edition des informations, ajout d'une bio, mise à jour d'un avatar et informations de contact.

### 2.2 Participation aux activités
* L'utilisateur peut parcourir la liste des activités dont le statut de publication est `published`.
* Il peut cliquer sur "Participer" si le statut des inscriptions est resté `open`.
* **Statut de participation** : 
  * `pending` : La demande est sous étude de l'administrateur.
  * `accepted` : L'utilisateur a été accepté et fait désormais partie de la jauge validée.
  * `rejected` : La demande a été déclinée.

### 2.3 Commentaires
* L'utilisateur peut commenter des publications (`posts`) et des activités (`activities`).
* Le système de commentaires autorise les **réponses imbriquées (nested comments)** (`parent_id`), ce qui favorise les fils de discussions naturels sous chaque actualité ou événement.

### 2.4 Favoris et engagement
* L'utilisateur peut mettre en **favoris** (`favorites`) les activités qu'il souhaite garder de côté ou retrouver ultérieurement très rapidement sans avoir à les rechercher, pratique pour des plannings chargés.

### 2.5 Notifications et Messages Privés
* **Notifications système** : Cloche d'alertes instantanées l'informant de la validation de sa participation, la parution d'un événement correspondant à ses besoins, ou une modération.
* **Messagerie privée** : Communication asynchrone (1 à 1) permettant à un utilisateur d'interpeller l'administrateur ou d'autres membres sur des questions précises ou une collaboration bénévole.

## 3. Description des pages Utilisateur

* `dashboard.php` : Tableau de bord personnel résumant ses futures présences, nombre de favoris, les notifications récentes non lues.
* `activities.php` : Annuaire catalogue avec filtres de toutes les activités publiques.
* `my-activities.php` : Espace personnel retraçant ses participations historiques et en cours, avec le statut d'acceptation visible immédiatement.
* `calendar.php` : Représentation visuelle de ses participations à venir, pour une planification simplifiée.
* `favorites.php` : Accès rapide à toutes ses activités favorisées.
* `notifications.php` : Historique complet des notifications reçues dans le temps.
* `messages.php` : Boîte de réception (inbox/outbox) pour discuter avec d'autres utilisateurs.
* `profile.php` : Page de paramétrage de son identité numérique (mot de passe, avatar, bio).

## 4. Expérience Utilisateur (UX)
L'interface est pensée pour la clarté et l'action.
Lorsqu'un utilisateur navigue :
* Des labels couleurs indiquent clairement le statut (badge vert pour Accepté, jaune/orange pour En Attente).
* Une notification dynamique (badge de compteur rouge) s'affiche sur la cloche ou dans le menu profil lorsqu'un message ou un avertissement n'est pas lu (`is_read = 0`).
* La création de commentaires favorise des interactions fluides style réseau social. 
* L'utilisation de favoris permet un parcours non contraignant : s'intéresser, stocker, décider après.
