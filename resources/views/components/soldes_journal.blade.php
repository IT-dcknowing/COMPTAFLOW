{{--
    Soldes du journal en saisie, dans l'en-tête — comme le cadre de Sage :

                        Débit        Crédit
        Solde février   166 355
        Mouvements      1 650 000    1 739 235
        Nouveau solde   77 120

    Solde <mois précédent> : cumul jusqu'à la fin du mois précédent
                             (« Solde février » quand on saisit mars).
    Mouvements   : débits et crédits du mois.
    Nouveau solde: ancien solde + débits − crédits.
    Un solde débiteur s'écrit au débit, un solde créditeur au crédit.

    Les chiffres suivent le journal et le mois choisis dans la carte de saisie,
    et se recalculent après chaque enregistrement ou suppression.
--}}
<style>
    #soldesJournal {
        display: none;
        border: 1px solid #cbd5e1;
        background: #f1f5f9;
        border-radius: 10px;
        font-size: 0.78rem;
        line-height: 1.25;
        overflow: hidden;
        min-width: 360px;
    }
    #soldesJournal.visible { display: block; }
    #soldesJournal table { border-collapse: collapse; width: 100%; margin: 0; }
    #soldesJournal th, #soldesJournal td { padding: 0.18rem 0.7rem; white-space: nowrap; }
    #soldesJournal thead th {
        font-size: 0.62rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        text-align: right;
        background: #e2e8f0;
    }
    #soldesJournal thead th:first-child { text-align: left; color: #334155; }
    #soldesJournal tbody th { font-weight: 600; color: #334155; text-align: left; border-right: 1px solid #cbd5e1; }
    #soldesJournal tbody td {
        text-align: right;
        font-variant-numeric: tabular-nums;
        font-weight: 700;
        color: #0f172a;
        min-width: 110px;
    }
    #soldesJournal tbody td + td { border-left: 1px solid #cbd5e1; }
    #soldesJournal tbody tr.ancien th { color: #0f766e; }
    #soldesJournal tbody tr.nouveau { background: #e0f2fe; }
    #soldesJournal tbody tr.nouveau th, #soldesJournal tbody tr.nouveau td { font-weight: 800; }
    #soldesJournal.chargement tbody td { color: #94a3b8; }
    @media (max-width: 991.98px) { #soldesJournal { display: none !important; } }
</style>

<div id="soldesJournal" aria-live="polite">
    <table>
        <thead>
            <tr>
                <th id="soldesJournalTitre">Journal</th>
                <th>Débit</th>
                <th>Crédit</th>
            </tr>
        </thead>
        <tbody>
            <tr class="ancien">
                <th id="soldesJournalLibelleAncien">Solde du mois précédent</th>
                <td data-solde="ancien-debit"></td>
                <td data-solde="ancien-credit"></td>
            </tr>
            <tr>
                <th>Mouvements</th>
                <td data-solde="mvt-debit"></td>
                <td data-solde="mvt-credit"></td>
            </tr>
            <tr class="nouveau">
                <th>Nouveau solde</th>
                <td data-solde="nouveau-debit"></td>
                <td data-solde="nouveau-credit"></td>
            </tr>
        </tbody>
    </table>
</div>

<script>
(function () {
    const cadre = document.getElementById('soldesJournal');
    if (!cadre) return;

    const adresse = @json(route('ecriture.soldes_journal'));
    const format = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 2 });
    const cellule = cle => cadre.querySelector('[data-solde="' + cle + '"]');
    const montant = v => (v ? format.format(v) : '');

    let minuterie = null;
    let demande = 0;
    let derniereCle = null;

    // Un solde se place dans la colonne de son sens : débiteur au débit, créditeur au crédit.
    function placerSolde(prefixe, solde) {
        cellule(prefixe + '-debit').textContent = solde > 0 ? montant(solde) : (solde === 0 ? '0' : '');
        cellule(prefixe + '-credit').textContent = solde < 0 ? montant(-solde) : '';
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
        const mois = document.getElementById('mois_ecriture')?.value || '';
        const params = new URLSearchParams({
            journal_id: journalId,
            mois: mois,
            exercice_id: document.getElementById('id_exercice')?.value || '',
        });

        cadre.classList.add('visible', 'chargement');
        try {
            const reponse = await fetch(adresse + '?' + params, { headers: { 'Accept': 'application/json' } });
            const json = await reponse.json();
            if (numero !== demande) return;           // une demande plus récente est partie entre-temps
            if (!json.success) { cadre.classList.remove('visible'); return; }

            // Le titre vient du serveur : il dit exactement ce qui a été calculé.
            document.getElementById('soldesJournalTitre').textContent =
                json.journal + (json.compte ? ' · ' + json.compte : '');
            cadre.title = 'Période du ' + json.periode[0] + ' au ' + json.periode[1];

            document.getElementById('soldesJournalLibelleAncien').textContent = json.libelle_ancien || 'Solde du mois précédent';
            placerSolde('ancien', json.ancien_solde);
            cellule('mvt-debit').textContent = montant(json.mouvements.debit) || '0';
            cellule('mvt-credit').textContent = montant(json.mouvements.credit) || '0';
            placerSolde('nouveau', json.nouveau_solde);
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
