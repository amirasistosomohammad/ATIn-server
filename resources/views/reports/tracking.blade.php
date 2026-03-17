<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Tracking Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        h1 { font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #333; padding: 4px 6px; text-align: left; }
        th { background: #eee; }
    </style>
</head>
<body>
    <h1>Tracking Report</h1>
    <p>Generated: {{ $generatedAt }}</p>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Control Number</th>
                <th>Document Type</th>
                <th>Status</th>
                <th>Current Holder</th>
                <th>Last Action</th>
                <th>Last Moved</th>
            </tr>
        </thead>
        <tbody>
            @foreach($documents as $index => $doc)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $doc->control_number }}</td>
                <td>{{ $doc->documentType?->name ?? '—' }}</td>
                <td>{{ $doc->status }}</td>
                <td>{{ $doc->currentHolder?->name ?? '—' }}</td>
                <td>{{ $doc->logbookEntries->first()?->action ?? '—' }}</td>
                <td>{{ $doc->logbookEntries->first()?->moved_at?->format('Y-m-d H:i') ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
