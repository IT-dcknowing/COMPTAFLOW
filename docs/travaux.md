# COMPTAFLOW — Suivi du chantier de modernisation

> Dépôt de travail : `IT-dcknowing/COMPTAFLOW`  
> Branche de suivi initiale : `superadmin/masquage-pages-et-cles-ia`  
> Règle : une branche française par groupe fonctionnel, revue puis fusionnée dans `main`.  
> Mise à jour : 11 novembre 2026.

## Objectif général

Moderniser les interfaces COMPTAFLOW sans régression métier, sécuriser les parcours de production et améliorer en priorité les modules de scan. Conserver la charte claire de l'application (fonds blancs/bleu clair, bleu de marque, composants cohérents), éviter les interfaces sombres.

## Tableau de suivi

| Priorité | Groupe / périmètre | Travaux prévus | État |
|---|---|---|---|
| P0 | Super Admin — visibilité des pages | Inventaire des routes/pages par section ; masquage global ou par entreprise ; activation/désactivation ; motif/commentaire ; contrôle serveur des accès et navigation cohérente | À faire |
| P0 | Super Admin — clés API IA | Fournisseurs/maisons, modèles, clés chiffrées, activation, clé par fournisseur/modèle, test de connexion, permissions et journalisation | À faire |
| P0 | Scan groupé `/bulk-scan` | Audit UI + contrôleur + services IA/OCR + tiers + écritures créées + règles d'exercice + paramétrage fournisseur/modèle ; refonte visuelle | À auditer |
| P0 | Scan unitaire `/ecriture-scan` | Comparaison avec le scan groupé ; cohérence métier et UX ; conservation du parcours une facture | À auditer |
| P1 | Analytique `/analytique` | Refonte axes, sections, ventilations, rapports ; centres de coûts, marges, répartitions, écarts et indicateurs analytiques | À auditer |
| P1 | Administration `/admin` | Inventaire complet des pages et navigation ; cohérence et regroupement | À auditer |
| P1 | Informations entreprise | Repenser la disposition et les groupes de champs de `/compagny_information` | À auditer |
| P1 | Habilitations | Tableau utilisateurs × permissions, recherche, filtres, profils et sauvegarde lisible | À auditer |
| P1 | Performance Super Admin | KPI CA, charges, trésorerie nette, salaires, postes de dépenses, TVA collectée/déductible, tendances et comparaisons ; remplacer le flux des dernières écritures | À auditer |
| P1 | Dashboard comptable | Retirer les KPI identifiés comme inutiles sur `/dashboard-compta` (inventaire à faire avant suppression) | À auditer |
| P1 | Reporting résultat | Présentation financière soignée type feuille Excel, totaux, sections, comparatifs, export | À auditer |
| P1 | Notifications | Corriger la relation expéditeur nullable et toute autre donnée orpheline ; écran de maintenance propre en cas d'indisponibilité ; contact support et lien WhatsApp | À corriger |
| P2 | Rapprochement bancaire | Refonte UI sans présumer que le workflow métier est terminé ; distinguer clairement étapes disponibles/en cours | À auditer |
| P1 | Liasses SN et SMT | Diagnostiquer CSS/conteneur/zoom/tableau qui réduit la feuille ; rétablir une feuille pleine largeur/écran et vérifier responsive | À auditer |

## Règles techniques et de sécurité

- Ne jamais exposer une exception, trace SQL, chemin serveur, cookie ou détail interne à un utilisateur final.
- Une page masquée doit aussi être protégée côté serveur : retirer le lien du menu ne suffit pas.
- Prévoir une priorité explicite : masquage global, puis exceptions par entreprise (afficher/masquer), sans ambiguïté.
- Les clés API sont chiffrées au repos, jamais renvoyées en clair après enregistrement, jamais inscrites dans les logs.
- Les secrets existants dans `.env` restent compatibles pendant la migration.
- Le scan doit appliquer les limites du forfait/tiers avant de lancer OCR/IA et avant toute création ; éviter les doublons et rendre les erreurs partielles explicites.
- Ne pas modifier le schéma métier ou supprimer des KPI/champs sans avoir identifié leurs usages.
- Tests ciblés, revue des routes, permissions et validations avant chaque fusion.
- La fusion GitHub ne signifie pas que le déploiement ou les tests en production sont effectués.

## Journal d'avancement

