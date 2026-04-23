/*
| Projet: SkillHub
| Rôle du fichier: Page inscription — redirige vers TP-5 SSO
| Dernière modification: 2026-04-23
*/

import { useEffect } from "react";
import "../styles/connexion.css";

// Redirige automatiquement l'utilisateur vers la page d'inscription TP-5.
function Inscription() {
  useEffect(() => {
    const tp5Url = import.meta.env.VITE_TP5_URL || "http://localhost:8080";
    const callbackUrl = `${window.location.origin}/auth/callback`;
    window.location.href = `${tp5Url}/register?redirect_uri=${encodeURIComponent(callbackUrl)}`;
  }, []);

  return (
    <main className="connexion-page">
      <section className="connexion-carte" aria-labelledby="titre-inscription">
        <h1 id="titre-inscription">Inscription</h1>
        <p>Redirection vers le service d'inscription…</p>
      </section>
    </main>
  );
}

export default Inscription;
