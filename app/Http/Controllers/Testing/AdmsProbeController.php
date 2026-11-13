<?php

namespace App\Http\Controllers\Testing;

use App\Domains\TimeRecords\Actions\ImportAttendanceClockAction;
use App\Domains\TimeRecords\Exceptions\AttendanceClockImportException;
use App\Models\AttendanceClock;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class AdmsProbeController
{
    public function data(Request $request): Response
    {
        $production = $this->authorizeReceiver($request);
        $import = $production || getenv('VERA_ADMS_IMPORT') === '1';
        $serial = $request->query('SN');
        if ($import || $request->isMethod('GET')) {
            abort_unless(is_string($serial) && preg_match('/\A[A-Za-z0-9_-]{1,64}\z/', $serial), 400);
        }

        if ($request->isMethod('GET')) {
            if ($import && ! AttendanceClock::query()->where('serial', strtoupper($serial))->exists()) {
                return $this->plain('Reloj sin registrar', 403);
            }

            if (! $production) {
                $this->record(['tipo' => 'inicio']);
            }

            return $this->plain(implode("\n", [
                "GET OPTION FROM: {$serial}",
                'ATTLOGStamp=None',
                'ErrorDelay=30',
                'Delay=10',
                'TransInterval=0',
                'TransFlag=100000000000',
                'Realtime=1',
                'Encrypt=None',
                'PushOptionsFlag=0',
                '',
            ]));
        }

        $table = $request->query('table');
        abort_unless(is_string($table) && strcasecmp($table, 'ATTLOG') === 0, 403);
        $length = (string) $request->server('CONTENT_LENGTH', '');
        abort_unless(ctype_digit($length) && (int) $length <= 65536, 413);

        if ($import) {
            try {
                $count = app(ImportAttendanceClockAction::class)->handle($serial, $request->getContent());
            } catch (InvalidArgumentException $exception) {
                $diagnostic = $exception instanceof AttendanceClockImportException
                    ? ['motivo' => $exception->getMessage(), 'fila' => $exception->row,
                        'id_en_reloj' => $exception->clockUserId, 'estado' => $exception->state]
                    : ['motivo' => 'evento_invalido', 'fila' => null];
                if ($production) {
                    Log::channel('adms')->warning('adms.importacion_rechazada', $diagnostic);
                } else {
                    $this->record(['tipo' => 'importacion_rechazada', ...$diagnostic]);
                }

                return $this->plain('', 503);
            }
            if (! $production) {
                $this->record(['tipo' => 'importacion_confirmada', 'filas' => $count]);
            }

            return $this->plain('OK: '.$count);
        }

        $timezone = new DateTimeZone('America/Mexico_City');
        $now = new DateTimeImmutable('now', $timezone);
        $recent = [];
        $count = 0;
        foreach (preg_split('/\r?\n/', $request->getContent()) as $line) {
            if (! preg_match('/\A[^\t\r\n]+\t(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\t/', $line, $fields)) {
                continue;
            }

            $timestamp = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $fields[1], $timezone);
            if ($timestamp === false || $timestamp->format('Y-m-d H:i:s') !== $fields[1]) {
                continue;
            }

            $count++;
            if (abs($now->getTimestamp() - $timestamp->getTimestamp()) <= 600) {
                $recent[$timestamp->format(DATE_ATOM)] = true;
            }
        }

        $this->record(['tipo' => 'ATTLOG', 'filas' => $count, 'horas_recientes' => array_keys($recent)]);

        return $this->plain('', 503);
    }

    public function poll(Request $request): Response
    {
        $this->authorizeReceiver($request);

        return $this->plain('OK');
    }

    private function authorizeReceiver(Request $request): bool
    {
        if (app()->environment('production')) {
            $serial = $request->query('SN');
            $allowedSerial = config('adms.pilot_serial');
            $allowedIp = config('adms.pilot_ip');
            abort_unless(
                config('adms.enabled') === true
                && $request->isSecure()
                && is_string($allowedIp) && filter_var($allowedIp, FILTER_VALIDATE_IP)
                && $request->server('REMOTE_ADDR') === $allowedIp
                && is_string($allowedSerial) && is_string($serial)
                && preg_match('/\A[A-Za-z0-9_-]{1,64}\z/', $serial)
                && hash_equals(strtoupper($allowedSerial), strtoupper($serial)),
                404,
            );

            return true;
        }

        $device = getenv('VERA_ADMS_PROBE_DEVICE');
        abort_unless(app()->environment('local', 'testing') && PHP_SAPI !== 'apache2handler'
            && $device && $request->server('REMOTE_ADDR') === $device, 404);

        return false;
    }

    private function record(array $entry): void
    {
        $entry['recibido_utc'] = gmdate(DATE_ATOM);
        $file = getenv('VERA_ADMS_PROBE_DEVICE') === '127.0.0.1' ? 'adms-simulado.jsonl' : 'adms-local.jsonl';
        $written = file_put_contents(storage_path('logs/'.$file), json_encode($entry, JSON_THROW_ON_ERROR)."\n", FILE_APPEND | LOCK_EX);
        abort_if($written === false, 503);
    }

    private function plain(string $body, int $status = 200): Response
    {
        return response($body, $status, ['Content-Type' => 'text/plain', 'Cache-Control' => 'no-store']);
    }
}
