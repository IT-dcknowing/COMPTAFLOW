{{--
    Soldes de trésorerie dans l'en-tête de la saisie :

        WVE1 · tous journaux      SOLDE JUIL.   DÉBIT AOÛT   CRÉDIT AOÛT   NOUVEAU SOLDE
        552003  MONNAIE ÉLEC.         11 D         150 000       283 300      133 289 C
        571000  CAISSE             2 090 D               0             0        2 090 D
        Total                      2 101 D         150 000       283 300      131 199 C

    Une ligne par compte de trésorerie, pour que chaque chiffre soit
    vérifiable tel quel sur la balance. Les montants se lisent sur TOUS les
    journaux : le solde d'une caisse, c'est la caisse entière, et non la part
    passée par le journal ouvert. Le journal, lui, sert seulement à désigner
    les comptes à suivre.

    Solde <mois précédent> : cumul jusqu'à la veille du mois choisi.
    Nouveau solde          : ancien solde + débits − crédits.
    Un solde débiteur s'écrit D, un solde créditeur C.

    Un journal sans compte de trésorerie (achats, ventes) affiche une seule
    ligne : les totaux de ce journal.

    Les chiffres suivent le journal et le mois choisis dans la carte de saisie,
    et se recalculent après chaque enregistrement ou suppression.
--}}
<style>
    #soldesJournal {
        display: none;
        border: 1px solid #cbd5e1;
        background: #f1f5f9;
        border-radius: 10px;
        font-size: 0.74rem;
        line-height: 1.25;
        /* Sept colonnes depuis qu'on separe « ce journal » des « autres
           journaux » : a 640 px la derniere — le nouveau solde — sortait du
           cadre et se faisait couper. On laisse la place, et on autorise le
           defilement lateral plutot que de rogner. */
        overflow-x: auto;
        overflow-y: hidden;
        max-width: min(860px, 46vw);
    }
    #soldesJournal.visible { display: block; }
    #soldesJournal table { border-collapse: collapse; width: 100%; margin: 0; }
    #soldesJournal th, #soldesJournal td { padding: 0.16rem 0.4rem; white-space: nowrap; }
    #soldesJournal thead th {
        font-size: 0.6rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #64748b;
        text-align: right;
        background: #e2e8f0;
    }
    #soldesJournal thead th:first-child { text-align: left; color: #334155; }
    #soldesJournal tbody th {
        font-weight: 600;
        color: #334155;
        text-align: left;
        border-right: 1px solid #cbd5e1;
        max-width: 230px;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    #soldesJournal tbody th small { color: #64748b; font-weight: 500; }
    #soldesJournal tbody td {
        text-align: right;
        font-variant-numeric: tabular-nums;
        font-weight: 700;
        color: #0f172a;
        min-width: 74px;
    }
    #soldesJournal tbody td + td { border-left: 1px solid #cbd5e1; }
    #soldesJournal tbody td .sens { font-weight: 600; color: #64748b; margin-left: 0.18rem; }
    #soldesJournal thead th small {
        display: block;
        font-size: 0.52rem;
        font-weight: 600;
        letter-spacing: 0;
        text-transform: none;
        color: #94a3b8;
    }
    #soldesJournal thead th.entete-ailleurs, #soldesJournal tbody td[data-colonne="ailleurs"] { background: #f8fafc; }
    #soldesJournal tbody td[data-colonne="ailleurs"] { color: #64748b; font-weight: 600; }
    #soldesJournal thead th:last-child, #soldesJournal tbody td[data-colonne="nouveau"] {
        border-left: 2px solid #94a3b8;
        background: #e0f2fe;
    }
    #soldesJournal tbody tr.total { background: #e0f2fe; }
    #soldesJournal tbody tr.total th, #soldesJournal tbody tr.total td { font-weight: 800; }
    #soldesJournal tbody tr.total td[data-colonne="nouveau"] { color: #0c4a6e; }
    #soldesJournal.chargement tbody td { color: #94a3b8; }
    @media (max-width: 1199.98px) { #soldesJournal { display: none !important; } }
</style>

<div id="soldesJournal" aria-live="polite">
    <table>
        <thead>
            <tr>
                <th id="soldesJournalTitre">Journal</th>
                <th id="soldesJournalEnteteAncien">Solde précédent</th>
                <th class="entete-ici">Débit <small>ce journal</small></th>
                <th class="entete-ici">Crédit <small>ce journal</small></th>
                <th class="entete-ailleurs">Débit <small>autres journaux</small></th>
                <th class="entete-ailleurs">Crédit <small>autres journaux</small></th>
                <th>Nouveau solde</th>
            </tr>
        </thead>
        <tbody id="soldesJournalCorps"></tbody>
    </table>
</div>

<script>
(function () {
    const cadre = document.getElementById('soldesJournal');
    if (!cadre) return;

    const adresse = @json(route('ecriture.soldes_journal'));
    const format = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 2 });
    const corps = document.getElementById('soldesJournalCorps');

    let minuterie = null;
    let demande = 0;
    let derniereCle = null;

    const montant = v => (v ? format.format(v) : '0');

    // Un solde s'écrit avec son sens : D pour débiteur, C pour créditeur.
    function solde(v) {
        if (!v) return '0';
        return format.format(Math.abs(v)) + '<span class="sens">' + (v > 0 ? 'D' : 'C') + '</span>';
    }

    function cellule(html, colonne) {
        return '<td' + (colonne ? ' data-colonne="' + colonne + '"' : '') + '>' + html + '</td>';
    }

    function ligne(titre, sousTitre, l, classe) {
        return '<tr' + (classe ? ' class="' + classe + '"' : '') + '>'
            + '<th title="' + (sousTitre || titre).replace(/"/g, '') + '">' + titre
            + (sousTitre ? ' <small>' + sousTitre + '</small>' : '') + '</th>'
            + cellule(solde(l.ancien))
            + cellule(montant(l.debit_ici), 'ici')
            + cellule(montant(l.credit_ici), 'ici')
            + cellule(montant(l.debit_ailleurs), 'ailleurs')
            + cellule(montant(l.credit_ailleurs), 'ailleurs')
            + cellule(solde(l.nouveau), 'nouveau')
            + '</tr>';
    }

    async function charger() {
        const journal = document.getElementById('code_journal_id');
        const journalId = journal?.value || '';
        derniereCle = journalId + '|' + (document.getElementById('mois_ecriture')?.value || '');
        if (!journalId) {
            cadre.classList.remove('visible');
            return;
        }

        const numero = ++demande;
        const params = new URLSearchParams({
            journal_id: journalId,
            mois: document.getElementById('mois_ecriture')?.value || '',
            exercice_id: document.getElementById('id_exercice')?.value || '',
        });

        cadre.classList.add('visible', 'chargement');
        try {
            const reponse = await fetch(adresse + '?' + params, { headers: { 'Accept': 'application/json' } });
            const json = await reponse.json();
            if (numero !== demande) return;           // une demande plus récente est partie entre-temps
            if (!json.success) {
                if (corps.innerHTML.trim() === '') cadre.classList.remove('visible');
                return;
            }

            // Le titre dit exactement ce qui a été calculé.
            document.getElementById('soldesJournalTitre').textContent =
                json.journal + (json.tous_journaux ? ' · soldes des comptes' : ' · totaux du journal');
            document.getElementById('soldesJournalEnteteAncien').textContent =
                json.libelle_ancien || 'Solde précédent';
            cadre.title = 'Période du ' + json.periode[0] + ' au ' + json.periode[1]
                + (json.tous_journaux
                    ? " — le solde d'un compte se lit sur tous les journaux, sinon il ne se recoupe "
                      + "plus avec la balance. Les colonnes disent ce qui vient de ce journal et ce qui "
                      + "vient des autres."
                    : ' — totaux de ce journal.');

            const comptes = json.comptes || [];
            const total = {
                ancien: json.ancien_solde,
                debit_ici: json.mouvements.debit_ici,
                credit_ici: json.mouvements.credit_ici,
                debit_ailleurs: json.mouvements.debit_ailleurs,
                credit_ailleurs: json.mouvements.credit_ailleurs,
                nouveau: json.nouveau_solde,
            };

            let html = '';
            comptes.forEach(function (c) {
                html += ligne(c.numero, c.intitule, c);
            });
            // Le total n'a de sens qu'à partir de deux comptes ; seul, il
            // répéterait la ligne du dessus.
            if (comptes.length !== 1) {
                html += ligne(comptes.length ? 'Total' : json.journal, '', total, 'total');
            } else {
                html = ligne(comptes[0].numero, comptes[0].intitule, comptes[0], 'total');
            }
            corps.innerHTML = html;
        } catch (e) {
            // Le cadre disparaissait au moindre accroc — session expirée,
            // réseau lent, requête coupée — et donnait l'impression de ne pas
            // tenir. Il garde maintenant ce qu'il montrait, en le disant.
            if (numero === demande && corps.innerHTML.trim() === '') {
                cadre.classList.remove('visible');
            } else if (numero === demande) {
                cadre.title = 'Chiffres momentanément indisponibles : ils datent du dernier calcul réussi.';
            }
        } finally {
            if (numero === demande) cadre.classList.remove('chargement');
        }
    }

    function planifier() {
        clearTimeout(minuterie);
        minuterie = setTimeout(charger, 150);
    }

    // La grille prévient à chaque rafraîchissement : changement de journal ou
    // de mois, enregistrement, suppression.
    document.addEventListener('saisie:liste-rafraichie', planifier);
    document.addEventListener('change', function (e) {
        if (e.target && (e.target.id === 'code_journal_id' || e.target.id === 'mois_ecriture')) planifier();
    });
    document.addEventListener('DOMContentLoaded', planifier);

    // La grille change parfois le journal ou le mois par programme (reprise
    // de la dernière sélection, édition d'une écriture) sans prévenir : on
    // vérifie régulièrement que les soldes affichés suivent la sélection.
    setInterval(function () {
        const journalId = document.getElementById('code_journal_id')?.value || '';
        const mois = document.getElementById('mois_ecriture')?.value || '';
        if (journalId + '|' + mois !== derniereCle) planifier();
    }, 1000);
})();
</script>
