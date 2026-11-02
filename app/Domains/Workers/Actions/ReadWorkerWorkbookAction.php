<?php

namespace App\Domains\Workers\Actions;

use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\User;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Throwable;
use ZipArchive;

class ReadWorkerWorkbookAction
{
    public const HEADERS = ['Clave', 'Nombre completo', 'Código de centro', 'Fecha de ingreso', 'RFC', 'Correo electrónico'];

    public function handle(User $actor, Company $company, string $path): array
    {
        Gate::forUser($actor)->authorize('viewAny', [Worker::class, $company]);
        $fail = fn (string $message) => throw ValidationException::withMessages(['file' => $message]);
        if (! is_file($path) || filesize($path) > 5 * 1024 * 1024) {
            $fail('Carga un archivo XLS o XLSX de hasta 5 MB.');
        }
        try {
            $type = IOFactory::identify($path, [IOFactory::READER_XLSX, IOFactory::READER_XLS]);
            if ($type === IOFactory::READER_XLSX) {
                $zip = new ZipArchive;
                if ($zip->open($path) !== true) {
                    $fail('El archivo Excel no es válido.');
                }
                try {
                    $size = 0;
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $size += $zip->statIndex($i)['size'];
                    }
                    if ($size > 40 * 1024 * 1024 || $zip->numFiles > 1000) {
                        $fail('El archivo Excel excede el tamaño permitido.');
                    }
                } finally {
                    $zip->close();
                }
            }
            $reader = IOFactory::createReader($type);
            $sheets = $reader->listWorksheetInfo($path);
            $info = collect($sheets)->firstWhere('worksheetName', 'Trabajadores') ?? ($sheets[0] ?? null);
            if (! $info || $info['totalRows'] > 501 || $info['totalColumns'] > 6) {
                $fail('Usa la plantilla con seis columnas y un máximo de 500 trabajadores.');
            }
            $reader->setLoadSheetsOnly($info['worksheetName']);
            $book = $reader->load($path);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable) {
            $fail('No se pudo leer el archivo. Utiliza un XLS o XLSX válido, sin contraseña.');
        }

        try {
            $sheet = $book->getSheetByName($info['worksheetName']);
            foreach (self::HEADERS as $i => $header) {
                if ($sheet->getCell([$i + 1, 1])->getValue() !== $header) {
                    $fail('Los encabezados no coinciden. Descarga la plantilla y conserva su primera fila.');
                }
            }
            $centers = $company->centers()->where('status', 'active')->get()->keyBy('code');
            $seen = [];
            $seenEmails = [];
            $rows = [];
            foreach (range(2, max(2, $sheet->getHighestDataRow())) as $line) {
                $values = [];
                $errors = [];
                for ($col = 1; $col <= 6; $col++) {
                    $cell = $sheet->getCell([$col, $line]);
                    if ($cell->getDataType() === DataType::TYPE_FORMULA || $cell->getDataType() === DataType::TYPE_ERROR) {
                        $errors[] = 'Usa valores, sin fórmulas ni errores de Excel.';
                        $values[] = '';
                    } elseif ($col === 4 && is_numeric($cell->getValue()) && Date::isDateTime($cell)) {
                        $values[] = Date::excelToDateTimeObject((float) $cell->getValue())->format('Y-m-d');
                    } else {
                        $values[] = trim((string) $cell->getFormattedValue());
                    }
                }
                if ($errors === [] && implode('', $values) === '') {
                    continue;
                }
                [$code, $name, $centerCode, $started, $rfc, $email] = $values;
                $email = mb_strtolower($email);
                $date = null;
                foreach (['Y-m-d', 'd/m/Y'] as $format) {
                    try {
                        $parsed = CarbonImmutable::createFromFormat('!'.$format, $started);
                        if ($parsed && $parsed->format($format) === $started) {
                            $date = $parsed->toDateString();
                            break;
                        }
                    } catch (Throwable) {
                        // Try the next supported date format.
                    }
                }
                $data = ['employee_code' => $code, 'full_name' => $name, 'email' => $email, 'rfc' => $rfc ?: null, 'started_at' => $date, 'status' => 'active'];
                $validator = Validator::make($data, [
                    'employee_code' => ['required', 'string', 'max:50', Rule::unique('workers', 'employee_code')->where('company_id', $company->id)],
                    'full_name' => ['required', 'string', 'max:255'],
                    'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
                    'rfc' => ['nullable', 'string', 'max:20'],
                    'started_at' => ['required', 'date_format:Y-m-d'],
                ], [], ['employee_code' => 'clave', 'full_name' => 'nombre completo', 'started_at' => 'fecha de ingreso']);
                $errors = array_merge($errors, $validator->errors()->all());
                $key = mb_strtolower($code);
                if (isset($seen[$key])) {
                    $errors[] = 'La clave se repite en el archivo.';
                }
                $seen[$key] = true;
                if ($email !== '' && isset($seenEmails[$email])) {
                    $errors[] = 'El correo se repite en el archivo.';
                }
                $seenEmails[$email] = true;
                $center = $centers->get($centerCode);
                $allowed = $center && Gate::forUser($actor)->allows('createForCenter', [Worker::class, $company, $center]) && Gate::forUser($actor)->allows('create', [EmploymentRelationship::class, $company, $center]);
                if (! $allowed) {
                    $errors[] = 'El centro no está activo o no tienes permiso para dar de alta trabajadores en él.';
                }
                $rows[] = ['line' => $line, 'data' => $data, 'center_id' => $allowed ? $center->id : null, 'center_code' => $centerCode, 'center_name' => $allowed ? $center->name : '', 'errors' => array_values(array_unique($errors))];
            }
            if ($rows === []) {
                $fail('El archivo no contiene trabajadores. Completa al menos una fila.');
            }

            return $rows;
        } finally {
            $book->disconnectWorksheets();
        }
    }
}
