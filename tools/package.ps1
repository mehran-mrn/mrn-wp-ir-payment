<#
.SYNOPSIS
	Build an installable, reproducible plugin archive.
#>
[CmdletBinding()]
param(
	[string]$OutputDirectory = ''
)

$ErrorActionPreference = 'Stop'
$pluginRoot = Split-Path -Parent $PSScriptRoot
$versionLine = Select-String -LiteralPath (Join-Path $pluginRoot 'mrn-ir-payment.php') -Pattern '^\s*\*\s*Version:\s*(.+)$' | Select-Object -First 1
if (-not $versionLine) {
	throw 'Plugin version header was not found.'
}
$version = $versionLine.Matches[0].Groups[1].Value.Trim()
$OutputDirectory = if ($OutputDirectory) { $OutputDirectory } else { Join-Path $pluginRoot 'build' }
$outputRoot = [System.IO.Path]::GetFullPath($OutputDirectory)
$pluginPrefix = [System.IO.Path]::GetFullPath($pluginRoot).TrimEnd('\') + '\'
if (-not $outputRoot.StartsWith($pluginPrefix, [System.StringComparison]::OrdinalIgnoreCase)) {
	throw 'OutputDirectory must be inside the plugin directory.'
}

$stageRoot = Join-Path $outputRoot 'stage'
$stagePlugin = Join-Path $stageRoot 'mrn-ir-payment'
$artifact = Join-Path $outputRoot "mrn-ir-payment-$version.zip"

if (Test-Path -LiteralPath $stageRoot) {
	$resolvedStage = [System.IO.Path]::GetFullPath($stageRoot)
	if (-not $resolvedStage.StartsWith($pluginPrefix, [System.StringComparison]::OrdinalIgnoreCase)) {
		throw 'Resolved stage path escaped the plugin directory.'
	}
	Remove-Item -LiteralPath $stageRoot -Recurse -Force
}
New-Item -ItemType Directory -Path $stagePlugin -Force | Out-Null

$runtimeItems = @(
	'mrn-ir-payment.php',
	'uninstall.php',
	'readme.txt',
	'README.md',
	'CHANGELOG.md',
	'src',
	'assets'
)
foreach ($item in $runtimeItems) {
	Copy-Item -LiteralPath (Join-Path $pluginRoot $item) -Destination $stagePlugin -Recurse -Force
}

if (Test-Path -LiteralPath $artifact) {
	Remove-Item -LiteralPath $artifact -Force
}

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$archiveStream = [System.IO.File]::Open(
	$artifact,
	[System.IO.FileMode]::CreateNew,
	[System.IO.FileAccess]::ReadWrite,
	[System.IO.FileShare]::None
)
try {
	$archive = [System.IO.Compression.ZipArchive]::new(
		$archiveStream,
		[System.IO.Compression.ZipArchiveMode]::Create,
		$false
	)
	try {
		Get-ChildItem -LiteralPath $stagePlugin -File -Recurse | ForEach-Object {
			$relative = $_.FullName.Substring($stagePlugin.Length).TrimStart('\', '/')
			$entryName = "mrn-ir-payment/$($relative.Replace('\', '/'))"
			[System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
				$archive,
				$_.FullName,
				$entryName,
				[System.IO.Compression.CompressionLevel]::Optimal
			) | Out-Null
		}
	}
	finally {
		$archive.Dispose()
	}
}
finally {
	$archiveStream.Dispose()
}
Remove-Item -LiteralPath $stageRoot -Recurse -Force

$hash = (Get-FileHash -LiteralPath $artifact -Algorithm SHA256).Hash.ToLowerInvariant()
[System.IO.File]::WriteAllText(
	"$artifact.sha256",
	"$hash  $([System.IO.Path]::GetFileName($artifact))`n",
	[System.Text.UTF8Encoding]::new($false)
)

Write-Host "Artifact: $artifact" -ForegroundColor Green
Write-Host "Version: $version"
Write-Host "SHA256: $hash"
