# Lancement — Intégration SSO TP-5 × SkillHub

Ce document explique **comment faire fonctionner TP-5 et SkillHub ensemble**, ce qui a été implémenté pour les relier, et comment démarrer les deux projets.

---

## Vue d'ensemble de l'architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        NAVIGATEUR                               │
│                                                                 │
│  SkillHub Frontend  ──redirect──►  TP-5 Login / Register       │
│  localhost:5173                    localhost:8080               │
│       ▲                                   │                     │
│       │         token dans l'URL          │                     │
│       └──────── /auth/callback ◄──────────┘                     │
└─────────────────────────────────────────────────────────────────┘
         │                              │
         ▼                              ▼
  SkillHub Auth Service          TP-5 /api/me
  localhost:8001                 localhost:8080
  (émet JWT Skillhub)            (valide token UUID)
```

**Principe :** TP-5 est le portail unique de connexion et d'inscription. Il authentifie l'utilisateur puis renvoie un token SSO vers SkillHub. SkillHub valide ce token auprès de TP-5, crée/récupère le compte local, et émet son propre JWT pour le reste de l'application.

---

## Prérequis

| Outil | Version minimum |
|-------|----------------|
| Java | 17+ |
| Maven | 3.8+ |
| Node.js | 18+ |
| PHP | 8.3+ |
| Composer | 2+ |
| MySQL | 8+ |
| Docker (optionnel) | 24+ |

---

## Ports utilisés

| Service | Port | Rôle |
|---------|------|------|
| TP-5 Auth Service | `8080` | Service d'authentification SSO (Java/Spring Boot) |
| SkillHub Auth API | `8001` | Microservice auth SkillHub (Laravel) |
| SkillHub Catalog API | `8002` | Microservice catalogue (Laravel) |
| SkillHub Inscription API | `8003` | Microservice inscriptions (Laravel) |
| SkillHub Frontend | `5173` | Interface utilisateur (React/Vite) |
| MySQL TP-5 | `3306` | Base de données TP-5 |
| MySQL SkillHub | `3306` | Base de données SkillHub (même instance, BDD différente) |

---

## Étape 1 — Configurer TP-5

### 1.1 Créer la base de données MySQL

```sql
CREATE DATABASE auth_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 1.2 Vérifier `application.properties`

Fichier : `TP-5/src/main/resources/application.properties`

```properties
spring.datasource.url=jdbc:mysql://localhost:3306/auth_db
spring.datasource.username=root
spring.datasource.password=        # adapter selon votre config MySQL
```

> Hibernate utilise `ddl-auto=update` : les tables sont créées/mises à jour automatiquement au démarrage (y compris les nouvelles colonnes `name` et `role` ajoutées pour le SSO).

### 1.3 Générer la Master Key AES-256

La clé est utilisée pour chiffrer les mots de passe en base. Elle doit faire **32 caractères minimum** et rester identique entre les redémarrages.

```bash
# Générer une clé aléatoire
openssl rand -base64 32 | head -c 32
# Exemple de sortie : aBcDeFgHiJkLmNoPqRsTuVwXyZ012345
```

Exporter la variable d'environnement :

```bash
export APP_MASTER_KEY="aBcDeFgHiJkLmNoPqRsTuVwXyZ012345"
```

> ⚠️ Si vous changez cette clé après avoir créé des comptes, les mots de passe existants ne pourront plus être déchiffrés. Gardez-la constante.

### 1.4 Démarrer TP-5

```bash
cd TP-5
APP_MASTER_KEY="aBcDeFgHiJkLmNoPqRsTuVwXyZ012345" ./mvnw spring-boot:run
```

Vérification :

```bash
# La page de login doit s'afficher
curl -I http://localhost:8080/login
# HTTP/1.1 200

# La page d'inscription doit s'afficher
curl -I http://localhost:8080/register
# HTTP/1.1 200
```

Au démarrage, TP-5 crée automatiquement un compte de test :
- Email : `toto@example.com`
- Mot de passe : `Toto1234!@secure`

---

## Étape 2 — Configurer SkillHub

### 2.1 Configurer le service d'authentification Laravel

```bash
cd skillhub-groupe-BC03/services/auth
cp .env.example .env
```

