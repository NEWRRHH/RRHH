<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DocumentTypesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $data = [
            ['name' => 'Nómina', 'description' => 'Documentos relacionados con nóminas', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Documentación', 'description' => 'Identificaciones, contratos, certificados', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Certificados', 'description' => 'Certificados médicos, formación, etc.', 'created_at' => $now, 'updated_at' => $now],
        ];

        foreach ($data as $row) {
            $exists = DB::table('document_types')->where('name', $row['name'])->first();
            if (! $exists) {
                DB::table('document_types')->insert($row);
            }
        }
    }
}
