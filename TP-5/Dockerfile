# ─── Étape 1 : Build Maven ────────────────────────────────────────────────────
FROM maven:3.9-eclipse-temurin-17 AS builder

WORKDIR /app

# Copier le pom.xml séparément pour tirer parti du cache des dépendances Maven
COPY pom.xml .
RUN mvn dependency:go-offline -q

# Copier le code source et builder le JAR (sans les tests)
COPY src ./src
RUN mvn clean package -DskipTests -q

# ─── Étape 2 : Image d'exécution légère ───────────────────────────────────────
FROM eclipse-temurin:17-jre-alpine

WORKDIR /app

# Copier uniquement le JAR produit par le build
COPY --from=builder /app/target/auth-0.0.1-SNAPSHOT.jar app.jar

# Port exposé par Spring Boot
EXPOSE 8080

# La Master Key AES-256 doit être injectée via variable d'environnement
# docker run -e APP_MASTER_KEY="..." ...
ENV APP_MASTER_KEY=""

ENTRYPOINT ["java", "-jar", "app.jar"]
