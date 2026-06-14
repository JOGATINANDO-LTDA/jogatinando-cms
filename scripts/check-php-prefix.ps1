param(
    [string]$Path = (Get-Location).Path
)

$badFiles = @()

Get-ChildItem -Path $Path -Recurse -File -Filter *.php | ForEach-Object {
    if ($_.FullName -match '\\.git\\') { return }
    if ($_.FullName -match 'docker\\debug_env\.php$') { return }
    if ($_.FullName -match 'includes\\footer\.php$') { return }

    $bytes = [System.IO.File]::ReadAllBytes($_.FullName)
    if ($bytes.Length -lt 4) { return }

    $hasBom = $bytes.Length -ge 3 -and $bytes[0] -eq 239 -and $bytes[1] -eq 187 -and $bytes[2] -eq 191
    $hasPrefix = $bytes[0] -ne 60

    if ($hasBom -or $hasPrefix) {
        $badFiles += [PSCustomObject]@{
            File = $_.FullName
            Type = if ($hasBom) { 'BOM' } else { 'Prefix' }
            Bytes = ($bytes[0..([Math]::Min(3, $bytes.Length - 1))] -join ' ')
        }
    }
}

if ($badFiles.Count -gt 0) {
    $badFiles | Sort-Object File | Format-Table -AutoSize
    exit 1
}

Write-Host 'OK: nenhum PHP com BOM/prefixo inválido encontrado.'
