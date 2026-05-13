<?php

namespace App\Imports;

use App\Models\StatusReason;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class StatusReasonsImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        // Expect columns: code, group, label, is_active (tweak to match your sheet headers)
        return new StatusReason([
            'code'      => (string)($row['code'] ?? ''),
            'group'     => $row['group'] ?? null,
            'label'     => $row['label'] ?? ($row['description'] ?? ''),
            'is_active' => isset($row['is_active']) ? (bool)$row['is_active'] : true,
        ]);
    }
}
