/*
| Projet: SkillHub
| Rôle du fichier: Page connexion — redirige vers TP-5 SSO
| Dernière modification: 2026-04-23
*/

import { useEffect } from "react";
import "../styles/connexion.css";

// Redirige automatiquement l'utilisateur vers le service d'auth TP-5.
function Connexion() {
  useEffect(() => {
    const tp5Url = import.meta.env.VITE_TP5_URL || "http://localhost:8080";
    const callbackUrl = `${window.location.origin}/auth/callback`;
    window.location.href = `${tp5Url}/login?redirect_uri=${encodeURIComponent(callbackUrl)}`;
  }, []);

  return (
    <main className="connexion-page">
      <section className="connexion-carte" aria-labelledby="titre-connexion">
        <h1 id="titre-connexion">Connexion</h1>
        <p>Redirection vers le service d'authentification…</p>
      </section>
    </main>
  );
}

export default Connexion;
