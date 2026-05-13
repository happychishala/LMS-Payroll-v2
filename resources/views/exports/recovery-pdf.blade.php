<!DOCTYPE html>
<html>
<head>
    <title>Loan Recovery Report</title>
    <style>
        body { font-family: sans-serif; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 10px; }
        th, td { border: 1px solid #444; padding: 6px; text-align: left; }
    </style>
</head>
<body>
    <h2>Loan Recovery Report</h2>
    <table>
        <thead>
            <tr>
                <th>Loan ID</th>
                <th>Loan Type</th>
                <th>Month</th>
                <th>Due Date</th>
                <th>Expected</th>
                <th>Paid</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data as $row)
                <tr>
                    <td>{{ $row['loan_id'] }}</td>
                    <td>{{ $row['loan_name'] }}</td>
                    <td>{{ $row['month'] }}</td>
                    <td>{{ $row['due_date'] }}</td>
                    <td>{{ number_format($row['expected'], 2) }}</td>
                    <td>{{ number_format($row['paid'], 2) }}</td>
                    <td>{{ $row['status'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
