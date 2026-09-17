namespace AltreoPrintAgent;

public sealed record FiscalPrinterDevice(string DeviceKey,string Name,string Host,int Port,string? SerialNumber=null);
public sealed record PrinterInventory(IReadOnlyList<string> Printers, string? DefaultPrinter,IReadOnlyList<FiscalPrinterDevice> FiscalPrinters);

public sealed class PrinterDiscovery
{
    private readonly AgentSettings? _settings;

    public PrinterDiscovery(AgentSettings? settings = null) => _settings = settings;

    public async Task<PrinterInventory> DiscoverAsync(CancellationToken cancellationToken)
    {
        if (OperatingSystem.IsWindows())
        {
            var names = await ProcessRunner.RunAsync("powershell.exe", ["-NoProfile", "-NonInteractive", "-Command", "Get-CimInstance Win32_Printer | Sort-Object Name | ForEach-Object { $_.Name }"], TimeSpan.FromSeconds(20), cancellationToken);
            var defaultResult = await ProcessRunner.RunAsync("powershell.exe", ["-NoProfile", "-NonInteractive", "-Command", "Get-CimInstance Win32_Printer | Where-Object Default | Select-Object -First 1 -ExpandProperty Name"], TimeSpan.FromSeconds(20), cancellationToken);
            var fiscalResult=await ProcessRunner.RunAsync("powershell.exe",["-NoProfile","-NonInteractive","-Command","Get-CimInstance Win32_Printer | Where-Object { $_.Name -match '(?i)posnet|trio' } | ForEach-Object { $p=Get-CimInstance Win32_TCPIPPrinterPort | Where-Object Name -eq $_.PortName | Select-Object -First 1; if ($p -and $p.HostAddress) { $_.Name + \"`t\" + $p.HostAddress + \"`t\" + $p.PortNumber } }"],TimeSpan.FromSeconds(25),cancellationToken);
            return new PrinterInventory(ParseLines(names.StandardOutput), FirstLine(defaultResult.StandardOutput),WithConfigured(ParseFiscalPrinterLines(fiscalResult.StandardOutput)));
        }
        if (OperatingSystem.IsMacOS())
        {
            var names = await ProcessRunner.RunAsync("/usr/bin/env", ["LC_ALL=C","/usr/bin/lpstat","-p"], TimeSpan.FromSeconds(20), cancellationToken);
            var defaultResult = await ProcessRunner.RunAsync("/usr/bin/env", ["LC_ALL=C","/usr/bin/lpstat","-d"], TimeSpan.FromSeconds(20), cancellationToken);
            var devices=await ProcessRunner.RunAsync("/usr/bin/env",["LC_ALL=C","/usr/bin/lpstat","-v"],TimeSpan.FromSeconds(20),cancellationToken);
            return new PrinterInventory(ParseCupsPrinters(names.StandardOutput), ParseCupsDefault(defaultResult.StandardOutput),WithConfigured(ParseCupsFiscalPrinters(devices.StandardOutput)));
        }
        return new PrinterInventory([], null,WithConfigured([]));
    }

    internal static IReadOnlyList<string> ParseLines(string output) => output.ReplaceLineEndings("\n").Split('\n', StringSplitOptions.RemoveEmptyEntries | StringSplitOptions.TrimEntries).Distinct(StringComparer.Ordinal).Take(100).ToArray();

    internal static IReadOnlyList<string> ParseCupsPrinters(string output)
    {
        return output.ReplaceLineEndings("\n").Split('\n', StringSplitOptions.RemoveEmptyEntries | StringSplitOptions.TrimEntries)
            .Select(line=>line.StartsWith("printer ",StringComparison.OrdinalIgnoreCase)?line[8..]:(line.StartsWith("drukarka ",StringComparison.OrdinalIgnoreCase)?line[9..]:""))
            .Where(line=>line.Length>0)
            .Select(line => line.Split(' ', 2, StringSplitOptions.RemoveEmptyEntries)[0])
            .Where(name => !string.IsNullOrWhiteSpace(name)).Distinct(StringComparer.Ordinal).Take(100).ToArray();
    }

    internal static string? ParseCupsDefault(string output)
    {
        var line=FirstLine(output); if (line is null) return null;
        var separator=line.LastIndexOf(':'); return separator>=0 ? line[(separator+1)..].Trim() : null;
    }

    internal static IReadOnlyList<FiscalPrinterDevice> ParseFiscalPrinterLines(string output)
    {
        var devices=new List<FiscalPrinterDevice>();
        foreach (var line in ParseLines(output)) {
            var parts=line.Split('\t',StringSplitOptions.TrimEntries);
            if (parts.Length<3 || !int.TryParse(parts[2],out var port) || port is <1 or >65535) continue;
            devices.Add(new FiscalPrinterDevice($"tcp:{parts[1]}:{port}",parts[0],parts[1],port));
        }
        return devices.DistinctBy(device=>device.DeviceKey,StringComparer.OrdinalIgnoreCase).Take(50).ToArray();
    }

    internal static IReadOnlyList<FiscalPrinterDevice> ParseCupsFiscalPrinters(string output)
    {
        var devices=new List<FiscalPrinterDevice>();
        foreach (var line in ParseLines(output)) {
            if (!line.Contains("posnet",StringComparison.OrdinalIgnoreCase) && !line.Contains("trio",StringComparison.OrdinalIgnoreCase)) continue;
            var colon=line.IndexOf(':'); if (colon<0) continue;
            var name=line[..colon].Replace("device for ","",StringComparison.OrdinalIgnoreCase).Replace("urządzenie dla ","",StringComparison.OrdinalIgnoreCase).Trim();
            var uriText=line[(colon+1)..].Trim(); if (!Uri.TryCreate(uriText,UriKind.Absolute,out var uri) || string.IsNullOrWhiteSpace(uri.Host)) continue;
            var port=uri.IsDefaultPort?9100:uri.Port;
            devices.Add(new FiscalPrinterDevice($"tcp:{uri.Host}:{port}",name,uri.Host,port));
        }
        return devices.DistinctBy(device=>device.DeviceKey,StringComparer.OrdinalIgnoreCase).Take(50).ToArray();
    }

    private IReadOnlyList<FiscalPrinterDevice> WithConfigured(IReadOnlyList<FiscalPrinterDevice> detected)
    {
        if (_settings?.IsFiscalPrinterConfigured != true) return detected;
        var configured = new FiscalPrinterDevice(
            $"tcp:{_settings.FiscalPrinterHost.ToLowerInvariant()}:{_settings.FiscalPrinterPort}",
            "Posnet Trio",
            _settings.FiscalPrinterHost,
            _settings.FiscalPrinterPort);
        return detected.Append(configured).DistinctBy(device => device.DeviceKey, StringComparer.OrdinalIgnoreCase).Take(50).ToArray();
    }

    private static string? FirstLine(string output) => ParseLines(output).FirstOrDefault();
}
