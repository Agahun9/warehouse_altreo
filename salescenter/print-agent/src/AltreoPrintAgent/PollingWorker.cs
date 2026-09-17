namespace AltreoPrintAgent;

public sealed class PollingWorker
{
    private readonly AgentSettings _settings;
    private readonly PrintAgentApi _api;
    private readonly PdfDownloader _downloader;
    private readonly IPrintService _printer;
    private readonly AgentLog _log;
    private readonly PrinterDiscovery _printerDiscovery;
    private readonly FiscalReceiptService _fiscal=new();

    public PollingWorker(AgentSettings settings, AgentLog log, IPrintService? printer = null, PdfDownloader? downloader = null)
    {
        _settings = settings;
        _log = log;
        _api = new PrintAgentApi(settings);
        _printerDiscovery = new PrinterDiscovery(settings);
        _printer = printer ?? new PrintService(settings);
        _downloader = downloader ?? new PdfDownloader();
    }

    public async Task RunAsync(CancellationToken cancellationToken)
    {
        _log.Info($"Agent uruchomiony dla stanowiska „{_settings.StationName}”.");
        var failureDelay = TimeSpan.FromSeconds(Math.Max(5, _settings.PollSeconds));
        var nextHeartbeat=DateTimeOffset.MinValue;
        while (!cancellationToken.IsCancellationRequested)
        {
            try
            {
                if (DateTimeOffset.UtcNow>=nextHeartbeat)
                {
                    nextHeartbeat=DateTimeOffset.UtcNow.AddMinutes(1);
                    try
                    {
                        var inventory=await _printerDiscovery.DiscoverAsync(cancellationToken);
                        await _api.HeartbeatAsync(inventory,cancellationToken);
                        _log.Info($"Zgłoszono {inventory.Printers.Count} drukarek do panelu.");
                    }
                    catch (Exception exception)
                    {
                        _log.Error($"Nie udało się odświeżyć listy drukarek: {exception.Message}");
                    }
                }
                var job = await _api.GetNextJobAsync(cancellationToken);
                if (job is not null) { await ProcessJobAsync(job, cancellationToken); continue; }
                var fiscalJob=await _api.GetNextFiscalJobAsync(cancellationToken);
                if (fiscalJob is null && BuildProfile.Name=="production")
                    fiscalJob=await _api.GetNextFiscalJobAsync(cancellationToken,"sandbox");
                if (fiscalJob is not null) { await ProcessFiscalJobAsync(fiscalJob,cancellationToken); continue; }
                await Task.Delay(TimeSpan.FromSeconds(Math.Clamp(_settings.PollSeconds, 2, 300)), cancellationToken);
            }
            catch (OperationCanceledException) when (cancellationToken.IsCancellationRequested)
            {
                break;
            }
            catch (Exception exception)
            {
                _log.Error($"Błąd komunikacji: {exception.Message}");
                await Task.Delay(failureDelay, cancellationToken);
            }
        }
        _log.Info("Agent zatrzymany.");
    }

    private async Task ProcessFiscalJobAsync(FiscalJob job,CancellationToken cancellationToken)
    {
        _log.Info($"Odebrano zadanie fiskalne {job.Id}; drukarka: {job.PrinterName}; tryb: {job.Environment}.");
        var result=await _fiscal.PrintAsync(job,cancellationToken);
        var fiscalNumber=result.Status==JobStatuses.Printed && job.Environment=="production" ? result.Reference : null;
        await _api.ReportFiscalAsync(job,result,fiscalNumber,cancellationToken);
        if (result.Status==JobStatuses.Printed) _log.Info($"Zadanie fiskalne {job.Id}: {result.Message}");
        else _log.Error($"Zadanie fiskalne {job.Id}: {result.Message}");
    }

    private async Task ProcessJobAsync(PrintJob job, CancellationToken cancellationToken)
    {
        if (string.IsNullOrWhiteSpace(job.Id) || string.IsNullOrWhiteSpace(job.PdfUrl) || string.IsNullOrWhiteSpace(job.PrinterName))
            throw new InvalidDataException("Zadanie nie zawiera id, pdfUrl lub printerName.");

        _log.Info($"Odebrano zadanie {job.Id}; drukarka: {job.PrinterName}.");
        try
        {
            await _api.ReportAsync(job, JobStatuses.Processing, "Agent odebrał zadanie.", cancellationToken);
        }
        catch (Exception exception)
        {
            // GET /jobs/next already atomically claims the job on the reference server.
            // A transient failure of this informational update must not lose the print.
            _log.Error($"Nie udało się potwierdzić odebrania zadania {job.Id}: {exception.Message}");
        }
        string? temporaryPdf = null;
        PrintResult result;
        try
        {
            temporaryPdf = await _downloader.DownloadAsync(job.PdfUrl, _settings.AllowInsecureHttp, cancellationToken);
            result = await _printer.PrintAsync(temporaryPdf, job.PrinterName, job.PrintSettings, cancellationToken);
        }
        catch (OperationCanceledException) when (cancellationToken.IsCancellationRequested)
        {
            throw;
        }
        catch (Exception exception)
        {
            result = PrintResult.Error(exception.Message);
        }
        finally
        {
            if (temporaryPdf is not null)
            {
                try { File.Delete(temporaryPdf); } catch { /* cleanup on next OS temp sweep */ }
            }
        }

        await ReportWithRetryAsync(job, result, cancellationToken);
        if (result.Status == JobStatuses.Printed) _log.Info($"Zadanie {job.Id}: {result.Message}");
        else _log.Error($"Zadanie {job.Id}: {result.Status} – {result.Message}");
    }

    private async Task ReportWithRetryAsync(PrintJob job, PrintResult result, CancellationToken cancellationToken)
    {
        Exception? last = null;
        for (var attempt = 1; attempt <= 3; attempt++)
        {
            try
            {
                await _api.ReportAsync(job, result.Status, result.Message, cancellationToken);
                return;
            }
            catch (Exception exception) when (attempt < 3)
            {
                last = exception;
                await Task.Delay(TimeSpan.FromSeconds(attempt * 2), cancellationToken);
            }
        }
        throw new HttpRequestException("Nie udało się odesłać końcowego statusu zadania.", last);
    }
}
