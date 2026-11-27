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

class GrandLivreReportExport implements FromArray, WithEvents, WithColumnWidths, WithTitle
{
    private array $rows = [];
    private array $previewRows = [];
    private string $displayMode;

    public function __construct(
        Collection $ecritures,
        array $soldesInitiaux,
        string $company = 'Non défini',
        string $dateDebut = '',
        string $dateFin = '',
        string $range = '',
        string $displayMode = 'comptaflow'
    ) {
        $this->displayMode = $displayMode;
        $headers = ['Date', 'Journal', 'N° Saisie', 'N° Pièce', 'Compte', 'Tiers', 'Libellé', 'Lettrage', 'Débit', 'Crédit', 'Solde'];

        $sorted = $ecritures->sort(function ($a, $b) {
            $cmp = strcmp((string)($a->planComptable->numero_de_compte ?? ''), (string)($b->planComptable->numero_de_compte ?? ''));
            if ($cmp !== 0) return $cmp;
            $cmp = strcmp((string)($a->date ?? ''), (string)($b->date ?? ''));
            return $cmp !== 0 ? $cmp : strcmp((string)($a->n_saisie ?? ''), (string)($b->n_saisie ?? ''));
        });

        $grandDebit = 0.0;
        $grandCredit = 0.0;

        foreach ($sorted->groupBy('plan_comptable_id') as $id => $operations) {
            $first = $operations->first();
            $plan = $first->planComptable;
            if (!$plan) continue;

            $num = (string)($plan->numero_de_compte ?? '-');
            $original = (string)($plan->numero_original ?? '');
            $account = $this->display($num, $original);
            $label = (string)($plan->intitule ?? '');
            $this->previewRows[] = array_merge([$account . ' — ' . mb_strtoupper($label)], array_fill(0, 10, ''));

            $si = $soldesInitiaux[$id] ?? ['debit'=>0,'credit'=>0,'solde'=>0];
            $running = (float)($si['solde'] ?? 0);
            $accountDebit = (float)($si['debit'] ?? 0);
            $accountCredit = (float)($si['credit'] ?? 0);

            if ($accountDebit != 0.0 || $accountCredit != 0.0) {
                $this->previewRows[] = ['SOLDE INITIAL','','','','','','','',$accountDebit,$accountCredit,$running];
            }

            foreach ($operations as $entry) {
                $debit = (float)($entry->debit ?? 0);
                $credit = (float)($entry->credit ?? 0);
                $running += $debit - $credit;
                $accountDebit += $debit;
                $accountCredit += $credit;

                $journal = (string)($entry->codeJournal?->code_journal ?? '-');
                $journalOriginal = (string)($entry->codeJournal?->numero_original ?? '');
                $tier = (string)($entry->planTiers?->numero_de_tiers ?? '');
                $tierOriginal = (string)($entry->planTiers?->numero_original ?? '');

                $this->previewRows[] = [
                    $this->formatDate((string)($entry->date ?? '')),
                    $this->display($journal, $journalOriginal),
                    $this->cleanNumber((string)($entry->n_saisie ?? '')),
                    (string)($entry->reference_piece ?? ''),
                    $this->display($num, $original),
                    $tier !== '' ? $this->display($tier, $tierOriginal) : '',
                    (string)($entry->description_operation ?? ''),
                    (string)($entry->lettrage ?? ''),
                    $debit,
                    $credit,
                    $running,
                ];
            }

            $this->previewRows[] = ['TOTAL ' . $account,'','','','','','','',$accountDebit,$accountCredit,$running];
            $grandDebit += $accountDebit;
            $grandCredit += $accountCredit;
        }

        $this->previewRows[] = ['TOTAL GÉNÉRAL','','','','','','','',$grandDebit,$grandCredit,$grandDebit-$grandCredit];

        $this->rows = [
            ['COMPTAFLOW'],
            [$company],
            ['GRAND LIVRE DES COMPTES'],
            ['Période du ' . $this->formatDate($dateDebut) . ' au ' . $this->formatDate($dateFin)],
            ['Impression définitive | Plage des comptes : ' . $range],
            ['Date de tirage : ' . now()->format('d/m/Y à H:i:s')],
            [],
            $headers,
            ...$this->previewRows,
        ];
    }

    private function display(string $system, string $original): string
    {
        return match ($this->displayMode) {
            'origine' => $original !== '' ? $original : $system,
            'both' => $original !== '' && $original !== $system ? $system . ' (' . $original . ')' : $system,
            default => $system,
        };
    }

