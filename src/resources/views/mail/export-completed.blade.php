@component('mail::message')
# Activity Log Export Ready

Hello {{ $user_name }},

Your requested activity log export ({{ $export_format }}) has been successfully generated and is ready for you to download.

@component('mail::button', ['url' => $download_url])
Download Export
@endcomponent

## Export Details
- **File name:** {{ $file_name }}
- **Format:** {{ $export_format }}
- **Generated at:** {{ $exported_at }}
- **Job ID:** {{ $job_id }}

@if(!empty($applied_filters))
## Applied Filters
@foreach($applied_filters as $filterKey => $filterValue)
- **{{ \Illuminate\Support\Str::headline($filterKey) }}:** {{ is_array($filterValue) ? implode(', ', $filterValue) : $filterValue }}
@endforeach
@endif

Thanks for using {{ config('app.name') }}!

Regards,
{{ $fromName ?? config('app.name') }}
@endcomponent
