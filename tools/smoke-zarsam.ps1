<#
.SYNOPSIS
	Run read-only runtime checks for MRN Iran Payment on Zarsam.
#>
[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$pluginRoot = Split-Path -Parent $PSScriptRoot
$workspaceRoot = Split-Path -Parent (Split-Path -Parent $pluginRoot)
$metadataPath = Join-Path $workspaceRoot 'Docs\.secrets\infrastructure.env'
$credentialPath = Join-Path $workspaceRoot 'Docs\.secrets\server-root.credential.xml'
$smokeFile = Join-Path $PSScriptRoot 'remote-smoke.php'
$plinkPath = 'C:\Program Files\PuTTY\plink.exe'
$pscpPath = 'C:\Program Files\PuTTY\pscp.exe'

foreach ($required in @($metadataPath, $credentialPath, $smokeFile, $plinkPath, $pscpPath)) {
	if (-not (Test-Path -LiteralPath $required -PathType Leaf)) {
		throw "Required file is missing: $required"
	}
}

$connection = @{}
Get-Content -LiteralPath $metadataPath | ForEach-Object {
	if ($_ -match '^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)\s*$') {
		$connection[$matches[1]] = $matches[2].Trim().Trim('"').Trim("'")
	}
}
$networkCredential = (Import-Clixml -LiteralPath $credentialPath).GetNetworkCredential()
$sshUser = $networkCredential.UserName
$sshPassword = $networkCredential.Password
$sshHost = $connection['MRN_SSH_FORWARD_HOST']
$sshPort = [int]$connection['MRN_SSH_FORWARD_PORT']
$docroot = $connection['MRN_REMOTE_PUBLIC_HTML']
$expectedFingerprint = $connection['MRN_SSH_ED25519_FINGERPRINT']
$keyFile = New-TemporaryFile

try {
	$previousErrorAction = $ErrorActionPreference
	$ErrorActionPreference = 'Continue'
	$keyLines = & ssh-keyscan -p $sshPort -t ed25519 $sshHost 2>$null
	$ErrorActionPreference = $previousErrorAction
	if (-not $keyLines) { throw 'SSH key scan returned no ED25519 key.' }
	[System.IO.File]::WriteAllLines($keyFile.FullName, [string[]]$keyLines, [System.Text.UTF8Encoding]::new($false))
	$shaLine = & ssh-keygen -lf $keyFile.FullName 2>$null | Select-Object -First 1
	if ($shaLine -notmatch '(SHA256:[A-Za-z0-9+/=]+)' -or $matches[1] -ne $expectedFingerprint) {
		throw 'SSH host fingerprint mismatch.'
	}
	$md5Line = & ssh-keygen -l -E md5 -f $keyFile.FullName 2>$null | Select-Object -First 1
	if ($md5Line -notmatch '(MD5:[0-9a-f:]+)') { throw 'Could not derive the PuTTY host key.' }
	$puttyHostKey = $matches[1].Substring(4)
}
finally {
	Remove-Item -LiteralPath $keyFile.FullName -Force -ErrorAction SilentlyContinue
}

$remoteFile = "/home/masnavi/.mrn-deploys/mrn-ir-payment-smoke-$([DateTimeOffset]::UtcNow.ToUnixTimeSeconds()).php"
$copyArgs = @('-batch', '-P', "$sshPort", '-l', $sshUser, '-pw', $sshPassword, '-hostkey', $puttyHostKey, $smokeFile, "${sshUser}@${sshHost}:$remoteFile")
& $pscpPath @copyArgs
if ($LASTEXITCODE -ne 0) { throw "Smoke file upload failed with exit code $LASTEXITCODE." }

$remoteCommand = "set -eu; trap 'rm -f ""$remoteFile""' EXIT; cd '$docroot'; wp --allow-root plugin is-active mrn-ir-payment; wp --allow-root eval-file '$remoteFile'"
$argsList = @('-batch', '-ssh', '-P', "$sshPort", '-l', $sshUser, '-pw', $sshPassword, '-hostkey', $puttyHostKey, $sshHost, $remoteCommand)
& $plinkPath @argsList
if ($LASTEXITCODE -ne 0) { throw "Runtime smoke checks failed with exit code $LASTEXITCODE." }
