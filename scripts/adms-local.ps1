param(
    [switch]$SelfTest,
    [switch]$ImportAttendance,
    [ValidateRange(1, 15)][int]$Minutes = 5
)

$ErrorActionPreference = 'Stop'
$local = '192.168.100.81'
$device = '192.168.100.82'
$port = 18081
if ($SelfTest) {
    if ($ImportAttendance) { throw 'La autoprueba no puede escribir checadas en MySQL.' }
    $local = '127.0.0.1'
    $device = $local
    $port = 18082
}

$rule = 'VeraTime-ADMS-Local-' + [guid]::NewGuid().ToString('N')
$ruleCreated = $false
$server = $null
try {
    if (Get-NetTCPConnection -LocalAddress $local -LocalPort $port -State Listen -ErrorAction SilentlyContinue) {
        throw 'El puerto de prueba ya tiene un receptor activo.'
    }
    $start = [Diagnostics.ProcessStartInfo]::new()
    $start.FileName = 'C:\xampp\php\php.exe'
    $start.WorkingDirectory = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
    $start.Arguments = "-d display_errors=0 -d post_max_size=64K -S ${local}:$port -t public scripts/adms-local-router.php"
    $start.UseShellExecute = $false
    $start.CreateNoWindow = $true
    $start.RedirectStandardError = $true
    $start.RedirectStandardOutput = $true
    $start.EnvironmentVariables['VERA_ADMS_PROBE_DEVICE'] = $device
    $start.EnvironmentVariables['VERA_ADMS_IMPORT'] = if ($ImportAttendance) { '1' } else { '0' }
    $server = [Diagnostics.Process]::Start($start)
    $server.BeginErrorReadLine()
    $server.BeginOutputReadLine()
    if (-not $SelfTest) {
        New-NetFirewallRule -Name $rule -DisplayName 'Vera Time ADMS local temporal' `
            -Direction Inbound -Action Allow -Protocol TCP -Profile Any -Program $start.FileName `
            -LocalAddress $local -LocalPort $port -RemoteAddress $device | Out-Null
        $ruleCreated = $true
    }

    $deadline = [DateTime]::UtcNow.AddMinutes($Minutes)
    while ([DateTime]::UtcNow -lt $deadline) {
        if ($server.WaitForExit(1000)) { throw 'El receptor PHP termino antes del plazo.' }
    }
} finally {
    if ($server -and -not $server.HasExited) { $server.Kill(); $server.WaitForExit() }
    if ($ruleCreated) { Remove-NetFirewallRule -Name $rule }
}
