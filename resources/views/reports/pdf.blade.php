<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>{{ $title }}</title>
        <style>
            body {
                font-family: DejaVu Sans, sans-serif;
                font-size: 12px;
                color: #1f2937;
            }

            h1 {
                font-size: 20px;
                margin-bottom: 6px;
            }

            .meta {
                color: #6b7280;
                margin-bottom: 18px;
            }

            table {
                width: 100%;
                border-collapse: collapse;
            }

            th, td {
                border: 1px solid #d1d5db;
                padding: 8px;
                text-align: left;
            }

            th {
                background: #f3f4f6;
                font-size: 11px;
                text-transform: uppercase;
            }
        </style>
    </head>
    <body>
        <h1>{{ $title }}</h1>
        <div class="meta">Reporting period: {{ \Carbon\Carbon::create($filters['year'], $filters['month'], 1)->format('F Y') }}</div>

        <table>
            <thead>
                <tr>
                    @foreach ($headings as $heading)
                        <th>{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($row as $value)
                            <td>{{ $value }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </body>
</html>
