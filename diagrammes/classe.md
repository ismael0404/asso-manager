# Diagramme de Classes (UML)
*Association Manager - Modélisation Statique et Structurale*

## 1. Description Textuelle
Ce diagramme de classes illustre le modèle conceptuel de la base de données relationnelle du projet Association Manager. Les relations entre les classes (tables) sont définies par des associations directes (clés étrangères) avec leurs cardinalités appropriées.

**Légende des Cardinalités (UML Standard) :**
- `1` : Exactement un
- `0..*` ou `*`: Zéro ou plusieurs
- `1..*` : Un ou plusieurs

---

## 2. Code Source PlantUML pour Visual Paradigm

```plantuml
@startuml
skinparam classAttributeIconSize 0
top to bottom direction

class Utilisateur {
  - id : INT
  - nom_utilisateur : VARCHAR(50)
  - email : VARCHAR(100)
  - mot_de_passe : VARCHAR(255)
  - nom_complet : VARCHAR(100)
  - telephone : VARCHAR(20)
  - bio : TEXT
  - avatar : VARCHAR(255)
  - role : Enum("admin", "user")
  - est_actif : TINYINT(1)
  - date_creation : DATETIME
  - date_modification : DATETIME
  + seConnecter()
  + sInscrire()
  + mettreAJourProfil()
}

class Activite {
  - id : INT
  - titre : VARCHAR(200)
  - description : TEXT
  - image : VARCHAR(255)
  - date_activite : DATE
  - lieu : VARCHAR(200)
  - statut : Enum("prochainement", "en_cours", "termine")
  - statut_publication : Enum("brouillon", "publie")
  - statut_inscription : Enum("ouvert", "ferme")
  - nb_participants_max : INT
  - cree_par : INT
  - date_creation : DATETIME
  + publier()
  + verifierDisponibilite()
}

class Participation {
  - id : INT
  - user_id : INT
  - activity_id : INT
  - statut : Enum("en_attente", "accepte", "rejete")
  - date_creation : DATETIME
  + accepter()
  + refuser()
}

class Article {
  - id : INT
  - titre : VARCHAR(200)
  - contenu : TEXT
  - categorie : VARCHAR(50)
  - est_publie : TINYINT(1)
  - date_creation : DATETIME
}

class Commentaire {
  - id : INT
  - user_id : INT
  - post_id : INT
  - activity_id : INT
  - parent_id : INT
  - contenu : TEXT
  - date_creation : DATETIME
}

class Notification {
  - id : INT
  - user_id : INT
  - type : VARCHAR(50)
  - message : VARCHAR(500)
  - lien : VARCHAR(255)
  - est_lu : TINYINT(1)
  - date_creation : DATETIME
  + marquerCommeLu()
}

class Message {
  - id : INT
  - expediteur_id : INT
  - destinataire_id : INT
  - sujet : VARCHAR(200)
  - contenu : TEXT
  - est_lu : TINYINT(1)
  - date_creation : DATETIME
}

class Favori {
  - id : INT
  - user_id : INT
  - activity_id : INT
  - date_creation : DATETIME
}

class Journal {
  - id : INT
  - user_id : INT
  - action : VARCHAR(500)
  - type_entite : VARCHAR(50)
  - date_creation : DATETIME
}

' Relations
Utilisateur "1" -- "0..*" Activite : "crée >" (Admin)
Utilisateur "1" -- "0..*" Participation : "participe >"
Activite "1" -- "0..*" Participation : "comporte >"

Utilisateur "1" -- "0..*" Article : "rédige >" (Admin)

Utilisateur "1" -- "0..*" Commentaire : "publie >"
Article "1" -- "0..*" Commentaire : "possède >"
Activite "1" -- "0..*" Commentaire : "possède >"
Commentaire "0..1" -- "0..*" Commentaire : "répond à >" (Auto-jointure parent_id)

Utilisateur "1" -- "0..*" Notification : "reçoit >"

Utilisateur "1" -- "0..*" Message : "envoie >"
Utilisateur "1" -- "0..*" Message : "reçoit >"

Utilisateur "1" -- "0..*" Favori : "ajoute >"
Activite "1" -- "0..*" Favori : "est ajoutée >"

Utilisateur "1" -- "0..*" Journal : "déclenche >"

@enduml
```

## 3. Remarques Spécifiques
* La classe d'association `Participation` matérialise la relation "Plusieurs à Plusieurs" (Many-To-Many) entre `Utilisateur` et `Activite`, permettant de rajouter l'attribut d'état (en attente/accepté/rejeté).
* Il en va de même pour la table `Favori`.
* La classe `Commentaire` dispose d'une **auto-jointure sur elle-même** (une relation réflexive) via l'attribut `parent_id`, conceptualisant la gestion des commentaires imbriqués (Nested replies).
* Les relations `user_id` vers `Journal` sont conservées (`SET NULL` dans le Schéma SQL) permettant de tracer toutes les actions majeures sur le système pour une sécurité absolue.