Éditer le `.env` généré et renseigner :

```env
APP_KEY=              # généré avec : php artisan key:generate
APP_MASTER_KEY=aBcDeFgHiJkLmNoPqRsTuVwXyZ012345   # même clé que TP-5

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=skillhub_auth    # ou le nom de votre BDD Skillhub
DB_USERNAME=root
DB_PASSWORD=

TP5_AUTH_URL=http://localhost:8080   # URL du service TP-5
```

> ⚠️ `APP_MASTER_KEY` doit être identique dans TP-5 et dans SkillHub Auth, car les deux utilisent AES-256-GCM pour chiffrer les mots de passe.

Installer les dépendances et migrer :

```bash
composer install
php artisan key:generate
php artisan migrate
```

Démarrer le service auth sur le port 8001 :

```bash
php artisan serve --port=8001
```

### 2.2 Configurer le frontend React

```bash
cd skillhub-groupe-BC03/frontend
```

Le fichier `.env` est déjà configuré avec :

```env
VITE_AUTH_URL=http://127.0.0.1:8001/api
VITE_TP5_URL=http://localhost:8080        # URL du service TP-5 (ajouté)
```

Installer les dépendances et démarrer :

```bash
npm install
npm run dev
```

Le frontend est accessible sur **http://localhost:5173**.

### 2.3 (Optionnel) Démarrer avec Docker Compose

Si vous préférez utiliser Docker pour les services Laravel :

```bash
cd skillhub-groupe-BC03
docker compose up -d
```

Les services Laravel (auth, catalog, inscription) et MySQL démarrent automatiquement.
Le frontend reste à lancer manuellement avec `npm run dev`.

---

## Étape 3 — Tester l'intégration

### Flux de connexion

1. Ouvrir **http://localhost:5173** dans le navigateur
2. Cliquer sur **"Se connecter"** (ou accéder à `/connexion`)
3. Redirection automatique vers **http://localhost:8080/login?redirect_uri=http://localhost:5173/auth/callback**
4. Saisir les identifiants (ex : `toto@example.com` / `Toto1234!@secure`)
5. TP-5 vérifie le HMAC, génère un token UUID, et redirige vers **http://localhost:5173/auth/callback?token=\<UUID\>**
6. SkillHub échange le token : appel server-side vers TP-5 `/api/me`
7. Redirection automatique vers le tableau de bord selon le rôle

### Flux d'inscription

1. Cliquer sur **"S'inscrire"** (ou accéder à `/inscription`)
2. Redirection automatique vers **http://localhost:8080/register?redirect_uri=http://localhost:5173/auth/callback**
3. Remplir le formulaire : nom, email, rôle (formateur / apprenant), mot de passe
4. Après inscription, TP-5 génère un token et redirige vers SkillHub `/auth/callback`
5. SkillHub crée le compte local et redirige vers le dashboard

---

## Ce qui a été implémenté et pourquoi

### Côté TP-5 (Java/Spring Boot)

#### Nouveaux champs `name` et `role` dans `User.java`

```java
@Column(name = "name")
private String name;

@Column(name = "role")
private String role;
```

**Pourquoi :** SkillHub a besoin du nom et du rôle (formateur/apprenant) pour créer le compte local et diriger l'utilisateur vers le bon dashboard. Ces informations doivent donc être saisies lors de l'inscription dans TP-5 et renvoyées par `/api/me`.

#### `RegisterRequest.java` — ajout de `name` et `role`

```java
private String name;
private String role;
```

**Pourquoi :** Le formulaire d'inscription TP-5 envoie maintenant ces champs en JSON. Le DTO les expose à `AuthService`.

#### `AuthService.register()` — surcharge avec nom, rôle et token SSO

```java
public User register(String email, String password, String name, String role) {
    // ... validation ...
    String encryptedPassword = aesGcmService.encrypt(password);
    User user = new User(email, encryptedPassword, name, role);

    // Émettre un token SSO immédiatement après inscription
    String token = UUID.randomUUID().toString();
    user.setSessionToken(token);
    userRepository.save(user);
    return user;
}
```

