<#
.SYNOPSIS
	Deploy the packaged plugin to Zarsam with host verification and rollback.
#>
[CmdletBinding()]
param(
	[string]$ArtifactPath = ''
)

$ErrorActionPreference = 'Stop'
$pluginRoot = Split-Path -Parent $PSScriptRoot
$workspaceRoot = Split-Path -Parent (Split-Path -Parent $pluginRoot)
$versionLine = Select-String -LiteralPath (Join-Path $pluginRoot 'mrn-ir-payment.php') -Pattern '^\s*\*\s*Version:\s*(.+)$' | Select-Object -First 1
$version = $versionLine.Matches[0].Groups[1].Value.Trim()
$ArtifactPath = if ($ArtifactPath) { $ArtifactPath } else { Join-Path $pluginRoot "build\mrn-ir-payment-$version.zip" }
$metadataPath = Join-Path $workspaceRoot 'Docs\.secrets\infrastructure.env'
$credentialPath = Join-Path $workspaceRoot 'Docs\.secrets\server-root.credential.xml'
$plinkPath = 'C:\Program Files\PuTTY\plink.exe'
$pscpPath = 'C:\Program Files\PuTTY\pscp.exe'

foreach ($required in @($ArtifactPath, $metadataPath, $credentialPath, $plinkPath, $pscpPath)) {
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
$credential = Import-Clixml -LiteralPath $credentialPath
$networkCredential = $credential.GetNetworkCredential()
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

function Invoke-RemoteCommand {
	param([Parameter(Mandatory)][string]$Command)
	$argsList = @('-batch', '-ssh', '-P', "$sshPort", '-l', $sshUser, '-pw', $sshPassword, '-hostkey', $puttyHostKey, $sshHost, $Command)
	& $plinkPath @argsList
	if ($LASTEXITCODE -ne 0) { throw "Remote command failed with exit code $LASTEXITCODE." }
}

$deploymentId = Get-Date -Format 'yyyyMMdd-HHmmss'
$stage = "/home/masnavi/.mrn-deploys/ir-payment-$deploymentId"
$backup = "/home/masnavi/backups/mrn-ir-payment-$deploymentId"
$remotePackage = "$stage/mrn-ir-payment-$version.zip"
$localHash = (Get-FileHash -LiteralPath $ArtifactPath -Algorithm SHA256).Hash.ToLowerInvariant()

Invoke-RemoteCommand -Command @"
set -eu
test -f '$docroot/wp-config.php'
cd '$docroot'
wp --allow-root core is-installed
wp --allow-root plugin is-active woocommerce
mkdir -p '$stage/extract' '$backup'
wp --allow-root db export '$backup/database.sql' --add-drop-table --quiet
chmod 600 '$backup/database.sql'
printf 'preflight=ok\nwoocommerce=%s\n' "`$(wp --allow-root plugin get woocommerce --field=version)"
"@

$copyArgs = @('-batch', '-P', "$sshPort", '-l', $sshUser, '-pw', $sshPassword, '-hostkey', $puttyHostKey, $ArtifactPath, "${sshUser}@${sshHost}:$remotePackage")
& $pscpPath @copyArgs
if ($LASTEXITCODE -ne 0) { throw "Package upload failed with exit code $LASTEXITCODE." }

Invoke-RemoteCommand -Command @"
set -eu
live='$docroot/wp-content/plugins/mrn-ir-payment'
incoming='$stage/extract/mrn-ir-payment'
previous='$backup/mrn-ir-payment.previous'
active_before=0
cd '$docroot'
if wp --allow-root plugin is-active mrn-ir-payment; then active_before=1; fi
printf '%s\n' "`$active_before" > '$backup/active-before'
rollback() {
	set +e
	cd '$docroot'
	wp --allow-root plugin deactivate mrn-ir-payment >/dev/null 2>&1
	if [ -d "`$live" ]; then mv "`$live" '$stage/mrn-ir-payment.failed'; fi
	if [ -d "`$previous" ]; then mv "`$previous" "`$live"; fi
	wp --allow-root db import '$backup/database.sql' >/dev/null 2>&1
	if [ "`$active_before" = '1' ] && [ -d "`$live" ]; then wp --allow-root plugin activate mrn-ir-payment >/dev/null 2>&1; fi
	wp --allow-root cache flush >/dev/null 2>&1
}
trap rollback EXIT HUP INT TERM
printf '%s  %s\n' '$localHash' '$remotePackage' | sha256sum -c -
unzip -q '$remotePackage' -d '$stage/extract'
test -f "`$incoming/mrn-ir-payment.php"
test -f "`$incoming/assets/css/admin.css"
grep -q 'Version:[[:space:]]*$version' "`$incoming/mrn-ir-payment.php"
find "`$incoming" -type f -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null
owner_group="`$(stat -c '%U:%G' '$docroot')"
chown -R "`$owner_group" "`$incoming"
find "`$incoming" -type d -exec chmod 755 {} +
find "`$incoming" -type f -exec chmod 644 {} +
if [ -d "`$live" ]; then mv "`$live" "`$previous"; fi
mv "`$incoming" "`$live"
wp --allow-root plugin activate mrn-ir-payment >/dev/null
wp --allow-root plugin is-active mrn-ir-payment
test "`$(wp --allow-root plugin get mrn-ir-payment --field=version)" = '$version'
test "`$(wp --allow-root option get mrn_ir_payment_db_version)" = '1.0.0'
wp --allow-root cache flush >/dev/null
curl -fsSL 'http://zarsamgold.ir/?mrn_payment_deploy=$deploymentId' >/dev/null
trap - EXIT HUP INT TERM
printf 'deploy=ok\nversion=$version\nbackup=%s\n' '$backup'
"@

Write-Host "MRN Iran Payment $version deployed to Zarsam." -ForegroundColor Green
Write-Host "Backup: $backup"
