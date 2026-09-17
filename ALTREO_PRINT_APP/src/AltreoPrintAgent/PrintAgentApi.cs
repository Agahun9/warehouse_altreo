using System.Net;
using System.Net.Http.Headers;
using System.Net.Http.Json;
using System.Text.Json;

namespace AltreoPrintAgent;

public sealed class PrintAgentApi
{
    private readonly HttpClient _http;
    private readonly AgentSettings _settings;
    private DateTimeOffset _nextFiscalProbeAt=DateTimeOffset.MinValue;

    public PrintAgentApi(AgentSettings settings):this(settings,new HttpClientHandler()) { }

    internal PrintAgentApi(AgentSettings settings,HttpMessageHandler handler)
    {
        _settings = settings;
        ValidateServerUrl(settings);
        _http = new HttpClient(handler) { BaseAddress = NormalizeBaseUri(settings.ServerUrl), Timeout = TimeSpan.FromSeconds(35) };
        _http.DefaultRequestHeaders.Authorization = new AuthenticationHeaderValue("Bearer", settings.StationToken);
        _http.DefaultRequestHeaders.UserAgent.ParseAdd("AltreoPrintAgent/1.0");
        _http.DefaultRequestHeaders.Add("X-Station-Name", settings.StationName);
        _http.DefaultRequestHeaders.Add("X-Agent-Environment",BuildProfile.Name);
    }

    public async Task<PrintJob?> GetNextJobAsync(CancellationToken cancellationToken)
    {
        using var response = await _http.GetAsync(Endpoint("jobs/next"), cancellationToken);
        if (response.StatusCode == HttpStatusCode.NoContent) return null;
        await EnsureSuccessAsync(response,cancellationToken);
        var json=await response.Content.ReadAsStringAsync(cancellationToken);
        PrintJob? job;
        try { job=JsonSerializer.Deserialize<PrintJob>(json,new JsonSerializerOptions { PropertyNameCaseInsensitive=true }); }
        catch (JsonException exception) { throw new InvalidDataException("Serwer nie zwrócił poprawnego zadania JSON. Sprawdź adres API.",exception); }
        if (job is null || string.IsNullOrWhiteSpace(job.Id) || string.IsNullOrWhiteSpace(job.PdfUrl) || string.IsNullOrWhiteSpace(job.PrinterName))
            throw new InvalidDataException("Serwer zwrócił odpowiedź inną niż zadanie druku. Zaktualizuj print-agent-api.php albo sprawdź adres API.");
        return job;
    }

    public async Task ReportAsync(PrintJob job, string status, string message, CancellationToken cancellationToken)
    {
        using var response = await _http.PostAsJsonAsync(Endpoint($"jobs/{Uri.EscapeDataString(job.Id)}/status"), new
        {
            status,
            message,
            printerName = job.PrinterName,
            stationName = _settings.StationName,
            reportedAt = DateTimeOffset.UtcNow
        }, cancellationToken);
        await EnsureSuccessAsync(response,cancellationToken);
    }

    public async Task TestAsync(CancellationToken cancellationToken)
    {
        using var response = await _http.GetAsync(Endpoint("health"), cancellationToken);
        await EnsureSuccessAsync(response,cancellationToken);
    }

    public async Task HeartbeatAsync(PrinterInventory inventory, CancellationToken cancellationToken)
    {
        using var response = await _http.PostAsJsonAsync(Endpoint("stations/heartbeat"), new
        {
            printers = inventory.Printers,
            defaultPrinter = inventory.DefaultPrinter,
            fiscalPrinters = inventory.FiscalPrinters,
            environment = BuildProfile.Name,
            agentVersion = typeof(PrintAgentApi).Assembly.GetName().Version?.ToString(3) ?? "1.0.0"
        }, cancellationToken);
        await EnsureSuccessAsync(response,cancellationToken);
    }

