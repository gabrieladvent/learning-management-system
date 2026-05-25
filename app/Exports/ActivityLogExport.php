<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Spatie\Activitylog\Models\Activity;

class ActivityLogExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(protected ?Builder $query = null) {}

    public function query(): Builder
    {
        return ($this->query ?? Activity::query())->with('causer')->latest();
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Tanggal', 'Log', 'Event', 'Subject Type', 'Subject ID',
            'Causer Type', 'Causer ID', 'Causer Name', 'Description', 'Changes',
        ];
    }

    /**
     * @param  Activity  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        $causer = $row->causer;
        $causerName = $causer
            ? ($causer->full_name ?? $causer->name ?? '—')
            : '—';

        $changes = $row->properties?->get('attributes') ?? [];

        return [
            $row->created_at?->format('Y-m-d H:i:s') ?? '—',
            $row->log_name ?? '—',
            $row->event ?? '—',
            $row->subject_type ?? '—',
            $row->subject_id ?? '—',
            $row->causer_type ?? '—',
            $row->causer_id ?? '—',
            $causerName,
            $row->description ?? '—',
            is_array($changes) ? json_encode($changes, JSON_UNESCAPED_UNICODE) : (string) $changes,
        ];
    }
}
