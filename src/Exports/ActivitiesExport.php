<?php

namespace MuhammadSadeeq\ActivitylogUi\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ActivitiesExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
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
                'changes' => $activity->hasPropertyChanges() ?
                    $activity->getChangesSummary() :
                    'No changes tracked',
                'properties' => json_encode($activity->properties),
                default => $activity->{$column} ?? '',
            };
        }

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