    public async Task<FiscalJob?> GetNextFiscalJobAsync(CancellationToken cancellationToken, string? environment = null)
    {
        if (DateTimeOffset.UtcNow<_nextFiscalProbeAt) return null;
        using var request=new HttpRequestMessage(HttpMethod.Get,Endpoint("fiscal/next"));
        request.Headers.Add("X-Fiscal-Environment",environment??BuildProfile.Name);
        using var response=await _http.SendAsync(request,cancellationToken);
        if (response.StatusCode==HttpStatusCode.NoContent) return null;
        if (response.StatusCode==HttpStatusCode.NotFound) { _nextFiscalProbeAt=DateTimeOffset.UtcNow.AddMinutes(1); return null; }
        await EnsureSuccessAsync(response,cancellationToken);
        var job=await response.Content.ReadFromJsonAsync<FiscalJob>(cancellationToken:cancellationToken);
        if (job is null || string.IsNullOrWhiteSpace(job.Id) || string.IsNullOrWhiteSpace(job.DeviceKey))
            throw new InvalidDataException("Serwer zwrócił niepełne zadanie fiskalne.");
        return job;
    }

    public async Task ReportFiscalAsync(FiscalJob job,PrintResult result,string? fiscalNumber,CancellationToken cancellationToken)
    {
        using var response=await _http.PostAsJsonAsync(Endpoint($"fiscal/{Uri.EscapeDataString(job.Id)}/status"),new { status=result.Status,message=result.Message,fiscalNumber,reportedAt=DateTimeOffset.UtcNow },cancellationToken);
        await EnsureSuccessAsync(response,cancellationToken);
    }

    private static async Task EnsureSuccessAsync(HttpResponseMessage response,CancellationToken cancellationToken)
    {
        if (response.IsSuccessStatusCode) return;
        var detail=(await response.Content.ReadAsStringAsync(cancellationToken)).Trim();
        if (detail.Length>300) detail=detail[..300];
        var message=response.StatusCode switch
        {
            HttpStatusCode.Unauthorized => "Serwer odrzucił token stanowiska (401). Wygeneruj nowy token w panelu i wpisz go w aplikacji.",
            HttpStatusCode.NotFound => "Serwer nie obsługuje wywołanego endpointu API (404). Wdróż aktualny print-agent-api.php.",
            _ => $"Serwer zwrócił {(int)response.StatusCode} ({response.ReasonPhrase})."
        };
        if (!string.IsNullOrWhiteSpace(detail) && detail[0]!='<') message+=" "+detail;
        throw new HttpRequestException(message,null,response.StatusCode);
    }

    private Uri Endpoint(string route)
    {
        if (_http.BaseAddress?.AbsolutePath.EndsWith(".php", StringComparison.OrdinalIgnoreCase)==true)
            return new Uri(_http.BaseAddress+"?route="+Uri.EscapeDataString(route),UriKind.Absolute);
        return new Uri(_http.BaseAddress!,route);
    }

    internal static Uri NormalizeBaseUri(string value)
    {
        var normalized=value.TrimEnd('/');
        return new(normalized.EndsWith(".php",StringComparison.OrdinalIgnoreCase)?normalized:normalized+"/",UriKind.Absolute);
    }

    internal static void ValidateServerUrl(AgentSettings settings)
    {
        if (!Uri.TryCreate(settings.ServerUrl, UriKind.Absolute, out var uri))
            throw new InvalidOperationException("Adres serwera jest nieprawidłowy.");
        if (uri.Scheme != Uri.UriSchemeHttps && !(settings.AllowInsecureHttp && uri.Scheme == Uri.UriSchemeHttp))
            throw new InvalidOperationException("Serwer musi używać HTTPS. HTTP można włączyć tylko do testów lokalnych.");
        if (string.IsNullOrWhiteSpace(settings.StationToken))
            throw new InvalidOperationException("Token stanowiska jest pusty.");
    }
}
