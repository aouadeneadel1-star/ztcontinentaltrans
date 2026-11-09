# ZT Continental Trans — Gestion de flotte (React + Supabase)

Application web de gestion de flotte : camions (propriété ou location avec alertes de fin de contrat et renouvellement),
contrôles photo matin/soir, signalements, documents, entretiens, **tournées envoyées par WhatsApp**, rippeurs, historique et exports CSV.
Trois rôles : administrateur, responsable de flotte, conducteur (espace mobile).

- **Front** : React 18 + Vite + React Router (`src/`)
- **Base, authentification, fichiers** : Supabase (PostgreSQL + RLS, Storage privé)
- **Fonctions serveur** : Supabase Edge Functions (`supabase/functions/`)
  - `send-dispatch` : valide un planning et envoie les messages WhatsApp
  - `sheet` : lien secret de téléchargement des feuilles de tournée
  - `admin-users` : création et gestion des comptes (administrateur uniquement)
- **Hébergement** : Vercel (`vercel.json` : redirection SPA et en-têtes de sécurité)

## Première connexion
Compte créé à l'installation : `admin@ztcontinentaltrans.com` — mot de passe provisoire `ChangeMe!2026`, à changer immédiatement (demandé à la première connexion).
Ensuite : *Utilisateurs* → créer les responsables et conducteurs ; *Camions* → ajouter la flotte et affecter les conducteurs ; *Rippeurs* → nom, prénom, WhatsApp.

## Envoi WhatsApp automatique
Sans configuration, les messages sont préparés et un bouton « Ouvrir WhatsApp » permet de les envoyer en un clic.
Pour l'envoi automatique (API WhatsApp Business Cloud de Meta) :
1. Créer une application sur developers.facebook.com, ajouter WhatsApp et enregistrer le numéro de l'entreprise ; générer un jeton permanent.
2. Créer un modèle de message (catégorie *Utilitaire*, langue *Français*) nommé `planning_tournee` :
   `Bonjour {{1}}, votre planning du {{2}} : camion {{3}}. Équipe : {{4}}. Missions : {{5}}. Détails : {{6}}`
   et le faire approuver par Meta.
3. Dans Supabase → Edge Functions → Secrets, ajouter `WHATSAPP_TOKEN` et `WHATSAPP_PHONE_NUMBER_ID`
   (facultatifs : `WHATSAPP_TEMPLATE_NAME`, `WHATSAPP_TEMPLATE_LANG`, `WHATSAPP_MODE=text`, `WHATSAPP_DEFAULT_COUNTRY`).

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
