<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ServiceJwt;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class AuthController extends Controller
{
    public function __construct(private ServiceJwt $serviceJwt)
    {
    }

    /**
     * Inscription d'un utilisateur avec chiffrement AES-GCM.
     */
    public function inscription(Request $requete): JsonResponse
    {
        $donneesValidees = $requete->validate([
            'nom'          => ['required', 'string', 'max:255'],
            'email'        => ['required', 'email', 'max:255', 'unique:users,email'],
            'mot_de_passe' => ['required', 'string', 'min:8', 'regex:/^(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).+$/'],
            'role'         => ['required', 'in:formateur,apprenant'],
        ]);

        $utilisateur = User::query()->create([
            'name'     => $donneesValidees['nom'],
            'email'    => $donneesValidees['email'],
            // Chiffrement réversible pour conformité TP
            'password' => $this->chiffrerAesGcm($donneesValidees['mot_de_passe']),
            'role'     => $donneesValidees['role'],
        ]);

        $expiration = CarbonImmutable::now()->addHours(8)->timestamp;

        $jeton = $this->serviceJwt->generer([
            'sub'   => $utilisateur->id,
            'email' => $utilisateur->email,
            'role'  => $utilisateur->role,
            'iat'   => CarbonImmutable::now()->timestamp,
            'exp'   => $expiration,
        ]);

        return response()->json([
            'token'      => $jeton,
            'token_type' => 'Bearer',
            'expires_at' => $expiration,
            'utilisateur' => [
                'id'    => $utilisateur->id,
                'nom'   => $utilisateur->name,
                'email' => $utilisateur->email,
                'role'  => $utilisateur->role,
            ],
        ], 201);
    }

    /**
     * Connexion : déchiffre le mot de passe stocké pour comparaison.
     */
    public function connexion(Request $requete): JsonResponse
    {
        $donneesValidees = $requete->validate([
            'email'        => ['required', 'email'],
            'mot_de_passe' => ['required', 'string'],
        ]);

        $utilisateur = User::query()->where('email', $donneesValidees['email'])->first();

        // On déchiffre le mot de passe stocké pour comparer avec la saisie

        $motDePasseClairEnBase = $utilisateur ? $this->dechiffrerAesGcm($utilisateur->password) : null;

        // On utilise trim() pour ignorer les espaces accidentels et on vérifie si c'est null
        if (! $utilisateur || $motDePasseClairEnBase === null || trim((string)$motDePasseClairEnBase) !== trim((string)$donneesValidees['mot_de_passe'])) {
           return response()->json(['message' => 'Identifiants invalides.'], 401);
        }

        $expiration = CarbonImmutable::now()->addHours(8)->timestamp;

        $jeton = $this->serviceJwt->generer([
            'sub'   => $utilisateur->id,
            'email' => $utilisateur->email,
            'role'  => $utilisateur->role,
            'iat'   => CarbonImmutable::now()->timestamp,
            'exp'   => $expiration,
        ]);

        return response()->json([
            'token'      => $jeton,
            'token_type' => 'Bearer',
            'expires_at' => $expiration,
            'utilisateur' => [
                'id'    => $utilisateur->id,
                'nom'   => $utilisateur->name,
                'email' => $utilisateur->email,
                'role'  => $utilisateur->role,
            ],
        ]);
    }

    public function profil(Request $requete): JsonResponse
    {
        $utilisateur = $requete->user();

        return response()->json([
            'id'    => $utilisateur->id,
            'nom'   => $utilisateur->name,
            'email' => $utilisateur->email,
            'role'  => $utilisateur->role,
        ]);
    }

    public function deconnexion(Request $requete): JsonResponse
    {
        $jeton = $requete->bearerToken();

        if ($jeton) {
            try {
                $payload          = $this->serviceJwt->decoder($jeton);
                $expiration       = (int) ($payload['exp'] ?? CarbonImmutable::now()->addHours(8)->timestamp);
                $secondesRestantes = max(1, $expiration - CarbonImmutable::now()->timestamp);

                Cache::put($this->cleBlacklist($jeton), true, now()->addSeconds($secondesRestantes));
            } catch (Throwable) {
            }
        }

        return response()->json(['message' => 'Déconnexion effectuée.']);
    }
    
    /**
     * Modification du mot de passe avec déchiffrement de l'ancien.
     */
    public function modifierMotDePasse(Request $requete): JsonResponse
    {
        $utilisateur = $requete->user();

        $donneesValidees = $requete->validate([
            'ancien_mot_de_passe' => ['required', 'string'],
            'nouveau_mot_de_passe' => ['required', 'string', 'min:8', 'regex:/^(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).+$/', 'different:ancien_mot_de_passe'],
        ]);

        // Vérification du mot de passe actuel
        $ancienMotDePasseClair = $this->dechiffrerAesGcm($utilisateur->password);

        if ($ancienMotDePasseClair !== $donneesValidees['ancien_mot_de_passe']) {
            return response()->json(['message' => 'L\'ancien mot de passe est incorrect.'], 403);
        }

        // Chiffrement du nouveau mot de passe
        $utilisateur->password = $this->chiffrerAesGcm($donneesValidees['nouveau_mot_de_passe']);
        $utilisateur->save();

        return response()->json(['message' => 'Mot de passe modifié avec succès.']);
    }

    public function validateToken(Request $requete): JsonResponse
    {
        $jeton = $requete->bearerToken();

        if (! $jeton) {
            return response()->json(['valid' => false, 'message' => 'Jeton manquant.'], 401);
        }

        if (Cache::has($this->cleBlacklist($jeton))) {
            return response()->json(['valid' => false, 'message' => 'Jeton blacklisté.'], 401);
        }

        try {
            $payload      = $this->serviceJwt->decoder($jeton);
            $idUtilisateur = (int) ($payload['sub'] ?? 0);

            $utilisateur = User::query()->find($idUtilisateur);

            if (! $utilisateur) {
                return response()->json(['valid' => false, 'message' => 'Utilisateur introuvable.'], 401);
            }

            return response()->json([
                'valid' => true,
                'user'  => [
                    'id'    => $utilisateur->id,
                    'nom'   => $utilisateur->name,
                    'email' => $utilisateur->email,
                    'role'  => $utilisateur->role,
                ],
            ]);
        } catch (Throwable) {
            return response()->json(['valid' => false, 'message' => 'Jeton invalide ou expiré.'], 401);
        }
    }

    /**
     * Échange un token TP-5 contre un JWT Skillhub (SSO).
     * Valide le token via l'endpoint /api/me de TP-5, puis crée ou récupère
     * l'utilisateur dans la base Skillhub, et émet un JWT local.
     */
    public function ssoTp5(Request $requete): JsonResponse
    {
        $donneesValidees = $requete->validate([
            'tp5_token' => ['required', 'string'],
        ]);

        $tp5Url = env('TP5_AUTH_URL', 'http://localhost:8080');

        try {
            $reponse = Http::withToken($donneesValidees['tp5_token'])
                ->timeout(5)
                ->get("{$tp5Url}/api/me");
        } catch (Throwable) {
            return response()->json(['message' => 'Service d\'authentification TP-5 inaccessible.'], 503);
        }

        if (!$reponse->successful()) {
            return response()->json(['message' => 'Token TP-5 invalide ou expiré.'], 401);
        }

        $profil = $reponse->json();
        $email  = $profil['email'] ?? null;

        if (!$email) {
            return response()->json(['message' => 'Profil TP-5 invalide.'], 422);
        }

        $nomParDefaut = $profil['name'] ?? explode('@', $email)[0];
        $roleParDefaut = $profil['role'] ?? 'apprenant';
        // On valide que le rôle est accepté par Skillhub
        if (!in_array($roleParDefaut, ['formateur', 'apprenant'], true)) {
            $roleParDefaut = 'apprenant';
        }

        // Créer ou récupérer l'utilisateur dans la base Skillhub
        $utilisateur = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name'     => $nomParDefaut,
                'password' => $this->chiffrerAesGcm(bin2hex(random_bytes(16))),
                'role'     => $roleParDefaut,
            ]
        );

        $expiration = CarbonImmutable::now()->addHours(8)->timestamp;

        $jeton = $this->serviceJwt->generer([
            'sub'   => $utilisateur->id,
            'email' => $utilisateur->email,
            'role'  => $utilisateur->role,
            'iat'   => CarbonImmutable::now()->timestamp,
            'exp'   => $expiration,
        ]);

        return response()->json([
            'token'       => $jeton,
            'token_type'  => 'Bearer',
            'expires_at'  => $expiration,
            'utilisateur' => [
                'id'    => $utilisateur->id,
                'nom'   => $utilisateur->name,
                'email' => $utilisateur->email,
                'role'  => $utilisateur->role,
            ],
        ]);
    }

    private function cleBlacklist(string $jeton): string
    {
        return 'jwt_blacklist:'.hash('sha256', $jeton);
    }
    
    /**
     * Chiffre en AES-256-GCM. 
     * Format : base64(iv):base64(ciphertext):base64(tag)
     */
    private function chiffrerAesGcm(string $motDePasseClair): string
    {
        $cle = hash('sha256', env('APP_MASTER_KEY', 'cle_par_defaut'), true);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-gcm'));
        $tag = "";
        
        $ciphertext = openssl_encrypt($motDePasseClair, 'aes-256-gcm', $cle, OPENSSL_RAW_DATA, $iv, $tag);
        
        return base64_encode($iv) . ':' . base64_encode($ciphertext) . ':' . base64_encode($tag);
    }

    /**
     * Déchiffre une chaîne AES-256-GCM.
     */
    private function dechiffrerAesGcm($motDePasseChiffre): ?string
{
    // 1. On vérifie que c'est bien une chaîne de caractères non vide
    if (!is_string($motDePasseChiffre) || empty($motDePasseChiffre)) {
        return null;
    }

    // 2. On vérifie la présence des 3 parties (iv:ciphertext:tag)
    $parties = explode(':', $motDePasseChiffre);
    if (count($parties) !== 3) {
        return null;
    }

    $cle = hash('sha256', env('APP_MASTER_KEY', 'cle_par_defaut'), true);

    try {
        // 3. On force le cast en string pour éviter l'erreur "array given"
        $iv         = base64_decode((string)$parties[0], true);
        $ciphertext = base64_decode((string)$parties[1], true);
        $tag        = base64_decode((string)$parties[2], true);

        // 4. Si un des decodages base64 a échoué
        if ($iv === false || $ciphertext === false || $tag === false) {
            return null;
        }

        return openssl_decrypt($ciphertext, 'aes-256-gcm', $cle, OPENSSL_RAW_DATA, $iv, $tag);
    } catch (\Throwable $e) {
        return null;
    }
}
}