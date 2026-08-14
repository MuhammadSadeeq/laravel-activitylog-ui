<?php

namespace MuhammadSadeeq\ActivitylogUi\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ActivitiesExport extends DefaultValueBinder implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithCustomValueBinder
{
    /**
     * Write every string as a string, and never as a formula.
     *
     * PhpSpreadsheet's default binder promotes any value starting with '=' to a
     * formula, which is how a logged description could execute when the workbook
     * was opened. Prefixing an apostrophe stopped that but changed the data: a
     * description of "=SUM(A1:A2)" was exported as "'=SUM(A1:A2)", and an audit
     * export that alters what it reports is its own kind of wrong. Binding the
     * type explicitly keeps the value exactly as recorded and inert.
     */
    public function bindValue(Cell $cell, $value): bool
    {
        if (is_string($value) && $value !== '') {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    protected Collection $activities;
    protected array $options;

    public function __construct(Collection $activities, array $options = [])
    {
        $this->activities = $activities;
        $this->options = $options;
    }

    /**
     * Return collection of activities to export.
     */
    public function collection(): Collection
    {
        return $this->activities;
    }

    /**
     * Define the headings for the Excel file.
     */
    public function headings(): array
    {
        return $this->options['columns'] ?? [
            'ID',
            'Date & Time',
            'User',
            'Event',
            'Subject',
            'Description',
            'Changes',
        ];
    }

    /**
     * Map each activity to the desired export format.
     */
    public function map($activity): array
    {
        $columns = $this->options['columns'] ?? [
            'id', 'date_time', 'causer', 'event', 'subject', 'description', 'changes'
        ];

        $row = [];

        foreach ($columns as $column) {
            $row[] = match ($column) {
                'id' => $activity->id,
                'date_time' => $activity->created_at->format('Y-m-d H:i:s'),
                'causer' => $activity->causer_name ?? 'System',
                'event' => $activity->event ?? 'unknown',
                'subject' => $activity->subject_type ?
                    $activity->subject_type . ' #' . $activity->subject_id :
                    'N/A',
                'description' => $activity->description,
                'changes' => $activity->hasAttributeChanges() ?
                    $activity->getChangesSummary() :
                    'No changes tracked',
                'properties' => json_encode($activity->properties),
                default => $activity->{$column} ?? '',
            };
        }

        // No rewriting here: bindValue() above types every string as a string,
        // so nothing in a workbook cell is ever evaluated and the exported text
        // is byte-for-byte what was logged.
        return $row;
    }

    /**
     * Apply styles to the Excel worksheet.
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            // Style the first row as bold
            1 => ['font' => ['bold' => true]],

            // Apply border to all cells
            'A1:' . $sheet->getHighestColumn() . $sheet->getHighestRow() => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    ],
                ],
            ],
        ];
    }
}
