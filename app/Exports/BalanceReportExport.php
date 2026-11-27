<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class BalanceReportExport implements FromArray, WithEvents, WithColumnWidths, WithTitle
{
    private array $rows = [];
    private array $headers = [];
    private array $dataRows = [];
    private array $totals = [];
    private string $type;
    private string $company;
    private string $dateDebut;
    private string $dateFin;
    private string $range;
    private string $displayMode;

    public function __construct(
        Collection $ecritures,
        string $type = '4',
        string $displayMode = 'comptaflow',
        string $company = 'Non défini',
        string $dateDebut = '',
        string $dateFin = '',
        string $range = ''
    ) {
        $this->type = in_array($type, ['4', '6', '8'], true) ? $type : '4';
        $this->company = $company;
        $this->dateDebut = $dateDebut;
        $this->dateFin = $dateFin;
        $this->range = $range;
        $this->displayMode = $displayMode;

        $grouped = $ecritures->sortBy(fn ($e) => (string) ($e->planComptable->numero_de_compte ?? ''))
            ->groupBy('plan_comptable_id');

        if ($this->type === '4') {
            $this->headers = ['Numéro de compte', 'Intitulé des comptes', 'Mouvements - Débit', 'Mouvements - Crédit', 'Soldes - Débit', 'Soldes - Crédit'];
        } elseif ($this->type === '6') {
            $this->headers = ['Compte', 'Intitulé', 'Solde initial - Débit', 'Solde initial - Crédit', 'Mouvement - Débit', 'Mouvement - Crédit', 'Solde final - Débit', 'Solde final - Crédit'];
        } else {
            $this->headers = ['Compte', 'Intitulé', 'Solde initial - Débit', 'Solde initial - Crédit', 'Mouvement - Débit', 'Mouvement - Crédit', 'Solde avant clôture - Débit', 'Solde avant clôture - Crédit', 'Solde final - Débit', 'Solde final - Crédit'];
        }

        $totals = array_fill(0, count($this->headers), 0.0);
        $classTotals = [
            'bilan' => ['debit' => 0.0, 'credit' => 0.0],
            'gestion' => ['debit' => 0.0, 'credit' => 0.0],
        ];
        foreach ($grouped as $operations) {
            $plan = $operations->first()->planComptable;
            if (!$plan) continue;
            $numero = (string) ($plan->numero_de_compte ?? '-');
            $original = (string) ($plan->numero_original ?? '');
            $display = match ($this->displayMode) {
                'origine' => $original !== '' ? $original : $numero,
                'both' => $original !== '' && $original !== $numero ? $numero . ' (' . $original . ')' : $numero,
                default => $numero,
            };
            $debit = (float) $operations->sum('debit');
            $credit = (float) $operations->sum('credit');
            $net = $debit - $credit;
            $sd = max($net, 0);
            $sc = max(-$net, 0);
            $firstClass = substr($numero, 0, 1);
            $bucket = in_array($firstClass, ['1','2','3','4','5'], true) ? 'bilan' : (in_array($firstClass, ['6','7'], true) ? 'gestion' : null);
            if ($bucket !== null) {
                $classTotals[$bucket]['debit'] += $debit;
                $classTotals[$bucket]['credit'] += $credit;
            }
            if ($this->type === '4') {
                $line = [$display, (string) ($plan->intitule ?? ''), $debit, $credit, $sd, $sc];
                for ($i=2; $i<6; $i++) $totals[$i] += $line[$i];
            } elseif ($this->type === '6') {
                // Les vues PDF 6 colonnes présentent les soldes initiaux à zéro.
                $line = [$display, (string) ($plan->intitule ?? ''), 0, 0, $debit, $credit, $sd, $sc];
                for ($i=2; $i<8; $i++) $totals[$i] += $line[$i];
            } else {
                // Même base de calcul que la vue PDF 8 colonnes.
                $line = [$display, (string) ($plan->intitule ?? ''), 0, 0, $debit, $credit, $sd, $sc, $sd, $sc];
                for ($i=2; $i<10; $i++) $totals[$i] += $line[$i];
            }
            $this->dataRows[] = $line;
        }
        $this->totals = $totals;
        if ($this->type === '4') {
            // La balance PDF présente les soldes globaux comme différence des mouvements,
            // et non comme somme des soldes débiteurs/créditeurs de chaque compte.
            foreach (['bilan', 'gestion'] as $bucket) {
                $net = $classTotals[$bucket]['debit'] - $classTotals[$bucket]['credit'];
                $line = ['', 'TOTAUX COMPTES DE ' . strtoupper($bucket), $classTotals[$bucket]['debit'], $classTotals[$bucket]['credit'], max($net, 0), max(-$net, 0)];
                $this->dataRows[] = $line;
            }
            $net = $totals[2] - $totals[3];
            $totalLine = ['', 'TOTAUX DE LA BALANCE', $totals[2], $totals[3], max($net, 0), max(-$net, 0)];
        } else {
            $totalLine = array_fill(0, count($this->headers), 0);
            $totalLine[1] = 'TOTAUX GÉNÉRAUX';
            foreach ($totals as $i => $value) if ($i >= 2) $totalLine[$i] = $value;
        }
        $this->dataRows[] = $totalLine;

        $this->rows = [
            ['COMPTAFLOW'],
            [$company],
            ['BALANCE DES COMPTES'],
            ['Période du ' . $this->formatDate($dateDebut) . ' au ' . $this->formatDate($dateFin)],
            ['Impression définitive | Plage des comptes : ' . $range],
            ['Tenue de compte : FCFA | Date de tirage : ' . now()->format('d/m/Y à H:i:s')],
            [],
            $this->headers,
            ...$this->dataRows,
        ];
    }

    private function formatDate(string $value): string
    {
        try { return \Carbon\Carbon::parse($value)->format('d/m/Y'); }
        catch (\Throwable) { return $value; }
    }

    public function array(): array { return $this->rows; }
    public function headings(): array { return []; }
    public function title(): string { return 'Balance'; }

    public function columnWidths(): array
    {
        $widths = ['A' => 20, 'B' => 42];
        foreach (range('C', chr(64 + count($this->headers))) as $column) $widths[$column] = 19;
        return $widths;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = $sheet->getHighestColumn();
                $lastRow = $sheet->getHighestRow();
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->mergeCells("A2:{$lastCol}2");
                $sheet->mergeCells("A3:{$lastCol}3");
                foreach ([4,5,6] as $r) $sheet->mergeCells("A{$r}:{$lastCol}{$r}");
                $sheet->getStyle('A1')->applyFromArray(['font'=>['bold'=>true,'size'=>22,'color'=>['rgb'=>'1F4E78']], 'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]]);
                $sheet->getStyle('A2')->applyFromArray(['font'=>['bold'=>true,'size'=>14], 'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]]);
                $sheet->getStyle('A3')->applyFromArray(['font'=>['bold'=>true,'size'=>16], 'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]]);
                $sheet->getStyle("A4:A6")->getFont()->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF666666'));
                $sheet->getStyle("A8:{$lastCol}8")->applyFromArray([
                    'font'=>['bold'=>true,'color'=>['rgb'=>'FFFFFF']],
                    'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'1F4E78']],
                    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
                    'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'B7C9D6']]],
                ]);
                if ($lastRow >= 9) {
                    $sheet->getStyle("A9:{$lastCol}{$lastRow}")->applyFromArray([
                        'font'=>['name'=>'Arial','size'=>10,'color'=>['rgb'=>'243247']],
                        'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_HAIR,'color'=>['rgb'=>'D9E2F3']]],
                        'alignment'=>['vertical'=>Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getStyle("A9:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle("C9:{$lastCol}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("C9:{$lastCol}{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00;[Red]-#,##0.00;–');
                    for ($r = 9; $r <= $lastRow; $r++) {
                        if (($r - 9) % 2 === 1) {
                            $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F7FAFD');
                        }
                    }
                }
                for ($r = 9; $r <= $lastRow; $r++) {
                    if (str_starts_with((string) $sheet->getCell("B{$r}")->getValue(), 'TOTAUX')) {
                        $sheet->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
                            'font'=>['bold'=>true],
                            'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'EAF0F6']],
                        ]);
                    }
                }
                $sheet->getStyle("A{$lastRow}:{$lastCol}{$lastRow}")->applyFromArray([
                    'font'=>['bold'=>true],
                    'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'D9EAF7']],
                    'borders'=>['top'=>['borderStyle'=>Border::BORDER_MEDIUM,'color'=>['rgb'=>'1F4E78']]],
                ]);
                $sheet->getRowDimension(8)->setRowHeight(26);
                $sheet->freezePane('A9');
                $sheet->setAutoFilter("A8:{$lastCol}" . max(8, $lastRow - 1));
                $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(0);
                $sheet->getPageSetup()->setFitToPage(true);
                $sheet->getPageMargins()->setTop(0.55)->setBottom(0.55)->setLeft(0.25)->setRight(0.25);
                $sheet->getHeaderFooter()->setOddHeader('&C&"Arial,Bold"&16COMPTAFLOW');
                $sheet->getHeaderFooter()->setOddFooter('&L© ComptaFlow&CImprimé le &D à &T&RPage &P / &N');
            },
        ];
    }

    public function previewData(): array
    {
        return ['headers' => $this->headers, 'rows' => $this->dataRows];
    }
}
