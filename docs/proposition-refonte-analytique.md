# Proposition de refonte — Comptabilité analytique COMPTAFLOW

## Intention graphique

Rester dans le langage visuel COMPTAFLOW : fond blanc / gris bleuté très léger, bleu de marque, cartes sobres, bordures fines, tableaux lisibles, espaces généreux. Pas de thème sombre, pas de grands dégradés décoratifs. Les tableaux doivent rester la surface principale des rapports.

## Architecture proposée

### 1. Accueil Analytique — cockpit de pilotage
Une page d'entrée qui synthétise les données de l'exercice sélectionné :
- Charges affectées aux axes et sections.
- Produits / chiffre d'affaires analytique.
- Résultat et marge analytique par axe, projet, département ou agence.
- Part des charges non ventilées et taux de couverture analytique.
- Top 5 des sections les plus coûteuses et des sections les plus contributrices.
- Évolution mensuelle et comparaison avec N-1 lorsque les données existent.
- Alertes : écritures en attente, comptes de charges sans règle, ventilations incomplètes, écarts de total.

Filtres persistants : exercice, période, axe, section, journal, compte, statut d'écriture.

### 2. Paramétrage — Axes & sections réunis
Fusionner les écrans Axes et Sections dans une page à deux panneaux :
- À gauche : liste des axes (Projet, Département, Agence, Produit, Site, etc.).
- À droite : sections de l'axe sélectionné.
- Création et édition dans des panneaux latéraux/modales légères.
- Code, libellé, type, statut actif/inactif, dates de validité.
- Indicateur du nombre d'écritures et du total ventilé par section.
- Import/export Excel pour initialiser un référentiel.

Ne pas supprimer les routes historiques tant que les liens et habitudes ne sont pas migrés.

### 3. Règles de ventilation
Écran dédié en tableau :
- Compte général / plage de comptes.
- Axe et sections destinataires.
- Pourcentage par section (total obligatoire = 100 %).
- Priorité et période de validité.
- Aperçu de l'effet sur les écritures futures.
- Contrôle des comptes et sections de la même entreprise.

### 4. Rapports — un espace unique avec trois vues
Onglets : Balance analytique, Grand livre analytique, Résultat analytique.
- Une barre de filtres commune.
- Balance : axes > sections > comptes, débits, crédits, solde, comparatif.
- Grand livre : détail chronologique, pièce, journal, compte, tiers, section, débit/crédit, cumul.
- Résultat : produits, charges directes, charges indirectes imputées, marge/résultat par axe et section.
- Export Excel fidèle aux colonnes et totaux affichés, PDF en complément.

## Principes comptables à respecter

- Distinguer charges directes et indirectes.
- Les charges indirectes sont regroupées dans des centres d'analyse puis réparties selon des clés explicites.
- Si le produit métier le nécessite, prévoir la répartition primaire et secondaire, les centres auxiliaires/principaux et les unités d'œuvre.
- Pour les analyses de marge, distinguer charges variables et fixes ; présenter marge sur coûts variables et seuil de rentabilité uniquement si les données de nature de charge sont suffisamment qualifiées.
- Les écritures non ventilées ne doivent pas disparaître : elles sont visibles dans une liste de contrôle et exclues explicitement des résultats analytiques ventilés.
- Les totaux analytiques doivent être rapprochables des écritures comptables approuvées de l'exercice.

## Audit technique initial

- Routes existantes : axes, sections, règles, balance, grand livre, résultat, génération, aperçu, suppression et exports Excel/PDF.
- Vues existantes : `analytique.axes.index`, `analytique.sections.index`, `analytique.regles.index`, `analytique.reports.balance`, `analytique.reports.grand_livre`, `analytique.reports.resultat`.
- Service de calcul : `Analytique\\AnalyticalReportingService`.
- Les écrans actuels sont séparés et partagent une esthétique de cartes similaires ; l'accueil analytique et les filtres transversaux manquent.
- Plusieurs validations de contrôleurs utilisent des `exists:... ,id` sans toujours ajouter explicitement le filtre `company_id` (ex. création de section / règle). À corriger avant la refonte UI pour garantir le cloisonnement multi-entreprise.

## Étapes de réalisation

1. Auditer le service et les requêtes pour vérifier les totaux, les écritures approuvées, les filtres d'exercice et les cas de ventilations partielles.
2. Corriger les validations tenant et ajouter tests de calcul.
3. Créer le cockpit et les filtres communs.
4. Fusionner axes/sections en une interface sans casser les routes.
5. Refaire règles de ventilation.
6. Harmoniser les trois rapports et leurs exports.
7. Vérifier avec un jeu d'écritures de test : charges directes, charges indirectes, deux axes, plusieurs sections, écritures non ventilées et comparatif N-1.
