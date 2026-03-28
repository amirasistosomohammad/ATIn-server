<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Accountability Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        h1 { font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #333; padding: 4px 6px; text-align: left; }
        th { background: #eee; }
    </style>
</head>
<body>
    <h1>Accountability Report</h1>
    <p>Generated: {{ $generatedAt }}</p>
    <table>
        <thead>
            <tr>
                <th>Control Number</th>
                <th>Document Type</th>
                <th>Action</th>
                <th>User</th>
                <th>Moved At</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @foreach($entries as $e)
            <tr>
                <td>{{ $e->document?->control_number ?? '—' }}</td>
                <td>{{ $e->document ? $e->document->documentTypeLabel() : '—' }}</td>
                <td>{{ $e->action }}</td>
                <td>{{ $e->user?->name ?? '—' }}</td>
                <td>{{ $e->moved_at?->format('Y-m-d H:i') ?? '—' }}</td>
                <td>{{ $e->remarks ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
