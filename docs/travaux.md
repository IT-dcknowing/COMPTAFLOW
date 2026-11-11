# COMPTAFLOW — Suivi du chantier de modernisation

> Dépôt de travail : `IT-dcknowing/COMPTAFLOW`  
> Branche de livraison intégrée : `main`  
> Règle : une branche française par groupe fonctionnel, revue puis fusionnée dans `main`.  
> Mise à jour : 11 novembre 2026.

## Objectif général

Moderniser les interfaces COMPTAFLOW sans régression métier, sécuriser les parcours de production et améliorer en priorité les modules de scan. Conserver la charte claire de l'application (fonds blancs/bleu clair, bleu de marque, composants cohérents), éviter les interfaces sombres.

## Tableau de suivi

| Priorité | Groupe / périmètre | Travaux prévus | État |
|---|---|---|---|
| P0 | Super Admin — visibilité des pages | Catalogue filtrable par section ; masquage global ou sur plusieurs entreprises ; exceptions visibles et supprimables ; contrôle serveur des accès | Fusionné dans `main` ; migrations et vérification en environnement restent à faire |
| P0 | Super Admin — clés API IA | Fournisseurs/maisons, modèles, clés chiffrées, activation, test de connexion et journalisation sans exposer les secrets | Fusionné dans `main` ; clé propriétaire et test réel restants |
| P0 | Scan groupé `/bulk-scan` | Audit métier, contrôles tenant/exercice, erreurs explicites et refonte claire du parcours | Fusionné dans `main` ; validation réelle des documents restante |
| P0 | Scan unitaire `/ecriture-scan` | Parcours une facture, contrôle avant validation, erreurs explicites et interface harmonisée | Fusionné dans `main` ; validation réelle des documents restante |
| P1 | Analytique `/analytique` | Refonte axes, sections, ventilations, rapports ; centres de coûts, marges, répartitions, écarts et indicateurs analytiques | À auditer |
| P1 | Administration `/admin` | Inventaire complet des pages et navigation ; cohérence et regroupement | À auditer |
| P1 | Informations entreprise | Repenser la disposition et les groupes de champs de `/compagny_information` | À auditer |
| P1 | Habilitations | Tableau utilisateurs × permissions, recherche, filtres, profils et sauvegarde lisible | À auditer |
| P1 | Performance Super Admin | KPI CA, charges, trésorerie nette, salaires, postes de dépenses, TVA collectée/déductible, tendances et comparaisons ; remplacer le flux des dernières écritures | KPI de pilotage fusionnés dans `main` ; vérification des comptes à poursuivre |
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
- [x] Remonter jusqu'au dépôt Python distant : identifier le contrat `/agent/facture`, le modèle réellement configuré et la fonction d'analyse documentaire.
- [x] Corriger un défaut de cloisonnement : le scan unitaire chargeait les plans comptables et tiers sans filtre d'entreprise ; filtres tenant ajoutés.
- [x] Supprimer l'exposition des chemins et lignes PHP dans les erreurs JSON du traitement IA.
- [x] Renforcer la validation du lot scanné : comptes, tiers, journaux et exercice doivent appartenir à l'entreprise et à l'exercice sélectionnés.
- [x] Cloisonner les sections/axes analytiques utilisés dans les ventilations envoyées avec les écritures.
- [x] Cloisonner le compte général lié à un tiers lors de la création et de la modification du tiers.
- [x] Corriger le rapprochement automatique du tiers dans le scan groupé (`tiers`, `compte_general`, `numero_de_tiers`).
- [x] Transmettre le prompt métier de Laravel au service Python et préserver le statut HTTP 429 du fournisseur.
- [x] Ajouter le contrôle entreprise lors de la résolution de l'exercice sur l'affichage et le chargement AJAX des liasses SN/SMT.
- [x] Masquer les exceptions techniques retournées par les endpoints d'enregistrement unitaire et groupé.
- [x] Auditer les contrôleurs, vues, services, migrations et middleware avant de coder les modules P0.
- [x] Implémenter le centre Super Admin de visibilité des pages et de configuration des clés IA, avec test de connexion fournisseur.
- [x] Rédiger la proposition cible de refonte analytique dans \`docs/proposition-refonte-analytique.md\`.
- [x] Recomposer le dashboard Performance avec CA classe 70, charges classe 6, trésorerie nette classe 5, résultat, masse salariale classe 66, TVA collectée 443 et TVA déductible 445 ; supprimer le tableau des dernières écritures et corriger les libellés des postes de charges.
- [x] Retirer les quatre KPI de \`/dashboard-compta\` et conserver un bandeau de contexte de l'exercice actif.
- [x] Renforcer les validations tenant des axes/sections/règles analytiques et filtrer les rapports analytiques sur entreprise pour les écritures et comptes.
- [x] Fusionner les livraisons SCAN/Super Admin dans `main` du dépôt COMPTAFLOW et le moteur Python dans `main` du dépôt DC-KNOWING-AGENT-IA (11/11/2026).

## Constats techniques de l'audit initial
- Le scan unitaire `/ecriture-scan` et le scan groupé `/bulk-scan` utilisent deux vues distinctes : `accounting.scan` et `accounting.bulk_scan`.
- Les deux écrans sont servis par `EcritureComptableController`; le traitement IA passe par `IaController::traiterFacture` et `PythonAiService::analyzeInvoice`.
- Dans le dépôt Python distant, `/agent/facture` acceptait le fichier mais `analyser_document` ignorait les octets du document et ne transmettait au modèle que le nom du fichier dans un prompt texte. La réponse ne respectait pas non plus le schéma `ecriture` attendu par les vues Laravel. Un correctif est préparé dans une branche séparée pour envoyer le contenu PDF/image au modèle et renvoyer le schéma comptable attendu.
- Aucune vérification explicite du forfait/quotas de scan n'apparaît dans `traiterFacture`, les routes IA ou le modèle d'abonnement consulté. `AppSubscription` suit des abonnements et dates, mais ne définit pas de compteur/limite de scans par pack. Les quotas par forfait restent volontairement désactivés et non appliqués, conformément à la demande actuelle. Leurs plafonds sont préparés dans le Super Admin ; le compteur ne sera nécessaire que lorsque l'option sera activée.
- Le scan groupé filtre les plans comptables et tiers à l'affichage, mais son endpoint d'enregistrement de lot ne revalidait pas systématiquement chaque compte, tiers, journal et exercice transmis. Des contrôles tenant/exercice/date ont été ajoutés avant création.
- Le scan groupé enregistre les écritures dans une transaction, mais son ancienne réponse d'exception exposait le message technique : elle renvoie maintenant un message générique et journalise l'exception côté serveur.
- Le scan unitaire filtre désormais les plans comptables et tiers par entreprise.
- Le prompt IA reçoit désormais jusqu'à 500 tiers de l'entreprise, triés par numéro. L'écran conserve toutefois la liste complète des tiers pour le rapprochement local.
- Le coffre des clés IA est maintenant consommé par PythonAiService pour les appels de scan. Le scan prend le fournisseur et le modèle actifs, et transmet la clé chiffrée en base puis déchiffrée en mémoire au service Python via HTTPS.
- Une clé Anthropic avait été inscrite dans un ancien commit Python. Elle a été révoquée par le propriétaire et n'est plus présente dans le code courant ; l'ancien commit reste dans l'historique Git.

## Livraison interface Super Admin et SCAN — 11 novembre 2026
- [x] Centre Super Admin visible dans la navigation système, avec onglets pages, fournisseurs IA et quotas.
- [x] Catalogue de pages par sections avec recherche, règle générale et sélection multiple d'entreprises.
- [x] Exceptions par entreprise affichées et supprimables individuellement ; règle entreprise prioritaire sur la règle globale.
- [x] Formulaire fournisseur avec Google Gemini prérempli, modèle Gemini 2.5 Flash-Lite, stockage chiffré Laravel et activation exclusive.
- [x] Bouton de test de connexion par fournisseur, avec retour utilisateur sans exposer la clé ni les détails internes.
- [x] Interface des quotas Starter / Pro / Enterprise préparée, désactivée par défaut comme demandé ; aucun quota n'est appliqué.
- [x] Refonte claire du scan unitaire et du scan groupé ; distinction des factures non conformes, documents illisibles et exercices incompatibles.
- [x] Erreurs de quota/configuration API remplacées par une orientation vers le support technique DC-KNOWING : it.dcknowing@gmail.com et WhatsApp 07 67 13 19 93.
- [ ] Rendu visuel navigateur, tests PHP/Laravel, tests d'intégration Python et validation de vrais documents à exécuter après installation des dépendances et migrations.
- [x] Intégration Git effectuée dans `main` (COMPTAFLOW : `235c765e6dc65dcc8ef7d6122bee9bc098a8981e` ; Python : `e4f9b4b7ad75039be948211d53fd2854d17d10d6`).
- [ ] Déployer sur les serveurs web et Python, exécuter les migrations et les tests de bout en bout. Aucun accès de déploiement/SSH n'est disponible dans cette session.

## Constats analytiques initiaux
- Six écrans / rapports identifiés : axes, sections, règles de ventilation, balance analytique, grand livre analytique et résultat analytique.
- Les contrôleurs de création/modification de sections et de règles utilisaient certaines validations \`exists\` sans restriction explicite à l'entreprise active ; les contrôles tenant ont été ajoutés sur axes, sections, comptes et sections de ventilation.
- Les rapports balance et résultat analytiques ne filtraient pas explicitement les écritures et comptes par entreprise dans leurs jointures ; les filtres tenant ont été ajoutés.
- Le service de reporting joint écritures et sections ; vérifier que les deux côtés de la jointure sont systématiquement limités à l'entreprise et à l'exercice avant de publier des KPI consolidés.

## Décisions / éléments à confirmer pendant l'audit
- Inventaire exact des sections et pages à partir des routes et des menus (ne pas se limiter aux exemples).
- Règles précises des quotas par forfait du module scan et unité de consommation.
- Fournisseurs IA et modèles déjà configurés dans le projet, variables d'environnement et stockage actuel.
- Liste exacte des KPI à retirer de `/dashboard-compta`, après lecture de la page et de ses usages.


## Audit approfondi du SCAN — 11 novembre 2026

### Dépôt COMPTAFLOW — intégré dans `main`
- [x] Transmettre le prompt métier construit par Laravel au service Python.
- [x] Préserver le statut HTTP du fournisseur IA afin que le 429 soit distingué d'une indisponibilité générique.
- [x] Ne plus journaliser le contenu complet des requêtes de création de lots.
- [x] Valider les ventilations analytiques uniquement contre les sections et axes de l'entreprise active.
- [x] Valider le compte collectif d'un tiers dans le périmètre de l'entreprise à la création et à la modification.
- [x] Aligner le scan groupé sur le vrai schéma de réponse et les champs du modèle PlanTiers.
- [x] Rendre accessible la création manuelle d'un nouveau tiers depuis les lignes du scan groupé ; l'IA ne crée jamais un tiers sans confirmation de l'utilisateur.
- [x] Ajouter des tentatives progressives côté scan groupé sur les réponses 429/503 ; cela ne remplace pas le quota métier par forfait.
- [x] Externaliser l'URL du service Python dans la configuration Laravel.

### Dépôt Python — intégré dans `main`
- [x] Retirer la clé Anthropic révoquée du code source courant ; aucun secret fournisseur n'est conservé dans les fichiers Python suivis.
- [x] Le fichier .env.example ne contient plus de variable de secret fournisseur ; les clés sont gérées dans le coffre chiffré Super Admin.
- [x] Faire accepter un prompt métier à `/agent/facture`.
- [x] Transmettre le contenu réel PDF/image au modèle au lieu de ne lui envoyer que le nom du fichier.
- [x] Structurer la réponse selon le schéma comptable attendu par COMPTAFLOW.

### Restant impérativement à faire avant déploiement
- [x] Clé Anthropic historique révoquée par le propriétaire ; le secret est absent du code courant. L'historique Git conserve le commit ancien, mais la clé n'est plus valide.
- [x] Ajouter dans le Super Admin un écran de paramétrage des quotas Starter / Pro / Enterprise (factures par mois et par traitement groupé), désactivé par défaut.
- [ ] En cas d'activation future des quotas par le Super Admin, implémenter le compteur de consommation et l'application atomique des plafonds côté serveur. Pour l'instant, aucun quota n'est appliqué.
- [x] Connecter le coffre Super Admin au service Python : le fournisseur, le modèle et la clé active sont transmis via HTTPS à chaque analyse.
- [ ] Vérifier les versions installées de `langchain-anthropic` et tester les blocs multimodaux image/PDF sur l'environnement cible.
- [ ] Ajouter tests unitaires/API pour le schéma de réponse, les fichiers invalides, les erreurs 429/5xx et les accès multi-entreprises.
- [ ] Tester avec de vraies factures anonymisées et comparer les écritures suggérées aux documents.
- [x] Refaire les interfaces SCAN unitaire et groupé dans la charte claire COMPTAFLOW.

**Statut sécurité :** la clé exposée a été révoquée par le propriétaire ; elle n'est plus présente dans le code courant. L'ancien commit reste dans l'historique Git. Les corrections sont fusionnées dans `main`, mais pas encore déployées sur les serveurs.


## Compléments demandés — fournisseur API, erreurs et exercice (11 novembre 2026)

### Faits dans les branches de travail
- [x] Le scan Laravel sélectionne la seule configuration IA active du coffre Super Admin ; l'enregistrement/activation d'un fournisseur désactive les autres configurations.
- [x] Les clés restent chiffrées par le cast Laravel encrypted du modèle AiProviderCredential. Elles ne sont jamais renvoyées dans la vue.
- [x] La clé, le fournisseur et le modèle sont transmis au service Python dans des en-têtes de requête HTTPS ; les logs applicatifs n'enregistrent pas la clé ni le corps complet de la requête.
- [x] Les appels chat des agents Python utilisent également le fournisseur actif du coffre Super Admin ; aucune clé Anthropic d'environnement n'est requise.
- [x] En production, le service Laravel refuse de transmettre une clé IA si l'URL du moteur Python n'est pas en HTTPS.
- [x] Le moteur Python accepte Google Gemini (SDK google-genai) et Anthropic avec la configuration fournie par COMPTAFLOW.
- [x] Le code Python courant ne contient plus de clé API. La clé Anthropic historique a été révoquée par le propriétaire.
- [x] Réponses d'erreur distinguées : quota API, clé invalide, API non configurée/indisponible, fichier illisible, document non-facture.
- [x] Le scan groupé affiche le motif précis d'échec au lieu d'un message générique.
- [x] Le scan unitaire ne relance plus automatiquement une erreur 429 identifiée comme quota épuisé.
- [x] L'analyse refuse une facture dont la date est hors de l'exercice sélectionné et demande de changer l'exercice avant de relancer.
- [x] Le point d'enregistrement groupé revalide les dates de toutes les lignes contre les bornes de l'exercice côté serveur.
- [x] Le Super Admin contient désormais les champs de quotas mensuels et de taille de lot par forfait, avec option désactivée par défaut.

### Configuration API de test
- Fournisseur : Google Gemini (Google AI Studio)
- Modèle de test : gemini-2.5-flash-lite
- Portail de création de clé : https://aistudio.google.com/app/apikey
- La clé doit être créée depuis le compte du propriétaire et collée dans le coffre Super Admin ; elle ne peut pas être générée depuis GitHub/ChatGPT.
- Le niveau gratuit de Google peut utiliser les prompts et fichiers soumis pour améliorer ses produits et permettre une revue humaine. Utiliser uniquement des factures fictives ou anonymisées pour les essais gratuits.

### Restant avant déploiement
- [ ] Créer la clé Google AI Studio du propriétaire et l'enregistrer dans le Super Admin (action nécessitant le compte Google du propriétaire).
- [ ] Installer requirements-ai.txt dans l'environnement Python (notamment google-genai) puis redémarrer le service.
- [ ] Exécuter les migrations COMPTAFLOW sur un environnement de test puis en production après validation.
- [ ] Tester des factures PDF et images anonymisées, un document non-facture, un scan illisible, une clé invalide et un quota épuisé.
- [ ] Vérifier les erreurs 401/403/429 et la communication HTTPS en environnement cible.
- [ ] Si les quotas doivent être activés ultérieurement, implémenter le compteur de consommation et la limitation serveur avant d'autoriser l'activation effective.
- [x] Refaire les interfaces SCAN unitaire et groupé dans la charte claire COMPTAFLOW, avec messages adaptés et contacts du support.


## Intégration Git — 11 novembre 2026
- [x] COMPTAFLOW : intégration de la branche de travail dans `main`, commit `235c765e6dc65dcc8ef7d6122bee9bc098a8981e`.
- [x] DC-KNOWING-AGENT-IA : intégration de la branche de travail dans `main`, commit `e4f9b4b7ad75039be948211d53fd2854d17d10d6`.
- [x] Les PR existantes ont été fusionnées et sont closes ; aucune PR ouverte ne reste nécessaire pour ces livraisons.
- [ ] Déploiement de COMPTAFLOW et du service Python : nécessite l'accès à l'hébergement et aux commandes de déploiement, non fourni par la connexion GitHub.
- [ ] Le dépôt miroir `guysergekouassi/COMPTAFLOW` est en retard de 59 commits par rapport au dépôt de référence au moment du contrôle. Sa synchronisation n'a pas été forcée pour ne pas écraser ses propres changements.
- [ ] Les contrôles GitHub ne retournent aucun workflow CI ni statut de commit configuré pour ces deux livraisons ; tests applicatifs non certifiés.


## Correctif de l'erreur 500 Super Admin — 11 novembre 2026
- [x] Cause identifiée : la migration initiale créait `superadmin_page_rules`, alors que la convention Eloquent et la requête SQL attendent `super_admin_page_rules`.
- [x] Middleware `EnforcePageVisibility` : contrôle désormais l'existence de la table canonique avant d'interroger les règles.
- [x] Modèle `SuperAdminPageRule` : nom de table explicite `super_admin_page_rules`.
- [x] Migration corrective non destructive : renomme l'ancienne table si elle existe, ou crée la table canonique si aucune des deux n'existe.
- [ ] Exécuter `php artisan migrate --force` sur l'environnement de test puis vérifier `/bulk-scan` et le centre Super Admin. Cette action nécessite l'accès au serveur ou au terminal d'hébergement.
- [ ] Vérifier ensuite le résultat avec `php artisan migrate:status` et confirmer que `2026_11_11_000004_normaliser_nom_table_super_admin_page_rules` est exécutée.

## Dépendances Python du moteur IA — 11 novembre 2026
- [x] Compléter `requirements-ai.txt` avec les dépendances réellement importées par l'API et `agents.py` : FastAPI, Uvicorn, Pydantic, python-multipart, python-dotenv, Google GenAI, LangChain Anthropic/Core, LangChain Chroma/Text Splitters et ChromaDB.
- [ ] Recréer/mettre à jour l'environnement virtuel Python de l'hébergement et redémarrer le service Passenger/ASGI.
- [ ] Valider les versions résolues sur l'hébergement et exécuter un test de santé puis un test de scan anonymisé.
