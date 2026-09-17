using System.Reflection;

namespace AltreoPrintAgent;

public interface IPrintService
{
    Task<PrintResult> PrintAsync(string pdfPath, string printerName, string? printSettings, CancellationToken cancellationToken);
}

public sealed class PrintService : IPrintService
{
    private readonly AgentSettings _settings;

    public PrintService(AgentSettings settings) => _settings = settings;

    public Task<PrintResult> PrintAsync(string pdfPath, string printerName, string? printSettings, CancellationToken cancellationToken)
    {
        if (string.IsNullOrWhiteSpace(printerName)) return Task.FromResult(PrintResult.Error("Nie podano nazwy drukarki."));
        if (!File.Exists(pdfPath)) return Task.FromResult(PrintResult.Error("Pobrany plik PDF nie istnieje."));
        if (printerName.Length > 300 || printerName.ContainsAny('\r', '\n'))
            return Task.FromResult(PrintResult.Error("Nazwa drukarki jest nieprawidłowa."));

        if (OperatingSystem.IsWindows()) return PrintOnWindowsAsync(pdfPath, printerName, printSettings, cancellationToken);
        if (OperatingSystem.IsMacOS()) return PrintOnMacAsync(pdfPath, printerName, printSettings, cancellationToken);
        return Task.FromResult(PrintResult.Error("Obsługiwane systemy to Windows i macOS."));
    }

    private async Task<PrintResult> PrintOnWindowsAsync(string pdfPath, string printerName, string? printSettings, CancellationToken cancellationToken)
    {
        var sumatra = ResolveSumatraPath();
        if (sumatra is null)
            return PrintResult.Error("Nie znaleziono SumatraPDF.exe. Zbuduj wersję Windows skryptem build-windows.ps1 albo wskaż plik w ustawieniach.");

        var args = new List<string> { "-silent", "-print-to", printerName };
        if (!string.IsNullOrWhiteSpace(printSettings))
        {
            if (printSettings.Length > 300 || printSettings.ContainsAny('\r', '\n'))
                return PrintResult.Error("Ustawienia druku są nieprawidłowe.");
            args.Add("-print-settings");
            args.Add(printSettings);
        }
        args.Add(pdfPath);

        var result = await ProcessRunner.RunAsync(sumatra, args, TimeSpan.FromMinutes(3), cancellationToken);
        return result.ExitCode switch
        {
            0 => PrintResult.Printed(),
            4 => PrintResult.Offline($"Drukarka „{printerName}” nie istnieje lub jest niedostępna."),
            5 => PrintResult.Offline($"Sterownik lub urządzenie „{printerName}” zgłosiło błąd."),
            2 => PrintResult.Error("SumatraPDF nie może otworzyć pobranego pliku."),
            3 => PrintResult.Error("Dokument PDF nie zezwala na drukowanie."),
            6 => PrintResult.Error("Drukowanie zostało zablokowane przez zasady systemowe."),
            _ => PrintResult.Error($"SumatraPDF zakończył pracę kodem {result.ExitCode}. {Trim(result.StandardError)}")
        };
    }

    private static async Task<PrintResult> PrintOnMacAsync(string pdfPath, string printerName, string? printSettings, CancellationToken cancellationToken)
    {
        var status = await ProcessRunner.RunAsync("/usr/bin/lpstat", ["-p", printerName], TimeSpan.FromSeconds(15), cancellationToken);
        if (status.ExitCode != 0 || status.StandardOutput.Contains("disabled", StringComparison.OrdinalIgnoreCase))
            return PrintResult.Offline($"Drukarka „{printerName}” nie istnieje, jest wyłączona lub offline.");

        var arguments=new List<string> { "-d",printerName };
        var media=ParseCustomPaperForCups(printSettings);
        if (media is not null) { arguments.AddRange(["-o","media="+media,"-o","fit-to-page"]); }
        arguments.Add(pdfPath);
        var result = await ProcessRunner.RunAsync("/usr/bin/lp", arguments, TimeSpan.FromMinutes(2), cancellationToken);
        return result.ExitCode == 0
            ? PrintResult.Printed(Trim(result.StandardOutput, "Zadanie przekazane do kolejki CUPS."))
            : PrintResult.Error($"CUPS odrzucił zadanie (kod {result.ExitCode}). {Trim(result.StandardError)}");
    }

    internal static string? ParseCustomPaperForCups(string? printSettings)
    {
        if (string.IsNullOrWhiteSpace(printSettings)) return null;
        var match=System.Text.RegularExpressions.Regex.Match(printSettings,@"(?:^|,)paper=(?<w>\d+(?:\.\d+)?)mm\s*x\s*(?<h>\d+(?:\.\d+)?)mm(?:,|$)",System.Text.RegularExpressions.RegexOptions.IgnoreCase);
        return match.Success?$"Custom.{match.Groups["w"].Value}x{match.Groups["h"].Value}mm":null;
    }

    private string? ResolveSumatraPath()
    {
        var candidates = new[]
        {
            _settings.SumatraPath,
            Path.Combine(AppContext.BaseDirectory, "SumatraPDF.exe"),
            Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "SumatraPDF", "SumatraPDF.exe"),
            Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.ProgramFiles), "SumatraPDF", "SumatraPDF.exe")
        };
        var existing = candidates.FirstOrDefault(path => !string.IsNullOrWhiteSpace(path) && File.Exists(path));
        return existing ?? ExtractEmbeddedSumatra();
    }

    private static string? ExtractEmbeddedSumatra()
    {
        var assembly = Assembly.GetExecutingAssembly();
        using var resource = assembly.GetManifestResourceStream("AltreoPrintAgent.SumatraPDF.exe");
        if (resource is null) return null;

        var directory = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "AltreoPrintAgent", "tools");
        Directory.CreateDirectory(directory);
        var path = Path.Combine(directory, "SumatraPDF-3.6.1.exe");
        if (!File.Exists(path) || new FileInfo(path).Length != resource.Length)
        {
            var temporaryPath = path + ".tmp";
            using (var output = File.Create(temporaryPath)) resource.CopyTo(output);
            File.Move(temporaryPath, path, true);
        }
        return path;
    }

    private static string Trim(string value, string fallback = "")
    {
        var normalized = value.ReplaceLineEndings(" ").Trim();
        if (normalized.Length > 300) normalized = normalized[..300];
        return string.IsNullOrEmpty(normalized) ? fallback : normalized;
    }
}

internal static class StringExtensions
{
    public static bool ContainsAny(this string value, params char[] characters) => value.IndexOfAny(characters) >= 0;
}
