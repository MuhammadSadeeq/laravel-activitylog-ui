<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #ddd;
            padding-bottom: 20px;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
            color: #2d3748;
        }

        .meta-info {
            margin-bottom: 20px;
            font-size: 11px;
            color: #666;
        }

        .meta-info table {
            width: 100%;
            border-collapse: collapse;
        }

        .meta-info td {
            padding: 4px 0;
            vertical-align: top;
        }

        .meta-info .label {
            font-weight: bold;
            width: 120px;
        }

        .activities-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .activities-table th,
        .activities-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }

        .activities-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            font-size: 11px;
            text-transform: uppercase;
        }

        .activities-table td {
            font-size: 10px;
        }

        .event-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .event-created { background-color: #d4edda; color: #155724; }
        .event-updated { background-color: #cce7ff; color: #004085; }
        .event-deleted { background-color: #f8d7da; color: #721c24; }
        .event-restored { background-color: #fff3cd; color: #856404; }
        .event-login { background-color: #e2e3ff; color: #383d75; }
        .event-logout { background-color: #e2e3ff; color: #383d75; }
        .event-system { background-color: #f1c2ff; color: #6b1f84; }
        .event-default { background-color: #e9ecef; color: #495057; }

        .description {
            max-width: 200px;
            word-wrap: break-word;
        }

        .changes {
            font-style: italic;
            color: #666;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #888;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }

        .page-break {
            page-break-after: always;
        }

        /* Responsive adjustments for smaller content */
        @media print {
            body { margin: 0; }
            .header { page-break-after: avoid; }
            .activities-table { page-break-inside: auto; }
            .activities-table tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $title }}</h1>
    </div>

    <div class="meta-info">
        <table>
            <tr>
                <td class="label">Generated At:</td>
                <td>{{ $generated_at->format('F j, Y \a\t g:i A T') }}</td>
            </tr>
            <tr>
                <td class="label">Total Records:</td>
                <td>{{ number_format($total_count) }}</td>
            </tr>
            @if(!empty($filters))
            <tr>
                <td class="label">Filters Applied:</td>
                <td>
                    @foreach($filters as $key => $value)
                        @if($value)
                            <strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong>
                            {{ is_array($value) ? implode(', ', $value) : $value }}
                            @if(!$loop->last), @endif
                        @endif
                    @endforeach
                </td>
            </tr>
            @endif
        </table>
    </div>

    <table class="activities-table">
        <thead>
            <tr>
                <th style="width: 8%;">ID</th>
                <th style="width: 15%;">Date & Time</th>
                <th style="width: 15%;">User</th>
                <th style="width: 10%;">Event</th>
                <th style="width: 15%;">Subject</th>
                <th style="width: 25%;">Description</th>
                <th style="width: 12%;">Changes</th>
            </tr>
        </thead>
        <tbody>
            @forelse($activities as $activity)
            <tr>
                <td>{{ $activity->id }}</td>
                <td>{{ $activity->created_at->format('M j, Y H:i') }}</td>
                <td>{{ $activity->causer_name ?? 'System' }}</td>
                <td>
                    <span class="event-badge event-{{ $activity->event ?? 'default' }}">
                        {{ $activity->event ?? 'unknown' }}
                    </span>
                </td>
                <td>
                    @if($activity->subject_type)
                        {{ class_basename($activity->subject_type) }} #{{ $activity->subject_id }}
                    @else
                        N/A
                    @endif
                </td>
                <td class="description">{{ $activity->description }}</td>
                <td class="changes">
                    @if($activity->hasPropertyChanges())
                        {{ $activity->getChangesSummary() }}
                    @else
                        No changes tracked
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" style="text-align: center; padding: 40px; color: #666;">
                    No activities found matching the specified criteria.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>
            This report contains {{ number_format($total_count) }} activity log entries.
            Generated by ActivityLog UI on {{ $generated_at->format('F j, Y \a\t g:i A') }}.
        </p>
    </div>
</body>
</html>
