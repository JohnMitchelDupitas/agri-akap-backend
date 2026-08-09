<?php

namespace App\Imports;

use App\Models\Farmer;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Bulk upsert of the official RSBSA masterlist.
 *
 * Expected Excel headers (aliases accepted):
 *   rsbsa_no, last_name|surname, first_name, middle_name, ext_name,
 *   birthday|birthdate, barangay|permanent_brgy, mobile_number
 *
 * Matching key: rsbsa_no — existing rows are updated; new rows are created
 * with safe defaults for columns required by the farmers schema.
 */
class FarmersImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $data = $this->normalizeRow($row->toArray());

            if ($data === null) {
                $this->skipped++;
                continue;
            }

            $existing = Farmer::withTrashed()->where('rsbsa_no', $data['rsbsa_no'])->first();

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }

                $existing->update([
                    'surname' => $data['surname'],
                    'first_name' => $data['first_name'],
                    'middle_name' => $data['middle_name'],
                    'ext_name' => $data['ext_name'],
                    'no_middle_name' => empty($data['middle_name']),
                    'no_ext_name' => empty($data['ext_name']),
                    'birthdate' => $data['birthdate'],
                    'permanent_brgy' => $data['permanent_brgy'],
                    'mobile_number' => $data['mobile_number'],
                ]);
                $this->updated++;
                continue;
            }

            Farmer::create([
                'rsbsa_no' => $data['rsbsa_no'],
                'transaction_code' => 'IMP-'.Str::upper(Str::random(10)),
                'qr_code_hash' => (string) Str::uuid(),
                'surname' => $data['surname'],
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'],
                'ext_name' => $data['ext_name'],
                'no_middle_name' => empty($data['middle_name']),
                'no_ext_name' => empty($data['ext_name']),
                'sex' => 'Male',
                'permanent_house_no' => 'N/A',
                'permanent_street' => 'N/A',
                'permanent_brgy' => $data['permanent_brgy'],
                'permanent_city' => 'Echague',
                'permanent_province' => 'Isabela',
                'permanent_region' => 'Region II',
                'birthdate' => $data['birthdate'],
                'mobile_number' => $data['mobile_number'],
                'is_mobile_owner' => true,
                'mothers_maiden_first_name' => 'N/A',
                'mothers_maiden_surname' => 'N/A',
                'civil_status' => 'Single',
                'highest_education' => 'None',
                'livelihood_type' => 'Farmer',
            ]);
            $this->created++;
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function normalizeRow(array $row): ?array
    {
        $get = function (array $keys) use ($row) {
            foreach ($keys as $key) {
                if (array_key_exists($key, $row) && $row[$key] !== null && trim((string) $row[$key]) !== '') {
                    return trim((string) $row[$key]);
                }
            }

            return null;
        };

        $rsbsa = $get(['rsbsa_no', 'rsbsa', 'rsbsa_number']);
        $surname = $get(['last_name', 'surname', 'lastname']);
        $firstName = $get(['first_name', 'firstname', 'given_name']);
        $barangay = $get(['barangay', 'permanent_brgy', 'brgy']);

        if (! $rsbsa || ! $surname || ! $firstName || ! $barangay) {
            return null;
        }

        $birthRaw = $get(['birthday', 'birthdate', 'date_of_birth', 'dob']);
        $birthdate = $this->parseDate($birthRaw);
        if ($birthdate === null) {
            return null;
        }

        $mobile = $get(['mobile_number', 'contact_number', 'phone', 'mobile']) ?? '09000000000';

        return [
            'rsbsa_no' => $rsbsa,
            'surname' => $surname,
            'first_name' => $firstName,
            'middle_name' => $get(['middle_name', 'middlename']),
            'ext_name' => $get(['ext_name', 'extension_name', 'suffix']),
            'birthdate' => $birthdate,
            'permanent_brgy' => $barangay,
            'mobile_number' => $mobile,
        ];
    }

    private function parseDate(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            // Excel serial date numbers
            if (is_numeric($value)) {
                return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value))
                    ->format('Y-m-d');
            }

            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
