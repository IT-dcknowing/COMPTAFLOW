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
        overflow: hidden;
        max-width: 640px;
    }
    #soldesJournal.visible { display: block; }
    #soldesJournal table { border-collapse: collapse; width: 100%; margin: 0; }
    #soldesJournal th, #soldesJournal td { padding: 0.16rem 0.55rem; white-space: nowrap; }
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
        min-width: 86px;
    }
    #soldesJournal tbody td + td { border-left: 1px solid #cbd5e1; }
    #soldesJournal tbody td .sens { font-weight: 600; color: #64748b; margin-left: 0.18rem; }
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
                <th>Débit</th>
                <th>Crédit</th>
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
            + cellule(montant(l.debit))
            + cellule(montant(l.credit))
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
            if (!json.success) { cadre.classList.remove('visible'); return; }

            // Le titre dit exactement ce qui a été calculé.
            document.getElementById('soldesJournalTitre').textContent =
                json.journal + (json.tous_journaux ? ' · tous journaux' : ' · ce journal');
            document.getElementById('soldesJournalEnteteAncien').textContent =
                json.libelle_ancien || 'Solde précédent';
            cadre.title = 'Période du ' + json.periode[0] + ' au ' + json.periode[1]
                + (json.tous_journaux
                    ? ' — soldes de ces comptes, tous journaux confondus : à recouper avec la balance.'
                    : ' — totaux de ce journal.');

            const comptes = json.comptes || [];
            const total = {
                ancien: json.ancien_solde,
                debit: json.mouvements.debit,
                credit: json.mouvements.credit,
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
            if (numero === demande) cadre.classList.remove('visible');
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
