param(
    [ValidateSet("win-x64", "win-arm64")]
    [string]$Runtime = "win-x64",
    [ValidateSet("production", "sandbox")]
    [string]$Profile = "production"
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$assetDirectory = Join-Path $root "src/AltreoPrintAgent/Assets"
$temporaryDirectory = Join-Path ([System.IO.Path]::GetTempPath()) ("altreo-print-agent-" + [guid]::NewGuid())
$zipPath = Join-Path $temporaryDirectory "sumatra.zip"
$sumatraVersion = "3.6.1"

if ($Runtime -eq "win-arm64") {
    $sumatraFile = "SumatraPDF-$sumatraVersion-arm64"
    $expectedHash = "f21091ca6741e4049b0ca375121708a7959803d39336f0468084808943667ffe"
} else {
    $sumatraFile = "SumatraPDF-$sumatraVersion-64"
    $expectedHash = "98b33a518d42986856d225064b0cd2d3643ecf78cbf84ab873d26cc51877a544"
}

New-Item -ItemType Directory -Force -Path $temporaryDirectory, $assetDirectory | Out-Null
try {
    $url = "https://www.sumatrapdfreader.org/dl/rel/$sumatraVersion/$sumatraFile.zip"
    Write-Host "Pobieranie SumatraPDF $sumatraVersion z oficjalnej strony..."
    Invoke-WebRequest -Uri $url -OutFile $zipPath
    $actualHash = (Get-FileHash -Path $zipPath -Algorithm SHA256).Hash.ToLowerInvariant()
    if ($actualHash -ne $expectedHash) { throw "Nieprawidlowy SHA-256 SumatraPDF: $actualHash" }
    Expand-Archive -Path $zipPath -DestinationPath $temporaryDirectory -Force
    Copy-Item (Join-Path $temporaryDirectory "$sumatraFile.exe") (Join-Path $assetDirectory "SumatraPDF.exe") -Force

    $output = Join-Path $root "dist/$Profile/$Runtime"
    dotnet publish (Join-Path $root "src/AltreoPrintAgent/AltreoPrintAgent.csproj") `
        -c Release -r $Runtime --self-contained true -o $output `
        -p:PublishSingleFile=true -p:IncludeNativeLibrariesForSelfExtract=true `
        -p:AgentProfile=$Profile `
        -p:DebugType=None -p:DebugSymbols=false
    if ($LASTEXITCODE -ne 0) { throw "dotnet publish zakonczyl sie bledem." }
    $binaryName = if ($Profile -eq "sandbox") { "AltreoPrintAgent-Sandbox.exe" } else { "AltreoPrintAgent.exe" }
    Write-Host "Gotowe: $output/$binaryName"
} finally {
    Remove-Item -Recurse -Force $temporaryDirectory -ErrorAction SilentlyContinue
}