### 11 novembre 2026 — Initialisation et première livraison technique
- [x] Confirmation du dépôt de référence et des conventions Git.
- [x] Inventaire initial des routes : `/bulk-scan`, `/ecriture-scan`, `/analytique/*`, `/compagny_information`, `/admin/habilitations`, `/rapprochement/*`, `/reporting/resultat`, `/notifications`, `/dashboard-compta`.
- [x] Première version du centre Super Admin (catalogue de routes par sections, règles globales/entreprise, gestion initiale des clés IA chiffrées).
- [x] Protection serveur des routes masquées (règle entreprise prioritaire sur la règle globale, Super Admin exempté).
- [x] Corriger l'affichage des notifications lorsque le compte expéditeur a été supprimé (relation nullable).
- [x] Ajouter une page d'erreur 500 publique, sans détails techniques, avec e-mail du support et lien WhatsApp.
- [x] Premier audit des deux contrôleurs d'affichage du scan et du traitement IA.
- [x] Corriger un défaut de cloisonnement : le scan unitaire chargeait les plans comptables et tiers sans filtre d'entreprise ; filtres tenant ajoutés.
- [x] Supprimer l'exposition des chemins et lignes PHP dans les erreurs JSON du traitement IA.
- [x] Renforcer la validation du lot scanné : comptes, tiers, journaux et exercice doivent appartenir à l'entreprise et à l'exercice sélectionnés.
- [x] Masquer les exceptions techniques retournées par les endpoints d'enregistrement unitaire et groupé.
- [ ] Auditer les contrôleurs, vues, services, migrations et middleware avant de coder les modules P0.
- [ ] Implémenter le centre Super Admin de visibilité des pages et de configuration des clés IA.
- [x] Rédiger la proposition cible de refonte analytique dans \`docs/proposition-refonte-analytique.md\`.
- [ ] Poursuivre les groupes fonctionnels avec branches dédiées, PR et fusion après vérification.

## Constats techniques de l'audit initial
- Le scan unitaire `/ecriture-scan` et le scan groupé `/bulk-scan` utilisent deux vues distinctes : `accounting.scan` et `accounting.bulk_scan`.
- Les deux écrans sont servis par `EcritureComptableController`; le traitement IA passe par `IaController::traiterFacture` et `PythonAiService::analyzeInvoice`.
- Dans `IaController`, le contexte du plan comptable, des tiers et des mappings est construit, et un prompt est généré, mais cet ensemble n'est pas transmis à `PythonAiService::analyzeInvoice` : ce service n'envoie actuellement que le fichier au service Python distant. Il faut confirmer le contrat de l'API distante avant de corriger ce point.
- Aucune vérification explicite du forfait/quotas de scan n'apparaît dans la méthode Laravel `traiterFacture` ni dans les routes IA consultées. Le respect des tiers ne peut donc pas être certifié sans audit du service Python et du système d'abonnement ; c'est un point bloquant à vérifier avant de déclarer le scan conforme.
- Le scan groupé filtre les plans comptables et tiers à l'affichage, mais son endpoint d'enregistrement de lot ne revalidait pas systématiquement chaque compte, tiers, journal et exercice transmis. Des contrôles tenant/exercice/date ont été ajoutés avant création.
- Le scan groupé enregistre les écritures dans une transaction, mais son ancienne réponse d'exception exposait le message technique : elle renvoie maintenant un message générique et journalise l'exception côté serveur.
- Le scan unitaire filtre désormais les plans comptables et tiers par entreprise.
- Le stockage des clés IA créé dans cette première version n'est pas encore consommé par `PythonAiService` ni raccordé à `VertexAiService`. L'écran prépare la gouvernance des clés, mais le routage effectif des appels par fournisseur/modèle reste à développer.

## Constats analytiques initiaux
- Six écrans / rapports identifiés : axes, sections, règles de ventilation, balance analytique, grand livre analytique et résultat analytique.
- Les contrôleurs de création de sections et de règles utilisent certaines validations \`exists\` sans restriction explicite à l'entreprise active ; le contrôle tenant doit être renforcé.
- Le service de reporting joint écritures et sections ; vérifier que les deux côtés de la jointure sont systématiquement limités à l'entreprise et à l'exercice avant de publier des KPI consolidés.

## Décisions / éléments à confirmer pendant l'audit
- Inventaire exact des sections et pages à partir des routes et des menus (ne pas se limiter aux exemples).
- Règles précises des quotas par tiers du module scan et unité de consommation.
- Fournisseurs IA et modèles déjà configurés dans le projet, variables d'environnement et stockage actuel.
- Liste exacte des KPI à retirer de `/dashboard-compta`, après lecture de la page et de ses usages.
