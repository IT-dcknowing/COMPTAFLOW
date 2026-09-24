# COURS — Comment COMPTAFLOW transforme des écritures en états comptables

*Dernière mise à jour : 24 septembre 2026*

Ce document explique, sans jargon informatique, comment l'application passe de la
saisie d'une écriture à la balance, au compte de résultat, au tableau des flux de
trésorerie et à la liasse fiscale. Il dit aussi, franchement, **ce qui fonctionne
et ce qui reste à construire**.

Il se lit dans l'ordre. Chaque notion est illustrée par un exemple chiffré.

---

## Table des matières

1. [La matière première : la ligne d'écriture](#1-la-matière-première--la-ligne-décriture)
2. [Les neuf classes de comptes](#2-les-neuf-classes-de-comptes)
3. [Deux façons de lire la comptabilité](#3-deux-façons-de-lire-la-comptabilité)
4. [Le compte de résultat](#4-le-compte-de-résultat)
5. [Le tableau des flux de trésorerie](#5-le-tableau-des-flux-de-trésorerie)
6. [Le poste de trésorerie](#6-le-poste-de-trésorerie)
7. [Le numéro de saisie](#7-le-numéro-de-saisie)
8. [Les liasses fiscales](#8-les-liasses-fiscales)
9. [État des lieux : fait, à faire](#9-état-des-lieux--fait-à-faire)

---

## 1. La matière première : la ligne d'écriture

Tout part d'une seule table : `ecriture_comptables`. **Une ligne = un montant sur
un compte.** Rien d'autre n'existe en base ; tous les états sont des façons
différentes de relire ces lignes.

Ce que porte une ligne :

| Colonne | Ce que c'est | Exemple |
|---|---|---|
| `date` | date de l'opération | 2026-03-15 |
| `code_journal_id` | le journal | CAI1 (caisse) |
| `plan_comptable_id` | **le compte** | 57100000 CAISSE |
| `debit` / `credit` | le montant, d'un seul côté | 100 000 / 0 |
| `n_saisie` | numéro de la **pièce** | ECR-150326-000001 |
| `n_saisie_user` | numéro côté utilisateur | CPT-AG-150326-000001 |
| `description_operation` | le libellé | VENTE COMPTANT |
| `reference_piece` | la référence du justificatif | FACT-2026-042 |
| `poste_tresorerie_id` | le poste de trésorerie (voir §6) | CAISSE PRINCIPALE |
| `is_ran` | report à nouveau (solde d'ouverture) | oui / non |

### La pièce

Une **pièce comptable**, c'est un groupe de lignes qui racontent une seule
opération, et qui s'équilibrent : autant au débit qu'au crédit.

Une vente de 100 000 encaissée en caisse :

| Compte | Débit | Crédit |
|---|---|---|
| 57100000 CAISSE | 100 000 | |
| 70100000 VENTES | | 100 000 |

Deux lignes, un seul numéro de saisie, total débit = total crédit. C'est ça, une
pièce. **C'est le numéro de saisie qui dit où une pièce commence et où elle
s'arrête.** Retiens-le, on y revient au §7.

---

## 2. Les neuf classes de comptes

Le premier chiffre du numéro de compte dit la nature. C'est la clé de tout : les
états ne « comprennent » pas la comptabilité, ils lisent ce premier chiffre.

| Classe | Nature | Exemples |
|---|---|---|
| **1** | Capitaux, emprunts, dettes financières | 101 Capital · 162 Emprunt · 14 Subventions d'investissement |
| **2** | Immobilisations | 241 Matériel · 231 Bâtiments · **28 Amortissements** · **29 Dépréciations** |
| **3** | Stocks | 311 Marchandises · 331 Matières |
| **4** | Tiers | 401 Fournisseurs · 411 Clients · 44 État · **481 Fournisseurs d'investissement** |
| **5** | **Trésorerie** | 521 Banque · 571 Caisse |
| **6** | Charges | 601 Achats · 661 Salaires · **68 Dotations** |
| **7** | Produits | 701 Ventes · 706 Services · **78 Reprises** |
| **8** | Hors activités ordinaires (H.A.O.) | 81 Valeurs comptables de cession · 82 Produits de cession |
| **9** | Comptes analytiques | — |

### Trois pièges à connaître

**Les comptes en gras ne sont pas ce que leur classe laisse croire.**

- **28 et 29** sont dans la classe 2, mais ce ne sont pas des immobilisations :
  ce sont les amortissements et les dépréciations. **Aucun argent ne bouge quand
  on amortit une machine.** Un état qui les compterait comme un investissement
  inventerait une sortie d'argent qui n'a jamais eu lieu.
- **68, 69, 78, 79** sont des dotations et des reprises. Même chose : écriture
  comptable, zéro mouvement de trésorerie.
- **481 Fournisseurs d'investissement** est en classe 4 avec les fournisseurs
  ordinaires, mais il porte un **investissement**. Quand tu règles un 481, c'est
  de l'investissement, pas de l'exploitation.

Ces trois pièges expliquent la plupart des erreurs qu'on a corrigées.

---

## 3. Deux façons de lire la comptabilité

C'est **la** notion centrale de ce cours. Il y a deux familles d'états, et elles
ne lisent pas la même chose.

### Famille 1 — lire PAR COMPTE

La balance, le grand livre, le bilan, le compte de résultat.

Le principe : on additionne les débits et les crédits **compte par compte**. On
se moque de savoir quelle ligne va avec quelle autre.

```
Compte 57100000 CAISSE   → total débit 100 000, total crédit 70 000
Compte 70100000 VENTES   → total crédit 100 000
Compte 60500000 ACHATS   → total débit  70 000
```

Ces états ne regardent jamais le numéro de saisie. **Ils sont donc insensibles à
tout problème de numérotation.** C'est important : même quand les numéros étaient
cassés, ta balance et ton bilan étaient justes.

### Famille 2 — lire PAR PIÈCE

Le tableau des flux de trésorerie, et lui seul.

Le principe : pour chaque pièce, il faut savoir **pourquoi** l'argent a bougé. Et
le « pourquoi » n'est pas sur la ligne de caisse — il est sur sa **contrepartie**.

```
Pièce 1 : caisse +100 000 ... contrepartie 701 VENTES → une vente
Pièce 2 : caisse  −40 000 ... contrepartie 241 MATÉRIEL → un investissement
```

La ligne de caisse est identique dans les deux cas : un montant sur le compte
571. Seule la contrepartie distingue une vente d'un achat de machine. **Et pour
savoir quelle contrepartie va avec quelle ligne de caisse, il faut connaître la
pièce.** D'où le numéro de saisie.

### Pourquoi cette distinction compte

Quand les numéros de saisie ont été cassés par un import, **seul le TFT a été
faussé**. La balance, le grand livre, le bilan et le compte de résultat sont
restés justes, parce qu'ils additionnent par compte.

C'est exactement ce qu'on a mesuré : une même comptabilité montée deux fois, une
fois avec des numéros distincts, une fois avec un numéro partagé.

```
Balance                     : identique dans les deux cas
Tableau des flux, numéros distincts : encaissements 100 000, décaissements 70 000
Tableau des flux, numéro partagé    : encaissements  30 000, décaissements      0
```

---

## 4. Le compte de résultat

Le compte de résultat lit **par compte**. Il ne retient que les classes 6, 7 et 8,
et il les empile en huit soldes successifs — les « soldes intermédiaires de
gestion ». Chaque solde part du précédent.

| # | Solde | Formule |
|---|---|---|
| 1 | **Marge commerciale** | ventes de marchandises (701) − achats (601) ± variation de stock (6031) |
| 2 | **Valeur ajoutée** | marge + production (70 hors 701, 72, 73) − consommations (602, 604-608, 61, 62, 63) |
| 3 | **Excédent brut d'exploitation** | VA + subventions (71) − impôts et taxes (64) − personnel (66) |
| 4 | **Résultat d'exploitation** | EBE + reprises (75, 791, 798) + transferts (781) − dotations (65, 681, 691) |
| 5 | **Résultat financier** | produits financiers (77, 787, 797) − charges financières (67, 687, 697) |
| 6 | **Résultat des activités ordinaires** | solde 4 + solde 5 |
| 7 | **Résultat H.A.O.** | produits H.A.O. (82, 84, 86, 88) − charges H.A.O. (81, 83, 85) |
| 8 | **Résultat net** | solde 6 + solde 7 − impôt sur le résultat (89) |

### Exemple

Une entreprise de négoce, sur un mois :

```
701 Ventes de marchandises        12 000 000  (crédit)
601 Achats de marchandises         7 000 000  (débit)
6031 Variation de stock              500 000  (débit — le stock a baissé)
622 Locations                        800 000  (débit)
661 Salaires                       2 000 000  (débit)
681 Dotations aux amortissements     400 000  (débit)
```

```
Marge commerciale   = 12 000 000 − 7 000 000 − 500 000 = 4 500 000
Valeur ajoutée      = 4 500 000 − 800 000              = 3 700 000
EBE                 = 3 700 000 − 2 000 000            = 1 700 000
Résultat d'exploit. = 1 700 000 − 400 000              = 1 300 000
Résultat net        = 1 300 000
```

Remarque bien la dotation de 400 000 : elle réduit le résultat, **mais aucun
franc n'est sorti de la banque**. C'est précisément la différence entre le
résultat et la trésorerie, et c'est tout l'objet du chapitre suivant.

> **Où c'est dans le code** : `AccountingReportingService::getSIGData`.

---

## 5. Le tableau des flux de trésorerie

### 5.1 Ce que le TFT cherche à dire

Le compte de résultat dit : *« j'ai gagné 1 300 000 ce mois-ci »*.
Le TFT dit : *« mais mon compte en banque, lui, a bougé de combien, et pourquoi ? »*

Les deux ne coïncident jamais, pour deux raisons :

1. **Des charges et des produits sans argent** : les dotations, les reprises.
2. **Du décalage** : une vente facturée en mars, encaissée en mai.

Le TFT range tous les mouvements d'argent en **trois sections** :

| Section | Ce qu'on y met | Exemples |
|---|---|---|
| **I. Opérationnelle** (ou exploitation) | l'activité courante | encaissement client, paiement fournisseur, salaires, impôts |
| **II. Investissement** | l'outil de travail | achat d'une machine, vente d'un véhicule |
| **III. Financement** | d'où vient l'argent | apport en capital, déblocage d'emprunt, remboursement, dividendes versés |

**« Opérationnelle » et « exploitation » sont le même mot.** La section I n'est
liée à aucune classe de comptes en particulier : c'est le **cas par défaut**, ce
qui reste quand ce n'est ni de l'investissement ni du financement.

### 5.2 Les trois tableaux de l'application

Attention, il y en a trois, et ils ne se ressemblent pas.

| Menu | Adresse | Méthode |
|---|---|---|
| **Flux de Trésorerie (TFT)** | `/reporting/tft` | section I indirecte, sections II et III directes |
| **TFT Mensuel** | `/reporting/tft-personalized` | tout en direct, mois par mois |
| Liasse fiscale, page 9 | via la liasse SN | *voir §8 — mécanisme séparé* |

### 5.3 Section I — la méthode indirecte (CAF + BFR)

On ne regarde pas la banque. On part de ce que l'activité a produit, et on
corrige de ce qui n'est pas encore passé en banque. Deux étapes.

**Étape A — la CAF, « capacité d'autofinancement »**

| Compte | Ce qu'on prend | Où |
|---|---|---|
| classe **7** sauf 78 et 79 | le **crédit** | Produits encaissables (+) |
| classe **6** sauf 68 et 69 | le **débit** | Charges décaissables (−) |

`CAF = produits − charges`

Les 68/69/78/79 sont exclus : ce sont les dotations et reprises, qui ne
correspondent à aucun mouvement d'argent.

**Étape B — la variation du BFR, « besoin en fonds de roulement »**

La CAF dit ce qui a été *facturé*. Pas ce qui est *rentré*. Le BFR fait la
correction, sur les classes 3 (stocks) et 4 (tiers) :

```
flux BFR = crédit − débit
```

Lis-le ainsi : **une créance qui monte, c'est de l'argent qui n'est pas rentré**
— donc un moins. Une dette qui monte, c'est de l'argent qu'on n'a pas encore
payé — donc un plus.

**Exemple : une vente à crédit de 5 000 000**

| Compte | Débit | Crédit |
|---|---|---|
| 411 Clients | 5 000 000 | |
| 701 Ventes | | 5 000 000 |

```
CAF            : + 5 000 000   (le produit est là)
Variation BFR  : − 5 000 000   (la créance a monté d'autant)
Flux opérationnel net = 0
```

Juste : la banque n'a rien reçu. Le mois où le client paie, le 411 se solde, le
BFR repasse à `+5 000 000`, et le flux opérationnel devient positif.

**Ce qui a été corrigé** : les comptes `481/482/485` (fournisseurs et créances
d'immobilisations) et `461/465` (associés) sont de classe 4, mais ils portent un
investissement ou un financement. Ils sont maintenant **exclus du BFR**, sinon ils
comptaient deux fois.

### 5.4 Sections II et III — la trésorerie réelle

Ici, on ne raisonne plus par le résultat. **On ne compte que ce qui a bougé en
banque ou en caisse.** Quatre temps.

**Temps 1 — reconstituer la pièce.** On regroupe par numéro de saisie, puis on
redécoupe en suivant l'équilibre : dès que le cumul débit − crédit revient à
zéro, une pièce se referme.

**Temps 2 — écarter les virements internes.** Caisse → banque n'est ni une
entrée ni une sortie. On solde chaque compte de trésorerie à l'intérieur de la
pièce, et la part qui entre d'un côté et sort de l'autre est retirée.

**Temps 3 — lire le montant et le sens sur la ligne.** Débit sur un compte de
classe 5 = **encaissement**. Crédit = **décaissement**. **En brut, sans
compenser.**

**Temps 4 — demander la section à la contrepartie**, au prorata s'il y en a
plusieurs :

| Contrepartie | Section |
|---|---|
| 20 à 27, **481/482/485**, 822/826/827 | Investissement |
| 10, 14, 16, 17, 18, 461, 465 | Financement |
| tout le reste | Opérationnelle — déjà portée par la CAF, donc ignorée ici |

Comptes écartés (aucun mouvement d'argent) : 19, 28, 29, 68, 69, 78, 79, 81, 85.

### 5.5 Exemples concrets

**Exemple A — une pièce qui paie deux choses à la fois**

| Compte | Débit | Crédit |
|---|---|---|
| 401 Fournisseur | 30 000 | |
| 241 Matériel | 70 000 | |
| 571 Caisse | | 100 000 |

L'ancien calcul basculait les 100 000 en investissement. Le nouveau répartit au
prorata : **30 000 en exploitation, 70 000 en investissement**.

**Exemple B — une machine achetée à crédit**

Mars, la facture :

| Compte | Débit | Crédit |
|---|---|---|
| 241 Matériel | 5 000 000 | |
| 481 Fournisseur d'investissement | | 5 000 000 |

Avril, le règlement :

| Compte | Débit | Crédit |
|---|---|---|
| 481 Fournisseur d'investissement | 5 000 000 | |
| 521 Banque | | 5 000 000 |

**Avant**, l'application lisait le mouvement du compte 241 : elle affichait un
décaissement d'investissement de 5 000 000 **en mars**, alors que la banque
n'avait pas bougé — et le règlement d'avril n'apparaissait nulle part.

**Après** : rien en mars, 5 000 000 de décaissement d'investissement en avril.

C'est ce défaut qui expliquait le `−13 500 000` d'A & I VENTURE, devenu
`+2 454 824` après correction.

**Exemple C — le contrôle qui prouve tout**

Emprunt encaissé 10 000 000, matériel réglé 5 000 000, achat payé 300 000. La
banque a bougé de `+4 700 000`. Le tableau doit annoncer `+4 700 000`.

**C'est le seul contrôle qui compte** : la « variation totale » du TFT doit égaler
la variation réelle de tes comptes de classe 5 sur la période. Si les deux ne
tombent pas, quelque chose ne va pas.

### 5.6 Les codes SYSCOHADA

Cinq codes permettent de **forcer** le classement d'un mouvement, en passant
outre la lecture de la contrepartie :

| Code | Signification |
|---|---|
| `INV_ACQ` | acquisition d'immobilisation |
| `INV_CES` | cession d'immobilisation |
| `FIN_CAP` | capital |
| `FIN_EMP` | emprunt |
| `FIN_DIV` | dividendes versés |

Ils se posent sur un **poste de trésorerie**. Chapitre suivant.

---

## 6. Le poste de trésorerie

### Ce que c'est

Un poste de trésorerie, c'est **une banque ou une caisse**, déclarée dans
l'application et rattachée à un compte du plan comptable.

### À quoi il sert vraiment — trois rôles

**1. Faire exister la banque dans l'application.** C'est son rôle principal, et
il n'a rien à voir avec le TFT. Le **module Trésorerie** et le **rapprochement
bancaire** partent de la liste des postes. Pas de poste, pas de compte à
rapprocher.

**2. Forcer le classement du TFT** — et **seulement** s'il porte un code
SYSCOHADA (`INV_ACQ`, `FIN_EMP`…). C'est une décision humaine explicite, elle
l'emporte sur la contrepartie.

> **Avant, la simple *catégorie* du poste suffisait à trancher.** Et comme tout
> poste créé automatiquement naît en catégorie « I. opérationnelles », ça ne
> servait à rien dans le bon cas et faisait des dégâts dans le mauvais : une
> banque rangée en « II. investissement » y envoyait les salaires. Ce n'est plus
> possible.

**3. Filtrer.** Sur la liste des écritures, « poste défini / non défini » montre
les lignes de classe 5 restées orphelines.

### Faut-il le renseigner à la saisie ?

**Dans la marche normale, non.** Le classement repose sur les comptes, qui sont
toujours là. Le poste est devenu une **dérogation**, utile quand la contrepartie
ne raconte pas l'histoire :

- une ligne de crédit dédiée, dont tous les mouvements doivent partir en
  financement quelle que soit la contrepartie ;
- une pièce sans contrepartie exploitable ;
- un compte d'attente en classe 47 qui masque la nature de l'opération.

### Ce qui a été corrigé

La saisie manuelle créait le poste manquant. **L'import, lui, se contentait d'en
chercher un.** Une entreprise alimentée uniquement par import n'avait donc aucun
poste — et ses banques et caisses n'apparaissaient ni dans le module Trésorerie
ni dans le rapprochement bancaire.

L'import fait désormais comme la saisie. Pour rattraper l'existant :

```bash
php artisan tresorerie:rattacher-postes                # simulation
php artisan tresorerie:rattacher-postes --appliquer
```

Elle ne touche que les lignes sans poste et **n'écrase jamais un choix déjà fait**.

---

## 7. Le numéro de saisie

### À quoi il sert

Il dit **où une pièce commence et où elle s'arrête**. Deux lignes qui portent le
même numéro appartiennent à la même opération.

Il y en a deux :

- `n_saisie` — le numéro **global**, attribué par l'application : `ECR-JJMMAA-000001`
- `n_saisie_user` — le numéro **utilisateur**, avec les initiales : `CPT-AG-JJMMAA-000001`

Le compteur **repart à 1 chaque jour**. Le 20 septembre donne `ECR-200926-000001`,
le 21 redonne `ECR-210926-000001`. C'est plus lisible qu'un compteur qui monte
indéfiniment, et les filtres par date s'en servent.

### Le défaut qui a été corrigé

L'import écrivait ses lignes **par paquets de mille** avant de les enregistrer,
tout en relisant la base pour connaître le dernier numéro attribué. La base
ignorant encore le paquet en cours, **toutes les pièces d'un import recevaient le
même numéro**. Un journal entier pouvait rester figé sur `ECR_000000000020`.

Conséquence : le TFT voyait une seule opération là où il y en avait des
centaines, et compensait les encaissements avec les décaissements. Les autres
états, qui lisent par compte, restaient justes.

Le diagnostic en production avait trouvé **492 numéros partagés, dont 253
touchant la trésorerie**, répartis sur 14 dossiers. Tout a été réparé : la
commande a redonné un numéro distinct à chaque pièce, en découpant sur
l'équilibre.

```bash
php artisan saisies:diagnostic-numeros --journaux      # lecture seule
php artisan saisies:reparer-numeros --appliquer --tout-lhistorique
```

### Depuis la réécriture du TFT, ce numéro pèse moins lourd

Le montant et le sens se lisent maintenant **sur la ligne**. Le numéro ne sert
plus qu'à rassembler les lignes d'une pièce pour identifier la contrepartie — et
ce regroupement est aussitôt redécoupé sur l'équilibre.

**Conséquence rassurante** : même si un découpage échouait, on perdrait un
rangement, jamais un franc.

---

## 8. Les liasses fiscales

### 8.1 Les deux régimes

| | **SN — Système Normal** | **SMT — Système Minimal de Trésorerie** |
|---|---|---|
| Pour qui | le cas général | les très petites entreprises |
| Pages dans l'application | **41** | **10** |
| Comptabilité | d'engagement (créances et dettes) | de trésorerie (encaissements et décaissements) |
| Tableau des flux | oui, page 9 | **non** — remplacé par deux pages Encaissements / Décaissements |
| Type de fichier DGI | `NO` | `RNI` |

> **Le seuil reste à confirmer.** Le code retient « CA ≤ 50 M FCFA ». L'AUDCIF
> prévoit en réalité des seuils qui dépendent de l'activité (négoce, artisanat,
> services). **À vérifier avec un texte officiel avant d'automatiser le choix du
> régime.**

Aujourd'hui, l'application affiche **les deux** et laisse l'utilisateur choisir
(`/reporting/liasse/sn` ou `/reporting/liasse/smt`). Le régime n'est pas encore
déduit de la fiche entreprise.

### 8.2 Les codes DGI

Chaque case d'une liasse porte un code à deux ou trois lettres, imposé par
l'administration. L'application les stocke dans la table `liasse_mappings`, avec
la plage de comptes qui les alimente.

| Page | Code | Libellé | Comptes |
|---|---|---|---|
| Bilan actif | `AF` | Bâtiments | 21 |
| Bilan actif | `AM` | Matériel | 241, 242 |
| Bilan actif | `BB` | Stocks | 3 |
| Bilan actif | `BS` | Trésorerie actif | 50, 52, 53, 57, 58 |
| Bilan actif | `BZ` | **Total général** | calculé |
| Bilan passif | `CA` | Capital | 101 à 109 |
| Bilan passif | `DA` | Emprunts | 161 à 168 |
| Résultat | `XA` | Ventes de marchandises | 701, 707 |
| Résultat | `XB` | Achats de marchandises | 601, 607 |
| Résultat | `XV` | **Résultat net** | calculé |
| TFT | `ZA` | Trésorerie nette d'ouverture | 5 |
| TFT | `ZK` | Variation de trésorerie | calculé |

### 8.3 La liasse SN, page par page

| # | Page | Comment elle se remplit |
|---|---|---|
| 1 | FICHE R1 — Identification | dates automatiques, le reste à la main |
| 2 | FICHE R2 — Activités | **entièrement à la main** |
| 3 | FICHE R3 — Dirigeants | **entièrement à la main** |
| 4 | Balance comptable | **automatique** |
| 5 | Grand livre (extrait) | **automatique** |
| 6 | Bilan actif | **automatique** (plages de comptes) |
| 7 | Bilan passif | **automatique** |
| 8 | Compte de résultat | **automatique** |
| 9 | Tableau des flux | **automatique** — *mais mécanisme grossier, voir 8.5* |
| 10 à 41 | **Notes 1 à 32** | **entièrement à la main** |

**Les 32 notes sont des formulaires vides.** L'utilisateur saisit chaque montant
au clavier, et l'application le range dans la table `liasse_data`. Rien n'est
prérempli depuis la comptabilité.

C'est le plus gros chantier restant. Exemples de ce qui pourrait être calculé
automatiquement :

| Note | Contenu | Ce qui l'alimenterait |
|---|---|---|
| Note 1 — Immobilisations brutes | ouverture, acquisitions, cessions, clôture | mouvements de la classe 2 |
| Note 2 — Amortissements | idem | mouvements des comptes 28 |
| Note 7 — Stocks | par nature | soldes de la classe 3 |
| Note 8 — Clients et créances | par échéance | soldes des 41, 4- |
| Note 11 — Disponibilités | par banque | soldes des 52, 57 |
| Note 16 — Dettes financières | par emprunt | soldes des 16 |
| Note 17 — Fournisseurs | par nature | soldes des 401, 481 |
| Note 24 — Charges de personnel | par poste | soldes des 66 |

Beaucoup de ces notes sont de simples ventilations de comptes déjà en base.

### 8.4 La liasse SMT

Les 10 pages sont calculées, avec possibilité de corriger à la main par-dessus.
Structure plus simple : identification, bilan actif, bilan passif, résultat,
encaissements, décaissements, notes, passage au résultat fiscal.

### 8.5 Deux manques identifiés

**a) La page TFT de la liasse n'utilise pas le calcul corrigé.**

Ses lignes ZA à ZM viennent de plages de comptes brutes :

```
'ZA' => '5'      'ZB' => '4'      'ZD' => '2'
'ZG' => '10'     'ZH' => '16'     'ZL' => '5'     'ZM' => '5'
```

C'est-à-dire : « flux opérationnels = tout le solde de la classe 4 »,
« investissement = tout le solde de la classe 2 ». **C'est exactement le défaut
qu'on vient de corriger ailleurs** — compter des mouvements comptables au lieu de
mouvements d'argent. Le service corrigé n'alimente ici qu'une annexe de détail.

C'est un document déposé à la DGI. **C'est le chantier le plus urgent.**

**b) La feuille COMMENT est absente.**

Le fichier DGI attend **44 commentaires**, un par note (`NO_COMMENT_11_1`,
`NO_COMMENT_13_1`…). Ils figurent dans le fichier de correspondance, mais
l'application n'a aucune page pour les saisir, et ils ne sont donc ni
enregistrés ni exportés.

### 8.6 Les exports

| Format | Usage |
|---|---|
| **XML** | dépôt e-impôts — type `NO` (SN) ou `RNI` (SMT) |
| **PDF** | une page, ou la liasse complète |
| **Excel** | une page, ou la liasse complète |

---

## 9. État des lieux : fait, à faire

### Ce qui fonctionne et a été vérifié

| Sujet | État |
|---|---|
| Balance, grand livre, bilan, compte de résultat | justes — lecture par compte, jamais affectés |
| Numéros de saisie | réparés en production ; 0 numéro partagé au dernier diagnostic |
| Numérotation à venir | `ECR-JJMMAA-000001`, remise à zéro chaque jour |
| TFT — page Flux de Trésorerie | réécrit : flux bruts, virements internes neutralisés, classement par contrepartie |
| TFT — page TFT Mensuel | réécrit de même |
| Postes de trésorerie | créés aussi à l'import ; commande de rattrapage disponible |
| Traçabilité des suppressions | toute suppression est archivée, y compris en masse |
| Liasse SMT | 10 pages calculées |
| Liasse SN — bilan, résultat | calculés |

### Ce qui reste à construire

| # | Sujet | Pourquoi ça compte |
|---|---|---|
| 1 | **Page TFT de la liasse SN** | document déposé à la DGI, calculé sur une base grossière |
| 2 | **Feuille COMMENT** — 44 commentaires | attendus par le fichier DGI, aucune page pour les saisir |
| 3 | **Préremplissage des 32 notes** | tout est saisi à la main alors que la comptabilité a les chiffres |
| 4 | **Choix automatique du régime** | SN ou SMT selon la fiche entreprise — seuils à confirmer |
| 5 | **Fiches R2 et R3** | entièrement manuelles |
| 6 | Entreprises 63 et 90 | écritures sans fiche entreprise |
| 7 | Report de page de la balance PDF | « À reporter / Report » |

### Contrôles à faire soi-même

Trois vérifications simples, qui valent tous les tests :

1. **Le TFT** — la « variation totale » doit égaler la variation réelle des
   comptes de classe 5 sur la période, report à nouveau déduit.
2. **Le bilan** — total actif = total passif.
3. **Le compte de résultat** — le résultat net doit correspondre au solde des
   comptes 12/13 au bilan.

---

## Annexe — où se trouve quoi dans le code

| Sujet | Fichier |
|---|---|
| Compte de résultat (SIG) | `app/Services/AccountingReportingService.php` → `getSIGData` |
| Bilan | idem → `getBilanData` |
| TFT — page Flux de Trésorerie | idem → `getTFTMatrixData` |
| TFT — page TFT Mensuel | idem → `getPersonalizedTFTData` |
| TFT — annexe de la liasse | idem → `getTFTData` |
| Classement des flux | `app/Services/ClassificationFlux.php` |
| Décomposition en mouvements | `app/Services/AnalyseFluxTresorerie.php` |
| Reconstitution des pièces | `app/Services/DecoupageDesPieces.php` |
| Numérotation | `app/Services/NumerotationSaisie.php` |
| Liasses | `app/Services/LiasseFiscaleService.php` |
| Pages des liasses | `resources/views/reporting/liasse/pages/` |
| Correspondance codes DGI | `database/seeders/LiasseMappingSeeder.php` |
| Postes de trésorerie | `app/Traits/HandlesTreasuryPosts.php` |

### Commandes utiles

```bash
# Où des pièces partagent-elles un numéro, et avec quel effet ? (lecture seule)
php artisan saisies:diagnostic-numeros --journaux

# Redonner un numéro distinct à chaque pièce
php artisan saisies:reparer-numeros --appliquer --tout-lhistorique

# Donner un poste de trésorerie aux lignes de classe 5 qui n'en ont pas
php artisan tresorerie:rattacher-postes --appliquer

# Restaurer des écritures depuis une sauvegarde
php artisan saisies:restaurer --source=<base> --company=<id> --appliquer
```

Toutes ces commandes tournent **en simulation** tant que `--appliquer` n'est pas
ajouté.
