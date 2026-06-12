# Diagrammes de Cas d'Utilisation (UML)
*Association Manager - Modélisation des Interactions Acteurs*

## 1. Introduction
Cette documentation détaille les cas d'utilisation du système **Association Manager**, séparés en deux diagrammes distincts pour une meilleure lisibilité :
*   **Diagramme Visiteur et Utilisateur** : Focus sur l'acquisition, la participation et l'interaction sociale.
*   **Diagramme Administrateur** : Focus sur la gestion, la modération et le suivi du système.

---

## 2. Diagramme : Visiteur et Utilisateur
Ce diagramme illustre le parcours d'un utilisateur non identifié (Visiteur) devenant membre actif (Utilisateur).

```plantuml
@startuml
left to right direction
skinparam packageStyle rectangle

actor "Visiteur (Non Identifié)" as Visiteur
actor "Utilisateur (Membre/Bénévole)" as Utilisateur

rectangle "Association Manager - Portail Utilisateur" {
    ' Cas d'utilisation Visiteur
    usecase "S'inscrire (Créer un compte)" as UC_Inscrire
    usecase "Envoyer un message de contact" as UC_Contact
    
    ' Cas d'utilisation de base
    usecase "Se connecter au système" as UC_Login
    
    ' Cas d'utilisation Utilisateur
    usecase "Consulter le catalogue d'activités" as UC_ViewActivities
    usecase "S'inscrire à une activité (Workflow)" as UC_Participer
    usecase "Ajouter/Retirer des favoris" as UC_Favoris
    usecase "Consulter son profil et planning" as UC_Profile
    usecase "Publier un commentaire" as UC_Comment
    usecase "Répondre à un commentaire" as UC_Reply
    usecase "Recevoir/Consulter les notifications" as UC_Notif
    usecase "Échanger par messagerie privée" as UC_MP
}

' Relations Visiteur
Visiteur --> UC_Inscrire
Visiteur --> UC_Contact

' Relations Utilisateur
Utilisateur --> UC_Login
Utilisateur --> UC_ViewActivities
Utilisateur --> UC_Participer
Utilisateur --> UC_Favoris
Utilisateur --> UC_Profile
Utilisateur --> UC_Comment
Utilisateur --> UC_Notif
Utilisateur --> UC_MP

' Inclusions et Extensions
UC_Participer .> UC_Login : <<include>>
UC_Favoris .> UC_ViewActivitiehs : <<extend>>
UC_Profile .> UC_Login : <<include>>
UC_Comment .> UC_Login : <<include>>
UC_Reply .> UC_Comment : <<extend>>
UC_Notif .> UC_Login : <<include>>
UC_MP .> UC_Login : <<include>>
@enduml
```

---

## 3. Diagramme : Administrateur (Directoire)
Ce diagramme illustre les fonctionnalités de gestion réservées aux utilisateurs possédant le statut Administrateur.

```plantuml
@startuml
left to right direction
skinparam packageStyle rectangle

actor Administrateur

rectangle "Association Manager - Console Admin" {
    usecase "Se connecter (Auth Admin)" as UC_Login
    
    ' Gestion des ressources
    usecase "Gérer les Utilisateurs (Modération/Edition)" as UC_GererUsers
    usecase "Gérer les Activités (CRUD)" as UC_GererActivities
    usecase "Valider/Refuser une participation" as UC_ValiderParticip
    
    ' Communication et Bureau
    usecase "Publier une actualité (Post)" as UC_GererPosts
    usecase "Gérer les membres officiels (Comptabilité)" as UC_GererMembres
    
    ' Audit
    usecase "Consulter les logs de traçabilité" as UC_Logs
}

' Relations Admin
Administrateur --> UC_Login
Administrateur --> UC_GererUsers
Administrateur --> UC_GererActivities
Administrateur --> UC_GererPosts
Administrateur --> UC_GererMembres
Administrateur --> UC_Logs

' Inclusions et Extensions
UC_GererUsers .> UC_Login : <<include>>
UC_GererActivities .> UC_Login : <<include>>
UC_ValiderParticip .> UC_GererActivities : <<extend>>
UC_GererPosts .> UC_Login : <<include>>
UC_GererMembres .> UC_Login : <<include>>
UC_Logs .> UC_Login : <<include>>
@enduml
```

---

## 4. Analyse des relations métiers
1.  **Authentification (`<<include>>`)** : La plupart des fonctionnalités (participation, commentaires, gestion) nécessitent une session active (`UC_Login`).
2.  **Extension (`<<extend>>`)** :
    *   **Favoris** : C'est une action optionnelle effectuée lors de la consultation des activités.
    *   **Réponses** : La réponse à un commentaire est une extension optionnelle de la fonction de commentaire de base (Nested comments).
    *   **Validation** : La validation des participants se greffe sur le flux de gestion des activités.
3.  **Membres Officiels vs Utilisateurs** : Le système distingue les utilisateurs de la plateforme numérique des membres officiels de l'association (gestion administrative/comptable).
