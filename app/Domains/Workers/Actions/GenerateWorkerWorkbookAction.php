<?php

namespace App\Domains\Workers\Actions;

use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Support\Facades\Gate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class GenerateWorkerWorkbookAction
{
    public function build(User $actor, Company $company): Spreadsheet
    {
        Gate::forUser($actor)->authorize('viewAny', [Worker::class, $company]);
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Trabajadores');
        $sheet->fromArray(ReadWorkerWorkbookAction::HEADERS, null, 'A1');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:F501');
        $sheet->getStyle('A2:C501')->getNumberFormat()->setFormatCode('@');
        $sheet->getStyle('D2:D501')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $sheet->getStyle('E2:F501')->getNumberFormat()->setFormatCode('@');
        $sheet->getColumnDimension('F')->setWidth(42);
        foreach (['A' => 20, 'B' => 42, 'C' => 24, 'D' => 24, 'E' => 24] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $centers = $book->createSheet()->setTitle('Centros');
        $centers->fromArray(['Código de centro', 'Nombre'], null, 'A1');
        $line = 2;
        foreach ($company->centers()->where('status', 'active')->orderBy('name')->get() as $center) {
            if (! Gate::forUser($actor)->allows('createForCenter', [Worker::class, $company, $center]) || ! Gate::forUser($actor)->allows('create', [EmploymentRelationship::class, $company, $center])) {
                continue;
            }
            $centers->setCellValueExplicit('A'.$line, $center->code, DataType::TYPE_STRING);
            $centers->setCellValueExplicit('B'.$line, $center->name, DataType::TYPE_STRING);
            $line++;
        }
        $centers->getColumnDimension('A')->setWidth(24);
        $centers->getColumnDimension('B')->setWidth(48);
        $centers->freezePane('A2');
        if ($line > 2) {
            $book->addNamedRange(new NamedRange('CentrosDisponibles', $centers, '$A$2:$A$'.($line - 1)));
            for ($row = 2; $row <= 501; $row++) {
                $validation = $sheet->getCell('C'.$row)->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST)->setFormula1('CentrosDisponibles')
                    ->setAllowBlank(false)->setShowDropDown(true)->setShowErrorMessage(true)
                    ->setErrorStyle(DataValidation::STYLE_STOP)->setErrorTitle('Centro inválido')
                    ->setError('Selecciona un centro de la lista.');
            }
        }
        $guide = $book->createSheet()->setTitle('Instrucciones');
        $instructions = [
            ['Importación de trabajadores', 'Cómo llenar la plantilla'],
            ['Empresa', $company->name],
            ['1. Completa Trabajadores', 'Una persona por fila, máximo 500. Conserva los seis encabezados.'],
            ['Clave', 'Obligatoria y única en la empresa. Conserva los ceros iniciales.'],
            ['Nombre completo', 'Obligatorio, tal como debe aparecer en el sistema.'],
            ['Código de centro', 'Elige un código de la lista. Consulta sus nombres en Centros.'],
            ['Fecha de ingreso', 'Obligatoria. Usa AAAA-MM-DD, DD/MM/AAAA o una fecha de Excel.'],
            ['RFC', 'Opcional.'],
            ['Correo electrónico', 'Obligatorio y único. Será el acceso de la cuenta de trabajador que se creará automáticamente.'],
            ['2. Guarda el archivo', 'Puedes cargar XLSX o XLS. Usa valores sin fórmulas.'],
            ['3. Sube y revisa', 'El sistema muestra errores por fila. Corrígelos y vuelve a cargar.'],
            ['4. Importa', 'Se crean trabajadores activos y su relación laboral inicial.'],
            ['Datos existentes', 'No se actualizan ni se duplican trabajadores con una clave existente.'],
            ['Credenciales y organización', 'Las credenciales, áreas y departamentos se configuran después.'],
            ['Errores', 'Si hay errores, no se guarda ningún trabajador.'],
        ];
        foreach ($instructions as $i => $cells) {
            foreach ($cells as $j => $value) {
                $guide->setCellValueExplicit([$j + 1, $i + 1], $value, DataType::TYPE_STRING);
            }
        }
        $guide->getColumnDimension('A')->setWidth(30);
        $guide->getColumnDimension('B')->setWidth(85);
        $guide->getStyle('A1:B14')->getAlignment()->setWrapText(true);
        foreach ($book->getAllSheets() as $tab) {
            $last = $tab->getHighestDataColumn();
            $tab->getStyle('A1:'.$last.'1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $tab->getStyle('A1:'.$last.'1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0066DD');
            $tab->getRowDimension(1)->setRowHeight(30);
            $tab->setShowGridlines(false);
        }
        $book->setActiveSheetIndex(0);

        return $book;
    }

    public function handle(User $actor, Company $company)
    {
        $book = $this->build($actor, $company);

        return response()->streamDownload(function () use ($book) {
            try {
                (new Xlsx($book))->save('php://output');
            } finally {
                $book->disconnectWorksheets();
            }
        }, 'vera-time-plantilla-trabajadores.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store',
        ]);
    }
}
