# EC06 — Intégration TP-5 × SkillHub

Projet Bachelor Informatique — Bloc 03 : Cloud, DevOps & Architecture

---

## Présentation

Ce dépôt regroupe deux projets reliés par une intégration SSO :

| Projet | Technologie | Rôle |
|--------|------------|------|
| **TP-5** | Java / Spring Boot | Service d'authentification SSO (login + inscription) |
| **SkillHub** | Laravel + React | Plateforme de formations (utilise TP-5 comme portail auth) |

**Principe :** L'utilisateur se connecte ou s'inscrit via TP-5. TP-5 émet un token UUID et redirige vers SkillHub. SkillHub échange ce token contre un JWT local, puis donne accès à l'application.

---

## Structure du dépôt

```
CI-CD/
├── TP-5/                          # Auth microservice (Spring Boot)
│   ├── src/
│   ├── Dockerfile
│   ├── sonar-project.properties
│   ├── .github/workflows/ci.yml
│   └── README.md
│
├── skillhub-groupe-BC03/          # Plateforme SkillHub
│   ├── frontend/                  # React 19 + Vite
│   ├── services/
│   │   ├── auth/                  # Microservice auth (Laravel)
│   │   ├── catalog/               # Microservice catalogue (Laravel)
│   │   └── inscription/           # Microservice inscriptions (Laravel)
│   ├── docker-compose.yml
│   ├── sonar-project.properties
│   ├── .github/workflows/ci.yml
│   └── README.md
│
├── LANCEMENT.md                   # Guide de démarrage et explication du code
└── README.md                      # Ce fichier
```

---

## Démarrage rapide

> Voir **[LANCEMENT.md](./LANCEMENT.md)** pour les instructions complètes étape par étape.

### TP-5 (port 8080)

```bash
cd TP-5
APP_MASTER_KEY="votre-cle-32-caracteres" ./mvnw spring-boot:run
```

### SkillHub (ports 8001 / 8002 / 8003 / 5173)

```bash
cd skillhub-groupe-BC03
docker compose up -d

cd frontend
npm install && npm run dev
```

Ajouter dans `skillhub-groupe-BC03/services/auth/.env` :

```env
TP5_AUTH_URL=http://localhost:8080
APP_MASTER_KEY=votre-cle-32-caracteres
```

---

## Flux SSO

```
/connexion ou /inscription (SkillHub)
  → TP-5 /login ou /register  (authentification HMAC)
    → /auth/callback?token=<UUID>  (retour SkillHub)
      → POST /api/sso/tp5  (échange token → JWT Skillhub)
        → /dashboard/formateur ou /dashboard/apprenant
```

---

## Ports

| Service | Port |
|---------|------|
| TP-5 Auth Service | 8080 |
| SkillHub Auth API | 8001 |
| SkillHub Catalog API | 8002 |
| SkillHub Inscription API | 8003 |
| SkillHub Frontend | 5173 |

---

## CI/CD & Qualité

Chaque projet possède son propre pipeline GitHub Actions :

- **TP-5** : build Maven → tests JUnit → analyse SonarCloud
- **SkillHub** : tests PHPUnit → analyse SonarCloud → build images Docker

Les deux pipelines bloquent le merge si les tests échouent ou si le Quality Gate SonarCloud est rouge.

---

## Auteurs

Projet réalisé dans le cadre du Bachelor CDWFS — Bloc 03, Promotion 2025/2026.