**Pourquoi :** Après l'inscription, le frontend TP-5 doit pouvoir rediriger vers SkillHub avec un token valide. Sans token émis dès l'inscription, il faudrait forcer un login séparé — expérience dégradée. La surcharge préserve la signature d'origine pour la rétro-compatibilité.

#### `AuthController.getMe()` — retourne `name` et `role`

```java
body.put("name", user.getName() != null ? user.getName() : "");
body.put("role", user.getRole() != null ? user.getRole() : "");
```

**Pourquoi :** L'endpoint `/api/me` est appelé par SkillHub pour valider le token et récupérer le profil. Sans `name` et `role`, SkillHub ne peut pas créer le compte local correctement.

#### `RegisterPageController.java` — nouvelle page `/register`

```java
@GetMapping("/register")
public String registerPage(
        @RequestParam(value = "redirect_uri", required = false, defaultValue = "") String redirectUri,
        Model model) {
    model.addAttribute("redirectUri", redirectUri);
    return "register";
}
```

**Pourquoi :** La page de login existait déjà avec support SSO (`redirect_uri`). Il manquait l'équivalent pour l'inscription. Ce contrôleur sert le template `register.html` en injectant l'URL de retour via Thymeleaf.

#### `register.html` — formulaire d'inscription SSO

Le template Thymeleaf contient :
- Un formulaire avec les champs : nom, email, rôle (select), mot de passe, confirmation
- Une validation JavaScript côté client (même politique que SkillHub : 8+ cars, majuscule, chiffre, spécial)
- Un `fetch POST` vers `/api/auth/register` — le mot de passe est envoyé en clair car c'est la première saisie (pas encore de HMAC possible)
- Après succès, redirection vers `{redirect_uri}?token={accessToken}`
- Un bandeau d'information si on vient d'une application cliente
- Le lien "Se connecter" préserve le `redirect_uri`

---

### Côté SkillHub Auth Service (Laravel)

#### Nouvelle route `POST /api/sso/tp5`

```php
Route::post('/sso/tp5', [AuthController::class, 'ssoTp5']);
```

**Pourquoi :** Cette route est l'endpoint d'échange SSO. Elle est placée sans middleware `AntiRejeuHmac` car elle n'est pas appelée depuis un navigateur mais depuis le code frontend React via Axios — le token TP-5 lui-même sert de preuve d'authentification.

#### Méthode `ssoTp5()` dans `AuthController.php`

```php
public function ssoTp5(Request $requete): JsonResponse
{
    // 1. Reçoit le token UUID émis par TP-5
    $donneesValidees = $requete->validate(['tp5_token' => ['required', 'string']]);

    // 2. Valide le token en appelant TP-5 côté serveur
    $reponse = Http::withToken($donneesValidees['tp5_token'])
        ->timeout(5)
        ->get("{$tp5Url}/api/me");

    // 3. Récupère email, name, role depuis TP-5
    $profil = $reponse->json();

    // 4. Crée ou récupère l'utilisateur dans la base Skillhub
    $utilisateur = User::query()->firstOrCreate(
        ['email' => $email],
        ['name' => $nomParDefaut, 'password' => ..., 'role' => $roleParDefaut]
    );

    // 5. Émet un JWT Skillhub (valable 8 heures)
    $jeton = $this->serviceJwt->generer([...]);

    return response()->json(['token' => $jeton, 'utilisateur' => [...]]);
}
```

**Pourquoi :** C'est le pont entre les deux systèmes. En appelant TP-5 **côté serveur** (PHP → Java), on évite tout problème CORS et on garantit que le token n'a pas été forgé. `firstOrCreate` permet la connexion des utilisateurs existants ET la création des nouveaux sans duplication.

---

### Côté SkillHub Frontend (React)

#### `Connexion.jsx` et `Inscription.jsx` — redirect SSO

```js
useEffect(() => {
  const tp5Url = import.meta.env.VITE_TP5_URL || "http://localhost:8080";
  const callbackUrl = `${window.location.origin}/auth/callback`;
  window.location.href = `${tp5Url}/login?redirect_uri=${encodeURIComponent(callbackUrl)}`;
}, []);
```

**Pourquoi :** Au lieu d'afficher un formulaire local, les pages redirigent immédiatement vers TP-5. `window.location.origin` est utilisé plutôt qu'une URL fixe pour s'adapter automatiquement au port de déploiement. Le `redirect_uri` est encodé pour éviter les problèmes avec les caractères spéciaux dans l'URL.

