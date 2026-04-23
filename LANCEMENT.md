# Lancement — Architecture microservice Skillhub + TP-5

## Prérequis

- Java 17+
- Maven 3.8+
- MySQL 8+ (pour TP-5)

---

## 1. Configurer la base de données (TP-5)

Créer la base de données MySQL :

```sql
CREATE DATABASE auth_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Vérifier les identifiants dans `TP-5/src/main/resources/application.properties` :

```properties
spring.datasource.url=jdbc:mysql://localhost:3306/auth_db
spring.datasource.username=root
spring.datasource.password=
```

---

## 2. Générer la Master Key AES-256 (TP-5)

La clé doit faire exactement **32 caractères** (256 bits) :

```bash
# Exemple de génération
openssl rand -base64 32 | head -c 32
```

Exporter la variable d'environnement avant de démarrer TP-5 :

```bash
export APP_MASTER_KEY="votre-cle-de-32-caracteres-ici!!"
```

> ⚠️ Cette clé doit rester la même entre les redémarrages, sinon les mots de passe chiffrés en base ne pourront plus être déchiffrés.

---

## 3. Démarrer TP-5 — Auth Service (port 8080)

```bash
cd TP-5
APP_MASTER_KEY="votre-cle-de-32-caracteres-ici!!" ./mvnw spring-boot:run
```

Vérifier que le service est démarré :

```bash
curl http://localhost:8080/login
# Doit retourner la page HTML de login
```

---

## 4. Démarrer Skillhub (port 8081)

Dans un nouveau terminal :

```bash
cd Skillhub
./mvnw spring-boot:run
```

---

## 5. Utiliser l'application

1. Ouvrir **http://localhost:8081** dans le navigateur
2. Cliquer sur **"Se connecter"**
3. Le navigateur est redirigé vers **http://localhost:8080/login** (TP-5)
4. Entrer les identifiants → le HMAC est calculé côté client, le mot de passe ne transite pas
5. Après connexion, redirection automatique vers **http://localhost:8081/dashboard**

---

## 6. Créer un compte (optionnel)

Via l'API TP-5 directement :

```bash
curl -X POST http://localhost:8080/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email": "user@exemple.com", "password": "MonMotDePasse1!"}'
```

Politique de mot de passe : minimum 8 caractères, au moins 1 majuscule, 1 chiffre, 1 caractère spécial.

---

## Résumé des ports et URLs

| Service       | Port | URL                              | Rôle                        |
|---------------|------|----------------------------------|-----------------------------|
| TP-5          | 8080 | http://localhost:8080            | Microservice d'auth (login) |
| TP-5 login UI | 8080 | http://localhost:8080/login      | Page de connexion SSO       |
| Skillhub      | 8081 | http://localhost:8081            | Application principale      |
| Skillhub dash | 8081 | http://localhost:8081/dashboard  | Dashboard (post-login)      |

---

## Flux SSO complet

```
Skillhub /login
  → redirect → TP-5 /login?redirect_uri=http://localhost:8081/auth/callback
    → login HMAC réussi
      → redirect → Skillhub /auth/callback?token=xxx
        → validation server-to-server (Skillhub → TP-5 /api/me)
          → session créée → redirect → Skillhub /dashboard
```
