/*
| Projet: SkillHub
| Rôle du fichier: Page de retour SSO — échange le token TP-5 contre un JWT Skillhub
| Dernière modification: 2026-04-23
*/

import { useEffect, useState } from "react";
import { useNavigate, useSearchParams } from "react-router-dom";
import { echangerTokenSso } from "../services/authApi";
import { sauvegarderSession } from "../services/auth";
import "../styles/connexion.css";

// Reçoit le token TP-5 via ?token=..., l'échange contre un JWT Skillhub,
// puis redirige l'utilisateur vers son tableau de bord.
function AuthCallback() {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const [erreur, setErreur] = useState("");

  useEffect(() => {
    const token = searchParams.get("token");

    if (!token) {
      setErreur("Paramètre token manquant dans l'URL de retour.");
      return;
    }

    echangerTokenSso(token)
      .then((donnees) => {
        sauvegarderSession(donnees.token, donnees.utilisateur);
        const route =
          donnees.utilisateur?.role === "apprenant"
            ? "/dashboard/apprenant"
            : "/dashboard/formateur";
        navigate(route, { replace: true });
      })
      .catch(() => {
        setErreur("Authentification échouée. Veuillez réessayer.");
      });
  }, []);

  if (erreur) {
    return (
      <main className="connexion-page">
        <section className="connexion-carte">
          <h1>Erreur d'authentification</h1>
          <p className="connexion-erreur">{erreur}</p>
          <p className="connexion-lien-secondaire">
            <a href="/connexion">Retour à la connexion</a>
          </p>
        </section>
      </main>
    );
  }

  return (
    <main className="connexion-page">
      <section className="connexion-carte">
        <h1>Authentification en cours…</h1>
        <p>Veuillez patienter pendant la validation de votre session.</p>
      </section>
    </main>
  );
}

export default AuthCallback;
