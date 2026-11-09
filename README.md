# ZT Continental Trans — Gestion de flotte (React + Supabase)

Application web de gestion de flotte : camions (propriété ou location avec alertes de fin de contrat et renouvellement),
contrôles photo matin/soir, signalements, documents, entretiens, **tournées envoyées par e-mail**, rippeurs, historique et exports CSV.
Trois rôles : administrateur, responsable de flotte, conducteur (espace mobile).

- **Front** : React 18 + Vite + React Router (`src/`)
- **Base, authentification, fichiers** : Supabase (PostgreSQL + RLS, Storage privé)
- **Fonctions serveur** : Supabase Edge Functions (`supabase/functions/`)
  - `send-dispatch` : valide un planning et envoie les e-mails
  - `sheet` : lien secret de téléchargement des feuilles de tournée
  - `signup` : création de compte par lien d'invitation
  - `admin-users` : modification et désactivation des comptes (administrateur uniquement)
- **Hébergement** : Vercel (`vercel.json` : redirection SPA et en-têtes de sécurité)

## Comptes : lien d'invitation
L'administrateur ne crée pas de mot de passe. Dans *Utilisateurs* → **Inviter un conducteur** : il obtient un lien (valable 7 jours, à usage unique) qu'il envoie à la personne. Elle ouvre le lien, renseigne son nom, son e-mail et son mot de passe, et son compte est créé et actif. Ensuite l'administrateur affecte le camion (*Camions* → fiche du camion) et envoie les tournées.
Le compte administrateur d'origine : `admin@ztcontinentaltrans.com`, mot de passe provisoire `ChangeMe!2026` (à changer dès la première connexion).

## Tournées envoyées par e-mail
*Tournées* → choisir la date, le conducteur, le camion, jusqu'à 2 rippeurs et les missions (tournée ou course, client, ville, feuille PDF/photo). L'aperçu montre l'e-mail de chaque personne ; **Valider** l'envoie : équipe, camion, missions et un bouton « Télécharger » par feuille.
Les rippeurs (*Rippeurs*) n'ont pas de compte : nom, prénom et e-mail.
Envoi par SMTP (`carambole.o2switch.net`, port 465, `admin@ztcontinentaltrans.com`). Le mot de passe est lu dans le secret `SMTP_PASSWORD` des Edge Functions, ou à défaut dans la table privée `app_secrets` (inaccessible depuis le navigateur).

## Développement
```
npm install
npm run dev      # http://localhost:5173
npm run build
```
Les variables `VITE_SUPABASE_URL` et `VITE_SUPABASE_ANON_KEY` (clé publique) sont dans `.env.production`.
La sécurité repose sur les règles RLS de `supabase/migrations/001_init.sql` : un conducteur ne voit que son camion,
ses contrôles et ses plannings validés ; les feuilles de tournée sont dans un stockage privé.

## Alertes
Le tableau de bord affiche les contrôles manquants, signalements ouverts, fins de location (60 j), documents qui expirent (30 j) et entretiens à prévoir (15 j).
