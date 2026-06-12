# Diagrammes de Séquences (UML)
*Association Manager - Modélisation des Flux Chronologiques*

## 1. Description Textuelle
Les diagrammes de séquence modélisent l'aspect dynamique et temporel du système. Ils définissent les appels de messages séquentiels et chronologiques effectués lors d'une action. Dans Association Manager, il y a de multiples cas d'usage impliquant de nombreux objets MVC (Navigateur, Serveurs PHP, Base de Données `MySQL`).

---

## 2. Inscription Utilisateur

```plantuml
@startuml
actor Utilisateur as U
participant "Frontend (register.php)" as Front
participant "Backend PHP" as PHP
database "MySQL (DB)" as DB

U -> Front : Remplit le formulaire d'inscription
Front -> PHP : POST / données (username, email, pass)
activate PHP
PHP -> DB : SELECT * FROM users WHERE email=? OR username=?
activate DB
DB --> PHP : Retourne résultat (Vide=OK)
deactivate DB

alt L'utilisateur existe déjà
    PHP --> Front : Erreur "Email/Username Existant"
    Front --> U : Affichage erreur
else Validation OK
    PHP -> PHP : password_hash(password)
    PHP -> DB : INSERT INTO users (...)
    activate DB
    DB --> PHP : Succès
    deactivate DB
    PHP -> PHP : Création Session $_SESSION['user_id']
    PHP --> Front : Redirection /user/dashboard.php
    Front --> U : Affichage Dashboard User
end
deactivate PHP
@enduml
```

---

## 3. Connexion (Login)

```plantuml
@startuml
actor "Utilisateur/Admin" as U
participant "Frontend (login.php)" as Front
participant "Backend PHP" as PHP
database "MySQL (DB)" as DB

U -> Front : Saisit Identifiants
Front -> PHP : POST username & password
activate PHP
PHP -> DB : SELECT * FROM users WHERE username=? AND is_active=1
activate DB
DB --> PHP : Renvoie ligne utilisateur (hash)
deactivate DB

alt Compte inactif ou introuvable
    PHP --> Front : Erreur d'accès
else Empreinte vérifiée
    PHP -> PHP : password_verify()
    alt Succès
        PHP -> PHP : Génère variables de Session (id, role)
        alt role == "admin"
            PHP --> Front : Redirection /admin/dashboard.php
        else role == "user"
            PHP --> Front : Redirection /user/dashboard.php
        end
    else Echec Verif
        PHP --> Front : Erreur "Mot de passe incorrect"
    end
end
deactivate PHP

@enduml
```

---

## 4. Participation à une Activité (User)
> *Processus lorsqu'un bénévole souhaite candidater.*

```plantuml
@startuml
actor Utilisateur as U
participant "Interface Activités" as Front
participant "Contrôleur PHP" as PHP
database "MySQL (DB)" as DB

U -> Front : Clic "Participer"
Front -> PHP : POST action=participate & activity_id
activate PHP
PHP -> DB : Vérifie (registration_status == 'open')
activate DB
DB --> PHP : OK (Ouvert)
deactivate DB

PHP -> DB : Vérifier max_participants non atteint
activate DB
DB --> PHP : OK
deactivate DB

PHP -> DB : INSERT INTO participations (status = 'pending')
activate DB
DB --> PHP : Succès de l'insertion
deactivate DB

PHP --> Front : Message Flash "Demande envoyée"
Front --> U : Mise à jour affichage "En Attente"
deactivate PHP
@enduml
```

---

## 5. Validation Admin (Workflow Participation)
> L'admin juge la demande en attente.

```plantuml
@startuml
actor Administrateur as A
participant "Admin / activities.php" as AdminPanel
participant "Script Traitement" as PHP
database "MySQL (DB)" as DB

A -> AdminPanel : Clic "Valider la demande" pour Utilisateur X
AdminPanel -> PHP : POST / accept_participation
activate PHP

PHP -> DB : UPDATE participations SET status='accepted'
activate DB
DB --> PHP : OK
deactivate DB

PHP -> DB : INSERT INTO logs (Admin action)
PHP -> DB : INSERT INTO notifications (Avertir User X)
activate DB
DB --> PHP : OK
deactivate DB

PHP --> AdminPanel : Rafraîchir Liste avec badge Vert
AdminPanel --> A : Confirmation Visuelle
deactivate PHP
@enduml
```

---

## 6. Ajout d'un Commentaire (Nested System)
> L'utilisateur ajoute un commentaire sur un post ou répond à un autre membre.

```plantuml
@startuml
actor Utilisateur as U
participant "Vue Post/Activité" as Front
participant "Action PHP" as PHP
database "MySQL (DB)" as DB

U -> Front : tape message, (et optionnellement parent_id si c'est une réponse)
Front -> PHP : POST / content, post_id, parent_id
activate PHP

PHP -> PHP : Assainir Content (XSS Prevention)
PHP -> DB : INSERT INTO comments (content, user_id, post_id, parent_id)
activate DB
DB --> PHP : Succès / Comment_Id créé
deactivate DB

PHP --> Front : Recharge la timeline de commentaires
Front --> U : Affichage Imbriqué (si réponse) ou à la racine
deactivate PHP
@enduml
```

---

## 7. Envoi Message Privé (User vers User ou Admin)

```plantuml
@startuml
actor Utilisateur as U
participant "Messagerie" as Front
participant "Controlleur PHP" as PHP
database "MySQL (DB)" as DB

U -> Front : Saisit Destinataire, Sujet, Message -> Envoi
Front -> PHP : POST / message data
activate PHP

PHP -> DB : SELECT id FROM users WHERE username = destinataire
activate DB
DB --> PHP : sender_id & receiver_id OK
deactivate DB

PHP -> DB : INSERT INTO messages (sender, receiver, subject, content, is_read=0)
activate DB
DB --> PHP : OK
deactivate DB

PHP -> DB : INSERT INTO notifications (Alerter destinataire nouveau message)

PHP --> Front : Redirection /messages.php?status=sent
Front --> U : "Message envoyé avec succès"
deactivate PHP
@enduml
```