    private function cleanNumber(string $value): string
    {
        if (preg_match('/^(.*-\d{6}-)(\d+)$/', $value, $m)) {
            $number = ltrim($m[2], '0');
            return $m[1] . ($number === '' ? '0' : $number);
        }
        if (str_contains($value, '_')) {
            [$prefix, $number] = explode('_', $value, 2);
            $number = ltrim($number, '0');
            return $prefix . '_' . ($number === '' ? '0' : $number);
        }
        return $value;
    }

    private function formatDate(string $value): string
    {
        try { return \Carbon\Carbon::parse($value)->format('d/m/Y'); }
        catch (\Throwable) { return $value; }
    }

    public function array(): array { return $this->rows; }
    public function title(): string { return 'Grand Livre'; }
    public function columnWidths(): array
    {
        return ['A'=>13,'B'=>10,'C'=>24,'D'=>25,'E'=>16,'F'=>18,'G'=>48,'H'=>10,'I'=>18,'J'=>18,'K'=>18];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();
                $sheet->mergeCells('A1:K1');
                $sheet->mergeCells('A2:K2');
                $sheet->mergeCells('A3:K3');
                foreach ([4,5,6] as $row) $sheet->mergeCells("A{$row}:K{$row}");
                $sheet->getStyle('A1')->applyFromArray(['font'=>['bold'=>true,'size'=>22,'color'=>['rgb'=>'1F4E78']], 'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]]);
                $sheet->getStyle('A2')->applyFromArray(['font'=>['bold'=>true,'size'=>14], 'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]]);
                $sheet->getStyle('A3')->applyFromArray(['font'=>['bold'=>true,'size'=>16], 'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]]);
                $sheet->getStyle('A4:A6')->getFont()->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF666666'));
                $sheet->getStyle('A8:K8')->applyFromArray([
                    'font'=>['bold'=>true,'color'=>['rgb'=>'FFFFFF']],
                    'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'1F4E78']],
                    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
                    'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'B7C9D6']]],
                ]);
                for ($row=9; $row<=$lastRow; $row++) {
                    $first = (string)$sheet->getCell("A{$row}")->getValue();
                    if (str_starts_with($first, 'TOTAL GÉNÉRAL')) {
                        $sheet->getStyle("A{$row}:K{$row}")->applyFromArray(['font'=>['bold'=>true],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'D9EAF7']],'borders'=>['top'=>['borderStyle'=>Border::BORDER_MEDIUM,'color'=>['rgb'=>'1F4E78']]]]);
                    } elseif (str_starts_with($first, 'TOTAL ')) {
                        $sheet->getStyle("A{$row}:K{$row}")->applyFromArray(['font'=>['bold'=>true],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'EAF0F6']]]);
                    } elseif (str_starts_with($first, 'SOLDE INITIAL')) {
                        $sheet->getStyle("A{$row}:K{$row}")->applyFromArray(['font'=>['italic'=>true,'bold'=>true],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'FFF2CC']]]);
                    } elseif (str_contains($first, ' — ')) {
                        $sheet->mergeCells("A{$row}:K{$row}");
                        $sheet->getStyle("A{$row}:K{$row}")->applyFromArray(['font'=>['bold'=>true],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'D9D9D9']]]);
                    }
                }
                $sheet->getStyle("I9:K{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00;[Red]-#,##0.00;–');
                $sheet->getStyle("A9:K{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);
                $sheet->freezePane('A9');
                $sheet->setAutoFilter("A8:K{$lastRow}");
                $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(0);
                $sheet->getPageSetup()->setFitToPage(true);
                $sheet->getPageMargins()->setTop(0.55)->setBottom(0.55)->setLeft(0.2)->setRight(0.2);
                $sheet->getHeaderFooter()->setOddHeader('&C&"Arial,Bold"&16COMPTAFLOW');
                $sheet->getHeaderFooter()->setOddFooter('&L© ComptaFlow&CImprimé le &D à &T&RPage &P / &N');
                $sheet->getSheetView()->setShowGridLines(false);
            },
        ];
    }

    public function previewData(): array
    {
        return ['headers'=>['Date','Journal','N° Saisie','N° Pièce','Compte','Tiers','Libellé','Lettrage','Débit','Crédit','Solde'], 'rows'=>$this->previewRows];
    }
}
