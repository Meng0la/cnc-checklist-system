<#
AeroCheck - backup automatico do banco de dados (mysqldump + compactacao + retencao).

Ajuste as variaveis abaixo para o seu servidor e depois registre este script no
Agendador de Tarefas do Windows (veja o passo a passo no README.md, secao 7).

Importante: o destino do backup fica FORA da pasta htdocs de proposito - um dump de banco
solto dentro do htdocs, mesmo com .htaccess, e um risco desnecessario. Nao mova este backup
para dentro de aerocheck\.
#>

$MysqlDumpExe = "D:\xampp\mysql\bin\mysqldump.exe"
$BackupDir    = "D:\xampp\backups\aerocheck"
$DbName       = "aerocheck_db"
$DbUser       = "root"
$DbPass       = ""      # defina aqui se o MySQL do XAMPP tiver senha de root
$DiasRetencao = 30      # backups mais antigos que isso sao apagados automaticamente

$logFile = Join-Path $BackupDir "backup.log"

function Write-Log($mensagem) {
    $linha = "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')  $mensagem"
    Add-Content -Path $logFile -Value $linha
}

try {
    if (-not (Test-Path $BackupDir)) {
        New-Item -ItemType Directory -Path $BackupDir -Force | Out-Null
    }

    if (-not (Test-Path $MysqlDumpExe)) {
        Write-Log "ERRO: mysqldump.exe nao encontrado em $MysqlDumpExe - ajuste a variavel MysqlDumpExe no script."
        exit 1
    }

    $timestamp = Get-Date -Format "yyyy-MM-dd_HHmmss"
    $sqlFile = Join-Path $BackupDir "aerocheck_$timestamp.sql"
    $zipFile = Join-Path $BackupDir "aerocheck_$timestamp.zip"

    $dumpArgs = @("-u$DbUser")
    if ($DbPass -ne "") { $dumpArgs += "-p$DbPass" }
    $dumpArgs += @("--single-transaction", "--routines", "--events", $DbName)

    & $MysqlDumpExe @dumpArgs 2>> $logFile | Out-File -FilePath $sqlFile -Encoding utf8

    if ((-not (Test-Path $sqlFile)) -or ((Get-Item $sqlFile).Length -eq 0)) {
        Write-Log "ERRO: dump ficou vazio ou nao foi criado ($sqlFile). Verifique usuario/senha do MySQL."
        exit 1
    }

    Compress-Archive -Path $sqlFile -DestinationPath $zipFile -Force
    Remove-Item $sqlFile -Force

    $tamanhoKb = [math]::Round((Get-Item $zipFile).Length / 1KB, 1)
    Write-Log "OK: backup criado em $zipFile ($tamanhoKb KB)"

    $removidos = Get-ChildItem -Path $BackupDir -Filter "aerocheck_*.zip" |
        Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-$DiasRetencao) }
    foreach ($f in $removidos) {
        Remove-Item $f.FullName -Force
        Write-Log "Removido backup antigo: $($f.Name)"
    }
} catch {
    Write-Log "ERRO INESPERADO: $($_.Exception.Message)"
    exit 1
}