#### `AuthCallback.jsx` — page de retour SSO

```jsx
useEffect(() => {
  const token = searchParams.get("token");  // token UUID reçu de TP-5

  echangerTokenSso(token)           // POST /api/sso/tp5
    .then((donnees) => {
      sauvegarderSession(donnees.token, donnees.utilisateur);  // JWT Skillhub
      navigate(route, { replace: true });  // vers /dashboard/formateur ou /apprenant
    });
}, []);
```

**Pourquoi :** Cette page est le point d'atterrissage après l'authentification TP-5. Elle lit le token dans l'URL (`?token=...`), le transmet au backend Skillhub pour l'échange, et sauvegarde la session Skillhub en localStorage. `replace: true` empêche l'utilisateur de revenir sur `/auth/callback` via le bouton Retour.

#### `authApi.js` — fonction `echangerTokenSso()`

```js
export async function echangerTokenSso(tp5Token) {
  const reponse = await apiAuth.post("/sso/tp5", { tp5_token: tp5Token });
  return reponse.data;
}
```

**Pourquoi :** Appel standardisé via l'instance Axios `apiAuth` déjà configurée avec la bonne `baseURL` (`VITE_AUTH_URL`). Le reste de l'application continue de fonctionner avec les JWT Skillhub sans rien changer.

---

## Flux SSO complet

```
[Utilisateur] clique "Se connecter" sur SkillHub (localhost:5173/connexion)
      │
      ▼
[Connexion.jsx] redirect vers :
  http://localhost:8080/login?redirect_uri=http://localhost:5173/auth/callback
      │
      ▼
[TP-5 login.html] affiche formulaire, calcule HMAC côté client,
  POST /api/auth/login avec {email, nonce, timestamp, hmac}
      │
      ▼
[TP-5 AuthService] vérifie HMAC, génère token UUID, sauvegarde en BDD
  → redirect vers http://localhost:5173/auth/callback?token=<UUID>
      │
      ▼
[AuthCallback.jsx] lit ?token=<UUID>
  POST http://localhost:8001/api/sso/tp5 avec {tp5_token: "<UUID>"}
      │
      ▼
[SkillHub AuthController.ssoTp5()] appel serveur-à-serveur :
  GET http://localhost:8080/api/me (Authorization: Bearer <UUID>)
  → reçoit {id, email, name, role}
  → firstOrCreate user dans BDD Skillhub
  → génère JWT Skillhub (8h)
  → retourne {token, utilisateur}
      │
      ▼
[AuthCallback.jsx] sauvegarderSession(jwtSkillhub, utilisateur)
  → navigate("/dashboard/formateur") ou navigate("/dashboard/apprenant")
      │
      ▼
[Utilisateur] est connecté sur SkillHub ✓
```

---

## Politique de mot de passe

Les deux services appliquent la même politique :

- Minimum **8 caractères**
- Au moins **1 majuscule**
- Au moins **1 chiffre**
- Au moins **1 caractère spécial** (ex : `!`, `@`, `#`, `$`)

Exemple valide : `MonMotDePasse1!`

---

## Résumé des URLs importantes

| URL | Description |
|-----|-------------|
| `http://localhost:8080/login` | Page de connexion TP-5 (SSO) |
| `http://localhost:8080/register` | Page d'inscription TP-5 (SSO) |
| `http://localhost:8080/api/auth/register` | API d'inscription TP-5 (JSON) |
| `http://localhost:8080/api/auth/login` | API de connexion TP-5 (HMAC) |
| `http://localhost:8080/api/me` | Profil utilisateur TP-5 (Bearer token) |
| `http://localhost:8001/api/sso/tp5` | Échange token SSO → JWT Skillhub |
| `http://localhost:8001/api/validate-token` | Validation JWT Skillhub (inter-services) |
| `http://localhost:5173/auth/callback` | Page de retour SSO SkillHub |
| `http://localhost:5173/connexion` | Déclenche redirect vers TP-5 login |
| `http://localhost:5173/inscription` | Déclenche redirect vers TP-5 register |
