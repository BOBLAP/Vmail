# Build and deploy the current Vinz checkout; never export local mail data or secrets.
[CmdletBinding(SupportsShouldProcess)]
param(
    [ValidatePattern('^[a-zA-Z0-9.-]+$')]
    [string]$Server = '91.214.190.192',
    [ValidatePattern('^[a-zA-Z0-9_-]+$')]
    [string]$User = 'ubuntu',
    [ValidateRange(1, 65535)]
    [int]$Port = 22,
    [string]$Image = 'pl-mail-vinz:local',
    [string]$IdentityFile,
    [switch]$CheckSshOnly,
    [switch]$SkipBuild
)

$ErrorActionPreference = 'Stop'
$repo = Split-Path $PSScriptRoot -Parent
$remoteScript = Join-Path $PSScriptRoot 'update-vps.sh'
$previewConfig = Join-Path $PSScriptRoot 'vps-preview-config.py'
$previewDefinition = Join-Path $repo 'compose.gotenberg.yaml'
$target = "${User}@${Server}"
$stamp = (Get-Date).ToUniversalTime().ToString('yyyyMMdd-HHmmss') + '-' + [guid]::NewGuid().ToString('N').Substring(0, 8)
$remoteDir = "/home/$User/vmail-updates/$stamp"
$localDir = Join-Path $repo "var/vps-updates/$stamp"

function Invoke-Checked {
    param([string]$Program, [string[]]$Arguments)
    & $Program @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "$Program a echoue (code $LASTEXITCODE). Mise a jour interrompue."
    }
}

if (-not $PSCmdlet.ShouldProcess($target, "Sauvegarder Vmail et deployer l'image $Image")) {
    return
}
foreach ($program in @('docker', 'ssh', 'scp')) {
    Get-Command $program -ErrorAction Stop | Out-Null
}
$sshOptions = @('-o', 'KexAlgorithms=curve25519-sha256')
if ($IdentityFile) {
    $key = (Resolve-Path -LiteralPath $IdentityFile -ErrorAction Stop).Path
    $sshOptions += @('-i', $key, '-o', 'IdentitiesOnly=yes')
}
Write-Host "Verification de l'acces SSH a $target..."
Invoke-Checked -Program ssh -Arguments (@('-p', "$Port") + $sshOptions + @($target, 'true'))
if ($CheckSshOnly) {
    Write-Host 'Connexion SSH valide. Aucun deploiement effectue.'
    return
}
foreach ($file in @($remoteScript, $previewConfig, $previewDefinition)) {
    if (-not (Test-Path -LiteralPath $file)) { throw "Fichier de deploiement manquant : $file" }
}
if (-not $SkipBuild) {
    if ($Image -ne 'pl-mail-vinz:local') {
        throw 'Une image personnalisee exige -SkipBuild. La construction automatique produit pl-mail-vinz:local.'
    }
    Write-Host 'Construction de la version actuelle du depot...'
    Push-Location $repo
    try {
        Invoke-Checked -Program docker -Arguments @('compose', '-f', 'compose.yaml', '-f', 'compose.vinz.yaml', 'build', 'php')
    } finally {
        Pop-Location
    }
}
$imageId = & docker image inspect $Image --format '{{.Id}}'
if ($LASTEXITCODE -ne 0 -or $imageId -notmatch '^sha256:[a-f0-9]{64}$') {
    throw "Image $Image introuvable. Construisez-la d'abord avec compose.vinz.yaml."
}
$imageId = $imageId.Trim()
New-Item -ItemType Directory -Path $localDir | Out-Null
$rawArchive = Join-Path $localDir 'image.tar'
$archive = Join-Path $localDir 'image.tar.gz'
$imageBytes = & docker image inspect $imageId --format '{{.Size}}'
if ($LASTEXITCODE -ne 0 -or $imageBytes -notmatch '^[0-9]+$') { throw "Taille de l'image introuvable." }
Write-Host "Export de $Image ($imageId)..."
Invoke-Checked -Program docker -Arguments @('image', 'save', '-o', $rawArchive, $imageId)
Write-Host 'Compression du transfert...'
$sourceStream = [System.IO.File]::OpenRead($rawArchive)
try {
    $destinationStream = [System.IO.File]::Create($archive)
    try {
        $gzipStream = [System.IO.Compression.GZipStream]::new($destinationStream, [System.IO.Compression.CompressionLevel]::Fastest)
        try { $sourceStream.CopyTo($gzipStream) } finally { $gzipStream.Dispose() }
    } finally { $destinationStream.Dispose() }
} finally { $sourceStream.Dispose() }
Remove-Item -LiteralPath $rawArchive
$hash = (Get-FileHash -LiteralPath $archive -Algorithm SHA256).Hash.ToLowerInvariant()
Write-Host 'Transfert vers le VPS (aucune donnee mail locale)...'
Invoke-Checked -Program ssh -Arguments (@('-p', "$Port") + $sshOptions + @($target, "umask 077; mkdir -p '$remoteDir'"))
Invoke-Checked -Program scp -Arguments (@('-P', "$Port") + $sshOptions + @($archive, $remoteScript, $previewConfig, $previewDefinition, "${target}:${remoteDir}/"))
Write-Host 'Sauvegarde et mise a jour sur le VPS. Sudo peut demander votre mot de passe.'
Invoke-Checked -Program ssh -Arguments (@('-t', '-p', "$Port") + $sshOptions + @($target,
    "sudo bash '$remoteDir/update-vps.sh' '$remoteDir/image.tar.gz' '$hash' '$imageId' '$imageBytes'"))
# Only remove this run's archive after the remote script reported success.
Remove-Item -LiteralPath $archive
Write-Host 'Mise a jour terminee : image verifiee et apercus Office actives. Rechargez https://vmail.lagratte.net avec Ctrl+F5.'
